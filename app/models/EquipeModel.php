<?php
/**
 * app/models/EquipeModel.php
 * Camada de persistência para Equipes participantes da Copa Interclasse
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../../core/Database.php';

class EquipeModel
{
    private PDO $db;

    public const SITUACAO_ATIVO   = 'ativo';
    public const SITUACAO_INATIVO = 'inativo';

    public const GRUPO_A = 'A';
    public const GRUPO_B = 'B';

    // Regra RN05: Quantidade mínima e máxima de atletas por equipe
    public const MIN_JOGADORES = 7;
    public const MAX_JOGADORES = 14;

    /**
     * Construtor com injeção de dependência opcional da conexão PDO.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Cadastra uma nova equipe na edição informada.
     *
     * @param array $dados [edicao_id, turma, nome, grupo, cor_uniforme, situacao?]
     * @return int ID da equipe cadastrada
     * @throws InvalidArgumentException
     */
    public function cadastrar(array $dados): int
    {
        $edicaoId    = (int)($dados['edicao_id'] ?? 0);
        $turma       = trim((string)($dados['turma'] ?? ''));
        $nome        = trim((string)($dados['nome'] ?? ''));
        $grupo       = !empty($dados['grupo']) ? strtoupper(trim((string)$dados['grupo'])) : null;
        $corUniforme = trim((string)($dados['cor_uniforme'] ?? ''));
        $situacao    = trim((string)($dados['situacao'] ?? self::SITUACAO_ATIVO));

        if ($edicaoId <= 0 || empty($turma) || empty($nome) || empty($corUniforme)) {
            throw new InvalidArgumentException("Edição, turma, nome da equipe e cor do uniforme são obrigatórios.");
        }

        if ($grupo !== null && !in_array($grupo, [self::GRUPO_A, self::GRUPO_B], true)) {
            throw new InvalidArgumentException("O grupo informado deve ser 'A' ou 'B'.");
        }

        $sql = "INSERT INTO equipes (edicao_id, turma, nome, grupo, cor_uniforme, situacao) 
                VALUES (:edicao_id, :turma, :nome, :grupo, :cor_uniforme, :situacao)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':edicao_id', $edicaoId, PDO::PARAM_INT);
        $stmt->bindValue(':turma', $turma, PDO::PARAM_STR);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':grupo', $grupo, $grupo !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':cor_uniforme', $corUniforme, PDO::PARAM_STR);
        $stmt->bindValue(':situacao', $situacao, PDO::PARAM_STR);

        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * Busca os dados de uma equipe pelo ID, com contagem total de jogadores inscritos.
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT 
                    e.*,
                    ed.nome AS edicao_nome,
                    ed.ano AS edicao_ano,
                    ed.status AS edicao_status,
                    (SELECT COUNT(*) FROM jogadores j WHERE j.equipe_id = e.id) AS total_jogadores,
                    (SELECT COUNT(*) FROM jogadores j WHERE j.equipe_id = e.id AND j.situacao = 'ativo') AS total_jogadores_aptos
                FROM equipes e
                INNER JOIN edicoes ed ON e.edicao_id = ed.id
                WHERE e.id = :id 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $equipe = $stmt->fetch();
        return $equipe ?: null;
    }

    /**
     * Lista as equipes de uma determinada edição, com opção de filtrar por grupo e situação.
     */
    public function listarPorEdicao(int $edicaoId, ?string $grupo = null, bool $apenasAtivas = false): array
    {
        $sql = "SELECT 
                    e.*,
                    (SELECT COUNT(*) FROM jogadores j WHERE j.equipe_id = e.id) AS total_jogadores,
                    (SELECT COUNT(*) FROM jogadores j WHERE j.equipe_id = e.id AND j.situacao = 'ativo') AS total_jogadores_aptos
                FROM equipes e
                WHERE e.edicao_id = :edicao_id";

        $params = [':edicao_id' => $edicaoId];

        if ($grupo !== null) {
            $sql .= " AND e.grupo = :grupo";
            $params[':grupo'] = strtoupper($grupo);
        }

        if ($apenasAtivas) {
            $sql .= " AND e.situacao = :situacao";
            $params[':situacao'] = self::SITUACAO_ATIVO;
        }

        $sql .= " ORDER BY e.grupo ASC, e.nome ASC";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            if (is_int($val)) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Atualiza os dados de uma equipe.
     */
    public function atualizar(int $id, array $dados): bool
    {
        $turma       = trim((string)($dados['turma'] ?? ''));
        $nome        = trim((string)($dados['nome'] ?? ''));
        $grupo       = !empty($dados['grupo']) ? strtoupper(trim((string)$dados['grupo'])) : null;
        $corUniforme = trim((string)($dados['cor_uniforme'] ?? ''));
        $situacao    = trim((string)($dados['situacao'] ?? self::SITUACAO_ATIVO));

        if (empty($turma) || empty($nome) || empty($corUniforme)) {
            throw new InvalidArgumentException("Turma, nome e cor do uniforme são obrigatórios.");
        }

        if ($grupo !== null && !in_array($grupo, [self::GRUPO_A, self::GRUPO_B], true)) {
            throw new InvalidArgumentException("Grupo inválido.");
        }

        $sql = "UPDATE equipes 
                SET turma = :turma, nome = :nome, grupo = :grupo, cor_uniforme = :cor_uniforme, situacao = :situacao 
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':turma', $turma, PDO::PARAM_STR);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':grupo', $grupo, $grupo !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':cor_uniforme', $corUniforme, PDO::PARAM_STR);
        $stmt->bindValue(':situacao', $situacao, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Inativa uma equipe respeitando a Regra de Negócio RN12.
     * Em conformidade com a integridade do banco (ON DELETE RESTRICT), equipes que possuem
     * histórico ou partidas não devem ser excluídas fisicamente, mas sim inativadas logicamente.
     *
     * @param int $id ID da equipe
     * @param bool $inativarJogadores Se true, inativa em cascata os jogadores da equipe
     * @return bool
     */
    public function inativar(int $id, bool $inativarJogadores = true): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE equipes SET situacao = :situacao WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':situacao', self::SITUACAO_INATIVO, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($inativarJogadores) {
                $sqlJogadores = "UPDATE jogadores SET situacao = :situacao WHERE equipe_id = :equipe_id";
                $stmtJog = $this->db->prepare($sqlJogadores);
                $stmtJog->bindValue(':situacao', self::SITUACAO_INATIVO, PDO::PARAM_STR);
                $stmtJog->bindValue(':equipe_id', $id, PDO::PARAM_INT);
                $stmtJog->execute();
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reativa uma equipe inativa.
     */
    public function reativar(int $id): bool
    {
        $sql = "UPDATE equipes SET situacao = :situacao WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':situacao', self::SITUACAO_ATIVO, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Verifica se a equipe possui partidas vinculadas (como mandante ou visitante).
     */
    public function temPartidas(int $equipeId): bool
    {
        $sql = "SELECT COUNT(*) FROM partidas WHERE equipe_casa_id = :id1 OR equipe_visitante_id = :id2";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id1', $equipeId, PDO::PARAM_INT);
        $stmt->bindValue(':id2', $equipeId, PDO::PARAM_INT);
        $stmt->execute();

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Avalia se uma equipe pode ser excluída fisicamente do banco de dados (respeitando RN12 e ON DELETE RESTRICT).
     *
     * @param int $equipeId
     * @return array [pode => bool, motivo => string]
     */
    public function podeExcluirFisicamente(int $equipeId): array
    {
        if ($this->temPartidas($equipeId)) {
            return [
                'pode'   => false,
                'motivo' => "RN12: A equipe possui partidas registradas no chaveamento. Utilize a inativação para preservar a integridade histórica da competição."
            ];
        }

        $sqlJog = "SELECT COUNT(*) FROM jogadores WHERE equipe_id = :equipe_id";
        $stmtJog = $this->db->prepare($sqlJog);
        $stmtJog->bindValue(':equipe_id', $equipeId, PDO::PARAM_INT);
        $stmtJog->execute();
        $totalJogadores = (int)$stmtJog->fetchColumn();

        if ($totalJogadores > 0) {
            return [
                'pode'   => false,
                'motivo' => "A equipe possui {$totalJogadores} jogador(es) vinculado(s). Remova os atletas antes ou inative a equipe."
            ];
        }

        return [
            'pode'   => true,
            'motivo' => "A equipe não possui vínculos de partidas nem atletas."
        ];
    }

    /**
     * Exclui fisicamente a equipe caso não tenha vínculos (conforme RN12 e restrições de FK).
     *
     * @throws RuntimeException Caso a exclusão física viole a integridade referencial
     */
    public function excluir(int $id): bool
    {
        $validacao = $this->podeExcluirFisicamente($id);
        if (!$validacao['pode']) {
            throw new RuntimeException($validacao['motivo']);
        }

        $sql = "DELETE FROM equipes WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Valida os requisitos da regra RN05 (equipes exigem de 7 a 14 atletas aptos).
     */
    public function verificarAptidao(int $equipeId): array
    {
        $sql = "SELECT COUNT(*) FROM jogadores WHERE equipe_id = :id AND situacao = 'ativo'";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $equipeId, PDO::PARAM_INT);
        $stmt->execute();
        $totalAptos = (int)$stmt->fetchColumn();

        $apta = ($totalAptos >= self::MIN_JOGADORES && $totalAptos <= self::MAX_JOGADORES);

        $motivo = '';
        if ($totalAptos < self::MIN_JOGADORES) {
            $motivo = "RN05: A equipe possui {$totalAptos} atleta(s) apto(s). O mínimo exigido é " . self::MIN_JOGADORES . ".";
        } elseif ($totalAptos > self::MAX_JOGADORES) {
            $motivo = "RN05: A equipe possui {$totalAptos} atletas inscritos. O limite máximo é " . self::MAX_JOGADORES . ".";
        } else {
            $motivo = "Equipe regularizada para disputar partidas.";
        }

        return [
            'apta'          => $apta,
            'total_atletas' => $totalAptos,
            'minimo'        => self::MIN_JOGADORES,
            'maximo'        => self::MAX_JOGADORES,
            'motivo'        => $motivo
        ];
    }
}
