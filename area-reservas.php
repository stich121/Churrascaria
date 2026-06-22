<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';

if (funcionarioLogado()) {
    header('Location: dashboard.php');
    exit;
}

$erro = isset($_GET['timeout']) ? 'Sua sessão foi encerrada por inatividade.' : '';
$ip = clienteIp();
$bloqueioRestante = verificarBloqueioLogin($pdo, $ip);

if ($bloqueioRestante > 0) {
    $erro = 'Muitas tentativas de login. Tente novamente em ' . ceil($bloqueioRestante / 60) . ' minuto(s).';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare('SELECT id, nome, senha_hash, nivel, ativo FROM funcionarios WHERE usuario = ?');
    $stmt->execute([$usuario]);
    $funcionario = $stmt->fetch();

    if ($funcionario && (int) $funcionario['ativo'] === 1 && password_verify($senha, $funcionario['senha_hash'])) {
        limparTentativasLogin($pdo, $ip);
        session_regenerate_id(true);
        $_SESSION['funcionario_id'] = $funcionario['id'];
        $_SESSION['funcionario_nome'] = $funcionario['nome'];
        $_SESSION['funcionario_nivel'] = (int) $funcionario['nivel'];
        registrarAtividadeUsuario();
        header('Location: dashboard.php');
        exit;
    }

    registrarTentativaFalhaLogin($pdo, $ip);
    $erro = 'Usuário ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área Reservas - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css?v=20260621-7">
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
                <input type="text" id="usuario" name="usuario" placeholder="Digite seu usuário" required autocomplete="username" <?= $bloqueioRestante > 0 ? 'disabled' : '' ?>>

                <label for="senha">Senha</label>
                <div class="campo-senha">
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required autocomplete="current-password" <?= $bloqueioRestante > 0 ? 'disabled' : '' ?>>
                    <button type="button" class="toggle-senha" data-target="senha" aria-label="Mostrar senha">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>

                <?php if ($erro !== ''): ?>
                    <p class="login-erro"><?= e($erro) ?></p>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary" <?= $bloqueioRestante > 0 ? 'disabled' : '' ?>><i class="fa-solid fa-right-to-bracket"></i>Entrar</button>
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

    <script>
        document.querySelectorAll('.toggle-senha').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var input = document.getElementById(botao.dataset.target);
                var icone = botao.querySelector('i');
                var mostrando = input.type === 'text';

                input.type = mostrando ? 'password' : 'text';
                icone.classList.toggle('fa-eye', mostrando);
                icone.classList.toggle('fa-eye-slash', !mostrando);
                botao.setAttribute('aria-label', mostrando ? 'Mostrar senha' : 'Ocultar senha');
            });
        });
    </script>
</body>
</html>
