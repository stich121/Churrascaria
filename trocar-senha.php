<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();

$nivel = nivelFuncionario();
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    $senhaAtual = $_POST['senha_atual'] ?? '';
    $senhaNova = $_POST['senha_nova'] ?? '';
    $confirmacao = $_POST['confirmar_senha_nova'] ?? '';

    $stmt = $pdo->prepare('SELECT senha_hash FROM funcionarios WHERE id = ?');
    $stmt->execute([$_SESSION['funcionario_id']]);
    $funcionario = $stmt->fetch();

    if (!$funcionario || !password_verify($senhaAtual, $funcionario['senha_hash'])) {
        $erro = 'Senha atual incorreta.';
    } elseif (strlen($senhaNova) < 6) {
        $erro = 'A nova senha precisa ter pelo menos 6 caracteres.';
    } elseif ($senhaNova !== $confirmacao) {
        $erro = 'A confirmação não corresponde à nova senha.';
    } else {
        $hash = password_hash($senhaNova, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE funcionarios SET senha_hash = ? WHERE id = ?')->execute([$hash, $_SESSION['funcionario_id']]);
        $sucesso = 'Senha alterada com sucesso.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trocar Senha - Churrascaria Pampulha</title>
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
                <ul class="funcionario-nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="painel-reservas.php">Painel de Reservas</a></li>
                    <li><a href="mesas.php">Mesas</a></li>
                    <?php if ($nivel >= NIVEL_GERENTE): ?>
                        <li><a href="funcionarios.php">Funcionários</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php" class="btn-voltar-site"><i class="fa-solid fa-right-from-bracket"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="login-funcionario">
        <div class="login-card">
            <i class="fa-solid fa-key login-icon"></i>
            <h2>Trocar Senha</h2>
            <p class="login-subtitle">Olá, <?= e($_SESSION['funcionario_nome']) ?></p>

            <form method="post" action="trocar-senha.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <label for="senha_atual">Senha atual</label>
                <div class="campo-senha">
                    <input type="password" id="senha_atual" name="senha_atual" required autocomplete="current-password">
                    <button type="button" class="toggle-senha" data-target="senha_atual" aria-label="Mostrar senha">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>

                <label for="senha_nova">Nova senha</label>
                <div class="campo-senha">
                    <input type="password" id="senha_nova" name="senha_nova" minlength="6" required autocomplete="new-password">
                    <button type="button" class="toggle-senha" data-target="senha_nova" aria-label="Mostrar senha">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>

                <label for="confirmar_senha_nova">Confirmar nova senha</label>
                <div class="campo-senha">
                    <input type="password" id="confirmar_senha_nova" name="confirmar_senha_nova" minlength="6" required autocomplete="new-password">
                    <button type="button" class="toggle-senha" data-target="confirmar_senha_nova" aria-label="Mostrar senha">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>

                <?php if ($erro !== ''): ?>
                    <p class="login-erro"><?= e($erro) ?></p>
                <?php endif; ?>
                <?php if ($sucesso !== ''): ?>
                    <p class="login-sucesso"><?= e($sucesso) ?></p>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Salvar Nova Senha</button>
            </form>
        </div>
    </section>

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
