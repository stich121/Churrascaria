<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();
garantirColunaChurrascaria($pdo);
garantirColunaChurrascariaMesas($pdo);

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

function dashboardUrl(array $params = []): string
{
    $query = http_build_query($params);

    return $query !== '' ? 'dashboard.php?' . $query : 'dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar_comparecimento') {
    exigirNivel(NIVEL_GERENTE);
    verificarCsrf();

    $idReserva = (int) ($_POST['id'] ?? 0);
    $statusComparecimento = $_POST['status_comparecimento'] ?? '';

    if ($statusComparecimento === 'nao') {
        $pessoasCompareceram = 0;
    } elseif ($statusComparecimento === 'sim') {
        $pessoasCompareceram = max(1, (int) ($_POST['pessoas_compareceram'] ?? 0));
    } else {
        $pessoasCompareceram = null;
    }

    if ($idReserva > 0) {
        $pdo->prepare('UPDATE reservas SET pessoas_compareceram = ? WHERE id = ?')
            ->execute([$pessoasCompareceram, $idReserva]);
    }

    $abaRetorno = in_array($_POST['aba'] ?? '', ['dia', 'semana', 'mes'], true) ? $_POST['aba'] : 'dia';
    header('Location: ' . dashboardUrl([
        'data' => validarDataYmd($_POST['data'] ?? null),
        'aba' => $abaRetorno,
        'churrascaria' => $_POST['churrascaria'] ?? 'pampulha',
        'ordenacao_dia' => $_POST['ordenacao_dia'] ?? 'hora_asc',
    ]));
    exit;
}

$dataSelecionada = validarDataYmd($_GET['data'] ?? null);
$dataSelecionadaDt = new DateTime($dataSelecionada);
$diaSemanaNum = (int) $dataSelecionadaDt->format('w');
$dataFormatada = $dataSelecionadaDt->format('d/m/Y');

$abasValidas = ['dia', 'semana', 'mes'];
$abaSelecionada = in_array($_GET['aba'] ?? '', $abasValidas, true) ? $_GET['aba'] : 'dia';

$opcoesDashboardChurrascaria = [
    'pampulha' => CHURRASCARIA_PADRAO,
    'casarao-itau' => 'Casarão Itau',
];

if ($nivel >= NIVEL_SUPERIOR) {
    $opcoesDashboardChurrascaria['todas'] = 'Todos';
}

$churrascariaDashboard = $_GET['churrascaria'] ?? 'pampulha';
if (!array_key_exists($churrascariaDashboard, $opcoesDashboardChurrascaria)) {
    $churrascariaDashboard = 'pampulha';
}

$ordenacoesDashboardDia = [
    'hora_asc' => [
        'label' => 'Horário mais próximo',
        'sql' => 'hora_reserva ASC, nome_cliente ASC',
    ],
    'hora_desc' => [
        'label' => 'Horário mais distante',
        'sql' => 'hora_reserva DESC, nome_cliente ASC',
    ],
    'cliente_asc' => [
        'label' => 'Cliente A-Z',
        'sql' => 'nome_cliente ASC, hora_reserva ASC',
    ],
    'cliente_desc' => [
        'label' => 'Cliente Z-A',
        'sql' => 'nome_cliente DESC, hora_reserva ASC',
    ],
];
$ordenacaoDashboardDia = $_GET['ordenacao_dia'] ?? 'hora_asc';
if (!array_key_exists($ordenacaoDashboardDia, $ordenacoesDashboardDia)) {
    $ordenacaoDashboardDia = 'hora_asc';
}
$ordenacaoDashboardDiaSql = $ordenacoesDashboardDia[$ordenacaoDashboardDia]['sql'];

$rotuloDashboardChurrascaria = $opcoesDashboardChurrascaria[$churrascariaDashboard];
$filtroChurrascariaSql = '';
$filtroChurrascariaParams = [];

if ($churrascariaDashboard !== 'todas') {
    $filtroChurrascariaSql = ' AND churrascaria = ?';
    $filtroChurrascariaParams[] = $rotuloDashboardChurrascaria;
}

// ===== Visão do dia =====
$stmt = $pdo->prepare(
    "SELECT id, churrascaria, nome_cliente, telefone, hora_reserva, pessoas, pessoas_compareceram, status_reserva, confirmacao
     FROM reservas
     WHERE data_reserva = ?{$filtroChurrascariaSql}
     ORDER BY {$ordenacaoDashboardDiaSql}"
);
$stmt->execute(array_merge([$dataSelecionada], $filtroChurrascariaParams));
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

if ($churrascariaDashboard === 'todas') {
    $totalMesas = (int) $pdo->query('SELECT COALESCE(SUM(quantidade), 0) FROM mesas')->fetchColumn();
} else {
    $stmtMesas = $pdo->prepare('SELECT COALESCE(SUM(quantidade), 0) FROM mesas WHERE churrascaria = ?');
    $stmtMesas->execute([$rotuloDashboardChurrascaria]);
    $totalMesas = (int) $stmtMesas->fetchColumn();
}
$mesasDisponiveisDia = max(0, $totalMesas - $totalReservasDia);

// ===== Visão semanal =====
$inicioSemana = (clone $dataSelecionadaDt)->modify("-{$diaSemanaNum} days");
$fimSemana = (clone $inicioSemana)->modify('+6 days');

$stmt = $pdo->prepare(
    "SELECT data_reserva, COUNT(*) AS total, COALESCE(SUM(pessoas), 0) AS pessoas
     FROM reservas
     WHERE data_reserva BETWEEN ? AND ? AND status_reserva = 'Reservado'{$filtroChurrascariaSql}
     GROUP BY data_reserva"
);
$stmt->execute(array_merge([$inicioSemana->format('Y-m-d'), $fimSemana->format('Y-m-d')], $filtroChurrascariaParams));

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
     WHERE data_reserva BETWEEN ? AND ? AND status_reserva = 'Reservado'{$filtroChurrascariaSql}"
);
$stmt->execute(array_merge([$inicioMes->format('Y-m-d'), $fimMes->format('Y-m-d')], $filtroChurrascariaParams));
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
                        <img src="logo-pampulha.png" alt="Churrascaria Pampulha" class="logo-img">
                    </a>
                </div>
                <ul class="funcionario-nav-links">
                    <li><a href="painel-reservas.php">Painel de Reservas</a></li>
                    <li><a href="mesas.php">Mesas</a></li>
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
                <div class="panel-header-icon"><i class="fa-solid fa-gauge-high"></i></div>
                <div>
                    <h2>Dashboard</h2>
                    <p>Olá, <?= e($_SESSION['funcionario_nome']) ?> (<?= e(nomeNivel($nivel)) ?>) — visão de <?= e($rotuloDashboardChurrascaria) ?> em <?= e($dataFormatada) ?> (<?= e($diasSemana[$diaSemanaNum]) ?>)</p>
                </div>
            </div>

            <div class="dashboard-date-bar">
                <form method="get" action="dashboard.php" class="dashboard-date-form">
                    <input type="hidden" name="aba" id="aba-input" class="dashboard-aba-input" value="<?= e($abaSelecionada) ?>">
                    <input type="hidden" name="churrascaria" value="<?= e($churrascariaDashboard) ?>">
                    <input type="hidden" name="ordenacao_dia" value="<?= e($ordenacaoDashboardDia) ?>">
                    <label for="data-input"><i class="fa-solid fa-calendar-days"></i> Visualizar reservas do dia</label>
                    <input type="date" id="data-input" name="data" value="<?= e($dataSelecionada) ?>" onchange="this.form.submit()">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i>Ver</button>
                    <?php if ($dataSelecionada !== date('Y-m-d')): ?>
                        <a href="<?= e(dashboardUrl(['aba' => $abaSelecionada, 'churrascaria' => $churrascariaDashboard, 'ordenacao_dia' => $ordenacaoDashboardDia])) ?>" class="dashboard-date-reset"><i class="fa-solid fa-rotate-left"></i>Voltar para hoje</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="dashboard-tabs dashboard-unit-tabs" aria-label="Selecionar churrascaria">
                <?php foreach ($opcoesDashboardChurrascaria as $chaveOpcao => $rotuloOpcao): ?>
                    <a
                        href="<?= e(dashboardUrl(['data' => $dataSelecionada, 'aba' => $abaSelecionada, 'churrascaria' => $chaveOpcao, 'ordenacao_dia' => $ordenacaoDashboardDia])) ?>"
                        class="dashboard-tab <?= $churrascariaDashboard === $chaveOpcao ? 'is-active' : '' ?>"
                        <?= $churrascariaDashboard === $chaveOpcao ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= $chaveOpcao === 'todas' ? 'fa-layer-group' : 'fa-location-dot' ?>"></i>
                        <?= e($rotuloOpcao) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="dashboard-tabs">
                <button type="button" class="dashboard-tab dashboard-period-tab <?= $abaSelecionada === 'dia' ? 'is-active' : '' ?>" data-tab="dia"><i class="fa-solid fa-calendar-day"></i> Dia</button>
                <button type="button" class="dashboard-tab dashboard-period-tab <?= $abaSelecionada === 'semana' ? 'is-active' : '' ?>" data-tab="semana"><i class="fa-solid fa-calendar-week"></i> Semana</button>
                <button type="button" class="dashboard-tab dashboard-period-tab <?= $abaSelecionada === 'mes' ? 'is-active' : '' ?>" data-tab="mes"><i class="fa-solid fa-calendar"></i> Mês</button>
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
                    <div class="card-header-bar dashboard-reservas-dia-header">
                        <i class="fa-solid fa-list-check"></i>
                        <h3>Reservas do Dia <span class="dashboard-view-date"><?= e($dataFormatada) ?></span></h3>
                        <div class="dashboard-reservas-controles">
                            <form method="get" action="dashboard.php" class="dashboard-reservas-ordenacao-form">
                                <input type="hidden" name="data" value="<?= e($dataSelecionada) ?>">
                                <input type="hidden" name="aba" value="<?= e($abaSelecionada) ?>">
                                <input type="hidden" name="churrascaria" value="<?= e($churrascariaDashboard) ?>">
                                <label for="ordenacao_dashboard_dia"><i class="fa-solid fa-arrow-down-a-z"></i>Ordenar</label>
                                <select name="ordenacao_dia" id="ordenacao_dashboard_dia" onchange="this.form.submit()">
                                    <?php foreach ($ordenacoesDashboardDia as $valorOrdenacao => $dadosOrdenacao): ?>
                                        <option value="<?= e($valorOrdenacao) ?>" <?= $valorOrdenacao === $ordenacaoDashboardDia ? 'selected' : '' ?>><?= e($dadosOrdenacao['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <label class="dashboard-reservas-busca">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="search" class="dashboard-reservas-busca-input" placeholder="Pesquisar nome ou telefone" autocomplete="off">
                            </label>
                        </div>
                    </div>
                    <div class="reservas-lista-wrapper">
                        <table class="reservas-tabela">
                            <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Cliente</th>
                                    <th>Churrascaria</th>
                                    <th>Telefone</th>
                                    <th>Pessoas</th>
                                    <th>Status</th>
                                    <th>Confirmação</th>
                                    <th>Compareceu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservasDia as $reserva): ?>
                                    <?php
                                        $statusComparecimentoAtual = $reserva['pessoas_compareceram'] === null
                                            ? ''
                                            : ((int) $reserva['pessoas_compareceram'] === 0 ? 'nao' : 'sim');
                                    ?>
                                    <tr>
                                        <td><?= e(date('H:i', strtotime($reserva['hora_reserva']))) ?></td>
                                        <td><?= e($reserva['nome_cliente']) ?></td>
                                        <td><span class="badge badge-info"><i class="fa-solid fa-location-dot"></i><?= e($reserva['churrascaria'] ?? CHURRASCARIA_PADRAO) ?></span></td>
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
                                        <td>
                                            <?php if ($nivel >= NIVEL_GERENTE): ?>
                                                <form method="post" action="dashboard.php" class="dashboard-comparecimento-form">
                                                    <input type="hidden" name="acao" value="atualizar_comparecimento">
                                                    <input type="hidden" name="id" value="<?= e((string) $reserva['id']) ?>">
                                                    <input type="hidden" name="data" value="<?= e($dataSelecionada) ?>">
                                                    <input type="hidden" name="aba" value="<?= e($abaSelecionada) ?>">
                                                    <input type="hidden" name="churrascaria" value="<?= e($churrascariaDashboard) ?>">
                                                    <input type="hidden" name="ordenacao_dia" value="<?= e($ordenacaoDashboardDia) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <div class="dashboard-comparecimento-opcoes" role="radiogroup" aria-label="Comparecimento">
                                                        <label class="dashboard-comparecimento-opcao">
                                                            <input type="radio" name="status_comparecimento" value="" <?= $statusComparecimentoAtual === '' ? 'checked' : '' ?>>
                                                            <span><i class="fa-solid fa-hourglass-half"></i>Pendente</span>
                                                        </label>
                                                        <label class="dashboard-comparecimento-opcao">
                                                            <input type="radio" name="status_comparecimento" value="sim" <?= $statusComparecimentoAtual === 'sim' ? 'checked' : '' ?>>
                                                            <span><i class="fa-solid fa-user-check"></i>Veio</span>
                                                        </label>
                                                        <label class="dashboard-comparecimento-opcao">
                                                            <input type="radio" name="status_comparecimento" value="nao" <?= $statusComparecimentoAtual === 'nao' ? 'checked' : '' ?>>
                                                            <span><i class="fa-solid fa-user-xmark"></i>Não veio</span>
                                                        </label>
                                                    </div>
                                                    <label class="dashboard-comparecimento-qtd-wrap" style="<?= $statusComparecimentoAtual === 'sim' ? '' : 'display:none;' ?>">
                                                        <span>Qtd</span>
                                                        <input
                                                            type="number"
                                                            name="pessoas_compareceram"
                                                            min="1"
                                                            placeholder="0"
                                                            class="dashboard-comparecimento-qtd"
                                                            value="<?= $statusComparecimentoAtual === 'sim' ? e((string) $reserva['pessoas_compareceram']) : '' ?>">
                                                    </label>
                                                    <button type="submit" class="dashboard-comparecimento-salvar" title="Salvar comparecimento"><i class="fa-solid fa-check"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <?php if ($statusComparecimentoAtual === 'sim'): ?>
                                                    <span class="badge badge-success"><i class="fa-solid fa-user-check"></i><?= e((string) $reserva['pessoas_compareceram']) ?> vieram</span>
                                                <?php elseif ($statusComparecimentoAtual === 'nao'): ?>
                                                    <span class="badge badge-danger"><i class="fa-solid fa-user-xmark"></i>Não veio</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i>Pendente</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (empty($reservasDia)): ?>
                            <p class="reservas-vazio" style="display: block;">Nenhuma reserva neste dia.</p>
                        <?php endif; ?>
                        <p class="reservas-vazio dashboard-reservas-busca-vazio">Nenhuma reserva encontrada para essa pesquisa.</p>
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
                                <a href="<?= e(dashboardUrl(['data' => $dia['data'], 'aba' => 'dia', 'churrascaria' => $churrascariaDashboard, 'ordenacao_dia' => $ordenacaoDashboardDia])) ?>">
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
                <a href="imprimir-reservas.php?data=<?= e($dataSelecionada) ?>&churrascaria=<?= e($churrascariaDashboard) ?>" target="_blank" class="btn btn-outline"><i class="fa-solid fa-print"></i>Imprimir reservas do dia</a>
            </div>
        </div>
    </section>

    <?php renderizarControleSessao(); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            inicializarDashboardTabs();
            inicializarAtualizacaoDashboard();
            inicializarComparecimentoDashboard();
            inicializarBuscaReservasDia();
        });

        function inicializarBuscaReservasDia() {
            document.querySelectorAll('.dashboard-reservas-busca-input').forEach(function (input) {
                if (input.dataset.inicializado === 'true') {
                    return;
                }

                input.dataset.inicializado = 'true';
                var card = input.closest('.reserva-form-card');
                if (!card) {
                    return;
                }

                var linhas = Array.prototype.slice.call(card.querySelectorAll('.reservas-tabela tbody tr'));
                var mensagemVazia = card.querySelector('.dashboard-reservas-busca-vazio');

                function normalizar(valor) {
                    return (valor || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
                }

                function filtrarReservas() {
                    var busca = normalizar(input.value);
                    var buscaDigitos = busca.replace(/\D/g, '');
                    var visiveis = 0;

                    linhas.forEach(function (linha) {
                        var celulas = linha.querySelectorAll('td');
                        var cliente = normalizar(celulas[1] ? celulas[1].textContent : '');
                        var telefone = normalizar(celulas[3] ? celulas[3].textContent : '');
                        var telefoneDigitos = telefone.replace(/\D/g, '');
                        var corresponde = busca === '' || cliente.indexOf(busca) !== -1 || telefone.indexOf(busca) !== -1 || (buscaDigitos !== '' && telefoneDigitos.indexOf(buscaDigitos) !== -1);

                        linha.style.display = corresponde ? '' : 'none';
                        if (corresponde) {
                            visiveis++;
                        }
                    });

                    if (mensagemVazia) {
                        mensagemVazia.style.display = busca !== '' && linhas.length > 0 && visiveis === 0 ? 'block' : 'none';
                    }
                }

                input.addEventListener('input', filtrarReservas);
                filtrarReservas();
            });
        }

        function inicializarComparecimentoDashboard() {
            document.querySelectorAll('.dashboard-comparecimento-form').forEach(function (form) {
                form.querySelectorAll('input[name="status_comparecimento"]').forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        var qtdWrap = form.querySelector('.dashboard-comparecimento-qtd-wrap');
                        var qtdInput = form.querySelector('.dashboard-comparecimento-qtd');
                        if (radio.value === 'sim') {
                            qtdWrap.style.display = '';
                            qtdInput.focus();
                        } else {
                            qtdWrap.style.display = 'none';
                            qtdInput.value = '';
                        }
                    });
                });
            });
        }

        function inicializarDashboardTabs() {
            var tabs = document.querySelectorAll('.dashboard-period-tab');
            var paineis = document.querySelectorAll('.dashboard-tab-panel');
            var abaInputs = document.querySelectorAll('.dashboard-aba-input');
            var unitLinks = document.querySelectorAll('.dashboard-unit-tabs a');

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
                    abaInputs.forEach(function (input) {
                        input.value = alvo;
                    });
                    unitLinks.forEach(function (link) {
                        var url = new URL(link.href, window.location.href);
                        url.searchParams.set('aba', alvo);
                        link.href = url.toString();
                    });
                });
            });
        }

        function inicializarAtualizacaoDashboard() {
            var intervaloMs = 30000;
            var atualizando = false;

            function formularioEmUso() {
                var ativo = document.activeElement;

                return ativo && ativo.closest && ativo.closest('.dashboard-date-form');
            }

            function atualizarDashboard() {
                if (atualizando || formularioEmUso()) {
                    return;
                }

                atualizando = true;

                var url = new URL(window.location.href);
                var abaAtiva = document.querySelector('.dashboard-period-tab.is-active');
                if (abaAtiva && abaAtiva.dataset.tab) {
                    url.searchParams.set('aba', abaAtiva.dataset.tab);
                }

                window.fetch(url.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'X-Dashboard-Auto-Refresh': '1'
                    }
                }).then(function (response) {
                    if (response.redirected && response.url.indexOf('area-reservas.php') !== -1) {
                        window.location.href = response.url;
                        return null;
                    }

                    if (!response.ok) {
                        return null;
                    }

                    return response.text();
                }).then(function (html) {
                    if (!html) {
                        return;
                    }

                    var buscaReservas = document.querySelector('.dashboard-reservas-busca-input');
                    var termoBuscaReservas = buscaReservas ? buscaReservas.value : '';
                    var documento = new DOMParser().parseFromString(html, 'text/html');
                    var novoPainel = documento.querySelector('.painel-reservas');
                    var painelAtual = document.querySelector('.painel-reservas');

                    if (novoPainel && painelAtual) {
                        painelAtual.replaceWith(novoPainel);
                        var novaBuscaReservas = document.querySelector('.dashboard-reservas-busca-input');
                        if (novaBuscaReservas) {
                            novaBuscaReservas.value = termoBuscaReservas;
                        }
                        inicializarDashboardTabs();
                        inicializarComparecimentoDashboard();
                        inicializarBuscaReservasDia();
                    }
                }).catch(function () {}).finally(function () {
                    atualizando = false;
                });
            }

            window.setInterval(atualizarDashboard, intervaloMs);
        }
    </script>
</body>
</html>
