<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();

$nivel = nivelFuncionario();
$hoje = date('Y-m-d');
$diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

$stmt = $pdo->prepare(
    'SELECT id, nome_cliente, telefone, hora_reserva, pessoas, status_reserva, confirmacao
     FROM reservas
     WHERE data_reserva = ?
     ORDER BY hora_reserva'
);
$stmt->execute([$hoje]);
$reservasHoje = $stmt->fetchAll();

$totalReservasHoje = 0;
$confirmadasHoje = 0;
$pessoasEsperadasHoje = 0;
foreach ($reservasHoje as $reserva) {
    if ($reserva['status_reserva'] === 'Reservado') {
        $totalReservasHoje++;
        $pessoasEsperadasHoje += (int) $reserva['pessoas'];
        if ($reserva['confirmacao'] === 'Confirmado') {
            $confirmadasHoje++;
        }
    }
}

$totalMesas = (int) $pdo->query('SELECT COALESCE(SUM(quantidade), 0) FROM mesas')->fetchColumn();
$mesasDisponiveis = max(0, $totalMesas - $totalReservasHoje);

$hojeFormatado = date('d/m/Y') . ' - ' . $diasSemana[(int) date('w')];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css?v=20260621-5">
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
                    <li><a href="mesas.php">Mesas</a></li>
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
                <div class="panel-header-icon"><i class="fa-solid fa-gauge-high"></i></div>
                <div>
                    <h2>Dashboard</h2>
                    <p>Olá, <?= e($_SESSION['funcionario_nome']) ?> (<?= e(nomeNivel($nivel)) ?>) — visão geral de hoje, <?= e($hojeFormatado) ?></p>
                </div>
            </div>

            <div class="stat-cards-row">
                <div class="stat">
                    <i class="fa-solid fa-calendar-day"></i>
                    <h4><?= e((string) $totalReservasHoje) ?></h4>
                    <p>Reservas hoje</p>
                </div>
                <div class="stat">
                    <i class="fa-solid fa-circle-check"></i>
                    <h4><?= e((string) $confirmadasHoje) ?></h4>
                    <p>Confirmadas hoje</p>
                </div>
                <div class="stat">
                    <i class="fa-solid fa-users"></i>
                    <h4><?= e((string) $pessoasEsperadasHoje) ?></h4>
                    <p>Pessoas esperadas hoje</p>
                </div>
                <div class="stat">
                    <i class="fa-solid fa-chair"></i>
                    <h4><?= e((string) $mesasDisponiveis) ?></h4>
                    <p>Mesas disponíveis hoje</p>
                </div>
            </div>
            <p class="dashboard-nota">*Mesas disponíveis é uma estimativa, considerando 1 mesa por reserva ativa do dia, de um total de <?= e((string) $totalMesas) ?> mesas cadastradas.</p>

            <div class="reserva-form-card">
                <div class="card-header-bar">
                    <i class="fa-solid fa-list-check"></i>
                    <h3>Reservas de Hoje</h3>
                </div>
                <div class="reservas-lista-wrapper">
                    <table class="reservas-tabela">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Telefone</th>
                                <th>Pessoas</th>
                                <th>Status</th>
                                <th>Confirmação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservasHoje as $reserva): ?>
                                <tr>
                                    <td><?= e(date('H:i', strtotime($reserva['hora_reserva']))) ?></td>
                                    <td><?= e($reserva['nome_cliente']) ?></td>
                                    <td><?= e($reserva['telefone']) ?></td>
                                    <td><?= e((string) $reserva['pessoas']) ?></td>
                                    <td>
                                        <?php if ($reserva['status_reserva'] === 'Reservado'): ?>
                                            <span class="badge badge-info"><i class="fa-solid fa-calendar-check"></i>Reservado</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger"><i class="fa-solid fa-ban"></i>Cancelado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($reserva['confirmacao'] === 'Confirmado'): ?>
                                            <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i>Confirmado</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i>Pendente</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($reservasHoje)): ?>
                        <p class="reservas-vazio" style="display: block;">Nenhuma reserva para hoje.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-actions">
                <a href="painel-reservas.php" class="btn btn-primary"><i class="fa-solid fa-calendar-check"></i>Ver Painel de Reservas</a>
                <a href="mesas.php" class="btn btn-outline"><i class="fa-solid fa-chair"></i>Gerenciar Mesas</a>
            </div>
        </div>
    </section>
</body>
</html>
