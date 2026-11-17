<?php
/**
 * app/controllers/AuthController.php
 * Controlador responsável pela autenticação, controle de sessão e logout de usuários.
 * Perfis atendidos: Organizador e Mesário.
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/AuthMiddleware.php';
require_once __DIR__ . '/../models/UsuarioModel.php';

class AuthController
{
    private UsuarioModel $usuarioModel;

    /**
     * Construtor com injeção de dependência opcional do Model de Usuários.
     */
    public function __construct(?UsuarioModel $usuarioModel = null)
    {
        $this->usuarioModel = $usuarioModel ?? new UsuarioModel();
    }

    /**
     * Processa a autenticação de login de organizadores e mesários.
     * Valida formato do e-mail, integridade de senha via password_verify()
     * e redireciona de acordo com o perfil atribuído.
     */
    public function login(): void
    {
        AuthMiddleware::initSession();

        $baseUrl = AuthMiddleware::getBaseUrl();

        // Se o usuário já estiver autenticado, redireciona diretamente para seu painel
        if (AuthMiddleware::isAuthenticated()) {
            $this->redirecionarPorPerfil();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: {$baseUrl}/login.php");
            exit;
        }

        // Sanitização e recepção de dados do formulário
        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
        $senha = (string)($_POST['senha'] ?? '');

        // Validações básicas de preenchimento
        if (empty($email) || empty($senha)) {
            AuthMiddleware::setFlash('erro', 'Por favor, preencha todos os campos para entrar.');
            header("Location: {$baseUrl}/login.php");
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            AuthMiddleware::setFlash('erro', 'O formato do e-mail informado é inválido.');
            header("Location: {$baseUrl}/login.php");
            exit;
        }

        try {
            // Autenticação segura contra o banco com password_verify() e checagem de usuário ativo
            $usuario = $this->usuarioModel->autenticar($email, $senha);

            if (!$usuario) {
                AuthMiddleware::setFlash('erro', 'E-mail ou senha incorretos, ou cadastro inativo.');
                header("Location: {$baseUrl}/login.php?erro=credenciais");
                exit;
            }

            // Registra a sessão segura com regeneração de ID contra Session Fixation
            AuthMiddleware::login($usuario);

            $nomeExibicao = htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8');
            AuthMiddleware::setFlash('sucesso', "Bem-vindo(a) de volta, {$nomeExibicao}!");

            // Redireciona para o painel correspondente ao perfil do usuário
            $this->redirecionarPorPerfil();

        } catch (Exception $e) {
            error_log("Erro de autenticação em AuthController: " . $e->getMessage());
            AuthMiddleware::setFlash('erro', 'Ocorreu um erro interno ao processar seu acesso. Tente novamente.');
            header("Location: {$baseUrl}/login.php");
            exit;
        }
    }

    /**
     * Encerra a sessão do usuário atual e o redireciona para a página de login.
     */
    public function logout(): void
    {
        AuthMiddleware::initSession();
        AuthMiddleware::logout(false);
    }

    /**
     * Redireciona o usuário para o painel de controle correspondente ao seu perfil.
     */
    public function redirecionarPorPerfil(): void
    {
        $baseUrl = AuthMiddleware::getBaseUrl();

        if (AuthMiddleware::isOrganizador()) {
            header("Location: {$baseUrl}/admin/index.php");
            exit;
        }

        if (AuthMiddleware::isMesario()) {
            header("Location: {$baseUrl}/mesario/index.php");
            exit;
        }

        header("Location: {$baseUrl}/index.php");
        exit;
    }

    /**
     * Despachante de ações (action router) para requisições diretas ao controller.
     */
    public function processar(): void
    {
        $action = trim((string)($_REQUEST['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'login' : '')));

        match ($action) {
            'login'  => $this->login(),
            'logout' => $this->logout(),
            default  => $this->redirecionarPorPerfil(),
        };
    }
}

// Auto-despacho caso o script seja invocado diretamente via URL ou form action
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'AuthController.php') {
    $controller = new AuthController();
    $controller->processar();
}
