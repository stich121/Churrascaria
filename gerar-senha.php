<?php
// Utilitário de uso único para gerar o hash da senha do primeiro administrador.
// Suba este arquivo no Hostinger, acesse pelo navegador, gere o hash e cole no
// INSERT indicado em schema.sql. Depois disso, APAGUE este arquivo do servidor.

$hash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha = $_POST['senha'] ?? '';
    if (strlen($senha) >= 6) {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerar hash de senha</title>
    <style>
        body { font-family: sans-serif; max-width: 500px; margin: 60px auto; padding: 0 20px; }
        input, textarea, button { width: 100%; padding: 10px; margin-top: 8px; box-sizing: border-box; }
        button { margin-top: 16px; cursor: pointer; }
        .aviso { color: #b00020; font-weight: bold; margin-top: 30px; }
    </style>
</head>
<body>
    <h2>Gerar hash de senha (uso único)</h2>
    <form method="post">
        <label for="senha">Senha desejada (mín. 6 caracteres)</label>
        <input type="password" id="senha" name="senha" minlength="6" required>
        <button type="submit">Gerar hash</button>
    </form>

    <?php if ($hash !== ''): ?>
        <p>Copie o hash abaixo e cole no lugar de <code>COLE_O_HASH_AQUI</code> no INSERT do schema.sql:</p>
        <textarea readonly rows="3"><?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?></textarea>
    <?php endif; ?>

    <p class="aviso">Apague este arquivo (gerar-senha.php) do servidor depois de usar!</p>
</body>
</html>
