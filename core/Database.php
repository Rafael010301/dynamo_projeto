<?php
/**
 * core/Database.php
 * Conexão com o banco de dados MySQL utilizando PDO e o padrão de projeto Singleton.
 * Projeto: Copa Interclasse Handebol
 */

class Database
{
    /**
     * Instância única da classe Database (Singleton)
     */
    private static ?Database $instance = null;

    /**
     * Instância do objeto de conexão PDO
     */
    private ?PDO $connection = null;

    /**
     * Configurações de conexão com o banco de dados
     */
    private string $host;
    private string $port;
    private string $dbname;
    private string $user;
    private string $pass;
    private string $charset;

    /**
     * Construtor privado para evitar instanciação direta fora da classe.
     * Estabelece a conexão com o MySQL via PDO com tratamento de exceções.
     */
    private function __construct()
    {
        $this->host    = defined('DB_HOST')    ? DB_HOST    : 'localhost';
        $this->port    = defined('DB_PORT')    ? DB_PORT    : '3306';
        $this->dbname  = defined('DB_NAME')    ? DB_NAME    : 'copa_interclasse';
        $this->user    = defined('DB_USER')    ? DB_USER    : 'root';
        $this->pass    = defined('DB_PASS')    ? DB_PASS    : '';
        $this->charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->connection = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            // Em caso de falha na conexão, interrompe com mensagem explicativa ou propaga a exceção
            throw new PDOException("Erro na conexão com o banco de dados [copa_interclasse]: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * Previne a clonagem do objeto Singleton.
     */
    private function __clone(): void
    {
    }

    /**
     * Previne a desserialização do objeto Singleton.
     * 
     * @throws Exception
     */
    public function __wakeup(): void
    {
        throw new Exception("Não é permitido desserializar uma instância de Singleton.");
    }

    /**
     * Retorna a instância única da classe Database.
     * 
     * @return Database
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Retorna o objeto de conexão PDO ativo.
     * Permite chamadas tanto estáticas: Database::getConnection()
     * quanto via instância: Database::getInstance()->getConnection()
     * 
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        return self::getInstance()->connection;
    }

    /**
     * Retorna diretamente o objeto PDO (atalho alternativo para a instância).
     * 
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->connection;
    }

    /**
     * Redireciona chamadas de métodos inexistentes na classe diretamente para o objeto PDO.
     * Exemplo: Database::getInstance()->prepare(...) ou Database::getInstance()->query(...)
     * 
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if ($this->connection && method_exists($this->connection, $name)) {
            return $this->connection->$name(...$arguments);
        }

        throw new BadMethodCallException("Método '{$name}' não encontrado na classe Database nem no PDO.");
    }
}
