<?php
/**
 * includes/protege.php
 * Script de inclusão rápida para proteção imediata de rotas e páginas restritas.
 * Garante que a sessão seja validada e que o usuário possua o perfil autorizado.
 * Projeto: Copa Interclasse Handebol
 * 
 * Uso básico:
 *   require_once __DIR__ . '/../includes/protege.php';
 * 
 * Ou com perfil específico explícito:
 *   $perfilNecessario = 'organizador'; // ou 'mesario'
 *   require_once __DIR__ . '/../includes/protege.php';
 */

require_once __DIR__ . '/../core/AuthMiddleware.php';

// Inicializa a sessão com segurança
AuthMiddleware::initSession();

// Bloqueia imediatamente usuários sem sessão ativa
AuthMiddleware::requireAuth();

// Se o script chamador definiu previamente $perfilNecessario, valida com prioridade
if (isset($perfilNecessario) && !empty($perfilNecessario)) {
    AuthMiddleware::requireRole($perfilNecessario);
} else {
    // Identificação contextual automática com base no diretório da requisição
    $scriptAtual = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    // Proteção automática para a pasta /admin/ (restrita a Organizadores)
    if (strpos($scriptAtual, '/admin/') !== false) {
        AuthMiddleware::requireOrganizador();
    }

    // Proteção automática para a pasta /mesario/ (Mesários e Organizadores)
    if (strpos($scriptAtual, '/mesario/') !== false) {
        AuthMiddleware::requireMesario();
    }
}
