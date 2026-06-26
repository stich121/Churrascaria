<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();
garantirColunaChurrascaria($pdo);
garantirTabelaTiposReserva($pdo);
garantirColunaTipoReserva($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['acao'] ?? '') === 'buscar_cliente') {
    header('Content-Type: application/json; charset=utf-8');
    $nomeBusca = trim($_GET['nome'] ?? '');
    $telefoneBusca = trim($_GET['telefone'] ?? '');
    $dataBusca = $_GET['data'] ?? '';
    $resposta = ['duplicado' => false, 'telefone' => null, 'churrascaria' => null];

    if ($nomeBusca !== '' || $telefoneBusca !== '') {
        if ($dataBusca !== '') {
            $condicoes = [];
            $parametrosDup = [];

            if ($nomeBusca !== '') {
                $condicoes[] = 'nome_cliente = ?';
                $parametrosDup[] = $nomeBusca;
            }

            if ($telefoneBusca !== '') {
                $condicoes[] = 'telefone = ?';
                $parametrosDup[] = $telefoneBusca;
            }

            $stmtDup = $pdo->prepare(
                "SELECT id FROM reservas WHERE (" . implode(' OR ', $condicoes) . ") AND data_reserva = ? AND status_reserva = 'Reservado' LIMIT 1"
            );
            $stmtDup->execute(array_merge($parametrosDup, [$dataBusca]));
            if ($stmtDup->fetch()) {
                $resposta['duplicado'] = true;
            }
        }

        if (!$resposta['duplicado'] && $nomeBusca !== '') {
            $stmtAnterior = $pdo->prepare(
                'SELECT telefone, churrascaria FROM reservas WHERE nome_cliente = ? ORDER BY criado_em DESC LIMIT 1'
            );
            $stmtAnterior->execute([$nomeBusca]);
            $anterior = $stmtAnterior->fetch();
            if ($anterior) {
                $resposta['telefone'] = $anterior['telefone'];
                $resposta['churrascaria'] = $anterior['churrascaria'];
            }
        }
    }

    echo json_encode($resposta);
    exit;
}

$nivel = nivelFuncionario();
$mensagemErro = '';

function urlPainelReservas(int $pagina, string $ordenacao = 'data_asc'): string
{
    $params = [];

    if ($pagina > 1) {
        $params['pagina'] = $pagina;
    }

    if ($ordenacao !== 'data_asc') {
        $params['ordenacao'] = $ordenacao;
    }

    $query = http_build_query($params);

    return 'painel-reservas.php' . ($query !== '' ? '?' . $query : '');
}

function parseValorBr(string $valor): float
{
    $limpo = str_replace(',', '.', preg_replace('/[^0-9,]/', '', $valor));

    return (float) $limpo;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $acao = $_POST['acao'] ?? '';
    $paginaRetorno = max(1, (int) ($_POST['pagina'] ?? 1));
    $ordenacoesValidasPost = ['data_asc', 'data_desc', 'cliente_asc', 'cliente_desc'];
    $ordenacaoRetorno = in_array($_POST['ordenacao'] ?? 'data_asc', $ordenacoesValidasPost, true) ? $_POST['ordenacao'] : 'data_asc';

    if ($acao === 'criar') {
        $churrascaria = trim($_POST['churrascaria'] ?? CHURRASCARIA_PADRAO);
        $tipoReserva = trim($_POST['tipo_reserva'] ?? '');
        $pessoas = (int) ($_POST['pessoas'] ?? 0);
        $statusReserva = $_POST['status_reserva'] ?? 'Reservado';
        $confirmacao = $_POST['confirmacao'] ?? 'Pendente';
        $observacao = trim($_POST['observacao'] ?? '');

        if (!churrascariaReservaValida($churrascaria)) {
            $mensagemErro = 'Escolha uma churrascaria válida.';
        } elseif ($pessoas < 1) {
            $mensagemErro = 'Informe um número de pessoas válido.';
        } elseif (!in_array($statusReserva, ['Reservado', 'Cancelado'], true)) {
            $mensagemErro = 'Escolha um status de reserva válido.';
        } elseif (!in_array($confirmacao, ['Pendente', 'Confirmado'], true)) {
            $mensagemErro = 'Escolha uma confirmação válida.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO reservas (nome_cliente, telefone, churrascaria, tipo_reserva, data_pedido, data_reserva, hora_reserva, pessoas, valor, status_reserva, confirmacao, observacao, funcionario_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim($_POST['nome'] ?? ''),
                trim($_POST['telefone'] ?? ''),
                $churrascaria,
                $tipoReserva !== '' ? $tipoReserva : null,
                $_POST['data_pedido'] ?? null,
                $_POST['data'] ?? '',
                $_POST['hora'] ?? '',
                $pessoas,
                parseValorBr($_POST['valor'] ?? '0'),
                $statusReserva,
                $confirmacao,
                $observacao !== '' ? $observacao : null,
                $_SESSION['funcionario_id'],
            ]);
            header('Location: painel-reservas.php');
            exit;
        }
    }

    if ($acao === 'editar') {
        $idReserva = (int) ($_POST['id'] ?? 0);
        $churrascaria = trim($_POST['churrascaria'] ?? CHURRASCARIA_PADRAO);
        $tipoReserva = trim($_POST['tipo_reserva'] ?? '');
        $pessoas = (int) ($_POST['pessoas'] ?? 0);
        $statusReserva = $_POST['status_reserva'] ?? 'Reservado';
        $observacao = trim($_POST['observacao'] ?? '');

        if ($idReserva < 1) {
            $mensagemErro = 'Reserva inválida.';
        } elseif (!churrascariaReservaValida($churrascaria)) {
            $mensagemErro = 'Escolha uma churrascaria válida.';
        } elseif ($pessoas < 1) {
            $mensagemErro = 'Informe um número de pessoas válido.';
        } elseif (!in_array($statusReserva, ['Reservado', 'Cancelado'], true)) {
            $mensagemErro = 'Escolha um status de reserva válido.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE reservas SET nome_cliente = ?, telefone = ?, churrascaria = ?, tipo_reserva = ?, data_pedido = ?, data_reserva = ?, hora_reserva = ?, pessoas = ?, valor = ?, status_reserva = ?, observacao = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                trim($_POST['nome'] ?? ''),
                trim($_POST['telefone'] ?? ''),
                $churrascaria,
                $tipoReserva !== '' ? $tipoReserva : null,
                $_POST['data_pedido'] ?? null,
                $_POST['data'] ?? '',
                $_POST['hora'] ?? '',
                $pessoas,
                parseValorBr($_POST['valor'] ?? '0'),
                $statusReserva,
                $observacao !== '' ? $observacao : null,
                $idReserva,
            ]);
            header('Location: ' . urlPainelReservas($paginaRetorno, $ordenacaoRetorno));
            exit;
        }
    }

    if ($acao === 'atualizar_comparecimento') {
        $idReserva = (int) ($_POST['id'] ?? 0);
        $compareceramPost = $_POST['pessoas_compareceram'] ?? '';
        $compareceram = $compareceramPost === '' ? null : (int) $compareceramPost;
        $confirmacaoAtualizada = $_POST['confirmacao'] ?? 'Pendente';

        if (in_array($confirmacaoAtualizada, ['Pendente', 'Confirmado'], true)) {
            $stmt = $pdo->prepare('UPDATE reservas SET pessoas_compareceram = ?, confirmacao = ? WHERE id = ?');
            $stmt->execute([$compareceram, $confirmacaoAtualizada, $idReserva]);
        }
        header('Location: ' . urlPainelReservas($paginaRetorno, $ordenacaoRetorno));
        exit;
    }

    if ($acao === 'excluir' && $nivel >= NIVEL_GERENTE) {
        $stmt = $pdo->prepare('DELETE FROM reservas WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        header('Location: ' . urlPainelReservas($paginaRetorno, $ordenacaoRetorno));
        exit;
    }
}

$diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

$tiposReserva = $pdo->query('SELECT id, nome FROM tipos_reserva ORDER BY nome')->fetchAll();

$reservasPorPagina = 50;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$ordenacoesReservas = [
    'data_asc' => [
        'label' => 'Data mais próxima',
        'sql' => 'r.data_reserva ASC, r.hora_reserva ASC, r.nome_cliente ASC',
    ],
    'data_desc' => [
        'label' => 'Data mais recente',
        'sql' => 'r.data_reserva DESC, r.hora_reserva DESC, r.nome_cliente ASC',
    ],
    'cliente_asc' => [
        'label' => 'Cliente A-Z',
        'sql' => 'r.nome_cliente ASC, r.data_reserva ASC, r.hora_reserva ASC',
    ],
    'cliente_desc' => [
        'label' => 'Cliente Z-A',
        'sql' => 'r.nome_cliente DESC, r.data_reserva ASC, r.hora_reserva ASC',
    ],
];
$ordenacaoAtual = $_GET['ordenacao'] ?? 'data_asc';
if (!array_key_exists($ordenacaoAtual, $ordenacoesReservas)) {
    $ordenacaoAtual = 'data_asc';
}
$ordenacaoSql = $ordenacoesReservas[$ordenacaoAtual]['sql'];

$resumoReservas = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN confirmacao = 'Confirmado' THEN 1 ELSE 0 END), 0) AS confirmadas,
        COALESCE(SUM(CASE WHEN confirmacao <> 'Confirmado' OR confirmacao IS NULL THEN 1 ELSE 0 END), 0) AS pendentes,
        COALESCE(SUM(CASE WHEN status_reserva = 'Reservado' THEN pessoas ELSE 0 END), 0) AS pessoas_esperadas
     FROM reservas"
)->fetch();

$totalReservas = (int) ($resumoReservas['total'] ?? 0);
$totalConfirmadas = (int) ($resumoReservas['confirmadas'] ?? 0);
$totalPendentes = (int) ($resumoReservas['pendentes'] ?? 0);
$totalPessoasEsperadas = (int) ($resumoReservas['pessoas_esperadas'] ?? 0);
$totalPaginas = max(1, (int) ceil($totalReservas / $reservasPorPagina));

if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
}

$offsetReservas = ($paginaAtual - 1) * $reservasPorPagina;
$primeiraReservaPagina = $totalReservas > 0 ? $offsetReservas + 1 : 0;
$ultimaReservaPagina = min($offsetReservas + $reservasPorPagina, $totalReservas);

$stmtReservas = $pdo->prepare(
    'SELECT r.id, r.nome_cliente, r.telefone, r.churrascaria, r.tipo_reserva, r.data_pedido, r.data_reserva, r.hora_reserva, r.pessoas,
            r.pessoas_compareceram, r.valor, r.status_reserva, r.confirmacao, r.observacao, f.nome AS criado_por
     FROM reservas r
     JOIN funcionarios f ON f.id = r.funcionario_id
     ORDER BY ' . $ordenacaoSql . '
     LIMIT :limite OFFSET :offset'
);
$stmtReservas->bindValue(':limite', $reservasPorPagina, PDO::PARAM_INT);
$stmtReservas->bindValue(':offset', $offsetReservas, PDO::PARAM_INT);
$stmtReservas->execute();
$reservas = $stmtReservas->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Reservas - Churrascaria Pampulha</title>
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
                    <li><a href="mesas.php">Mesas</a></li>
                    <li><a href="tipos-reserva.php">Tipos de Reserva</a></li>
                    <li><a href="clientes.php">Clientes</a></li>
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
                <div class="panel-header-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <div>
                    <h2>Painel de Reservas</h2>
                    <p>Olá, <?= e($_SESSION['funcionario_nome']) ?> (<?= e(nomeNivel($nivel)) ?>) — cadastre e acompanhe as reservas da casa</p>
                </div>
                <a href="imprimir-reservas.php" target="_blank" class="btn btn-outline btn-imprimir-reservas-dia"><i class="fa-solid fa-print"></i>Imprimir reservas do dia</a>
            </div>

            <?php if ($totalReservas > 0): ?>
                <div class="stat-cards-row">
                    <div class="stat">
                        <i class="fa-solid fa-calendar-day"></i>
                        <h4><?= e((string) $totalReservas) ?></h4>
                        <p>Reservas no total</p>
                    </div>
                    <div class="stat">
                        <i class="fa-solid fa-circle-check"></i>
                        <h4><?= e((string) $totalConfirmadas) ?></h4>
                        <p>Confirmadas</p>
                    </div>
                    <div class="stat">
                        <i class="fa-solid fa-hourglass-half"></i>
                        <h4><?= e((string) $totalPendentes) ?></h4>
                        <p>Pendentes</p>
                    </div>
                    <div class="stat">
                        <i class="fa-solid fa-users"></i>
                        <h4><?= e((string) $totalPessoasEsperadas) ?></h4>
                        <p>Pessoas esperadas</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="reserva-form-card">
                <div class="card-header-bar">
                    <i class="fa-solid fa-circle-plus"></i>
                    <h3>Nova Reserva</h3>
                </div>
                <form method="post" action="painel-reservas.php">
                    <input type="hidden" name="acao" value="criar">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="reserva-form-grid">
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-user"></i>Nome do cliente</span>
                            <input type="text" name="nome" id="novo_nome" placeholder="Nome do cliente" required autocomplete="off">
                            <small id="novo_cliente_aviso" class="reserva-form-aviso" style="display:none;"></small>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-phone"></i>Telefone</span>
                            <input type="tel" name="telefone" id="novo_telefone" inputmode="numeric" placeholder="(00) 00000-0000" maxlength="15" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-store"></i>Churrascaria</span>
                            <select name="churrascaria" id="novo_churrascaria" required>
                                <?php foreach (CHURRASCARIAS_RESERVA as $churrascariaOpcao): ?>
                                    <option value="<?= e($churrascariaOpcao) ?>" <?= $churrascariaOpcao === CHURRASCARIA_PADRAO ? 'selected' : '' ?>><?= e($churrascariaOpcao) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-cake-candles"></i>Tipo de reserva</span>
                            <select name="tipo_reserva">
                                <option value="">Selecione (opcional)</option>
                                <?php foreach ($tiposReserva as $tipoOpcao): ?>
                                    <option value="<?= e($tipoOpcao['nome']) ?>"><?= e($tipoOpcao['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a href="tipos-reserva.php" class="reserva-form-link-gerenciar"><i class="fa-solid fa-gear"></i>Gerenciar tipos de reserva</a>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-calendar"></i>Data do pedido</span>
                            <input type="date" name="data_pedido" value="<?= e(date('Y-m-d')) ?>" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-calendar-check"></i>Data da reserva</span>
                            <input type="date" name="data" id="novo_data" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-clock"></i>Horário</span>
                            <input type="time" name="hora" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-users"></i>Nº de pessoas</span>
                            <input type="number" name="pessoas" placeholder="Nº de pessoas" min="1" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-sack-dollar"></i>Valor</span>
                            <input type="text" name="valor" inputmode="numeric" placeholder="R$ 0,00" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-clipboard-list"></i>Status</span>
                            <select name="status_reserva" required>
                                <option value="Reservado" selected>Reservado</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-circle-check"></i>Confirmação</span>
                            <select name="confirmacao" required>
                                <option value="Pendente" selected>Confirmação pendente</option>
                                <option value="Confirmado">Confirmado</option>
                            </select>
                        </label>
                        <label class="reserva-form-label reserva-form-label--full">
                            <span><i class="fa-solid fa-comment"></i>Observação</span>
                            <textarea name="observacao" placeholder="Observação (opcional)" rows="3"></textarea>
                        </label>
                    </div>
                    <?php if ($mensagemErro !== ''): ?>
                        <p class="login-erro"><?= e($mensagemErro) ?></p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i>Adicionar Reserva</button>
                </form>
            </div>

            <div class="reservas-lista-toolbar">
                <div>
                    <h3>Reservas cadastradas</h3>
                    <p><?= e($ordenacoesReservas[$ordenacaoAtual]['label']) ?> · <?= e((string) $totalReservas) ?> reservas</p>
                </div>
                <div class="reservas-lista-controles">
                    <form method="get" action="painel-reservas.php" class="reservas-ordenacao-form">
                        <label for="ordenacao_reservas"><i class="fa-solid fa-arrow-down-a-z"></i>Ordenar por</label>
                        <select name="ordenacao" id="ordenacao_reservas" onchange="this.form.submit()">
                            <?php foreach ($ordenacoesReservas as $valorOrdenacao => $dadosOrdenacao): ?>
                                <option value="<?= e($valorOrdenacao) ?>" <?= $valorOrdenacao === $ordenacaoAtual ? 'selected' : '' ?>><?= e($dadosOrdenacao['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <label class="reservas-busca">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" class="reservas-busca-input" placeholder="Pesquisar nome ou telefone" autocomplete="off">
                    </label>
                </div>
            </div>

            <div class="reservas-lista-wrapper">
                <table class="reservas-tabela">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Churrascaria</th>
                            <th>Tipo</th>
                            <th>Telefone</th>
                            <th>Pedido</th>
                            <th>Data</th>
                            <th>Dia</th>
                            <th>Hora</th>
                            <th>Pessoas</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Confirmação / Compareceram</th>
                            <th>Cadastrada por</th>
                            <th>Observação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservas as $reserva): ?>
                            <tr>
                                <td><?= e($reserva['nome_cliente']) ?></td>
                                <td><span class="badge badge-info"><i class="fa-solid fa-location-dot"></i><?= e($reserva['churrascaria'] ?? CHURRASCARIA_PADRAO) ?></span></td>
                                <td><?= $reserva['tipo_reserva'] ? '<span class="badge badge-info"><i class="fa-solid fa-tag"></i>' . e($reserva['tipo_reserva']) . '</span>' : '-' ?></td>
                                <td><?= e($reserva['telefone']) ?></td>
                                <td><?= $reserva['data_pedido'] ? e(date('d/m/Y', strtotime($reserva['data_pedido']))) : '-' ?></td>
                                <td><?= e(date('d/m/Y', strtotime($reserva['data_reserva']))) ?></td>
                                <td><?= e($diasSemana[(int) date('w', strtotime($reserva['data_reserva']))]) ?></td>
                                <td><?= e(date('H:i', strtotime($reserva['hora_reserva']))) ?></td>
                                <td><?= e((string) $reserva['pessoas']) ?></td>
                                <td>R$ <?= e(number_format((float) $reserva['valor'], 2, ',', '.')) ?></td>
                                <td>
                                    <?php if ($reserva['status_reserva'] === 'Reservado'): ?>
                                        <span class="badge badge-info"><i class="fa-solid fa-calendar-check"></i>Reservado</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><i class="fa-solid fa-ban"></i>Cancelado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="painel-reservas.php" class="reserva-comparecimento-form">
                                        <input type="hidden" name="acao" value="atualizar_comparecimento">
                                        <input type="hidden" name="id" value="<?= e((string) $reserva['id']) ?>">
                                        <input type="hidden" name="pagina" value="<?= e((string) $paginaAtual) ?>">
                                        <input type="hidden" name="ordenacao" value="<?= e($ordenacaoAtual) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <select name="confirmacao">
                                            <option value="Pendente" <?= $reserva['confirmacao'] === 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                                            <option value="Confirmado" <?= $reserva['confirmacao'] === 'Confirmado' ? 'selected' : '' ?>>Confirmado</option>
                                        </select>
                                        <input type="number" name="pessoas_compareceram" min="0" placeholder="Vieram" value="<?= e($reserva['pessoas_compareceram'] !== null ? (string) $reserva['pessoas_compareceram'] : '') ?>">
                                        <button type="submit" title="Salvar"><i class="fa-solid fa-check"></i></button>
                                    </form>
                                </td>
                                <td><?= e($reserva['criado_por']) ?></td>
                                <td><?= $reserva['observacao'] ? e($reserva['observacao']) : '-' ?></td>
                                <td class="reserva-acoes-col">
                                    <button type="button" class="btn-editar-reserva" title="Editar reserva"
                                        data-id="<?= e((string) $reserva['id']) ?>"
                                        data-nome="<?= e($reserva['nome_cliente']) ?>"
                                        data-telefone="<?= e($reserva['telefone']) ?>"
                                        data-churrascaria="<?= e($reserva['churrascaria'] ?? CHURRASCARIA_PADRAO) ?>"
                                        data-tipo-reserva="<?= e($reserva['tipo_reserva'] ?? '') ?>"
                                        data-data-pedido="<?= e($reserva['data_pedido'] ?? '') ?>"
                                        data-data="<?= e($reserva['data_reserva']) ?>"
                                        data-hora="<?= e(substr((string) $reserva['hora_reserva'], 0, 5)) ?>"
                                        data-pessoas="<?= e((string) $reserva['pessoas']) ?>"
                                        data-valor="<?= e(number_format((float) $reserva['valor'], 2, ',', '.')) ?>"
                                        data-status="<?= e($reserva['status_reserva']) ?>"
                                        data-observacao="<?= e($reserva['observacao'] ?? '') ?>"
                                        onclick="abrirEdicaoReserva(this)">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <?php if ($nivel >= NIVEL_GERENTE): ?>
                                        <form method="post" action="painel-reservas.php" onsubmit="return confirm('Remover esta reserva?');">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?= e((string) $reserva['id']) ?>">
                                            <input type="hidden" name="pagina" value="<?= e((string) $paginaAtual) ?>">
                                            <input type="hidden" name="ordenacao" value="<?= e($ordenacaoAtual) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <button type="submit" class="btn-remover-reserva" title="Remover reserva">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($reservas)): ?>
                    <p class="reservas-vazio" style="display: block;">Nenhuma reserva cadastrada ainda.</p>
                <?php endif; ?>
                <p class="reservas-vazio reservas-busca-vazio">Nenhuma reserva encontrada para essa pesquisa.</p>
                <?php if ($totalPaginas > 1): ?>
                    <nav class="reservas-paginacao" aria-label="Paginação de reservas">
                        <span class="reservas-paginacao-info">Mostrando <?= e((string) $primeiraReservaPagina) ?>-<?= e((string) $ultimaReservaPagina) ?> de <?= e((string) $totalReservas) ?></span>
                        <div class="reservas-paginacao-links">
                            <?php if ($paginaAtual > 1): ?>
                                <a href="<?= e(urlPainelReservas($paginaAtual - 1, $ordenacaoAtual)) ?>" aria-label="Página anterior"><i class="fa-solid fa-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($pagina = 1; $pagina <= $totalPaginas; $pagina++): ?>
                                <a href="<?= e(urlPainelReservas($pagina, $ordenacaoAtual)) ?>" class="<?= $pagina === $paginaAtual ? 'ativa' : '' ?>" <?= $pagina === $paginaAtual ? 'aria-current="page"' : '' ?>><?= e((string) $pagina) ?></a>
                            <?php endfor; ?>
                            <?php if ($paginaAtual < $totalPaginas): ?>
                                <a href="<?= e(urlPainelReservas($paginaAtual + 1, $ordenacaoAtual)) ?>" aria-label="Próxima página"><i class="fa-solid fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div id="modalEditarReserva" class="modal-overlay" onclick="if (event.target === this) fecharEdicaoReserva();">
        <div class="modal-card">
            <div class="card-header-bar">
                <i class="fa-solid fa-pen"></i>
                <h3>Editar Reserva</h3>
                <button type="button" class="modal-close" onclick="fecharEdicaoReserva()" title="Fechar"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="post" action="painel-reservas.php">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" id="editar_id" value="">
                <input type="hidden" name="pagina" value="<?= e((string) $paginaAtual) ?>">
                <input type="hidden" name="ordenacao" value="<?= e($ordenacaoAtual) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="reserva-form-grid">
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-user"></i>Nome do cliente</span>
                        <input type="text" name="nome" id="editar_nome" required>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-phone"></i>Telefone</span>
                        <input type="tel" name="telefone" id="editar_telefone" inputmode="numeric" placeholder="(00) 00000-0000" maxlength="15" required>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-store"></i>Churrascaria</span>
                        <select name="churrascaria" id="editar_churrascaria" required>
                            <?php foreach (CHURRASCARIAS_RESERVA as $churrascariaOpcao): ?>
                                <option value="<?= e($churrascariaOpcao) ?>"><?= e($churrascariaOpcao) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-cake-candles"></i>Tipo de reserva</span>
                        <select name="tipo_reserva" id="editar_tipo_reserva">
                            <option value="">Selecione (opcional)</option>
                            <?php foreach ($tiposReserva as $tipoOpcao): ?>
                                <option value="<?= e($tipoOpcao['nome']) ?>"><?= e($tipoOpcao['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-calendar"></i>Data do pedido</span>
                        <input type="date" name="data_pedido" id="editar_data_pedido" required>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-calendar-check"></i>Data da reserva</span>
                        <input type="date" name="data" id="editar_data" required>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-clock"></i>Horário</span>
                        <input type="time" name="hora" id="editar_hora" required>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-users"></i>Nº de pessoas</span>
                        <input type="number" name="pessoas" id="editar_pessoas" min="1" required>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-sack-dollar"></i>Valor</span>
                        <input type="text" name="valor" id="editar_valor" inputmode="numeric" required>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-clipboard-list"></i>Status</span>
                        <select name="status_reserva" id="editar_status" required>
                            <option value="Reservado">Reservado</option>
                            <option value="Cancelado">Cancelado</option>
                        </select>
                    </label>
                    <label class="reserva-form-label reserva-form-label--full">
                        <span><i class="fa-solid fa-comment"></i>Observação</span>
                        <textarea name="observacao" id="editar_observacao" placeholder="Observação (opcional)" rows="3"></textarea>
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="fecharEdicaoReserva()">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Salvar alterações</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderizarControleSessao(); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            inicializarBuscaReservasPainel();
            inicializarVerificacaoClienteNovaReserva();
            document.querySelectorAll('input[name="telefone"]').forEach(function (telefoneInput) {
                telefoneInput.addEventListener('input', function () {
                    var digitos = telefoneInput.value.replace(/\D/g, '').slice(0, 11);
                    var formatado = digitos;
                    if (digitos.length > 10) {
                        formatado = digitos.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
                    } else if (digitos.length > 6) {
                        formatado = digitos.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                    } else if (digitos.length > 2) {
                        formatado = digitos.replace(/(\d{2})(\d{0,5})/, '($1) $2');
                    } else if (digitos.length > 0) {
                        formatado = digitos.replace(/(\d{0,2})/, '($1');
                    }
                    telefoneInput.value = formatado.trim();
                });
            });

            document.querySelectorAll('input[name="valor"]').forEach(function (valorInput) {
                valorInput.addEventListener('input', function () {
                    var digitos = valorInput.value.replace(/\D/g, '');
                    var numero = (parseInt(digitos || '0', 10) / 100).toFixed(2);
                    var partes = numero.split('.');
                    var inteiro = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    valorInput.value = 'R$ ' + inteiro + ',' + partes[1];
                });
            });
        });

        function inicializarVerificacaoClienteNovaReserva() {
            var nomeInput = document.getElementById('novo_nome');
            var telefoneInput = document.getElementById('novo_telefone');
            var churrascariaSelect = document.getElementById('novo_churrascaria');
            var dataInput = document.getElementById('novo_data');
            var aviso = document.getElementById('novo_cliente_aviso');

            if (!dataInput || !aviso) {
                return;
            }

            var timeoutId = null;

            function verificarCliente() {
                var nome = nomeInput ? nomeInput.value.trim() : '';
                var telefone = telefoneInput ? telefoneInput.value.replace(/\D/g, '') : '';

                if (nome === '' && telefone === '') {
                    aviso.style.display = 'none';
                    return;
                }

                var params = new URLSearchParams({ acao: 'buscar_cliente', nome: nome, telefone: telefone, data: dataInput.value });
                fetch('painel-reservas.php?' + params.toString())
                    .then(function (resposta) { return resposta.json(); })
                    .then(function (dados) {
                        if (dados.duplicado) {
                            aviso.textContent = 'Esse cliente já está reservado para esse dia.';
                            aviso.style.display = 'block';
                            return;
                        }

                        aviso.style.display = 'none';

                        if (dados.telefone && telefoneInput && telefoneInput.value.trim() === '') {
                            telefoneInput.value = dados.telefone;
                        }

                        if (dados.churrascaria && churrascariaSelect) {
                            churrascariaSelect.value = dados.churrascaria;
                        }
                    })
                    .catch(function () {});
            }

            function agendarVerificacao() {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(verificarCliente, 400);
            }

            if (nomeInput) {
                nomeInput.addEventListener('input', agendarVerificacao);
            }

            if (telefoneInput) {
                telefoneInput.addEventListener('input', agendarVerificacao);
            }

            dataInput.addEventListener('change', agendarVerificacao);
        }

        function inicializarBuscaReservasPainel() {
            document.querySelectorAll('.reservas-busca-input').forEach(function (input) {
                var bloco = input.closest('.painel-reservas');
                if (!bloco) {
                    return;
                }

                var linhas = Array.prototype.slice.call(bloco.querySelectorAll('.reservas-tabela tbody tr'));
                var mensagemVazia = bloco.querySelector('.reservas-busca-vazio');

                function normalizar(valor) {
                    return (valor || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
                }

                function filtrarReservas() {
                    var busca = normalizar(input.value);
                    var buscaDigitos = busca.replace(/\D/g, '');
                    var visiveis = 0;

                    linhas.forEach(function (linha) {
                        var celulas = linha.querySelectorAll('td');
                        var cliente = normalizar(celulas[0] ? celulas[0].textContent : '');
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

        function abrirEdicaoReserva(botao) {
            document.getElementById('editar_id').value = botao.dataset.id;
            document.getElementById('editar_nome').value = botao.dataset.nome;
            document.getElementById('editar_telefone').value = botao.dataset.telefone;
            document.getElementById('editar_churrascaria').value = botao.dataset.churrascaria || 'Churrascaria Pampulha';
            document.getElementById('editar_tipo_reserva').value = botao.dataset.tipoReserva || '';
            document.getElementById('editar_data_pedido').value = botao.dataset.dataPedido;
            document.getElementById('editar_data').value = botao.dataset.data;
            document.getElementById('editar_hora').value = botao.dataset.hora;
            document.getElementById('editar_pessoas').value = botao.dataset.pessoas;
            document.getElementById('editar_valor').value = 'R$ ' + botao.dataset.valor;
            document.getElementById('editar_status').value = botao.dataset.status;
            document.getElementById('editar_observacao').value = botao.dataset.observacao;
            document.getElementById('modalEditarReserva').classList.add('aberto');
        }

        function fecharEdicaoReserva() {
            document.getElementById('modalEditarReserva').classList.remove('aberto');
        }

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                fecharEdicaoReserva();
            }
        });
    </script>
</body>
</html>

