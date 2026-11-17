<?php
/**
 * app/models/UsuarioModel.php
 * Camada de persistência para Usuários (Organizador e Mesário)
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../../core/Database.php';

class UsuarioModel
{
    private PDO $db;

    public const PERFIL_ORGANIZADOR = 'organizador';
    public const PERFIL_MESARIO     = 'mesario';

    public const SITUACAO_ATIVO   = 'ativo';
    public const SITUACAO_INATIVO = 'inativo';

    /**
     * Construtor com injeção de dependência opcional da conexão PDO.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Autentica um usuário verificando email, situação ativa e hash de senha com password_verify().
     *
     * @param string $email
     * @param string $senha
     * @return array|null Retorna os dados do usuário autenticado (sem a senha) ou null se inválido
     */
    public function autenticar(string $email, string $senha): ?array
    {
        $sql = "SELECT id, nome, email, senha, perfil, situacao, criado_at 
                FROM usuarios 
                WHERE email = :email 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', trim($email), PDO::PARAM_STR);
        $stmt->execute();

        $usuario = $stmt->fetch();

        if (!$usuario) {
            return null;
        }

        // Usuários inativos não podem logar no sistema
        if ($usuario['situacao'] !== self::SITUACAO_ATIVO) {
            return null;
        }

        // Validação rigorosa do hash da senha
        if (password_verify($senha, $usuario['senha'])) {
            // Remove o hash da memória antes de retornar
            unset($usuario['senha']);
            return $usuario;
        }

        return null;
    }

    /**
     * Busca um usuário pelo ID.
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT id, nome, email, perfil, situacao, criado_at 
                FROM usuarios 
                WHERE id = :id 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $usuario = $stmt->fetch();
        return $usuario ?: null;
    }

    /**
     * Busca um usuário pelo e-mail.
     */
    public function buscarPorEmail(string $email): ?array
    {
        $sql = "SELECT id, nome, email, perfil, situacao, criado_at 
                FROM usuarios 
                WHERE email = :email 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', trim($email), PDO::PARAM_STR);
        $stmt->execute();

        $usuario = $stmt->fetch();
        return $usuario ?: null;
    }

    /**
     * Cadastra um novo usuário gerando o hash seguro com password_hash().
     *
     * @param array $dados [nome, email, senha, perfil, situacao?]
     * @return int ID do usuário inserido
     * @throws InvalidArgumentException|RuntimeException
     */
    public function cadastrar(array $dados): int
    {
        $nome     = trim((string)($dados['nome'] ?? ''));
        $email    = trim((string)($dados['email'] ?? ''));
        $senha    = (string)($dados['senha'] ?? '');
        $perfil   = trim((string)($dados['perfil'] ?? self::PERFIL_MESARIO));
        $situacao = trim((string)($dados['situacao'] ?? self::SITUACAO_ATIVO));

        if (empty($nome) || empty($email) || empty($senha)) {
            throw new InvalidArgumentException("Nome, e-mail e senha são obrigatórios.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Formato de e-mail inválido.");
        }

        if (!in_array($perfil, [self::PERFIL_ORGANIZADOR, self::PERFIL_MESARIO], true)) {
            throw new InvalidArgumentException("Perfil de usuário inválido.");
        }

        if ($this->emailExiste($email)) {
            throw new RuntimeException("O e-mail informado já está cadastrado.");
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "INSERT INTO usuarios (nome, email, senha, perfil, situacao) 
                VALUES (:nome, :email, :senha, :perfil, :situacao)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':senha', $senhaHash, PDO::PARAM_STR);
        $stmt->bindValue(':perfil', $perfil, PDO::PARAM_STR);
        $stmt->bindValue(':situacao', $situacao, PDO::PARAM_STR);

        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * Atualiza dados de cadastro do usuário (sem alterar a senha).
     */
    public function atualizar(int $id, array $dados): bool
    {
        $nome     = trim((string)($dados['nome'] ?? ''));
        $email    = trim((string)($dados['email'] ?? ''));
        $perfil   = trim((string)($dados['perfil'] ?? ''));
        $situacao = trim((string)($dados['situacao'] ?? self::SITUACAO_ATIVO));

        if (empty($nome) || empty($email) || empty($perfil)) {
            throw new InvalidArgumentException("Nome, e-mail e perfil são campos obrigatórios.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Formato de e-mail inválido.");
        }

        if ($this->emailExiste($email, $id)) {
            throw new RuntimeException("O e-mail informado já está em uso por outro usuário.");
        }

        $sql = "UPDATE usuarios 
                SET nome = :nome, email = :email, perfil = :perfil, situacao = :situacao 
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':perfil', $perfil, PDO::PARAM_STR);
        $stmt->bindValue(':situacao', $situacao, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Atualiza a senha de um usuário gerando novo hash seguro.
     */
    public function atualizarSenha(int $id, string $novaSenha): bool
    {
        if (strlen($novaSenha) < 6) {
            throw new InvalidArgumentException("A nova senha deve ter no mínimo 6 caracteres.");
        }

        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

        $sql = "UPDATE usuarios SET senha = :senha WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':senha', $senhaHash, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Inativa um usuário do sistema.
     */
    public function inativar(int $id): bool
    {
        $sql = "UPDATE usuarios SET situacao = :situacao WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':situacao', self::SITUACAO_INATIVO, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Reativa um usuário no sistema.
     */
    public function ativar(int $id): bool
    {
        $sql = "UPDATE usuarios SET situacao = :situacao WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':situacao', self::SITUACAO_ATIVO, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Lista todos os usuários cadastrados com filtros opcionais.
     */
    public function listarTodos(?string $perfil = null, ?string $situacao = null): array
    {
        $sql = "SELECT id, nome, email, perfil, situacao, criado_at 
                FROM usuarios 
                WHERE 1=1";
        $params = [];

        if ($perfil !== null) {
            $sql .= " AND perfil = :perfil";
            $params[':perfil'] = $perfil;
        }

        if ($situacao !== null) {
            $sql .= " AND situacao = :situacao";
            $params[':situacao'] = $situacao;
        }

        $sql .= " ORDER BY nome ASC";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Lista os mesários cadastrados e aptos para atribuição de partidas.
     */
    public function listarMesarios(bool $apenasAtivos = true): array
    {
        $situacao = $apenasAtivos ? self::SITUACAO_ATIVO : null;
        return $this->listarTodos(self::PERFIL_MESARIO, $situacao);
    }

    /**
     * Verifica se determinado e-mail já existe no banco.
     */
    public function emailExiste(string $email, ?int $ignorarId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email";
        if ($ignorarId !== null) {
            $sql .= " AND id != :ignorarId";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', trim($email), PDO::PARAM_STR);
        if ($ignorarId !== null) {
            $stmt->bindValue(':ignorarId', $ignorarId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return ((int)$stmt->fetchColumn()) > 0;
    }
}
