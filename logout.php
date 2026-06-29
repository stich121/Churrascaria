<?php
require __DIR__ . '/auth.php';

if (!empty($_SESSION['funcionario_id'])) {
    Logger::audit('logout', ['user_id' => $_SESSION['funcionario_id']]);
}

encerrarSessao();

$destino = isset($_GET['timeout']) ? 'area-reservas.php?timeout=1' : 'area-reservas.php';
header('Location: ' . $destino);
exit;
