<?php
/**
 * logout.php
 * Script de Encerramento Seguro de Sessão
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/core/AuthMiddleware.php';

AuthMiddleware::initSession();
AuthMiddleware::logout(false);
