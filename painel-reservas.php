<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();
garantirColunaChurrascaria($pdo);
garantirTabelaTiposReserva($pdo);
garantirColunaTipoReserva($pdo);
garantirTabelaClientes($pdo);
garantirColunaChurrascariaClientes($pdo);
garantirColunaMesasAlocadasReservas($pdo);

$diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
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

/**
 * Filtros da lista do painel: dia (?data=) e casa. A casa segue a churrascaria escolhida
 * no menu; ?casas=ambas mostra as duas. Nos POSTs, os filtros voltam em filtro_data /
 * filtro_casas para o redirecionamento devolver a mesma lista.
 */
function filtrosPainelReservas(): array
{
    static $filtros = null;

    if ($filtros !== null) {
        return $filtros;
    }

    $origem = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ['data' => $_POST['filtro_data'] ?? '', 'casas' => $_POST['filtro_casas'] ?? '']
        : ['data' => $_GET['data'] ?? '', 'casas' => $_GET['casas'] ?? ''];

    $data = is_string($origem['data']) ? $origem['data'] : '';
    $dt = $data !== '' ? DateTime::createFromFormat('Y-m-d', $data) : false;

    return $filtros = [
        'data' => ($dt && $dt->format('Y-m-d') === $data) ? $data : '',
        'casas' => $origem['casas'] === 'ambas' ? 'ambas' : '',
    ];
}

/** Nome da churrascaria usada no filtro da lista, ou null para mostrar as duas casas. */
function casaFiltroPainelReservas(): ?string
{
    return filtrosPainelReservas()['casas'] === 'ambas' ? null : nomeChurrascariaSelecionada();
}

/** Campos escondidos que levam os filtros da lista nos formulários de POST. */
function camposFiltroPainelReservas(): string
{
    $filtros = filtrosPainelReservas();

    return '<input type="hidden" name="filtro_data" value="' . e($filtros['data']) . '">'
        . '<input type="hidden" name="filtro_casas" value="' . e($filtros['casas']) . '">';
}

/** Endereço do painel com os filtros atuais, trocando só o que vier em $alterar. */
function urlFiltroPainelReservas(array $alterar = []): string
{
    $filtros = filtrosPainelReservas();
    $params = array_filter(array_merge([
        'data' => $filtros['data'],
        'casas' => $filtros['casas'],
        'ordenacao' => $_GET['ordenacao'] ?? '',
    ], $alterar), function ($valor) {
        return $valor !== '' && $valor !== null && $valor !== 'data_asc';
    });

    return 'painel-reservas.php' . ($params ? '?' . http_build_query($params) : '');
}

function renderizarLinhaReservaPainel(array $reserva, int $paginaAtual, string $ordenacaoAtual, int $nivel, array $diasSemana): string
{
    ob_start();
    ?>
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
        <td><?= $reserva['mesas_alocadas'] ? e($reserva['mesas_alocadas']) : '-' ?></td>
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
                <?= camposFiltroPainelReservas() ?>
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
                    <?= camposFiltroPainelReservas() ?>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <button type="submit" class="btn-remover-reserva" title="Remover reserva">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['acao'] ?? '') === 'buscar_reservas_painel') {
    header('Content-Type: application/json; charset=utf-8');
    $nivelBusca = nivelFuncionario();
    $termoBusca = trim($_GET['termo'] ?? '');
    $ordenacaoBusca = $_GET['ordenacao'] ?? 'data_asc';
    if (!array_key_exists($ordenacaoBusca, $ordenacoesReservas)) {
        $ordenacaoBusca = 'data_asc';
    }
    $digitosBusca = preg_replace('/\D/', '', $termoBusca);

    $respostaBusca = ['html' => '', 'total' => 0, 'mostrando' => 0];

    if ($termoBusca !== '') {
        $limiteResultadosBusca = 300;
        $condicoesBusca = ['(r.nome_cliente LIKE ? OR r.telefone LIKE ?'];
        $parametrosBusca = ['%' . $termoBusca . '%', '%' . $termoBusca . '%'];

        if ($digitosBusca !== '') {
            $condicoesBusca[0] .= " OR REPLACE(REPLACE(REPLACE(REPLACE(r.telefone, '(', ''), ')', ''), ' ', ''), '-', '') LIKE ?";
            $parametrosBusca[] = '%' . $digitosBusca . '%';
        }
        $condicoesBusca[0] .= ')';

        $stmtContagemBusca = $pdo->prepare(
            'SELECT COUNT(*) FROM reservas r WHERE ' . implode(' AND ', $condicoesBusca)
        );
        $stmtContagemBusca->execute($parametrosBusca);
        $respostaBusca['total'] = (int) $stmtContagemBusca->fetchColumn();

        $stmtBusca = $pdo->prepare(
            'SELECT r.id, r.nome_cliente, r.telefone, r.churrascaria, r.tipo_reserva, r.data_pedido, r.data_reserva, r.hora_reserva, r.pessoas, r.mesas_alocadas,
                    r.pessoas_compareceram, r.valor, r.status_reserva, r.confirmacao, r.observacao, f.nome AS criado_por
             FROM reservas r
             JOIN funcionarios f ON f.id = r.funcionario_id
             WHERE ' . implode(' AND ', $condicoesBusca) . '
             ORDER BY ' . $ordenacoesReservas[$ordenacaoBusca]['sql'] . '
             LIMIT ' . $limiteResultadosBusca
        );
        $stmtBusca->execute($parametrosBusca);
        $reservasEncontradas = $stmtBusca->fetchAll();

        $htmlLinhas = '';
        foreach ($reservasEncontradas as $reservaEncontrada) {
            $htmlLinhas .= renderizarLinhaReservaPainel($reservaEncontrada, 1, $ordenacaoBusca, $nivelBusca, $diasSemana);
        }
        $respostaBusca['html'] = $htmlLinhas;
        $respostaBusca['mostrando'] = count($reservasEncontradas);
    }

    echo json_encode($respostaBusca);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['acao'] ?? '') === 'buscar_cliente') {
    header('Content-Type: application/json; charset=utf-8');
    $nomeBusca = trim($_GET['nome'] ?? '');
    $telefoneBusca = trim($_GET['telefone'] ?? '');
    $dataBusca = $_GET['data'] ?? '';
    $resposta = ['duplicado' => false, 'telefone' => null, 'churrascaria' => null, 'data_nascimento' => null];

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

        if (!$resposta['duplicado']) {
            $telefoneCliente = $telefoneBusca !== '' ? $telefoneBusca : ($resposta['telefone'] ?? '');
            if ($telefoneCliente !== '') {
                $stmtCliente = $pdo->prepare('SELECT data_nascimento FROM clientes WHERE telefone = ? LIMIT 1');
                $stmtCliente->execute([$telefoneCliente]);
                $cliente = $stmtCliente->fetch();
                if ($cliente && $cliente['data_nascimento']) {
                    $resposta['data_nascimento'] = $cliente['data_nascimento'];
                }
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
    $filtros = filtrosPainelReservas();
    $params = [];

    if ($filtros['data'] !== '') {
        $params['data'] = $filtros['data'];
    }

    if ($filtros['casas'] !== '') {
        $params['casas'] = $filtros['casas'];
    }

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
    $ordenacaoRetorno = in_array($_POST['ordenacao'] ?? '', $ordenacoesValidasPost, true) ? $_POST['ordenacao'] : 'data_asc';

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
            $nomeCliente = trim($_POST['nome'] ?? '');
            $telefoneCliente = trim($_POST['telefone'] ?? '');
            $aniversarioCliente = trim($_POST['cliente_data_nascimento'] ?? '');
            $mesasAlocadas = calcularMesasReserva($pdo, $churrascaria, $pessoas);

            $stmt = $pdo->prepare(
                'INSERT INTO reservas (nome_cliente, telefone, churrascaria, tipo_reserva, data_pedido, data_reserva, hora_reserva, pessoas, mesas_alocadas, valor, status_reserva, confirmacao, observacao, funcionario_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $nomeCliente,
                $telefoneCliente,
                $churrascaria,
                $tipoReserva !== '' ? $tipoReserva : null,
                $_POST['data_pedido'] ?? null,
                $_POST['data'] ?? '',
                $_POST['hora'] ?? '',
                $pessoas,
                $mesasAlocadas,
                parseValorBr($_POST['valor'] ?? '0'),
                $statusReserva,
                $confirmacao,
                $observacao !== '' ? $observacao : null,
                $_SESSION['funcionario_id'],
            ]);
            $idReservaCriada = (int) $pdo->lastInsertId();
            $reservaCriada = historicoBuscarRegistro($pdo, 'reserva', $idReservaCriada);
            registrarHistorico($pdo, 'reserva', $idReservaCriada, 'criou', historicoDescrever('reserva', $reservaCriada), null, $reservaCriada);
            salvarClienteAutomatico($pdo, $nomeCliente, $telefoneCliente, $aniversarioCliente !== '' ? $aniversarioCliente : null, $churrascaria);
            Logger::audit('reserva_criada', [
                'reserva_id' => $idReservaCriada,
                'churrascaria' => $churrascaria,
                'pessoas' => $pessoas,
            ]);
            header('Location: ' . urlPainelReservas(1, $ordenacaoRetorno));
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
            $nomeCliente = trim($_POST['nome'] ?? '');
            $telefoneCliente = trim($_POST['telefone'] ?? '');
            $mesasAlocadas = calcularMesasReserva($pdo, $churrascaria, $pessoas);
            $reservaAntes = historicoBuscarRegistro($pdo, 'reserva', $idReserva);

            $stmt = $pdo->prepare(
                'UPDATE reservas SET nome_cliente = ?, telefone = ?, churrascaria = ?, tipo_reserva = ?, data_pedido = ?, data_reserva = ?, hora_reserva = ?, pessoas = ?, mesas_alocadas = ?, valor = ?, status_reserva = ?, observacao = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $nomeCliente,
                $telefoneCliente,
                $churrascaria,
                $tipoReserva !== '' ? $tipoReserva : null,
                $_POST['data_pedido'] ?? null,
                $_POST['data'] ?? '',
                $_POST['hora'] ?? '',
                $pessoas,
                $mesasAlocadas,
                parseValorBr($_POST['valor'] ?? '0'),
                $statusReserva,
                $observacao !== '' ? $observacao : null,
                $idReserva,
            ]);
            $reservaDepois = historicoBuscarRegistro($pdo, 'reserva', $idReserva);
            registrarHistorico($pdo, 'reserva', $idReserva, 'editou', historicoDescrever('reserva', $reservaDepois), $reservaAntes, $reservaDepois);
            salvarClienteAutomatico($pdo, $nomeCliente, $telefoneCliente, null, $churrascaria);
            Logger::audit('reserva_editada', ['reserva_id' => $idReserva, 'churrascaria' => $churrascaria]);
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
            $reservaAntes = historicoBuscarRegistro($pdo, 'reserva', $idReserva);
            $stmt = $pdo->prepare('UPDATE reservas SET pessoas_compareceram = ?, confirmacao = ? WHERE id = ?');
            $stmt->execute([$compareceram, $confirmacaoAtualizada, $idReserva]);
            $reservaDepois = historicoBuscarRegistro($pdo, 'reserva', $idReserva);
            registrarHistorico($pdo, 'reserva', $idReserva, 'editou', historicoDescrever('reserva', $reservaDepois), $reservaAntes, $reservaDepois);
        }
        header('Location: ' . urlPainelReservas($paginaRetorno, $ordenacaoRetorno));
        exit;
    }

    if ($acao === 'excluir' && $nivel >= NIVEL_GERENTE) {
        $idExcluir = (int) ($_POST['id'] ?? 0);
        $reservaAntes = historicoBuscarRegistro($pdo, 'reserva', $idExcluir);
        $stmt = $pdo->prepare('DELETE FROM reservas WHERE id = ?');
        $stmt->execute([$idExcluir]);
        if ($reservaAntes !== null) {
            registrarHistorico($pdo, 'reserva', $idExcluir, 'excluiu', historicoDescrever('reserva', $reservaAntes), $reservaAntes, null);
        }
        Logger::audit('reserva_excluida', ['reserva_id' => $idExcluir]);
        header('Location: ' . urlPainelReservas($paginaRetorno, $ordenacaoRetorno));
        exit;
    }
}

$tiposReserva = $pdo->query('SELECT id, nome FROM tipos_reserva ORDER BY nome')->fetchAll();

$reservasPorPagina = 50;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$ordenacaoAtual = $_GET['ordenacao'] ?? 'data_asc';
if (!array_key_exists($ordenacaoAtual, $ordenacoesReservas)) {
    $ordenacaoAtual = 'data_asc';
}
$ordenacaoSql = $ordenacoesReservas[$ordenacaoAtual]['sql'];

$filtrosPainel = filtrosPainelReservas();
$casaFiltroPainel = casaFiltroPainelReservas();
$condicoesFiltroPainel = [];
$parametrosFiltroPainel = [];

if ($filtrosPainel['data'] !== '') {
    $condicoesFiltroPainel[] = 'r.data_reserva = ?';
    $parametrosFiltroPainel[] = $filtrosPainel['data'];
}

if ($casaFiltroPainel !== null) {
    $condicoesFiltroPainel[] = 'r.churrascaria = ?';
    $parametrosFiltroPainel[] = $casaFiltroPainel;
}

$whereFiltroPainel = $condicoesFiltroPainel ? ' WHERE ' . implode(' AND ', $condicoesFiltroPainel) : '';

$stmtResumoReservas = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN confirmacao = 'Confirmado' THEN 1 ELSE 0 END), 0) AS confirmadas,
        COALESCE(SUM(CASE WHEN confirmacao <> 'Confirmado' OR confirmacao IS NULL THEN 1 ELSE 0 END), 0) AS pendentes,
        COALESCE(SUM(CASE WHEN status_reserva = 'Reservado' THEN pessoas ELSE 0 END), 0) AS pessoas_esperadas
     FROM reservas r" . $whereFiltroPainel
);
$stmtResumoReservas->execute($parametrosFiltroPainel);
$resumoReservas = $stmtResumoReservas->fetch();

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
    'SELECT r.id, r.nome_cliente, r.telefone, r.churrascaria, r.tipo_reserva, r.data_pedido, r.data_reserva, r.hora_reserva, r.pessoas, r.mesas_alocadas,
            r.pessoas_compareceram, r.valor, r.status_reserva, r.confirmacao, r.observacao, f.nome AS criado_por
     FROM reservas r
     JOIN funcionarios f ON f.id = r.funcionario_id' . $whereFiltroPainel . '
     ORDER BY ' . $ordenacaoSql . '
     LIMIT ' . (int) $reservasPorPagina . ' OFFSET ' . (int) $offsetReservas
);
$stmtReservas->execute($parametrosFiltroPainel);
$reservas = $stmtReservas->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Reservas - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css?v=20261127-3">
    <?php include __DIR__ . '/pwa-head.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php $dataNovaReserva = $filtrosPainel['data']; include __DIR__ . '/menu-funcionario.php'; ?>

    <section class="painel-reservas<?= temaCasaraoAtivo() ? ' painel-reservas--casarao' : '' ?>">
        <div class="container">
            <div class="panel-header">
                <div class="panel-header-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <div>
                    <h2>Painel de Reservas</h2>
                    <p>Olá, <?= e($_SESSION['funcionario_nome']) ?> (<?= e(nomeNivel($nivel)) ?>) — cadastre e acompanhe as reservas da casa</p>
                </div>
                <a href="imprimir-reservas.php<?= $filtrosPainel['data'] !== '' ? '?' . e(http_build_query(['data' => $filtrosPainel['data']])) : '' ?>" target="_blank" class="btn btn-outline btn-imprimir-reservas-dia"><i class="fa-solid fa-print"></i>Imprimir reservas do dia</a>
            </div>

            <?php
            $hojePainel = date('Y-m-d');
            $baseSetasPainel = $filtrosPainel['data'] !== '' ? new DateTime($filtrosPainel['data']) : new DateTime($hojePainel);
            $diaAnteriorPainel = (clone $baseSetasPainel)->modify('-1 day')->format('Y-m-d');
            $diaSeguintePainel = (clone $baseSetasPainel)->modify('+1 day')->format('Y-m-d');
            $rotuloCasaPainel = $casaFiltroPainel ?? 'as duas casas';
            ?>
            <div class="painel-filtros-bar">
                <form method="get" action="painel-reservas.php" class="painel-filtros-form">
                    <?php if ($filtrosPainel['casas'] !== ''): ?><input type="hidden" name="casas" value="<?= e($filtrosPainel['casas']) ?>"><?php endif; ?>
                    <?php if ($ordenacaoAtual !== 'data_asc'): ?><input type="hidden" name="ordenacao" value="<?= e($ordenacaoAtual) ?>"><?php endif; ?>
                    <label for="filtro_data_painel"><i class="fa-solid fa-calendar-days"></i> Dia</label>
                    <div class="data-navegacao">
                        <a href="<?= e(urlFiltroPainelReservas(['data' => $diaAnteriorPainel])) ?>" class="data-navegacao-seta" title="Dia anterior" aria-label="Dia anterior"><i class="fa-solid fa-chevron-left"></i></a>
                        <input type="date" id="filtro_data_painel" name="data" value="<?= e($filtrosPainel['data']) ?>" onchange="this.form.submit()">
                        <a href="<?= e(urlFiltroPainelReservas(['data' => $diaSeguintePainel])) ?>" class="data-navegacao-seta" title="Próximo dia" aria-label="Próximo dia"><i class="fa-solid fa-chevron-right"></i></a>
                        <a href="<?= e(urlFiltroPainelReservas(['data' => $hojePainel])) ?>" class="data-navegacao-hoje<?= $filtrosPainel['data'] === $hojePainel ? ' is-hoje' : '' ?>" title="Reservas de hoje">Hoje</a>
                    </div>
                </form>
                <?php if ($filtrosPainel['data'] !== ''): ?>
                    <a href="<?= e(urlFiltroPainelReservas(['data' => ''])) ?>" class="painel-filtros-link"><i class="fa-solid fa-list"></i>Todas as datas</a>
                <?php endif; ?>
                <?php if (nomeChurrascariaSelecionada() !== null): ?>
                    <?php if ($filtrosPainel['casas'] === 'ambas'): ?>
                        <a href="<?= e(urlFiltroPainelReservas(['casas' => ''])) ?>" class="painel-filtros-link"><i class="fa-solid fa-location-dot"></i>Só <?= e(nomeChurrascariaSelecionada()) ?></a>
                    <?php else: ?>
                        <a href="<?= e(urlFiltroPainelReservas(['casas' => 'ambas'])) ?>" class="painel-filtros-link"><i class="fa-solid fa-layer-group"></i>Ver as duas casas</a>
                    <?php endif; ?>
                <?php endif; ?>
                <span class="painel-filtros-resumo">
                    <?= $filtrosPainel['data'] !== '' ? 'Reservas de ' . e(date('d/m/Y', strtotime($filtrosPainel['data']))) . ' (' . e($diasSemana[(int) date('w', strtotime($filtrosPainel['data']))]) . ')' : 'Todas as datas' ?> · <?= e($rotuloCasaPainel) ?>
                </span>
            </div>

            <?php if ($totalReservas > 0): ?>
                <div class="stat-cards-row">
                    <div class="stat">
                        <i class="fa-solid fa-calendar-day"></i>
                        <h4><?= e((string) $totalReservas) ?></h4>
                        <p><?= $filtrosPainel['data'] !== '' ? 'Reservas no dia' : 'Reservas no total' ?></p>
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

            <div class="reserva-form-card" id="nova-reserva">
                <div class="card-header-bar">
                    <i class="fa-solid fa-circle-plus"></i>
                    <h3>Nova Reserva</h3>
                </div>
                <form method="post" action="painel-reservas.php" class="reserva-form-secionado">
                    <input type="hidden" name="acao" value="criar">
                    <input type="hidden" name="ordenacao" value="<?= e($ordenacaoAtual) ?>">
                    <?= camposFiltroPainelReservas() ?>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                    <div class="reserva-form-secao">
                        <h4 class="reserva-form-secao-titulo"><i class="fa-solid fa-id-card"></i>Dados do cliente</h4>
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
                                <span><i class="fa-solid fa-cake-candles"></i>Data de nascimento</span>
                                <input type="date" name="cliente_data_nascimento" id="novo_cliente_aniversario">
                            </label>
                        </div>
                    </div>

                    <div class="reserva-form-secao">
                        <h4 class="reserva-form-secao-titulo"><i class="fa-solid fa-calendar-days"></i>Detalhes da reserva</h4>
                        <div class="reserva-form-grid">
                            <label class="reserva-form-label">
                                <span><i class="fa-solid fa-store"></i>Churrascaria</span>
                                <select name="churrascaria" id="novo_churrascaria" required>
                                    <?php foreach (CHURRASCARIAS_RESERVA as $churrascariaOpcao): ?>
                                        <option value="<?= e($churrascariaOpcao) ?>" <?= $churrascariaOpcao === (nomeChurrascariaSelecionada() ?? CHURRASCARIA_PADRAO) ? 'selected' : '' ?>><?= e($churrascariaOpcao) ?></option>
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
                                <input type="date" name="data" id="novo_data" value="<?= e($filtrosPainel['data']) ?>" required>
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
                        </div>
                    </div>

                    <div class="reserva-form-secao">
                        <h4 class="reserva-form-secao-titulo"><i class="fa-solid fa-clipboard-check"></i>Status e observação</h4>
                        <div class="reserva-form-grid">
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
                        <?php if ($filtrosPainel['data'] !== ''): ?><input type="hidden" name="data" value="<?= e($filtrosPainel['data']) ?>"><?php endif; ?>
                        <?php if ($filtrosPainel['casas'] !== ''): ?><input type="hidden" name="casas" value="<?= e($filtrosPainel['casas']) ?>"><?php endif; ?>
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
                            <th>Mesas</th>
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
                            <?= renderizarLinhaReservaPainel($reserva, $paginaAtual, $ordenacaoAtual, $nivel, $diasSemana) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($reservas)): ?>
                    <p class="reservas-vazio reservas-vazio-inicial" style="display: block;"><?= ($filtrosPainel['data'] !== '' || $casaFiltroPainel !== null) ? 'Nenhuma reserva para este filtro.' : 'Nenhuma reserva cadastrada ainda.' ?></p>
                <?php endif; ?>
                <p class="reservas-vazio reservas-busca-vazio">Nenhuma reserva encontrada para essa pesquisa.</p>
                <nav class="reservas-paginacao" aria-label="Paginação de reservas" <?= $totalPaginas > 1 ? '' : 'style="display: none;"' ?>>
                    <span class="reservas-paginacao-info"><?= $totalPaginas > 1 ? 'Mostrando ' . e((string) $primeiraReservaPagina) . '-' . e((string) $ultimaReservaPagina) . ' de ' . e((string) $totalReservas) : '' ?></span>
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
                <p class="reservas-busca-resultado-info" style="display: none;"></p>
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
                <?= camposFiltroPainelReservas() ?>
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
            inicializarAtalhoNovaReserva();
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

        // "+ Nova reserva" (menu e botão flutuante) leva a #nova-reserva: rola até o formulário e foca o nome.
        function inicializarAtalhoNovaReserva() {
            function focarNovaReserva() {
                if (window.location.hash !== '#nova-reserva') {
                    return;
                }
                var nome = document.getElementById('novo_nome');
                if (nome) {
                    nome.focus({ preventScroll: true });
                }
            }

            focarNovaReserva();
            window.addEventListener('hashchange', focarNovaReserva);

            document.addEventListener('click', function (evento) {
                var link = evento.target.closest('a[href="#nova-reserva"]');
                if (link && window.location.hash === '#nova-reserva') {
                    evento.preventDefault();
                    document.getElementById('nova-reserva').scrollIntoView({ behavior: 'smooth' });
                    focarNovaReserva();
                }
            });
        }

        function inicializarVerificacaoClienteNovaReserva() {
            var nomeInput = document.getElementById('novo_nome');
            var telefoneInput = document.getElementById('novo_telefone');
            var churrascariaSelect = document.getElementById('novo_churrascaria');
            var aniversarioInput = document.getElementById('novo_cliente_aniversario');
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

                        if (dados.data_nascimento && aniversarioInput && aniversarioInput.value === '') {
                            aniversarioInput.value = dados.data_nascimento;
                        }
                    })
                    .catch(function (erro) {
                        if (window.AppLogger) {
                            window.AppLogger.error('Falha ao buscar cliente para preencher reserva', { erro: String(erro) });
                        }
                    });
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

                var tabela = bloco.querySelector('.reservas-tabela');
                var tbody = tabela ? tabela.querySelector('tbody') : null;
                if (!tbody) {
                    return;
                }

                var mensagemVazia = bloco.querySelector('.reservas-busca-vazio');
                var mensagemVaziaInicial = bloco.querySelector('.reservas-vazio-inicial');
                var nav = bloco.querySelector('.reservas-paginacao');
                var infoBusca = bloco.querySelector('.reservas-busca-resultado-info');
                var htmlOriginal = tbody.innerHTML;
                var navDisplayOriginal = nav ? nav.style.display : '';

                var timeoutId = null;
                var requisicaoAtual = 0;

                function restaurarListaOriginal() {
                    tbody.innerHTML = htmlOriginal;
                    if (mensagemVazia) {
                        mensagemVazia.style.display = 'none';
                    }
                    if (mensagemVaziaInicial) {
                        mensagemVaziaInicial.style.display = htmlOriginal.trim() === '' ? 'block' : 'none';
                    }
                    if (nav) {
                        nav.style.display = navDisplayOriginal;
                    }
                    if (infoBusca) {
                        infoBusca.style.display = 'none';
                    }
                }

                function buscarNoServidor(termo) {
                    var ordenacaoSelect = bloco.querySelector('#ordenacao_reservas');
                    var params = new URLSearchParams({
                        acao: 'buscar_reservas_painel',
                        termo: termo,
                        ordenacao: ordenacaoSelect ? ordenacaoSelect.value : 'data_asc',
                        data: <?= json_encode($filtrosPainel['data']) ?>,
                        casas: <?= json_encode($filtrosPainel['casas']) ?>,
                    });

                    var idRequisicao = ++requisicaoAtual;

                    fetch('painel-reservas.php?' + params.toString())
                        .then(function (resposta) { return resposta.json(); })
                        .then(function (dados) {
                            if (idRequisicao !== requisicaoAtual) {
                                return;
                            }

                            tbody.innerHTML = dados.html;
                            if (mensagemVaziaInicial) {
                                mensagemVaziaInicial.style.display = 'none';
                            }
                            if (nav) {
                                nav.style.display = 'none';
                            }
                            if (mensagemVazia) {
                                mensagemVazia.style.display = dados.total === 0 ? 'block' : 'none';
                            }
                            if (infoBusca) {
                                if (dados.total > 0) {
                                    infoBusca.style.display = 'block';
                                    infoBusca.textContent = dados.total > dados.mostrando
                                        ? 'Mostrando ' + dados.mostrando + ' de ' + dados.total + ' resultados (refine a busca para ver todos)'
                                        : 'Mostrando ' + dados.total + ' resultado' + (dados.total === 1 ? '' : 's') + ' em todas as p\u00e1ginas';
                                } else {
                                    infoBusca.style.display = 'none';
                                }
                            }
                        })
                        .catch(function (erro) {
                            if (window.AppLogger) {
                                window.AppLogger.error('Falha ao buscar reservas em todas as p\u00e1ginas', { erro: String(erro) });
                            }
                        });
                }

                function tratarEntrada() {
                    var termo = input.value.trim();

                    clearTimeout(timeoutId);

                    if (termo === '') {
                        requisicaoAtual++;
                        restaurarListaOriginal();
                        return;
                    }

                    timeoutId = setTimeout(function () {
                        buscarNoServidor(termo);
                    }, 300);
                }

                input.addEventListener('input', tratarEntrada);
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

