<?php
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Metodo nao permitido.']);
    exit;
}

if (!funcionarioLogado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sessao expirada.']);
    exit;
}

registrarAtividadeUsuario();

echo json_encode([
    'ok' => true,
    'lastActivity' => $_SESSION['ultima_atividade_usuario'],
    'timeout' => SESSAO_INATIVIDADE_SEGUNDOS,
]);
