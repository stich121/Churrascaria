<?php
// Dados de conexão com o MySQL do Hostinger.
// Pegue host/usuario/senha em hPanel > Bancos de Dados > Gerenciar.
$DB_HOST = 'localhost';
$DB_NAME = 'u654041352_Reserva';
$DB_USER = 'TROQUE_PELO_SEU_USUARIO';
$DB_PASS = 'TROQUE_PELA_SUA_SENHA';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('Erro ao conectar ao banco de dados.');
}
