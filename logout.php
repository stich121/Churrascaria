<?php
require __DIR__ . '/auth.php';

encerrarSessao();

$destino = isset($_GET['timeout']) ? 'area-reservas.php?timeout=1' : 'area-reservas.php';
header('Location: ' . $destino);
exit;
