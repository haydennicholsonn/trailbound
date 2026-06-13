<?php

declare(strict_types=1);

$privatePath = realpath(__DIR__ . '/../../private');

if ($privatePath === false) {
    $fallback = realpath(__DIR__ . '/../private');
    $privatePath = $fallback !== false ? $fallback : null;
}

if ($privatePath === null) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'BOOTSTRAP_MISSING',
            'message' => 'Private bootstrap folder could not be found.',
        ],
    ]);
    exit;
}

require_once $privatePath . '/config/bootstrap.php';
