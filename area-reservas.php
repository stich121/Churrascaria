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
        $_SESSION['funcionario_usuario'] = $usuario;
        registrarAtividadeUsuario();
        Logger::audit('login_success', ['usuario' => $usuario]);
        header('Location: dashboard.php');
        exit;
    }

    registrarTentativaFalhaLogin($pdo, $ip);
    Logger::warn('Tentativa de login falhou', ['action' => 'login_failed', 'usuario' => $usuario]);
    $erro = 'Usuário ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área Reservas - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css?v=20260630-1">
    <?php include __DIR__ . '/pwa-head.php'; ?>
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
                    <path d="M31 24 C21 12 14 10 10 18 C17 16 22 19 27 27" fill="none" stroke="#c41e3a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M69 24 C79 12 86 10 90 18 C83 16 78 19 73 27" fill="none" stroke="#c41e3a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M24 39 C14 31 7 34 8 46 C9 55 17 59 25 53" fill="#fff" stroke="#c41e3a" stroke-width="3" stroke-linejoin="round"/>
                    <path d="M76 39 C86 31 93 34 92 46 C91 55 83 59 75 53" fill="#fff" stroke="#c41e3a" stroke-width="3" stroke-linejoin="round"/>
                    <path d="M50 19 C29 19 19 35 20 52 C21 72 34 82 50 82 C66 82 79 72 80 52 C81 35 71 19 50 19Z" fill="#fff" stroke="#c41e3a" stroke-width="3" stroke-linejoin="round"/>
                    <path d="M33 62 C37 54 43 51 50 51 C57 51 63 54 67 62 C68 73 61 78 50 78 C39 78 32 73 33 62Z" fill="#fbe6e9" stroke="#c41e3a" stroke-width="2" stroke-linejoin="round"/>
                    <ellipse cx="42" cy="66" rx="3.2" ry="4.8" fill="#7b2d1b" transform="rotate(-12 42 66)"/>
                    <ellipse cx="58" cy="66" rx="3.2" ry="4.8" fill="#7b2d1b" transform="rotate(12 58 66)"/>
                    <path d="M28 31 C33 25 39 24 43 29" fill="none" stroke="#c41e3a" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M72 31 C67 25 61 24 57 29" fill="none" stroke="#c41e3a" stroke-width="2.5" stroke-linecap="round"/>
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
