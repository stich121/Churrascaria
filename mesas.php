<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();

$nivel = nivelFuncionario();
$mensagemErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar_mesa') {
        $capacidade = (int) ($_POST['capacidade'] ?? 0);
        $quantidadeMesas = (int) ($_POST['quantidade_mesas'] ?? 0);

        if (!in_array($capacidade, [2, 4, 6], true) || $quantidadeMesas < 1) {
            $mensagemErro = 'Escolha uma capacidade válida (2, 4 ou 6 lugares) e uma quantidade de mesas maior que zero.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO mesas (capacidade, quantidade) VALUES (?, ?)');
            $stmt->execute([$capacidade, $quantidadeMesas]);
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

$mesas = $pdo->query('SELECT id, capacidade, quantidade FROM mesas ORDER BY capacidade, id')->fetchAll();
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
    <link rel="stylesheet" href="style.css?v=20260621-3">
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
                    <li><a href="painel-reservas.php">Painel de Reservas</a></li>
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
            <h2>Mesas do Espaço</h2>
            <p class="section-subtitle">Cadastre quantas mesas existem em cada capacidade</p>

            <div class="reserva-form-card">
                <form method="post" action="mesas.php">
                    <input type="hidden" name="acao" value="criar_mesa">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="reserva-form-grid">
                        <select name="capacidade" required>
                            <option value="">Lugares por mesa</option>
                            <option value="2">2 lugares</option>
                            <option value="4">4 lugares</option>
                            <option value="6">6 lugares</option>
                        </select>
                        <input type="number" name="quantidade_mesas" placeholder="Nº de mesas" min="1" required>
                    </div>
                    <?php if ($mensagemErro !== ''): ?>
                        <p class="login-erro"><?= e($mensagemErro) ?></p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">Adicionar Mesas</button>
                </form>
            </div>

            <?php if (!empty($mesas)): ?>
                <p class="mesas-resumo">Total: <strong><?= e((string) $totalMesas) ?></strong> mesas — <strong><?= e((string) $totalLugares) ?></strong> lugares no espaço</p>
            <?php endif; ?>

            <div class="reservas-lista-wrapper">
                <table class="reservas-tabela">
                    <thead>
                        <tr>
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
</body>
</html>
