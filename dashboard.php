<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();

$nivel = nivelFuncionario();
$diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$diasSemanaAbrev = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$mesesNome = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

function validarDataYmd(?string $valor): string
{
    $hoje = date('Y-m-d');
    if (!$valor) {
        return $hoje;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $valor);

    return ($dt && $dt->format('Y-m-d') === $valor) ? $valor : $hoje;
}

$dataSelecionada = validarDataYmd($_GET['data'] ?? null);
$dataSelecionadaDt = new DateTime($dataSelecionada);
$diaSemanaNum = (int) $dataSelecionadaDt->format('w');
$dataFormatada = $dataSelecionadaDt->format('d/m/Y');

$abasValidas = ['dia', 'semana', 'mes'];
$abaSelecionada = in_array($_GET['aba'] ?? '', $abasValidas, true) ? $_GET['aba'] : 'dia';

// ===== Visão do dia =====
$stmt = $pdo->prepare(
    'SELECT id, nome_cliente, telefone, hora_reserva, pessoas, status_reserva, confirmacao
     FROM reservas
     WHERE data_reserva = ?
     ORDER BY hora_reserva'
);
$stmt->execute([$dataSelecionada]);
$reservasDia = $stmt->fetchAll();

$totalReservasDia = 0;
$confirmadasDia = 0;
$pessoasEsperadasDia = 0;
foreach ($reservasDia as $reserva) {
    if ($reserva['status_reserva'] === 'Reservado') {
        $totalReservasDia++;
        $pessoasEsperadasDia += (int) $reserva['pessoas'];
        if ($reserva['confirmacao'] === 'Confirmado') {
            $confirmadasDia++;
        }
    }
}

$totalMesas = (int) $pdo->query('SELECT COALESCE(SUM(quantidade), 0) FROM mesas')->fetchColumn();
$mesasDisponiveisDia = max(0, $totalMesas - $totalReservasDia);

// ===== Visão semanal =====
$inicioSemana = (clone $dataSelecionadaDt)->modify("-{$diaSemanaNum} days");
$fimSemana = (clone $inicioSemana)->modify('+6 days');

$stmt = $pdo->prepare(
    "SELECT data_reserva, COUNT(*) AS total, COALESCE(SUM(pessoas), 0) AS pessoas
     FROM reservas
     WHERE data_reserva BETWEEN ? AND ? AND status_reserva = 'Reservado'
     GROUP BY data_reserva"
);
$stmt->execute([$inicioSemana->format('Y-m-d'), $fimSemana->format('Y-m-d')]);

$linhasSemana = [];
foreach ($stmt->fetchAll() as $linha) {
    $linhasSemana[$linha['data_reserva']] = $linha;
}

$diasDaSemana = [];
for ($i = 0; $i < 7; $i++) {
    $dia = (clone $inicioSemana)->modify("+{$i} days");
    $chave = $dia->format('Y-m-d');
    $diasDaSemana[] = [
        'data' => $chave,
        'label' => $diasSemanaAbrev[$i],
        'dataFormatada' => $dia->format('d/m'),
        'total' => isset($linhasSemana[$chave]) ? (int) $linhasSemana[$chave]['total'] : 0,
        'pessoas' => isset($linhasSemana[$chave]) ? (int) $linhasSemana[$chave]['pessoas'] : 0,
        'selecionado' => $chave === $dataSelecionada,
    ];
}
$totalReservasSemana = array_sum(array_column($diasDaSemana, 'total'));
$totalPessoasSemana = array_sum(array_column($diasDaSemana, 'pessoas'));

// ===== Visão mensal =====
$inicioMes = (clone $dataSelecionadaDt)->modify('first day of this month');
$fimMes = (clone $dataSelecionadaDt)->modify('last day of this month');

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total, COALESCE(SUM(pessoas), 0) AS pessoas,
            COALESCE(SUM(confirmacao = 'Confirmado'), 0) AS confirmadas
     FROM reservas
     WHERE data_reserva BETWEEN ? AND ? AND status_reserva = 'Reservado'"
);
$stmt->execute([$inicioMes->format('Y-m-d'), $fimMes->format('Y-m-d')]);
$resumoMes = $stmt->fetch();

$totalReservasMes = (int) $resumoMes['total'];
$totalPessoasMes = (int) $resumoMes['pessoas'];
$confirmadasMes = (int) $resumoMes['confirmadas'];
$pendentesMes = $totalReservasMes - $confirmadasMes;
$nomeMesAno = $mesesNome[(int) $dataSelecionadaDt->format('n')] . ' de ' . $dataSelecionadaDt->format('Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Churrascaria Pampulha</title>
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
                    <p>Olá, <?= e($_SESSION['funcionario_nome']) ?> (<?= e(nomeNivel($nivel)) ?>) — visão geral de <?= e($dataFormatada) ?> (<?= e($diasSemana[$diaSemanaNum]) ?>)</p>
                </div>
            </div>

            <div class="dashboard-date-bar">
                <form method="get" action="dashboard.php" class="dashboard-date-form">
                    <input type="hidden" name="aba" id="aba-input" value="<?= e($abaSelecionada) ?>">
                    <label for="data-input"><i class="fa-solid fa-calendar-days"></i> Visualizar reservas do dia</label>
                    <input type="date" id="data-input" name="data" value="<?= e($dataSelecionada) ?>" onchange="this.form.submit()">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i>Ver</button>
                    <?php if ($dataSelecionada !== date('Y-m-d')): ?>
                        <a href="dashboard.php" class="dashboard-date-reset"><i class="fa-solid fa-rotate-left"></i>Voltar para hoje</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="dashboard-tabs">
                <button type="button" class="dashboard-tab <?= $abaSelecionada === 'dia' ? 'is-active' : '' ?>" data-tab="dia"><i class="fa-solid fa-calendar-day"></i> Dia</button>
                <button type="button" class="dashboard-tab <?= $abaSelecionada === 'semana' ? 'is-active' : '' ?>" data-tab="semana"><i class="fa-solid fa-calendar-week"></i> Semana</button>
                <button type="button" class="dashboard-tab <?= $abaSelecionada === 'mes' ? 'is-active' : '' ?>" data-tab="mes"><i class="fa-solid fa-calendar"></i> Mês</button>
            </div>

            <div class="dashboard-tab-panel <?= $abaSelecionada === 'dia' ? 'is-active' : '' ?>" data-panel="dia">
                <div class="stat-cards-row">
                    <div class="stat">
                        <i class="fa-solid fa-calendar-day"></i>
                        <h4><?= e((string) $totalReservasDia) ?></h4>
                        <p>Reservas no dia</p>
                    </div>
                    <div class="stat">
                        <i class="fa-solid fa-circle-check"></i>
                        <h4><?= e((string) $confirmadasDia) ?></h4>
                        <p>Confirmadas no dia</p>
                    </div>
                    <div class="stat">
                        <i class="fa-solid fa-users"></i>
                        <h4><?= e((string) $pessoasEsperadasDia) ?></h4>
                        <p>Pessoas esperadas</p>
                    </div>
                    <div class="stat">
                        <i class="fa-solid fa-chair"></i>
                        <h4><?= e((string) $mesasDisponiveisDia) ?></h4>
                        <p>Mesas disponíveis</p>
                    </div>
                </div>
                <p class="dashboard-nota">*Mesas disponíveis é uma estimativa, considerando 1 mesa por reserva ativa do dia selecionado, de um total de <?= e((string) $totalMesas) ?> mesas cadastradas.</p>

                <div class="reserva-form-card">
                    <div class="card-header-bar">
                        <i class="fa-solid fa-list-check"></i>
                        <h3>Reservas do Dia <span class="dashboard-view-date"><?= e($dataFormatada) ?></span></h3>
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
                                <?php foreach ($reservasDia as $reserva): ?>
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
                        <?php if (empty($reservasDia)): ?>
                            <p class="reservas-vazio" style="display: block;">Nenhuma reserva neste dia.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="dashboard-tab-panel <?= $abaSelecionada === 'semana' ? 'is-active' : '' ?>" data-panel="semana">
                <div class="reserva-form-card">
                    <div class="card-header-bar">
                        <i class="fa-solid fa-calendar-week"></i>
                        <h3>Visão Semanal</h3>
                    </div>
                    <p class="dashboard-view-subtitle">
                        <?= e($inicioSemana->format('d/m')) ?> a <?= e($fimSemana->format('d/m')) ?>
                        — <?= e((string) $totalReservasSemana) ?> reservas · <?= e((string) $totalPessoasSemana) ?> pessoas
                    </p>
                    <ul class="dashboard-week-list">
                        <?php foreach ($diasDaSemana as $dia): ?>
                            <li class="<?= $dia['selecionado'] ? 'is-selected' : '' ?>">
                                <a href="dashboard.php?data=<?= e($dia['data']) ?>">
                                    <span class="dashboard-week-day"><?= e($dia['label']) ?> <small><?= e($dia['dataFormatada']) ?></small></span>
                                    <span class="dashboard-week-count"><?= e((string) $dia['total']) ?> res. · <?= e((string) $dia['pessoas']) ?> pessoas</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="dashboard-tab-panel <?= $abaSelecionada === 'mes' ? 'is-active' : '' ?>" data-panel="mes">
                <div class="reserva-form-card">
                    <div class="card-header-bar">
                        <i class="fa-solid fa-calendar"></i>
                        <h3>Visão Mensal <span class="dashboard-view-date"><?= e(ucfirst($nomeMesAno)) ?></span></h3>
                    </div>
                    <div class="stat-cards-row">
                        <div class="stat">
                            <i class="fa-solid fa-calendar-day"></i>
                            <h4><?= e((string) $totalReservasMes) ?></h4>
                            <p>Reservas no mês</p>
                        </div>
                        <div class="stat">
                            <i class="fa-solid fa-users"></i>
                            <h4><?= e((string) $totalPessoasMes) ?></h4>
                            <p>Pessoas esperadas</p>
                        </div>
                        <div class="stat">
                            <i class="fa-solid fa-circle-check"></i>
                            <h4><?= e((string) $confirmadasMes) ?></h4>
                            <p>Confirmadas</p>
                        </div>
                        <div class="stat">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <h4><?= e((string) $pendentesMes) ?></h4>
                            <p>Pendentes</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-actions">
                <a href="painel-reservas.php" class="btn btn-primary"><i class="fa-solid fa-calendar-check"></i>Ver Painel de Reservas</a>
                <a href="mesas.php" class="btn btn-outline"><i class="fa-solid fa-chair"></i>Gerenciar Mesas</a>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tabs = document.querySelectorAll('.dashboard-tab');
            var paineis = document.querySelectorAll('.dashboard-tab-panel');
            var abaInput = document.getElementById('aba-input');

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var alvo = tab.dataset.tab;

                    tabs.forEach(function (t) { t.classList.remove('is-active'); });
                    paineis.forEach(function (p) { p.classList.remove('is-active'); });

                    tab.classList.add('is-active');
                    var painelAlvo = document.querySelector('.dashboard-tab-panel[data-panel="' + alvo + '"]');
                    if (painelAlvo) {
                        painelAlvo.classList.add('is-active');
                    }
                    if (abaInput) {
                        abaInput.value = alvo;
                    }
                });
            });
        });
    </script>
</body>
</html>
