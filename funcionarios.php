<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirNivel(NIVEL_GERENTE);

$nivelLogado = nivelFuncionario();
$idLogado = (int) $_SESSION['funcionario_id'];
$erro = '';
$sucesso = '';

function nivelMaximoQuePodeCriar(int $nivelLogado): int
{
    return $nivelLogado >= NIVEL_SUPERIOR ? NIVEL_SUPERIOR : NIVEL_ATENDENTE;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $nivelNovo = (int) ($_POST['nivel'] ?? 0);

        if ($nome === '' || $usuario === '' || strlen($senha) < 6) {
            $erro = 'Preencha nome, usuário e uma senha com pelo menos 6 caracteres.';
        } elseif ($nivelNovo < NIVEL_ATENDENTE || $nivelNovo > nivelMaximoQuePodeCriar($nivelLogado)) {
            $erro = 'Você não tem permissão para cadastrar um funcionário desse nível.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM funcionarios WHERE usuario = ?');
            $stmt->execute([$usuario]);

            if ($stmt->fetch()) {
                $erro = 'Esse nome de usuário já está em uso.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO funcionarios (nome, usuario, senha_hash, nivel) VALUES (?, ?, ?, ?)');
                $stmt->execute([$nome, $usuario, $hash, $nivelNovo]);
                $sucesso = 'Funcionário cadastrado com sucesso.';
            }
        }
    }

    if ($acao === 'alternar_status') {
        $idAlvo = (int) ($_POST['id'] ?? 0);

        if ($idAlvo === $idLogado) {
            $erro = 'Você não pode ativar ou desativar a própria conta.';
        } else {
            $stmt = $pdo->prepare('SELECT nivel FROM funcionarios WHERE id = ?');
            $stmt->execute([$idAlvo]);
            $alvo = $stmt->fetch();

            $podeAlterar = $alvo && ($nivelLogado >= NIVEL_SUPERIOR || (int) $alvo['nivel'] < NIVEL_GERENTE);

            if ($podeAlterar) {
                $pdo->prepare('UPDATE funcionarios SET ativo = NOT ativo WHERE id = ?')->execute([$idAlvo]);
            } else {
                $erro = 'Você não tem permissão para alterar este funcionário.';
            }
        }
    }
}

$funcionarios = $pdo->query('SELECT id, nome, usuario, nivel, ativo FROM funcionarios ORDER BY nivel DESC, nome')->fetchAll();

$totalFuncionarios = count($funcionarios);
$totalAtivos = 0;
foreach ($funcionarios as $funcionario) {
    if ((int) $funcionario['ativo'] === 1) {
        $totalAtivos++;
    }
}
$totalInativos = $totalFuncionarios - $totalAtivos;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funcionários - Churrascaria Pampulha</title>
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
                    <li><a href="trocar-senha.php">Trocar senha</a></li>
                    <li><a href="logout.php" class="btn-voltar-site"><i class="fa-solid fa-right-from-bracket"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="painel-reservas">
        <div class="container">
            <div class="panel-header">
                <div class="panel-header-icon"><i class="fa-solid fa-user-tie"></i></div>
                <div>
                    <h2>Funcionários</h2>
                    <p>Cadastre e gerencie o acesso da equipe</p>
                </div>
            </div>

            <?php if ($totalFuncionarios > 0): ?>
                <div class="stat-cards-row">
                    <div class="stat">
                        <i class="fa-solid fa-users"></i>
                        <h4><?= e((string) $totalFuncionarios) ?></h4>
                        <p>Funcionários cadastrados</p>
                    </div>
                    <div class="stat">
                        <i class="fa-solid fa-circle-check"></i>
                        <h4><?= e((string) $totalAtivos) ?></h4>
                        <p>Ativos</p>
                    </div>
                    <div class="stat">
                        <i class="fa-solid fa-ban"></i>
                        <h4><?= e((string) $totalInativos) ?></h4>
                        <p>Inativos</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="reserva-form-card">
                <div class="card-header-bar">
                    <i class="fa-solid fa-user-plus"></i>
                    <h3>Cadastrar Funcionário</h3>
                </div>
                <form method="post" action="funcionarios.php">
                    <input type="hidden" name="acao" value="criar">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="reserva-form-grid">
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-id-card"></i>Nome completo</span>
                            <input type="text" name="nome" placeholder="Nome completo" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-user"></i>Usuário de acesso</span>
                            <input type="text" name="usuario" placeholder="Usuário de acesso" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-lock"></i>Senha</span>
                            <input type="password" name="senha" placeholder="Senha (mín. 6 caracteres)" minlength="6" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-layer-group"></i>Nível de acesso</span>
                            <select name="nivel" required>
                                <option value="">Nível de acesso</option>
                                <option value="<?= NIVEL_ATENDENTE ?>">Atendente</option>
                                <?php if ($nivelLogado >= NIVEL_SUPERIOR): ?>
                                    <option value="<?= NIVEL_GERENTE ?>">Gerente</option>
                                    <option value="<?= NIVEL_SUPERIOR ?>">Nível Superior</option>
                                <?php endif; ?>
                            </select>
                        </label>
                    </div>
                    <?php if ($erro !== ''): ?>
                        <p class="login-erro"><?= e($erro) ?></p>
                    <?php endif; ?>
                    <?php if ($sucesso !== ''): ?>
                        <p class="login-sucesso"><?= e($sucesso) ?></p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i>Cadastrar Funcionário</button>
                </form>
            </div>

            <div class="reservas-lista-wrapper">
                <table class="reservas-tabela">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Usuário</th>
                            <th>Nível</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($funcionarios as $funcionario): ?>
                            <?php
                                $podeAlterarEsteAqui = (int) $funcionario['id'] !== $idLogado
                                    && ($nivelLogado >= NIVEL_SUPERIOR || (int) $funcionario['nivel'] < NIVEL_GERENTE);
                            ?>
                            <tr>
                                <td><?= e($funcionario['nome']) ?></td>
                                <td><?= e($funcionario['usuario']) ?></td>
                                <td><?= e(nomeNivel((int) $funcionario['nivel'])) ?></td>
                                <td>
                                    <?php if ((int) $funcionario['ativo'] === 1): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i>Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><i class="fa-solid fa-ban"></i>Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($podeAlterarEsteAqui): ?>
                                        <form method="post" action="funcionarios.php" onsubmit="return confirm('Alterar o status deste funcionário?');">
                                            <input type="hidden" name="acao" value="alternar_status">
                                            <input type="hidden" name="id" value="<?= e((string) $funcionario['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <button type="submit" class="btn-remover-reserva" title="Ativar/Desativar">
                                                <i class="fa-solid fa-power-off"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php renderizarControleSessao(); ?>
</body>
</html>
