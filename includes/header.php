<?php
/**
 * includes/header.php
 * Cabeçalho unificado com Bootstrap 5 via CDN, barra de navegação responsiva e suporte a UTF-8.
 * Projeto: Copa Interclasse Handebol
 */

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/../core/AuthMiddleware.php';
AuthMiddleware::initSession();

$baseUrl      = AuthMiddleware::getBaseUrl();
$isLogged     = AuthMiddleware::isAuthenticated();
$currentUser  = AuthMiddleware::getUser();
$userPerfil   = AuthMiddleware::getPerfil();
$paginaTitulo = isset($tituloPagina) ? htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8') . ' | Copa Interclasse' : 'Copa Interclasse Handebol';
$scriptAtual  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $paginaTitulo ?></title>

    <!-- Bootstrap 5 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Estilo Customizado (se existir) -->
    <?php if (file_exists(__DIR__ . '/../assets/css/style.css')): ?>
        <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css">
    <?php endif; ?>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

    <!-- Barra de Navegação Superior -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= $baseUrl ?>/index.php">
                <i class="bi bi-trophy-fill text-warning fs-4 me-2"></i>
                <span>Copa Interclasse <span class="badge bg-warning text-dark fs-6 ms-1">Handebol</span></span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarConteudo" aria-controls="navbarConteudo" aria-expanded="false" aria-label="Alternar navegação">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarConteudo">
                <!-- Links Públicos -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= (str_ends_with($scriptAtual, '/index.php') && !str_contains($scriptAtual, '/admin/') && !str_contains($scriptAtual, '/mesario/')) ? 'active fw-semibold' : '' ?>" href="<?= $baseUrl ?>/index.php">
                            <i class="bi bi-house-door me-1"></i>Início
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_ends_with($scriptAtual, '/classificacao.php') ? 'active fw-semibold' : '' ?>" href="<?= $baseUrl ?>/classificacao.php">
                            <i class="bi bi-bar-chart-line me-1"></i>Classificação
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_ends_with($scriptAtual, '/jogos.php') ? 'active fw-semibold' : '' ?>" href="<?= $baseUrl ?>/jogos.php">
                            <i class="bi bi-calendar-event me-1"></i>Jogos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_ends_with($scriptAtual, '/equipes.php') ? 'active fw-semibold' : '' ?>" href="<?= $baseUrl ?>/equipes.php">
                            <i class="bi bi-shield-shaded me-1"></i>Equipes
                        </a>
                    </li>

                    <!-- Menu Restrito do Organizador -->
                    <?php if (AuthMiddleware::isOrganizador()): ?>
                        <li class="nav-item dropdown ms-lg-2">
                            <a class="nav-link dropdown-toggle text-warning fw-semibold <?= str_contains($scriptAtual, '/admin/') ? 'active' : '' ?>" href="#" id="menuAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-gear-fill me-1"></i>Administração
                            </a>
                            <ul class="dropdown-menu shadow" aria-labelledby="menuAdmin">
                                <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/index.php"><i class="bi bi-speedometer2 me-2"></i>Painel Geral</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/edicoes.php"><i class="bi bi-award me-2"></i>Edições</a></li>
                                <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/equipes.php"><i class="bi bi-people me-2"></i>Equipes & Atletas</a></li>
                                <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/partidas.php"><i class="bi bi-calendar2-week me-2"></i>Partidas & Chaveamento</a></li>
                                <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/usuarios.php"><i class="bi bi-person-badge me-2"></i>Usuários do Sistema</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-primary" href="<?= $baseUrl ?>/mesario/index.php"><i class="bi bi-stopwatch me-2"></i>Acessar Mesa / Súmula</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- Menu Restrito do Mesário -->
                    <?php if (AuthMiddleware::isMesario() && !AuthMiddleware::isOrganizador()): ?>
                        <li class="nav-item ms-lg-2">
                            <a class="nav-link text-warning fw-semibold <?= str_contains($scriptAtual, '/mesario/') ? 'active' : '' ?>" href="<?= $baseUrl ?>/mesario/index.php">
                                <i class="bi bi-stopwatch-fill me-1"></i>Súmula Eletrônica
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Informações do Usuário / Login / Logout -->
                <div class="d-flex align-items-center">
                    <?php if ($isLogged): ?>
                        <div class="d-flex align-items-center me-3 text-white">
                            <?php if ($userPerfil === AuthMiddleware::PERFIL_ORGANIZADOR): ?>
                                <span class="badge bg-danger shadow-sm me-2">
                                    <i class="bi bi-shield-lock-fill me-1"></i>Organizador
                                </span>
                            <?php elseif ($userPerfil === AuthMiddleware::PERFIL_MESARIO): ?>
                                <span class="badge bg-warning text-dark shadow-sm me-2">
                                    <i class="bi bi-stopwatch-fill me-1"></i>Mesário
                                </span>
                            <?php endif; ?>

                            <span class="small fw-semibold">
                                <i class="bi bi-person-circle me-1"></i>
                                <?= htmlspecialchars($currentUser['nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <a href="<?= $baseUrl ?>/logout.php" class="btn btn-outline-light btn-sm" title="Encerrar Sessão">
                            <i class="bi bi-box-arrow-right me-1"></i>Sair
                        </a>
                    <?php else: ?>
                        <a href="<?= $baseUrl ?>/login.php" class="btn btn-warning btn-sm fw-semibold shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Acesso Restrito
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Exibição de Mensagens Flash do Sistema -->
    <div class="container mt-3">
        <?php foreach (['sucesso' => 'success', 'erro' => 'danger', 'aviso' => 'warning', 'info' => 'info'] as $tipo => $classeBootstrap): ?>
            <?php $mensagemFlash = AuthMiddleware::getFlash($tipo); ?>
            <?php if ($mensagemFlash !== null): ?>
                <div class="alert alert-<?= $classeBootstrap ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi <?= match($tipo) {
                        'sucesso' => 'bi-check-circle-fill',
                        'erro'    => 'bi-exclamation-triangle-fill',
                        'aviso'   => 'bi-exclamation-circle-fill',
                        'info'    => 'bi-info-circle-fill',
                    } ?> me-2"></i>
                    <?= htmlspecialchars($mensagemFlash, ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Conteúdo Principal da Página -->
    <main class="container py-4 flex-grow-1">
