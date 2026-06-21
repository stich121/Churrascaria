<?php
// Modelo de config.php — copie este arquivo para config.php e preencha com os
// dados reais do banco (hPanel > Bancos de Dados > Gerenciar). O config.php real
// não é versionado (está no .gitignore) para não expor credenciais no GitHub.
// No servidor, ele deve ficar UM NÍVEL ACIMA da public_html (fora da pasta
// pública do site), pra sobreviver mesmo se a public_html for limpa e
// resubstituída por um novo upload.
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
