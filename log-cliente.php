<?php
// Recebe logs de erro/warn do navegador (logger.js) e grava via Logger,
// já sanitizado. Só aceita requisições de quem já está autenticado.
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !funcionarioLogado()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$corpo = json_decode(file_get_contents('php://input'), true);
$nivel = is_array($corpo) ? ($corpo['level'] ?? '') : '';
$mensagem = is_array($corpo) ? (string) ($corpo['message'] ?? '') : '';
$contexto = is_array($corpo) && is_array($corpo['context'] ?? null) ? $corpo['context'] : [];

if ($mensagem !== '' && in_array($nivel, ['warn', 'error'], true)) {
    $contexto['origin'] = 'client';
    if ($nivel === 'error') {
        Logger::error($mensagem, $contexto);
    } else {
        Logger::warn($mensagem, $contexto);
    }
}

echo json_encode(['ok' => true]);
