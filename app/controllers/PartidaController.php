<?php
/**
 * app/controllers/PartidaController.php
 * Controlador responsável pelo Agendamento de Partidas e Controle da Súmula Eletrônica em Tempo Real
 * Implementa as regras de negócio: RN07 (Conflito de horários), RN08 (Minutos 1-40) e RN09 (Desqualificação na 3ª exclusão de 2min)
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/AuthMiddleware.php';
require_once __DIR__ . '/../models/PartidaModel.php';
require_once __DIR__ . '/../models/EquipeModel.php';

class PartidaController
{
    private PDO $db;
    private PartidaModel $partidaModel;
    private EquipeModel $equipeModel;

    /**
     * Construtor com injeção de dependência opcional dos Models e conexão PDO.
     */
    public function __construct(
        ?PartidaModel $partidaModel = null,
        ?EquipeModel $equipeModel = null,
        ?PDO $db = null
    ) {
        $this->db = $db ?? Database::getConnection();
        $this->partidaModel = $partidaModel ?? new PartidaModel($this->db);
        $this->equipeModel  = $equipeModel ?? new EquipeModel($this->db);
    }

    /**
     * Agenda uma nova partida com validação rigorosa de conflitos de quadra, data e horário (RN07).
     * Acesso restrito ao perfil de Organizador.
     */
    public function agendar(): void
    {
        AuthMiddleware::requireOrganizador();

        $baseUrl = AuthMiddleware::getBaseUrl();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: {$baseUrl}/admin/partidas.php");
            exit;
        }

        $edicaoId          = (int)($_POST['edicao_id'] ?? 0);
        $fase              = trim(strip_tags((string)($_POST['fase'] ?? PartidaModel::FASE_GRUPOS)));
        $grupo             = !empty($_POST['grupo']) ? strtoupper(trim(strip_tags((string)$_POST['grupo']))) : null;
        $data              = trim(strip_tags((string)($_POST['data'] ?? '')));
        $horario           = trim(strip_tags((string)($_POST['horario'] ?? '')));
        $quadra            = (int)($_POST['quadra'] ?? 1);
        $equipeCasaId      = (int)($_POST['equipe_casa_id'] ?? 0);
        $equipeVisitanteId = (int)($_POST['equipe_visitante_id'] ?? 0);
        $mesarioId         = !empty($_POST['mesario_id']) ? (int)$_POST['mesario_id'] : null;

        $redirectUrl = "{$baseUrl}/admin/partidas.php" . ($edicaoId > 0 ? "?edicao_id={$edicaoId}" : "");

        // Validações básicas de preenchimento
        if ($edicaoId <= 0 || empty($data) || empty($horario) || $quadra <= 0) {
            AuthMiddleware::setFlash('erro', 'Edição, data, horário e quadra são obrigatórios para agendar a partida.');
            header("Location: {$redirectUrl}");
            exit;
        }

        if ($equipeCasaId <= 0 || $equipeVisitanteId <= 0) {
            AuthMiddleware::setFlash('erro', 'Selecione as duas equipes (mandante e visitante) para a disputa.');
            header("Location: {$redirectUrl}");
            exit;
        }

        if ($equipeCasaId === $equipeVisitanteId) {
            AuthMiddleware::setFlash('erro', 'Uma equipe não pode jogar contra si mesma.');
            header("Location: {$redirectUrl}");
            exit;
        }

        try {
            // Valida existência e pertencimento das equipes à edição
            $equipeCasa = $this->equipeModel->buscarPorId($equipeCasaId);
            $equipeVisitante = $this->equipeModel->buscarPorId($equipeVisitanteId);

            if (!$equipeCasa || !$equipeVisitante) {
                AuthMiddleware::setFlash('erro', 'Uma ou ambas as equipes selecionadas não foram encontradas.');
                header("Location: {$redirectUrl}");
                exit;
            }

            if ((int)$equipeCasa['edicao_id'] !== $edicaoId || (int)$equipeVisitante['edicao_id'] !== $edicaoId) {
                AuthMiddleware::setFlash('erro', 'As equipes selecionadas devem pertencer à mesma edição do torneio.');
                header("Location: {$redirectUrl}");
                exit;
            }

            // Validação de fase de grupos
            if ($fase === PartidaModel::FASE_GRUPOS) {
                if (empty($grupo)) {
                    $grupo = $equipeCasa['grupo'];
                }

                if ($equipeCasa['grupo'] !== $grupo || $equipeVisitante['grupo'] !== $grupo) {
                    AuthMiddleware::setFlash('erro', "Na fase de grupos, ambas as equipes devem pertencer ao mesmo Grupo ({$grupo}).");
                    header("Location: {$redirectUrl}");
                    exit;
                }
            }

            // Validação da regra RN07: Restrição de conflitos de quadra, data e horário
            if ($this->partidaModel->verificarConflitoHorario($data, $horario, $quadra)) {
                $dataFormatada = date('d/m/Y', strtotime($data));
                AuthMiddleware::setFlash('erro', "RN07: Conflito de agendamento! Já existe uma partida agendada para a Quadra {$quadra} no dia {$dataFormatada} às {$horario}.");
                header("Location: {$redirectUrl}");
                exit;
            }

            // Cadastro da partida
            $dados = [
                'edicao_id'           => $edicaoId,
                'fase'                => $fase,
                'grupo'               => $grupo,
                'data'                => $data,
                'horario'             => $horario,
                'quadra'              => $quadra,
                'equipe_casa_id'      => $equipeCasaId,
                'equipe_visitante_id' => $equipeVisitanteId,
                'mesario_id'          => $mesarioId,
            ];

            $partidaId = $this->partidaModel->agendar($dados);

            $casaNome = htmlspecialchars($equipeCasa['nome'], ENT_QUOTES, 'UTF-8');
            $visNome  = htmlspecialchars($equipeVisitante['nome'], ENT_QUOTES, 'UTF-8');
            AuthMiddleware::setFlash('sucesso', "Partida #{$partidaId} ({$casaNome} x {$visNome}) agendada com sucesso!");
            header("Location: {$redirectUrl}");
            exit;

        } catch (Exception $e) {
            error_log("Erro ao agendar partida: " . $e->getMessage());
            AuthMiddleware::setFlash('erro', 'Falha ao agendar partida: ' . $e->getMessage());
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Inicia uma partida agendada, alterando o status para 'andamento' e liberando a súmula eletrônica.
     */
    public function iniciar(): void
    {
        AuthMiddleware::requireMesario();

        $baseUrl = AuthMiddleware::getBaseUrl();
        $partidaId = (int)($_POST['partida_id'] ?? ($_GET['id'] ?? 0));

        if ($partidaId <= 0) {
            AuthMiddleware::setFlash('erro', 'Partida não especificada.');
            header("Location: {$baseUrl}/mesario/index.php");
            exit;
        }

        try {
            $partida = $this->partidaModel->buscarPorId($partidaId);
            if (!$partida) {
                AuthMiddleware::setFlash('erro', 'Partida não encontrada.');
                header("Location: {$baseUrl}/mesario/index.php");
                exit;
            }

            if ($partida['status'] === PartidaModel::STATUS_ENCERRADA) {
                AuthMiddleware::setFlash('aviso', 'Esta partida já foi encerrada e não pode ser reiniciada.');
                header("Location: {$baseUrl}/mesario/sumula.php?id={$partidaId}");
                exit;
            }

            $this->partidaModel->iniciarPartida($partidaId);

            AuthMiddleware::setFlash('sucesso', 'Partida iniciada! Cronômetro e registro de súmula liberados.');
            header("Location: {$baseUrl}/mesario/sumula.php?id={$partidaId}");
            exit;

        } catch (Exception $e) {
            error_log("Erro ao iniciar partida {$partidaId}: " . $e->getMessage());
            AuthMiddleware::setFlash('erro', 'Erro ao iniciar partida: ' . $e->getMessage());
            header("Location: {$baseUrl}/mesario/index.php");
            exit;
        }
    }

    /**
     * Encerra a partida, consolidando o placar e gravando penalidades de 7 metros em caso de empate no mata-mata.
     */
    public function encerrar(): void
    {
        AuthMiddleware::requireMesario();

        $baseUrl = AuthMiddleware::getBaseUrl();
        $partidaId = (int)($_POST['partida_id'] ?? 0);
        $casa7m    = isset($_POST['placar_casa_7m']) && $_POST['placar_casa_7m'] !== '' ? (int)$_POST['placar_casa_7m'] : null;
        $vis7m     = isset($_POST['placar_visitante_7m']) && $_POST['placar_visitante_7m'] !== '' ? (int)$_POST['placar_visitante_7m'] : null;

        if ($partidaId <= 0) {
            AuthMiddleware::setFlash('erro', 'Partida não especificada.');
            header("Location: {$baseUrl}/mesario/index.php");
            exit;
        }

        try {
            $partida = $this->partidaModel->buscarPorId($partidaId);
            if (!$partida) {
                AuthMiddleware::setFlash('erro', 'Partida não encontrada.');
                header("Location: {$baseUrl}/mesario/index.php");
                exit;
            }

            // Em fases eliminatórias (mata-mata), empate exige desempate nos tiros de 7 metros
            if ($partida['fase'] !== PartidaModel::FASE_GRUPOS) {
                $golsCasa = (int)$partida['gols_casa'];
                $golsVis  = (int)$partida['gols_visitante'];

                if ($golsCasa === $golsVis) {
                    if ($casa7m === null || $vis7m === null) {
                        AuthMiddleware::setFlash('erro', 'Partida eliminatória empatada no tempo normal! É obrigatório registrar o placar dos tiros de 7 metros.');
                        header("Location: {$baseUrl}/mesario/sumula.php?id={$partidaId}");
                        exit;
                    }

                    if ($casa7m === $vis7m) {
                        AuthMiddleware::setFlash('erro', 'O desempate nos tiros de 7 metros não pode terminar empatado.');
                        header("Location: {$baseUrl}/mesario/sumula.php?id={$partidaId}");
                        exit;
                    }
                }
            }

            $this->partidaModel->encerrarPartida($partidaId, $casa7m, $vis7m);

            AuthMiddleware::setFlash('sucesso', 'Partida encerrada com sucesso! Súmula finalizada e estatísticas computadas.');
            header("Location: {$baseUrl}/mesario/sumula.php?id={$partidaId}");
            exit;

        } catch (Exception $e) {
            error_log("Erro ao encerrar partida {$partidaId}: " . $e->getMessage());
            AuthMiddleware::setFlash('erro', 'Erro ao encerrar partida: ' . $e->getMessage());
            header("Location: {$baseUrl}/mesario/sumula.php?id={$partidaId}");
            exit;
        }
    }

    /**
     * Lança um evento na súmula da partida (Gol, Exclusão de 2 minutos, Cartão Amarelo ou Vermelho).
     * Aplica integralmente as regras:
     * - RN08: Restrição de minuto (1 a 40)
     * - RN09: 3ª exclusão de 2 minutos acarreta automaticamente cartão vermelho (desqualificação)
     * Suporta respostas via redirecionamento tradicional ou formato JSON para requisições AJAX.
     */
    public function lancarEvento(): void
    {
        AuthMiddleware::requireMesario();

        $isAjax = $this->isAjaxRequest();

        $partidaId = (int)($_POST['partida_id'] ?? 0);
        $jogadorId = (int)($_POST['jogador_id'] ?? 0);
        $tipo      = trim(strip_tags((string)($_POST['tipo'] ?? '')));
        $minuto    = (int)($_POST['minuto'] ?? 0);

        $baseUrl = AuthMiddleware::getBaseUrl();
        $redirectUrl = "{$baseUrl}/mesario/sumula.php?id={$partidaId}";

        // Validações dos parâmetros do lance
        if ($partidaId <= 0 || $jogadorId <= 0 || empty($tipo)) {
            $this->responderErro("Todos os campos do lance (partida, jogador e tipo de lance) são obrigatórios.", $redirectUrl, $isAjax);
            return;
        }

        // RN08: Minuto do lance restrito entre 1 e 40
        if ($minuto < 1 || $minuto > 40) {
            $this->responderErro("RN08: O minuto do lance deve estar rigorosamente entre 1 e 40 (duração regulamentar de 2 tempos de 20 min).", $redirectUrl, $isAjax);
            return;
        }

        try {
            // Verifica status da partida
            $partida = $this->partidaModel->buscarPorId($partidaId);
            if (!$partida) {
                $this->responderErro("Partida não localizada.", $redirectUrl, $isAjax);
                return;
            }

            if ($partida['status'] !== PartidaModel::STATUS_ANDAMENTO) {
                $this->responderErro("Lances só podem ser registrados em partidas com status 'andamento'.", $redirectUrl, $isAjax);
                return;
            }

            // Busca os dados do jogador e valida aptidão
            $jogador = $this->buscarJogador($jogadorId);
            if (!$jogador) {
                $this->responderErro("Atleta não cadastrado no sistema.", $redirectUrl, $isAjax);
                return;
            }

            if ($jogador['situacao'] !== 'ativo') {
                $this->responderErro("O atleta está inativo e não pode participar da partida.", $redirectUrl, $isAjax);
                return;
            }

            // O atleta deve pertencer a uma das equipes em quadra
            $equipeIdJogador = (int)$jogador['equipe_id'];
            if ($equipeIdJogador !== (int)$partida['equipe_casa_id'] && $equipeIdJogador !== (int)$partida['equipe_visitante_id']) {
                $this->responderErro("O atleta selecionado não pertence a nenhuma das equipes desta partida.", $redirectUrl, $isAjax);
                return;
            }

            // Verifica se o atleta já foi desqualificado (cartão vermelho) nesta partida
            if ($this->partidaModel->jogadorTemVermelho($partidaId, $jogadorId)) {
                $this->responderErro("Ação negada: O atleta #{$jogador['numero_camisa']} ({$jogador['nome']}) já recebeu Cartão Vermelho (desqualificado) e não pode realizar novos lances.", $redirectUrl, $isAjax);
                return;
            }

            $mensagensFeedback = [];

            // Tratamento específico por tipo de evento
            switch ($tipo) {
                case PartidaModel::EVENTO_GOL:
                    $this->partidaModel->registrarEvento($partidaId, $jogadorId, PartidaModel::EVENTO_GOL, $minuto);
                    $mensagensFeedback[] = "Gol registrado para #{$jogador['numero_camisa']} {$jogador['nome']} aos {$minuto}'!";
                    break;

                case PartidaModel::EVENTO_AMARELO:
                    $qtdAmarelos = $this->partidaModel->contarAmarelosJogador($partidaId, $jogadorId);
                    if ($qtdAmarelos >= 1) {
                        $this->responderErro("O atleta já recebeu Cartão Amarelo nesta partida. A próxima advertência disciplinar deve ser Exclusão de 2 minutos.", $redirectUrl, $isAjax);
                        return;
                    }
                    $this->partidaModel->registrarEvento($partidaId, $jogadorId, PartidaModel::EVENTO_AMARELO, $minuto);
                    $mensagensFeedback[] = "Cartão Amarelo aplicado a #{$jogador['numero_camisa']} {$jogador['nome']} aos {$minuto}'.";
                    break;

                case PartidaModel::EVENTO_EXCLUSAO_2MIN:
                    // Aplicação da Regra RN09
                    $exclusoesPrevias = $this->partidaModel->contarExclusoesJogador($partidaId, $jogadorId);

                    if ($exclusoesPrevias >= 2) {
                        // 3ª exclusão: grava a exclusão de 2min E o cartão vermelho automático
                        $this->partidaModel->registrarEvento($partidaId, $jogadorId, PartidaModel::EVENTO_EXCLUSAO_2MIN, $minuto);
                        $this->partidaModel->registrarEvento($partidaId, $jogadorId, PartidaModel::EVENTO_VERMELHO, $minuto);
                        $mensagensFeedback[] = "RN09: 3ª Exclusão de 2 minutos de #{$jogador['numero_camisa']} {$jogador['nome']} aos {$minuto}'!";
                        $mensagensFeedback[] = "CARTÃO VERMELHO APLICADO AUTOMATICAMENTE: Atleta desqualificado da partida.";
                    } else {
                        // 1ª ou 2ª exclusão normal
                        $this->partidaModel->registrarEvento($partidaId, $jogadorId, PartidaModel::EVENTO_EXCLUSAO_2MIN, $minuto);
                        $numExclusao = $exclusoesPrevias + 1;
                        $mensagensFeedback[] = "Exclusão de 2 minutos ({$numExclusao}ª) aplicada a #{$jogador['numero_camisa']} {$jogador['nome']} aos {$minuto}'.";
                    }
                    break;

                case PartidaModel::EVENTO_VERMELHO:
                    $this->partidaModel->registrarEvento($partidaId, $jogadorId, PartidaModel::EVENTO_VERMELHO, $minuto);
                    $mensagensFeedback[] = "CARTÃO VERMELHO DIRETO: Atleta #{$jogador['numero_camisa']} {$jogador['nome']} desqualificado aos {$minuto}'.";
                    break;

                default:
                    $this->responderErro("Tipo de lance desconhecido.", $redirectUrl, $isAjax);
                    return;
            }

            $mensagemFinal = implode(" | ", $mensagensFeedback);
            $placarAtualizado = $this->partidaModel->obterPlacar($partidaId);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'sucesso'   => true,
                    'mensagem'  => $mensagemFinal,
                    'placar'    => $placarAtualizado,
                    'eventos'   => $this->partidaModel->obterEventos($partidaId),
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            AuthMiddleware::setFlash('sucesso', $mensagemFinal);
            header("Location: {$redirectUrl}");
            exit;

        } catch (Exception $e) {
            error_log("Erro ao lançar evento na súmula: " . $e->getMessage());
            $this->responderErro("Erro ao registrar lance: " . $e->getMessage(), $redirectUrl, $isAjax);
        }
    }

    /**
     * Remove um evento da súmula (estorno/correção de lance).
     */
    public function removerEvento(): void
    {
        AuthMiddleware::requireMesario();

        $isAjax    = $this->isAjaxRequest();
        $eventoId  = (int)($_POST['evento_id'] ?? ($_GET['evento_id'] ?? 0));
        $partidaId = (int)($_POST['partida_id'] ?? ($_GET['partida_id'] ?? 0));

        $baseUrl = AuthMiddleware::getBaseUrl();
        $redirectUrl = "{$baseUrl}/mesario/sumula.php?id={$partidaId}";

        if ($eventoId <= 0) {
            $this->responderErro("Identificador de lance inválido.", $redirectUrl, $isAjax);
            return;
        }

        try {
            $evento = $this->partidaModel->buscarEventoPorId($eventoId);
            if (!$evento) {
                $this->responderErro("Lance não encontrado para remoção.", $redirectUrl, $isAjax);
                return;
            }

            $partida = $this->partidaModel->buscarPorId((int)$evento['partida_id']);
            if (!$partida || $partida['status'] === PartidaModel::STATUS_ENCERRADA) {
                $this->responderErro("Não é permitido estornar lances de uma partida já encerrada.", $redirectUrl, $isAjax);
                return;
            }

            $this->partidaModel->removerEvento($eventoId);

            $msg = "Lance #{$eventoId} estornado com sucesso da súmula.";

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'sucesso'  => true,
                    'mensagem' => $msg,
                    'placar'   => $this->partidaModel->obterPlacar((int)$evento['partida_id']),
                    'eventos'  => $this->partidaModel->obterEventos((int)$evento['partida_id']),
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            AuthMiddleware::setFlash('sucesso', $msg);
            header("Location: {$redirectUrl}");
            exit;

        } catch (Exception $e) {
            error_log("Erro ao remover evento {$eventoId}: " . $e->getMessage());
            $this->responderErro("Erro ao estornar lance: " . $e->getMessage(), $redirectUrl, $isAjax);
        }
    }

    /**
     * Atribui um mesário responsável à partida.
     */
    public function atribuirMesario(): void
    {
        AuthMiddleware::requireOrganizador();

        $baseUrl   = AuthMiddleware::getBaseUrl();
        $partidaId = (int)($_POST['partida_id'] ?? 0);
        $mesarioId = (int)($_POST['mesario_id'] ?? 0);
        $edicaoId  = (int)($_POST['edicao_id'] ?? 0);

        $redirectUrl = "{$baseUrl}/admin/partidas.php" . ($edicaoId > 0 ? "?edicao_id={$edicaoId}" : "");

        if ($partidaId <= 0 || $mesarioId <= 0) {
            AuthMiddleware::setFlash('erro', 'Partida e mesário devem ser especificados.');
            header("Location: {$redirectUrl}");
            exit;
        }

        try {
            $this->partidaModel->atribuirMesario($partidaId, $mesarioId);
            AuthMiddleware::setFlash('sucesso', 'Mesário atribuído à partida com sucesso!');
            header("Location: {$redirectUrl}");
            exit;
        } catch (Exception $e) {
            error_log("Erro ao atribuir mesário: " . $e->getMessage());
            AuthMiddleware::setFlash('erro', 'Erro ao atribuir mesário: ' . $e->getMessage());
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Retorna a listagem de atletas aptos de uma equipe para a súmula eletrônica.
     */
    public function listarJogadoresEquipe(int $equipeId): array
    {
        $sql = "SELECT id, equipe_id, rm, nome, numero_camisa, situacao 
                FROM jogadores 
                WHERE equipe_id = :equipe_id AND situacao = 'ativo' 
                ORDER BY numero_camisa ASC, nome ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':equipe_id', $equipeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Busca dados cadastrais de um atleta específico.
     */
    private function buscarJogador(int $jogadorId): ?array
    {
        $sql = "SELECT id, equipe_id, rm, nome, numero_camisa, situacao 
                FROM jogadores 
                WHERE id = :id 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $jogadorId, PDO::PARAM_INT);
        $stmt->execute();

        $jogador = $stmt->fetch();
        return $jogador ?: null;
    }

    /**
     * Verifica se a requisição atual foi disparada via AJAX ou espera resposta JSON.
     */
    private function isAjaxRequest(): bool
    {
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $acceptHeader  = $_SERVER['HTTP_ACCEPT'] ?? '';

        return strtolower($requestedWith) === 'xmlhttprequest' 
            || str_contains($acceptHeader, 'application/json')
            || isset($_POST['ajax']) 
            || isset($_GET['ajax']);
    }

    /**
     * Responde mensagens de erro de forma unificada (JSON ou Flash redirect).
     */
    private function responderErro(string $mensagem, string $redirectUrl, bool $isAjax): void
    {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode([
                'sucesso'  => false,
                'mensagem' => $mensagem
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        AuthMiddleware::setFlash('erro', $mensagem);
        header("Location: {$redirectUrl}");
        exit;
    }

    /**
     * Roteador de ações disparadas via requisições diretas ao controller.
     */
    public function processar(): void
    {
        $action = trim((string)($_REQUEST['action'] ?? ''));

        match ($action) {
            'agendar'         => $this->agendar(),
            'iniciar'         => $this->iniciar(),
            'encerrar'        => $this->encerrar(),
            'lancar_evento'   => $this->lancarEvento(),
            'remover_evento'  => $this->removerEvento(),
            'atribuir_mesario'=> $this->atribuirMesario(),
            default           => header("Location: " . AuthMiddleware::getBaseUrl() . "/admin/partidas.php"),
        };
    }
}

// Auto-despacho caso o script seja invocado diretamente via URL ou form action
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'PartidaController.php') {
    $controller = new PartidaController();
    $controller->processar();
}
