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
    <link rel="stylesheet" href="style.css?v=20260626-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-content">
                <div class="logo">
                    <a href="index.html">
                        <img src="logo-pampulha.png" alt="Churrascaria Pampulha" class="logo-img">
                    </a>
                </div>
                <a href="index.html" class="btn-voltar-site"><i class="fa-solid fa-arrow-left"></i> Voltar ao site</a>
            </div>
        </div>
    </nav>

    <section class="login-funcionario">
        <div class="login-card">
            <div class="boi-login" id="boiLogin" aria-hidden="true">
                <svg viewBox="0 0 100 90" xmlns="http://www.w3.org/2000/svg">
                    <path d="M25 18 Q15 5 10 15" stroke="#c41e3a" stroke-width="4" fill="none" stroke-linecap="round"/>
                    <path d="M75 18 Q85 5 90 15" stroke="#c41e3a" stroke-width="4" fill="none" stroke-linecap="round"/>
                    <ellipse cx="14" cy="44" rx="9" ry="13" fill="#fff" stroke="#c41e3a" stroke-width="3"/>
                    <ellipse cx="86" cy="44" rx="9" ry="13" fill="#fff" stroke="#c41e3a" stroke-width="3"/>
                    <ellipse cx="50" cy="50" rx="38" ry="32" fill="#fff" stroke="#c41e3a" stroke-width="3"/>
                    <ellipse cx="50" cy="68" rx="20" ry="13" fill="#fbe6e9"/>
                    <ellipse cx="42" cy="68" rx="3" ry="4" fill="#c41e3a"/>
                    <ellipse cx="58" cy="68" rx="3" ry="4" fill="#c41e3a"/>
                    <g class="boi-olho">
                        <circle class="boi-olho-aberto" cx="36" cy="46" r="6" fill="#2b1b12"/>
                        <path class="boi-olho-fechado" d="M30 46 Q36 52 42 46" stroke="#2b1b12" stroke-width="3" fill="none" stroke-linecap="round"/>
                    </g>
                    <g class="boi-olho">
                        <circle class="boi-olho-aberto" cx="64" cy="46" r="6" fill="#2b1b12"/>
                        <path class="boi-olho-fechado" d="M58 46 Q64 52 70 46" stroke="#2b1b12" stroke-width="3" fill="none" stroke-linecap="round"/>
                    </g>
                </svg>
            </div>
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

                var boi = document.getElementById('boiLogin');
                if (boi) {
                    boi.classList.toggle('boi-olhos-fechados', !mostrando);
                }
            });
        });
    </script>
</body>
</html>
