<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';

if (funcionarioLogado()) {
    header('Location: painel-reservas.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare('SELECT id, nome, senha_hash, nivel, ativo FROM funcionarios WHERE usuario = ?');
    $stmt->execute([$usuario]);
    $funcionario = $stmt->fetch();

    if ($funcionario && (int) $funcionario['ativo'] === 1 && password_verify($senha, $funcionario['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['funcionario_id'] = $funcionario['id'];
        $_SESSION['funcionario_nome'] = $funcionario['nome'];
        $_SESSION['funcionario_nivel'] = (int) $funcionario['nivel'];
        header('Location: painel-reservas.php');
        exit;
    }

    $erro = 'Usuário ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área Reservas - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-content">
                <div class="logo">
                    <a href="index.html">
                        <img src="Logo Branca carroça MC.png" alt="Churrascaria Pampulha" class="logo-img">
                    </a>
                </div>
                <a href="index.html" class="btn-voltar-site"><i class="fa-solid fa-arrow-left"></i> Voltar ao site</a>
            </div>
        </div>
    </nav>

    <section class="login-funcionario">
        <div class="login-card">
            <i class="fa-solid fa-lock login-icon"></i>
            <h2>Área Reservas</h2>
            <p class="login-subtitle">Acesso restrito à equipe da Churrascaria Pampulha</p>

            <form method="post" action="area-reservas.php">
                <label for="usuario">Usuário</label>
                <input type="text" id="usuario" name="usuario" placeholder="Digite seu usuário" required autocomplete="username">

                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required autocomplete="current-password">

                <?php if ($erro !== ''): ?>
                    <p class="login-erro"><?= e($erro) ?></p>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Entrar</button>
            </form>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 Churrascaria Pampulha - CNPJ 26.240.071/0001-55 | Todos os direitos reservados</p>
            </div>
        </div>
    </footer>
</body>
</html>
