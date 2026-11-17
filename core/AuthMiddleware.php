<?php
/**
 * core/AuthMiddleware.php
 * Middleware de Autenticação e Controle de Acesso Baseado em Perfis (RBAC)
 * Projeto: Copa Interclasse Handebol
 */

class AuthMiddleware
{
    // Constantes de perfis do sistema
    public const PERFIL_ORGANIZADOR = 'organizador';
    public const PERFIL_MESARIO     = 'mesario';
    public const PERFIL_VISITANTE   = 'visitante';

    // Tempo máximo de inatividade da sessão em segundos (2 horas)
    private const SESSION_TIMEOUT = 7200;

    /**
     * Inicializa a sessão com configurações seguras caso não esteja ativa.
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
                    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

                session_set_cookie_params([
                    'lifetime' => 0,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            @session_start();
        }

        // Validação de expiração de sessão por inatividade
        if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['usuario_perfil'])) {
            if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso'] > self::SESSION_TIMEOUT)) {
                self::logout(true);
            } else {
                $_SESSION['ultimo_acesso'] = time();
            }
        }
    }

    /**
     * Verifica se o usuário atual está autenticado na sessão.
     */
    public static function isAuthenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::initSession();
        }

        return !empty($_SESSION['usuario_id']) && !empty($_SESSION['usuario_perfil']);
    }

    /**
     * Retorna os dados do usuário autenticado ou nulo se visitante.
     */
    public static function getUser(): ?array
    {
        if (!self::isAuthenticated()) {
            return null;
        }

        return [
            'id'     => (int)$_SESSION['usuario_id'],
            'nome'   => (string)($_SESSION['usuario_nome'] ?? ''),
            'email'  => (string)($_SESSION['usuario_email'] ?? ''),
            'perfil' => (string)($_SESSION['usuario_perfil'] ?? self::PERFIL_VISITANTE),
        ];
    }

    /**
     * Retorna o perfil do usuário logado ou 'visitante'.
     */
    public static function getPerfil(): string
    {
        if (!self::isAuthenticated()) {
            return self::PERFIL_VISITANTE;
        }

        return strtolower(trim((string)($_SESSION['usuario_perfil'] ?? self::PERFIL_VISITANTE)));
    }

    /**
     * Verifica se o usuário logado possui determinado perfil ou está em um grupo de perfis permitidos.
     * 
     * @param string|array $perfis Perfil único ou array de perfis autorizados
     */
    public static function hasRole(string|array $perfis): bool
    {
        if (!self::isAuthenticated()) {
            return false;
        }

        $usuarioPerfil = self::getPerfil();

        if (is_array($perfis)) {
            $perfisNormalizados = array_map(fn($p) => strtolower(trim((string)$p)), $perfis);
            return in_array($usuarioPerfil, $perfisNormalizados, true);
        }

        return $usuarioPerfil === strtolower(trim((string)$perfis));
    }

    /**
     * Verifica se o usuário autenticado possui perfil de Organizador.
     */
    public static function isOrganizador(): bool
    {
        return self::hasRole(self::PERFIL_ORGANIZADOR);
    }

    /**
     * Verifica se o usuário autenticado possui perfil de Mesário.
     */
    public static function isMesario(): bool
    {
        return self::hasRole(self::PERFIL_MESARIO);
    }

    /**
     * Registra a autenticação do usuário na sessão, aplicando regeneração de ID para evitar Session Fixation.
     *
     * @param array $usuario Array associativo contendo dados do usuário (id, nome, email, perfil)
     */
    public static function login(array $usuario): void
    {
        self::initSession();

        // Regenera ID da sessão para prevenir Session Fixation
        if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }

        $_SESSION['usuario_id']     = (int)($usuario['id'] ?? 0);
        $_SESSION['usuario_nome']   = (string)($usuario['nome'] ?? '');
        $_SESSION['usuario_email']  = (string)($usuario['email'] ?? '');
        $_SESSION['usuario_perfil'] = strtolower(trim((string)($usuario['perfil'] ?? self::PERFIL_VISITANTE)));
        $_SESSION['ultimo_acesso']  = time();
    }

    /**
     * Encerra a sessão atual e apaga os cookies de sessão.
     *
     * @param bool $sessaoExpirada Indica se o logout ocorreu por tempo de inatividade
     */
    public static function logout(bool $sessaoExpirada = false): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::initSession();
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        $baseUrl = self::getBaseUrl();
        $param = $sessaoExpirada ? '?erro=expirada' : '?sucesso=logout';
        header("Location: {$baseUrl}/login.php{$param}");
        exit;
    }

    /**
     * Exige que o usuário esteja autenticado. Caso contrário, redireciona para o login.
     */
    public static function requireAuth(): void
    {
        self::initSession();

        if (!self::isAuthenticated()) {
            $baseUrl = self::getBaseUrl();
            $_SESSION['flash_erro'] = "Acesso restrito. Por favor, autentique-se para continuar.";
            header("Location: {$baseUrl}/login.php?erro=autenticacao");
            exit;
        }
    }

    /**
     * Exige autenticação e validação estrita de perfis permitidos.
     * Caso o usuário não tenha o perfil exigido, redireciona para a área adequada.
     *
     * @param string|array $perfisPermitidos Perfil ou lista de perfis permitidos
     */
    public static function requireRole(string|array $perfisPermitidos): void
    {
        self::requireAuth();

        if (!self::hasRole($perfisPermitidos)) {
            $baseUrl = self::getBaseUrl();
            $_SESSION['flash_erro'] = "Acesso negado. Você não possui permissão para acessar esta funcionalidade.";

            if (self::isOrganizador()) {
                header("Location: {$baseUrl}/admin/index.php");
            } elseif (self::isMesario()) {
                header("Location: {$baseUrl}/mesario/index.php");
            } else {
                header("Location: {$baseUrl}/index.php");
            }
            exit;
        }
    }

    /**
     * Exige estritamente o perfil de Organizador.
     */
    public static function requireOrganizador(): void
    {
        self::requireRole(self::PERFIL_ORGANIZADOR);
    }

    /**
     * Exige o perfil de Mesário ou Organizador (Organizador tem privilégio de supervisão da mesa).
     */
    public static function requireMesario(): void
    {
        self::requireRole([self::PERFIL_MESARIO, self::PERFIL_ORGANIZADOR]);
    }

    /**
     * Define uma mensagem flash na sessão para ser exibida na próxima renderização.
     */
    public static function setFlash(string $tipo, string $mensagem): void
    {
        self::initSession();
        $_SESSION['flash_' . $tipo] = $mensagem;
    }

    /**
     * Recupera e consome (limpa) uma mensagem flash da sessão.
     */
    public static function getFlash(string $tipo): ?string
    {
        self::initSession();
        $chave = 'flash_' . $tipo;
        if (isset($_SESSION[$chave])) {
            $msg = $_SESSION[$chave];
            unset($_SESSION[$chave]);
            return $msg;
        }
        return null;
    }

    /**
     * Detecta dinamicamente a URL base do projeto para redirecionamentos e links seguros.
     */
    public static function getBaseUrl(): string
    {
        if (defined('BASE_URL')) {
            return rtrim(BASE_URL, '/');
        }

        $projectFolder = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));

        if (!empty($docRoot) && strpos($projectFolder, $docRoot) === 0) {
            $relative = substr($projectFolder, strlen($docRoot));
            return rtrim(str_replace('\\', '/', $relative), '/');
        }

        // Fallback baseado no script em execução
        $scriptDir = dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
        if (preg_match('#/(admin|mesario|includes|core)$#i', $scriptDir)) {
            $scriptDir = dirname($scriptDir);
        }

        return $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
    }
}
