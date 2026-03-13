<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metodo nao permitido.'], 405);
}

unset($_SESSION['user']);
jsonResponse(['success' => true, 'message' => 'Sessao Google encerrada.']);
