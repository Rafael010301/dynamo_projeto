<?php
/**
 * app/controllers/EquipeController.php
 * Controlador responsável pelo gerenciamento de Equipes (cadastro, edição, inativação lógica e validações)
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/AuthMiddleware.php';
require_once __DIR__ . '/../models/EquipeModel.php';

class EquipeController
{
    private EquipeModel $equipeModel;

    /**
     * Construtor com injeção de dependência opcional do Model de Equipes.
     */
    public function __construct(?EquipeModel $equipeModel = null)
    {
        $this->equipeModel = $equipeModel ?? new EquipeModel();
    }

    /**
     * Cadastra uma nova equipe vinculada a uma edição anual.
     * Valida unicidade de nome por edição, turma, grupo (A ou B) e cor do uniforme.
     */
    public function cadastrar(): void
    {
        AuthMiddleware::requireOrganizador();

        $baseUrl = AuthMiddleware::getBaseUrl();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: {$baseUrl}/admin/equipes.php");
            exit;
        }

        $edicaoId    = (int)($_POST['edicao_id'] ?? 0);
        $turma       = trim(strip_tags((string)($_POST['turma'] ?? '')));
        $nome        = trim(strip_tags((string)($_POST['nome'] ?? '')));
        $grupo       = !empty($_POST['grupo']) ? strtoupper(trim(strip_tags((string)$_POST['grupo']))) : null;
        $corUniforme = trim(strip_tags((string)($_POST['cor_uniforme'] ?? '')));
        $situacao    = trim(strip_tags((string)($_POST['situacao'] ?? EquipeModel::SITUACAO_ATIVO)));

        $redirectUrl = "{$baseUrl}/admin/equipes.php" . ($edicaoId > 0 ? "?edicao_id={$edicaoId}" : "");

        // Validação de campos obrigatórios
        if ($edicaoId <= 0 || empty($turma) || empty($nome) || empty($corUniforme)) {
            AuthMiddleware::setFlash('erro', 'Edição, turma, nome da equipe e cor do uniforme são de preenchimento obrigatório.');
            header("Location: {$redirectUrl}");
            exit;
        }

        // Validação de grupo
        if ($grupo !== null && !in_array($grupo, [EquipeModel::GRUPO_A, EquipeModel::GRUPO_B], true)) {
            AuthMiddleware::setFlash('erro', 'O grupo informado deve ser exclusivamente "A" ou "B".');
            header("Location: {$redirectUrl}");
            exit;
        }

        try {
            // Validação de unicidade do nome da equipe na mesma edição
            if ($this->equipeModel->nomeExisteNaEdicao($nome, $edicaoId)) {
                $nomeSanitizado = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
                AuthMiddleware::setFlash('erro', "Já existe uma equipe cadastrada com o nome '{$nomeSanitizado}' nesta edição.");
                header("Location: {$redirectUrl}");
                exit;
            }

            // Realiza o cadastro
            $dados = [
                'edicao_id'    => $edicaoId,
                'turma'        => $turma,
                'nome'         => $nome,
                'grupo'        => $grupo,
                'cor_uniforme' => $corUniforme,
                'situacao'     => in_array($situacao, [EquipeModel::SITUACAO_ATIVO, EquipeModel::SITUACAO_INATIVO], true) ? $situacao : EquipeModel::SITUACAO_ATIVO,
            ];

            $novoId = $this->equipeModel->cadastrar($dados);

            $nomeSanitizado = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
            AuthMiddleware::setFlash('sucesso', "Equipe '{$nomeSanitizado}' cadastrada com sucesso! (ID: {$novoId})");
            header("Location: {$redirectUrl}");
            exit;

        } catch (Exception $e) {
            error_log("Erro ao cadastrar equipe: " . $e->getMessage());
            AuthMiddleware::setFlash('erro', 'Falha ao cadastrar equipe: ' . $e->getMessage());
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Atualiza os dados de uma equipe existente.
     * Valida unicidade de nome dentro da edição ignorando o próprio registro.
     */
    public function editar(): void
    {
        AuthMiddleware::requireOrganizador();

        $baseUrl = AuthMiddleware::getBaseUrl();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: {$baseUrl}/admin/equipes.php");
            exit;
        }

        $id          = (int)($_POST['id'] ?? 0);
        $edicaoId    = (int)($_POST['edicao_id'] ?? 0);
        $turma       = trim(strip_tags((string)($_POST['turma'] ?? '')));
        $nome        = trim(strip_tags((string)($_POST['nome'] ?? '')));
        $grupo       = !empty($_POST['grupo']) ? strtoupper(trim(strip_tags((string)$_POST['grupo']))) : null;
        $corUniforme = trim(strip_tags((string)($_POST['cor_uniforme'] ?? '')));
        $situacao    = trim(strip_tags((string)($_POST['situacao'] ?? EquipeModel::SITUACAO_ATIVO)));

        $redirectUrl = "{$baseUrl}/admin/equipes.php" . ($edicaoId > 0 ? "?edicao_id={$edicaoId}" : "");

        if ($id <= 0 || empty($turma) || empty($nome) || empty($corUniforme)) {
            AuthMiddleware::setFlash('erro', 'Dados insuficientes para atualizar a equipe.');
            header("Location: {$redirectUrl}");
            exit;
        }

        // Validação de grupo
        if ($grupo !== null && !in_array($grupo, [EquipeModel::GRUPO_A, EquipeModel::GRUPO_B], true)) {
            AuthMiddleware::setFlash('erro', 'O grupo informado deve ser exclusivamente "A" ou "B".');
            header("Location: {$redirectUrl}");
            exit;
        }

        try {
            $equipeExistente = $this->equipeModel->buscarPorId($id);
            if (!$equipeExistente) {
                AuthMiddleware::setFlash('erro', 'Equipe não localizada para atualização.');
                header("Location: {$redirectUrl}");
                exit;
            }

            $edicaoAlvo = $edicaoId > 0 ? $edicaoId : (int)$equipeExistente['edicao_id'];

            // Valida unicidade de nome ignorando a própria equipe
            if ($this->equipeModel->nomeExisteNaEdicao($nome, $edicaoAlvo, $id)) {
                $nomeSanitizado = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
                AuthMiddleware::setFlash('erro', "Já existe outra equipe com o nome '{$nomeSanitizado}' nesta edição.");
                header("Location: {$redirectUrl}");
                exit;
            }

            $dados = [
                'turma'        => $turma,
                'nome'         => $nome,
                'grupo'        => $grupo,
                'cor_uniforme' => $corUniforme,
                'situacao'     => in_array($situacao, [EquipeModel::SITUACAO_ATIVO, EquipeModel::SITUACAO_INATIVO], true) ? $situacao : EquipeModel::SITUACAO_ATIVO,
            ];

            $this->equipeModel->atualizar($id, $dados);

            $nomeSanitizado = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
            AuthMiddleware::setFlash('sucesso', "Equipe '{$nomeSanitizado}' atualizada com sucesso!");
            header("Location: {$redirectUrl}");
            exit;

        } catch (Exception $e) {
            error_log("Erro ao atualizar equipe {$id}: " . $e->getMessage());
            AuthMiddleware::setFlash('erro', 'Falha ao atualizar equipe: ' . $e->getMessage());
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Inativa logicamente uma equipe em conformidade com a Regra de Negócio RN12.
     * Preserva a integridade de jogos e chaveamentos existentes (ON DELETE RESTRICT).
     */
    public function inativar(): void
    {
        AuthMiddleware::requireOrganizador();

        $baseUrl = AuthMiddleware::getBaseUrl();
        $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
        $edicaoId = (int)($_POST['edicao_id'] ?? ($_GET['edicao_id'] ?? 0));

        $redirectUrl = "{$baseUrl}/admin/equipes.php" . ($edicaoId > 0 ? "?edicao_id={$edicaoId}" : "");

        if ($id <= 0) {
            AuthMiddleware::setFlash('erro', 'Identificador de equipe inválido para inativação.');
            header("Location: {$redirectUrl}");
            exit;
        }

        try {
            $equipe = $this->equipeModel->buscarPorId($id);
            if (!$equipe) {
                AuthMiddleware::setFlash('erro', 'Equipe não encontrada.');
                header("Location: {$redirectUrl}");
                exit;
            }

            // Inativação lógica com cascata para os atletas
            $this->equipeModel->inativar($id, true);

            $nomeSanitizado = htmlspecialchars($equipe['nome'], ENT_QUOTES, 'UTF-8');
            AuthMiddleware::setFlash('sucesso', "RN12: Equipe '{$nomeSanitizado}' e seus atletas inativados com sucesso. O histórico de partidas e a classificação foram integralmente preservados.");
            header("Location: {$redirectUrl}");
            exit;

        } catch (Exception $e) {
            error_log("Erro ao inativar equipe {$id}: " . $e->getMessage());
            AuthMiddleware::setFlash('erro', 'Erro ao inativar equipe: ' . $e->getMessage());
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Reativa uma equipe previamente inativada.
     */
    public function reativar(): void
    {
        AuthMiddleware::requireOrganizador();

        $baseUrl = AuthMiddleware::getBaseUrl();
        $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
        $edicaoId = (int)($_POST['edicao_id'] ?? ($_GET['edicao_id'] ?? 0));

        $redirectUrl = "{$baseUrl}/admin/equipes.php" . ($edicaoId > 0 ? "?edicao_id={$edicaoId}" : "");

        if ($id <= 0) {
            AuthMiddleware::setFlash('erro', 'Identificador de equipe inválido para reativação.');
            header("Location: {$redirectUrl}");
            exit;
        }

        try {
            $this->equipeModel->reativar($id);

            AuthMiddleware::setFlash('sucesso', 'Equipe reativada com sucesso! Lembre-se de reativar os atletas necessários.');
            header("Location: {$redirectUrl}");
            exit;

        } catch (Exception $e) {
            error_log("Erro ao reativar equipe {$id}: " . $e->getMessage());
            AuthMiddleware::setFlash('erro', 'Erro ao reativar equipe: ' . $e->getMessage());
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Exclui fisicamente a equipe caso ela não possua partidas nem atletas vinculados.
     * Caso possua restrições, alerta sobre a regra RN12.
     */
    public function excluir(): void
    {
        AuthMiddleware::requireOrganizador();

        $baseUrl = AuthMiddleware::getBaseUrl();
        $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
        $edicaoId = (int)($_POST['edicao_id'] ?? ($_GET['edicao_id'] ?? 0));

        $redirectUrl = "{$baseUrl}/admin/equipes.php" . ($edicaoId > 0 ? "?edicao_id={$edicaoId}" : "");

        if ($id <= 0) {
            AuthMiddleware::setFlash('erro', 'Identificador de equipe inválido.');
            header("Location: {$redirectUrl}");
            exit;
        }

        try {
            $validacao = $this->equipeModel->podeExcluirFisicamente($id);
            if (!$validacao['pode']) {
                AuthMiddleware::setFlash('aviso', $validacao['motivo']);
                header("Location: {$redirectUrl}");
                exit;
            }

            $this->equipeModel->excluir($id);
            AuthMiddleware::setFlash('sucesso', 'Equipe sem vínculos removida permanentemente com sucesso.');
            header("Location: {$redirectUrl}");
            exit;

        } catch (Exception $e) {
            error_log("Erro ao excluir equipe {$id}: " . $e->getMessage());
            AuthMiddleware::setFlash('erro', 'Não foi possível excluir a equipe: ' . $e->getMessage());
            header("Location: {$redirectUrl}");
            exit;
        }
    }

    /**
     * Avalia e retorna a aptidão da equipe conforme a Regra de Negócio RN05 (7 a 14 atletas aptos).
     */
    public function verificarAptidao(int $id): array
    {
        return $this->equipeModel->verificarAptidao($id);
    }

    /**
     * Consulta auxiliar para listagem de equipes em views públicas ou administrativas.
     */
    public function listar(int $edicaoId, ?string $grupo = null, bool $apenasAtivas = false): array
    {
        return $this->equipeModel->listarPorEdicao($edicaoId, $grupo, $apenasAtivas);
    }

    /**
     * Consulta os detalhes de uma equipe pelo ID.
     */
    public function buscar(int $id): ?array
    {
        return $this->equipeModel->buscarPorId($id);
    }

    /**
     * Roteador de ações disparadas via requisições POST ou GET diretas.
     */
    public function processar(): void
    {
        $action = trim((string)($_REQUEST['action'] ?? ''));

        match ($action) {
            'cadastrar' => $this->cadastrar(),
            'editar'    => $this->editar(),
            'inativar'  => $this->inativar(),
            'reativar'  => $this->reativar(),
            'excluir'   => $this->excluir(),
            default     => header("Location: " . AuthMiddleware::getBaseUrl() . "/admin/equipes.php"),
        };
    }
}

// Auto-despacho caso o script seja invocado diretamente via URL ou form action
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'EquipeController.php') {
    $controller = new EquipeController();
    $controller->processar();
}
