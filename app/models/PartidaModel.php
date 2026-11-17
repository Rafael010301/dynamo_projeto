<?php
/**
 * app/models/PartidaModel.php
 * Camada de persistência para Partidas, Agendamento e Súmula Eletrônica
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../../core/Database.php';

class PartidaModel
{
    private PDO $db;

    public const FASE_GRUPOS    = 'grupos';
    public const FASE_QUARTAS   = 'quartas';
    public const FASE_SEMIFINAL = 'semifinal';
    public const FASE_FINAL     = 'final';

    public const STATUS_AGENDADA  = 'agendada';
    public const STATUS_ANDAMENTO = 'andamento';
    public const STATUS_ENCERRADA = 'encerrada';

    /**
     * Construtor com injeção de dependência opcional da conexão PDO.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Agenda uma nova partida validando conflitos de quadra, data e horário (RN07).
     *
     * @param array $dados [edicao_id, fase, grupo?, data, horario, quadra, equipe_casa_id, equipe_visitante_id, mesario_id?]
     * @return int ID da partida agendada
     * @throws InvalidArgumentException|RuntimeException
     */
    public function agendar(array $dados): int
    {
        $edicaoId          = (int)($dados['edicao_id'] ?? 0);
        $fase              = trim((string)($dados['fase'] ?? self::FASE_GRUPOS));
        $grupo             = !empty($dados['grupo']) ? strtoupper(trim((string)$dados['grupo'])) : null;
        $data              = trim((string)($dados['data'] ?? ''));
        $horario           = trim((string)($dados['horario'] ?? ''));
        $quadra            = (int)($dados['quadra'] ?? 1);
        $equipeCasaId      = (int)($dados['equipe_casa_id'] ?? 0);
        $equipeVisitanteId = (int)($dados['equipe_visitante_id'] ?? 0);
        $mesarioId         = !empty($dados['mesario_id']) ? (int)$dados['mesario_id'] : null;

        // Validações básicas
        if ($edicaoId <= 0 || empty($data) || empty($horario) || $quadra <= 0) {
            throw new InvalidArgumentException("Edição, data, horário e quadra são obrigatórios.");
        }

        if ($equipeCasaId <= 0 || $equipeVisitanteId <= 0) {
            throw new InvalidArgumentException("Ambas as equipes (mandante e visitante) devem ser selecionadas.");
        }

        if ($equipeCasaId === $equipeVisitanteId) {
            throw new InvalidArgumentException("Uma equipe não pode jogar contra si mesma.");
        }

        if (!in_array($fase, [self::FASE_GRUPOS, self::FASE_QUARTAS, self::FASE_SEMIFINAL, self::FASE_FINAL], true)) {
            throw new InvalidArgumentException("Fase da competição inválida.");
        }

        // Validação da regra RN07: Restrição de conflitos de quadra, data e horário
        if ($this->verificarConflitoHorario($data, $horario, $quadra)) {
            throw new RuntimeException("RN07: Já existe uma partida agendada para a quadra {$quadra} na data {$data} no horário {$horario}.");
        }

        $sql = "INSERT INTO partidas (
                    edicao_id, fase, grupo, data, horario, quadra, 
                    equipe_casa_id, equipe_visitante_id, mesario_id, status
                ) VALUES (
                    :edicao_id, :fase, :grupo, :data, :horario, :quadra, 
                    :equipe_casa_id, :equipe_visitante_id, :mesario_id, :status
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':edicao_id', $edicaoId, PDO::PARAM_INT);
        $stmt->bindValue(':fase', $fase, PDO::PARAM_STR);
        $stmt->bindValue(':grupo', $grupo, $grupo !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':data', $data, PDO::PARAM_STR);
        $stmt->bindValue(':horario', $horario, PDO::PARAM_STR);
        $stmt->bindValue(':quadra', $quadra, PDO::PARAM_INT);
        $stmt->bindValue(':equipe_casa_id', $equipeCasaId, PDO::PARAM_INT);
        $stmt->bindValue(':equipe_visitante_id', $equipeVisitanteId, PDO::PARAM_INT);
        $stmt->bindValue(':mesario_id', $mesarioId, $mesarioId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':status', self::STATUS_AGENDADA, PDO::PARAM_STR);

        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * Busca os dados detalhados de uma partida pelo ID, incluindo placar calculado via súmula.
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT 
                    p.*,
                    ed.nome AS edicao_nome,
                    ed.ano AS edicao_ano,
                    ec.nome AS equipe_casa_nome,
                    ec.turma AS equipe_casa_turma,
                    ec.cor_uniforme AS equipe_casa_cor,
                    ev.nome AS equipe_visitante_nome,
                    ev.turma AS equipe_visitante_turma,
                    ev.cor_uniforme AS equipe_visitante_cor,
                    u.nome AS mesario_nome,
                    (
                        SELECT COUNT(*) 
                        FROM eventos_sumula es 
                        INNER JOIN jogadores j ON es.jogador_id = j.id 
                        WHERE es.partida_id = p.id AND es.tipo = 'gol' AND j.equipe_id = p.equipe_casa_id
                    ) AS gols_casa,
                    (
                        SELECT COUNT(*) 
                        FROM eventos_sumula es 
                        INNER JOIN jogadores j ON es.jogador_id = j.id 
                        WHERE es.partida_id = p.id AND es.tipo = 'gol' AND j.equipe_id = p.equipe_visitante_id
                    ) AS gols_visitante
                FROM partidas p
                INNER JOIN edicoes ed ON p.edicao_id = ed.id
                INNER JOIN equipes ec ON p.equipe_casa_id = ec.id
                INNER JOIN equipes ev ON p.equipe_visitante_id = ev.id
                LEFT JOIN usuarios u ON p.mesario_id = u.id
                WHERE p.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $partida = $stmt->fetch();
        return $partida ?: null;
    }

    /**
     * Lista as partidas de uma edição com filtros opcionais por fase e status.
     */
    public function listarPorEdicao(int $edicaoId, ?string $fase = null, ?string $status = null): array
    {
        $sql = "SELECT 
                    p.*,
                    ec.nome AS equipe_casa_nome,
                    ec.turma AS equipe_casa_turma,
                    ec.cor_uniforme AS equipe_casa_cor,
                    ev.nome AS equipe_visitante_nome,
                    ev.turma AS equipe_visitante_turma,
                    ev.cor_uniforme AS equipe_visitante_cor,
                    u.nome AS mesario_nome,
                    (
                        SELECT COUNT(*) 
                        FROM eventos_sumula es 
                        INNER JOIN jogadores j ON es.jogador_id = j.id 
                        WHERE es.partida_id = p.id AND es.tipo = 'gol' AND j.equipe_id = p.equipe_casa_id
                    ) AS gols_casa,
                    (
                        SELECT COUNT(*) 
                        FROM eventos_sumula es 
                        INNER JOIN jogadores j ON es.jogador_id = j.id 
                        WHERE es.partida_id = p.id AND es.tipo = 'gol' AND j.equipe_id = p.equipe_visitante_id
                    ) AS gols_visitante
                FROM partidas p
                INNER JOIN equipes ec ON p.equipe_casa_id = ec.id
                INNER JOIN equipes ev ON p.equipe_visitante_id = ev.id
                LEFT JOIN usuarios u ON p.mesario_id = u.id
                WHERE p.edicao_id = :edicao_id";

        $params = [':edicao_id' => $edicaoId];

        if ($fase !== null) {
            $sql .= " AND p.fase = :fase";
            $params[':fase'] = $fase;
        }

        if ($status !== null) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY p.data ASC, p.horario ASC, p.quadra ASC";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Lista as partidas atribuídas a determinado mesário (para painel e súmula eletrônica).
     */
    public function listarPorMesario(int $mesarioId, ?string $status = null): array
    {
        $sql = "SELECT 
                    p.*,
                    ec.nome AS equipe_casa_nome,
                    ec.turma AS equipe_casa_turma,
                    ev.nome AS equipe_visitante_nome,
                    ev.turma AS equipe_visitante_turma,
                    ed.nome AS edicao_nome,
                    (
                        SELECT COUNT(*) 
                        FROM eventos_sumula es 
                        INNER JOIN jogadores j ON es.jogador_id = j.id 
                        WHERE es.partida_id = p.id AND es.tipo = 'gol' AND j.equipe_id = p.equipe_casa_id
                    ) AS gols_casa,
                    (
                        SELECT COUNT(*) 
                        FROM eventos_sumula es 
                        INNER JOIN jogadores j ON es.jogador_id = j.id 
                        WHERE es.partida_id = p.id AND es.tipo = 'gol' AND j.equipe_id = p.equipe_visitante_id
                    ) AS gols_visitante
                FROM partidas p
                INNER JOIN equipes ec ON p.equipe_casa_id = ec.id
                INNER JOIN equipes ev ON p.equipe_visitante_id = ev.id
                INNER JOIN edicoes ed ON p.edicao_id = ed.id
                WHERE p.mesario_id = :mesario_id";

        $params = [':mesario_id' => $mesarioId];

        if ($status !== null) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY p.data ASC, p.horario ASC";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Atualiza o status de uma partida.
     */
    public function atualizarStatus(int $id, string $novoStatus): bool
    {
        if (!in_array($novoStatus, [self::STATUS_AGENDADA, self::STATUS_ANDAMENTO, self::STATUS_ENCERRADA], true)) {
            throw new InvalidArgumentException("Status de partida inválido.");
        }

        $sql = "UPDATE partidas SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':status', $novoStatus, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Inicia a partida alterando o status para 'andamento'.
     */
    public function iniciarPartida(int $id): bool
    {
        return $this->atualizarStatus($id, self::STATUS_ANDAMENTO);
    }

    /**
     * Encerra a partida e registra placar de 7 metros (caso haja desempate no mata-mata).
     */
    public function encerrarPartida(int $id, ?int $placarCasa7m = null, ?int $placarVisitante7m = null): bool
    {
        $sql = "UPDATE partidas 
                SET status = :status, 
                    placar_casa_7m = :casa7m, 
                    placar_visitante_7m = :vis7m 
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':status', self::STATUS_ENCERRADA, PDO::PARAM_STR);
        $stmt->bindValue(':casa7m', $placarCasa7m, $placarCasa7m !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':vis7m', $placarVisitante7m, $placarVisitante7m !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Atribui um mesário responsável para a partida.
     */
    public function atribuirMesario(int $partidaId, int $mesarioId): bool
    {
        $sql = "UPDATE partidas SET mesario_id = :mesario_id WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':mesario_id', $mesarioId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $partidaId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Retorna o placar consolidado da partida com base nos gols registrados na súmula.
     */
    public function obterPlacar(int $partidaId): array
    {
        $partida = $this->buscarPorId($partidaId);
        if (!$partida) {
            return ['gols_casa' => 0, 'gols_visitante' => 0, 'casa_7m' => null, 'visitante_7m' => null];
        }

        return [
            'gols_casa'      => (int)$partida['gols_casa'],
            'gols_visitante' => (int)$partida['gols_visitante'],
            'casa_7m'        => $partida['placar_casa_7m'] !== null ? (int)$partida['placar_casa_7m'] : null,
            'visitante_7m'   => $partida['placar_visitante_7m'] !== null ? (int)$partida['placar_visitante_7m'] : null,
        ];
    }

    /**
     * Obtém o histórico cronológico de todos os eventos da súmula da partida.
     */
    public function obterEventos(int $partidaId): array
    {
        $sql = "SELECT 
                    es.id,
                    es.partida_id,
                    es.jogador_id,
                    es.tipo,
                    es.minuto,
                    j.nome AS jogador_nome,
                    j.numero_camisa AS jogador_numero,
                    j.equipe_id,
                    e.nome AS equipe_nome
                FROM eventos_sumula es
                INNER JOIN jogadores j ON es.jogador_id = j.id
                INNER JOIN equipes e ON j.equipe_id = e.id
                WHERE es.partida_id = :partida_id
                ORDER BY es.minuto ASC, es.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':partida_id', $partidaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Valida a regra RN07: impede agendamento simultâneo na mesma quadra, data e horário.
     */
    public function verificarConflitoHorario(string $data, string $horario, int $quadra, ?int $ignorarPartidaId = null): bool
    {
        $sql = "SELECT COUNT(*) 
                FROM partidas 
                WHERE data = :data 
                  AND horario = :horario 
                  AND quadra = :quadra";

        if ($ignorarPartidaId !== null) {
            $sql .= " AND id != :ignorar_id";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':data', $data, PDO::PARAM_STR);
        $stmt->bindValue(':horario', $horario, PDO::PARAM_STR);
        $stmt->bindValue(':quadra', $quadra, PDO::PARAM_INT);

        if ($ignorarPartidaId !== null) {
            $stmt->bindValue(':ignorar_id', $ignorarPartidaId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Valida a regra RE07: Geração de novas fases condicionada ao encerramento prévio de todas as partidas da fase atual.
     */
    public function todasPartidasEncerradas(int $edicaoId, string $fase): bool
    {
        $sql = "SELECT COUNT(*) 
                FROM partidas 
                WHERE edicao_id = :edicao_id 
                  AND fase = :fase 
                  AND status != :status_encerrada";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':edicao_id', $edicaoId, PDO::PARAM_INT);
        $stmt->bindValue(':fase', $fase, PDO::PARAM_STR);
        $stmt->bindValue(':status_encerrada', self::STATUS_ENCERRADA, PDO::PARAM_STR);
        $stmt->execute();

        return ((int)$stmt->fetchColumn()) === 0;
    }

    // Constantes de tipos de evento da súmula
    public const EVENTO_GOL           = 'gol';
    public const EVENTO_EXCLUSAO_2MIN = 'exclusao_2min';
    public const EVENTO_AMARELO       = 'cartao_amarelo';
    public const EVENTO_VERMELHO      = 'cartao_vermelho';

    /**
     * Registra um novo lance/evento na súmula da partida.
     *
     * @param int $partidaId
     * @param int $jogadorId
     * @param string $tipo
     * @param int $minuto
     * @return int ID do evento registrado
     * @throws InvalidArgumentException
     */
    public function registrarEvento(int $partidaId, int $jogadorId, string $tipo, int $minuto): int
    {
        $tiposValidos = [
            self::EVENTO_GOL,
            self::EVENTO_EXCLUSAO_2MIN,
            self::EVENTO_AMARELO,
            self::EVENTO_VERMELHO
        ];

        if (!in_array($tipo, $tiposValidos, true)) {
            throw new InvalidArgumentException("Tipo de evento inválido na súmula.");
        }

        // RN08: Restrição de minuto (1 a 40)
        if ($minuto < 1 || $minuto > 40) {
            throw new InvalidArgumentException("RN08: O minuto do lance deve estar entre 1 e 40.");
        }

        $sql = "INSERT INTO eventos_sumula (partida_id, jogador_id, tipo, minuto) 
                VALUES (:partida_id, :jogador_id, :tipo, :minuto)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':partida_id', $partidaId, PDO::PARAM_INT);
        $stmt->bindValue(':jogador_id', $jogadorId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':minuto', $minuto, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * Remove um evento da súmula pelo ID (estorno/correção de lance).
     */
    public function removerEvento(int $eventoId): bool
    {
        $sql = "DELETE FROM eventos_sumula WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $eventoId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Busca um evento específico da súmula pelo ID.
     */
    public function buscarEventoPorId(int $eventoId): ?array
    {
        $sql = "SELECT es.*, j.nome AS jogador_nome, j.numero_camisa, j.equipe_id 
                FROM eventos_sumula es 
                INNER JOIN jogadores j ON es.jogador_id = j.id 
                WHERE es.id = :id 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $eventoId, PDO::PARAM_INT);
        $stmt->execute();
        $evento = $stmt->fetch();
        return $evento ?: null;
    }

    /**
     * Conta a quantidade de exclusões de 2 minutos aplicadas a um jogador em determinada partida.
     */
    public function contarExclusoesJogador(int $partidaId, int $jogadorId): int
    {
        $sql = "SELECT COUNT(*) FROM eventos_sumula 
                WHERE partida_id = :partida_id 
                  AND jogador_id = :jogador_id 
                  AND tipo = :tipo";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':partida_id', $partidaId, PDO::PARAM_INT);
        $stmt->bindValue(':jogador_id', $jogadorId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', self::EVENTO_EXCLUSAO_2MIN, PDO::PARAM_STR);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * Verifica se o atleta já recebeu cartão vermelho (desqualificação) na partida.
     */
    public function jogadorTemVermelho(int $partidaId, int $jogadorId): bool
    {
        $sql = "SELECT COUNT(*) FROM eventos_sumula 
                WHERE partida_id = :partida_id 
                  AND jogador_id = :jogador_id 
                  AND tipo = :tipo";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':partida_id', $partidaId, PDO::PARAM_INT);
        $stmt->bindValue(':jogador_id', $jogadorId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', self::EVENTO_VERMELHO, PDO::PARAM_STR);
        $stmt->execute();

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Conta quantos cartões amarelos o atleta já recebeu nesta partida.
     */
    public function contarAmarelosJogador(int $partidaId, int $jogadorId): int
    {
        $sql = "SELECT COUNT(*) FROM eventos_sumula 
                WHERE partida_id = :partida_id 
                  AND jogador_id = :jogador_id 
                  AND tipo = :tipo";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':partida_id', $partidaId, PDO::PARAM_INT);
        $stmt->bindValue(':jogador_id', $jogadorId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', self::EVENTO_AMARELO, PDO::PARAM_STR);
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }
}
