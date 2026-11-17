<?php
/**
 * login.php
 * Tela de Autenticação para Organizadores e Mesários
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/core/AuthMiddleware.php';
AuthMiddleware::initSession();

// Se já estiver logado, redireciona para a área adequada
if (AuthMiddleware::isAuthenticated()) {
    $baseUrl = AuthMiddleware::getBaseUrl();
    if (AuthMiddleware::isOrganizador()) {
        header("Location: {$baseUrl}/admin/index.php");
        exit;
    } elseif (AuthMiddleware::isMesario()) {
        header("Location: {$baseUrl}/mesario/index.php");
        exit;
    } else {
        header("Location: {$baseUrl}/index.php");
        exit;
    }
}

$tituloPagina = 'Acesso Restrito';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center my-auto py-4">
    <div class="col-md-7 col-lg-5 col-xl-4">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="card-header bg-primary text-white text-center py-4">
                <div class="mb-2">
                    <i class="bi bi-shield-lock display-4 text-warning"></i>
                </div>
                <h4 class="fw-bold mb-1">Acesso Restrito</h4>
                <p class="text-white-50 small mb-0">Organização e Arbitragem da Copa Interclasse</p>
            </div>

            <div class="card-body p-4">
                <form method="POST" action="<?= $baseUrl ?>/app/controllers/AuthController.php?action=login" autocomplete="off">
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold text-dark">E-mail Institucional</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-envelope-at"></i>
                            </span>
                            <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" placeholder="seu.email@copa.com" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="senha" class="form-label fw-semibold text-dark">Senha de Acesso</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-key"></i>
                            </span>
                            <input type="password" class="form-control border-start-0 ps-0" id="senha" name="senha" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Entrar no Sistema
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="<?= $baseUrl ?>/index.php" class="text-decoration-none small text-muted">
                        <i class="bi bi-arrow-left me-1"></i>Voltar para a Página Inicial
                    </a>
                </div>
            </div>

            <!-- Dica de Acesso Padrão -->
            <div class="card-footer bg-light p-3 text-center border-top">
                <small class="text-muted d-block fw-semibold mb-1">Credenciais Demonstrativas:</small>
                <div class="small text-muted">
                    <span class="badge bg-secondary me-1">Organizador:</span> <code>admin@copa.com</code> / <code>admin123</code>
                </div>
                <div class="small text-muted mt-1">
                    <span class="badge bg-secondary me-1">Mesário:</span> <code>mesario@copa.com</code> / <code>mesario123</code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
