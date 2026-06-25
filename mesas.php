<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();
garantirColunaChurrascariaMesas($pdo);

$nivel = nivelFuncionario();
$mensagemErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar_mesa') {
        $capacidade = (int) ($_POST['capacidade'] ?? 0);
        $quantidadeMesas = (int) ($_POST['quantidade_mesas'] ?? 0);
        $churrascaria = trim($_POST['churrascaria'] ?? CHURRASCARIA_PADRAO);

        if (!in_array($capacidade, [2, 4, 6], true) || $quantidadeMesas < 1) {
            $mensagemErro = 'Escolha uma capacidade válida (2, 4 ou 6 lugares) e uma quantidade de mesas maior que zero.';
        } elseif (!churrascariaReservaValida($churrascaria)) {
            $mensagemErro = 'Escolha uma churrascaria válida.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO mesas (capacidade, quantidade, churrascaria) VALUES (?, ?, ?)');
            $stmt->execute([$capacidade, $quantidadeMesas, $churrascaria]);
            header('Location: mesas.php');
            exit;
        }
    }

    if ($acao === 'excluir_mesa' && $nivel >= NIVEL_GERENTE) {
        $stmt = $pdo->prepare('DELETE FROM mesas WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        header('Location: mesas.php');
        exit;
    }
}

$mesas = $pdo->query('SELECT id, capacidade, quantidade, churrascaria FROM mesas ORDER BY churrascaria, capacidade, id')->fetchAll();
$totalMesas = 0;
$totalLugares = 0;
foreach ($mesas as $mesa) {
    $totalMesas += (int) $mesa['quantidade'];
    $totalLugares += (int) $mesa['quantidade'] * (int) $mesa['capacidade'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesas do Espaço - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css?v=20260621-7">
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
                <ul class="funcionario-nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="painel-reservas.php">Painel de Reservas</a></li>
                    <li><a href="tipos-reserva.php">Tipos de Reserva</a></li>
                    <?php if ($nivel >= NIVEL_GERENTE): ?>
                        <li><a href="funcionarios.php">Funcionários</a></li>
                    <?php endif; ?>
                    <li><a href="trocar-senha.php">Trocar senha</a></li>
                    <li><a href="logout.php" class="btn-voltar-site"><i class="fa-solid fa-right-from-bracket"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="painel-reservas">
        <div class="container">
            <div class="panel-header">
                <div class="panel-header-icon"><i class="fa-solid fa-chair"></i></div>
                <div>
                    <h2>Mesas do Espaço</h2>
                    <p>Cadastre quantas mesas existem em cada capacidade</p>
                </div>
            </div>

            <?php if (!empty($mesas)): ?>
                <div class="stat-cards-row">
                    <div class="stat">
                        <i class="fa-solid fa-table-cells"></i>
                        <h4><?= e((string) $totalMesas) ?></h4>
                        <p>Mesas cadastradas</p>
                    </div>
                    <div class="stat">
                        <i class="fa-solid fa-users"></i>
                        <h4><?= e((string) $totalLugares) ?></h4>
                        <p>Lugares no espaço</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="reserva-form-card">
                <div class="card-header-bar">
                    <i class="fa-solid fa-circle-plus"></i>
                    <h3>Cadastrar Mesas</h3>
                </div>
                <form method="post" action="mesas.php">
                    <input type="hidden" name="acao" value="criar_mesa">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="reserva-form-grid">
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-store"></i>Churrascaria</span>
                            <select name="churrascaria" required>
                                <?php foreach (CHURRASCARIAS_RESERVA as $churrascariaOpcao): ?>
                                    <option value="<?= e($churrascariaOpcao) ?>" <?= $churrascariaOpcao === CHURRASCARIA_PADRAO ? 'selected' : '' ?>><?= e($churrascariaOpcao) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-chair"></i>Lugares por mesa</span>
                            <select name="capacidade" required>
                                <option value="">Lugares por mesa</option>
                                <option value="2">2 lugares</option>
                                <option value="4">4 lugares</option>
                                <option value="6">6 lugares</option>
                            </select>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-table-cells"></i>Nº de mesas</span>
                            <input type="number" name="quantidade_mesas" placeholder="Nº de mesas" min="1" required>
                        </label>
                    </div>
                    <?php if ($mensagemErro !== ''): ?>
                        <p class="login-erro"><?= e($mensagemErro) ?></p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i>Adicionar Mesas</button>
                </form>
            </div>

            <div class="reservas-lista-wrapper">
                <table class="reservas-tabela">
                    <thead>
                        <tr>
                            <th>Churrascaria</th>
                            <th>Lugares por mesa</th>
                            <th>Quantidade de mesas</th>
                            <th>Lugares totais</th>
                            <?php if ($nivel >= NIVEL_GERENTE): ?>
                                <th></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mesas as $mesa): ?>
                            <tr>
                                <td><span class="badge badge-info"><i class="fa-solid fa-location-dot"></i><?= e($mesa['churrascaria'] ?? CHURRASCARIA_PADRAO) ?></span></td>
                                <td><?= e((string) $mesa['capacidade']) ?></td>
                                <td><?= e((string) $mesa['quantidade']) ?></td>
                                <td><?= e((string) ($mesa['capacidade'] * $mesa['quantidade'])) ?></td>
                                <?php if ($nivel >= NIVEL_GERENTE): ?>
                                    <td>
                                        <form method="post" action="mesas.php" onsubmit="return confirm('Remover este lote de mesas?');">
                                            <input type="hidden" name="acao" value="excluir_mesa">
                                            <input type="hidden" name="id" value="<?= e((string) $mesa['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <button type="submit" class="btn-remover-reserva" title="Remover lote de mesas">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($mesas)): ?>
                    <p class="reservas-vazio" style="display: block;">Nenhuma mesa cadastrada ainda.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php renderizarControleSessao(); ?>
</body>
</html>
