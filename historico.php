<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirNivel(NIVEL_GERENTE);
garantirTabelaHistorico($pdo);

function dataFiltroHistorico(string $chave): string
{
    $valor = trim((string) ($_GET[$chave] ?? ''));
    $data = DateTime::createFromFormat('Y-m-d', $valor);

    return ($data && $data->format('Y-m-d') === $valor) ? $valor : '';
}

$filtros = [
    'de' => dataFiltroHistorico('de'),
    'ate' => dataFiltroHistorico('ate'),
    'funcionario' => max(0, (int) ($_GET['funcionario'] ?? 0)),
    'entidade' => array_key_exists($_GET['entidade'] ?? '', HISTORICO_ENTIDADES) ? $_GET['entidade'] : '',
    'acao' => array_key_exists($_GET['acao'] ?? '', HISTORICO_ACOES) ? $_GET['acao'] : '',
    'registro' => max(0, (int) ($_GET['registro'] ?? 0)),
    'busca' => mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 100),
];

$condicoes = [];
$parametros = [];

if ($filtros['de'] !== '') {
    $condicoes[] = 'criado_em >= ?';
    $parametros[] = $filtros['de'] . ' 00:00:00';
}
if ($filtros['ate'] !== '') {
    $condicoes[] = 'criado_em <= ?';
    $parametros[] = $filtros['ate'] . ' 23:59:59';
}
if ($filtros['funcionario'] > 0) {
    $condicoes[] = 'funcionario_id = ?';
    $parametros[] = $filtros['funcionario'];
}
if ($filtros['entidade'] !== '') {
    $condicoes[] = 'entidade = ?';
    $parametros[] = $filtros['entidade'];
    if ($filtros['registro'] > 0) {
        $condicoes[] = 'entidade_id = ?';
        $parametros[] = $filtros['registro'];
    }
}
if ($filtros['acao'] !== '') {
    $condicoes[] = 'acao = ?';
    $parametros[] = $filtros['acao'];
}
if ($filtros['busca'] !== '') {
    $condicoes[] = '(descricao LIKE ? OR alteracoes LIKE ? OR funcionario_nome LIKE ?)';
    $termo = '%' . addcslashes($filtros['busca'], '%_\\') . '%';
    array_push($parametros, $termo, $termo, $termo);
}

$where = $condicoes ? ' WHERE ' . implode(' AND ', $condicoes) : '';

$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM historico_alteracoes' . $where);
$stmtTotal->execute($parametros);
$totalRegistros = (int) $stmtTotal->fetchColumn();

$porPagina = 30;
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
$paginaAtual = max(1, min($totalPaginas, (int) ($_GET['pagina'] ?? 1)));
$deslocamento = ($paginaAtual - 1) * $porPagina;

$stmt = $pdo->prepare(
    'SELECT * FROM historico_alteracoes' . $where . ' ORDER BY criado_em DESC, id DESC LIMIT ' . $porPagina . ' OFFSET ' . $deslocamento
);
$stmt->execute($parametros);
$registros = $stmt->fetchAll();

// Quem aparece no filtro: funcionários atuais e também quem já foi excluído mas deixou histórico.
$funcionariosFiltro = $pdo->query(
    "SELECT funcionario_id AS id, MAX(funcionario_nome) AS nome
     FROM historico_alteracoes
     WHERE funcionario_id IS NOT NULL
     GROUP BY funcionario_id
     UNION
     SELECT id, nome FROM funcionarios"
)->fetchAll();
$nomesFuncionariosFiltro = [];
foreach ($funcionariosFiltro as $linhaFuncionario) {
    $idFuncionarioFiltro = (int) $linhaFuncionario['id'];
    if (!isset($nomesFuncionariosFiltro[$idFuncionarioFiltro]) || $nomesFuncionariosFiltro[$idFuncionarioFiltro] === '') {
        $nomesFuncionariosFiltro[$idFuncionarioFiltro] = (string) $linhaFuncionario['nome'];
    }
}
asort($nomesFuncionariosFiltro, SORT_NATURAL | SORT_FLAG_CASE);

$filtrosAtivos = array_filter($filtros, function ($valor) {
    return $valor !== '' && $valor !== 0;
});

function urlHistorico(array $filtros, array $mudancas = []): string
{
    $parametrosUrl = array_filter(array_merge($filtros, $mudancas), function ($valor) {
        return $valor !== '' && $valor !== 0 && $valor !== null;
    });

    return 'historico.php' . ($parametrosUrl ? '?' . http_build_query($parametrosUrl) : '');
}

$iconesEntidade = [
    'reserva' => 'fa-calendar-check',
    'cliente' => 'fa-address-book',
    'mesa' => 'fa-chair',
    'tipo_reserva' => 'fa-tags',
    'funcionario' => 'fa-user-tie',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de alterações - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css?v=20261127-4">
    <?php include __DIR__ . '/pwa-head.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/menu-funcionario.php'; ?>

    <section class="painel-reservas<?= temaCasaraoAtivo() ? ' painel-reservas--casarao' : '' ?>">
        <div class="container">
            <div class="panel-header">
                <div class="panel-header-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div>
                    <h2>Histórico de alterações</h2>
                    <p>Quem alterou, quando e o que mudou em reservas, clientes, mesas, tipos de reserva e funcionários</p>
                </div>
            </div>

            <form method="get" action="historico.php" class="historico-filtros">
                <?php if ($filtros['registro'] > 0 && $filtros['entidade'] !== ''): ?>
                    <input type="hidden" name="registro" value="<?= e((string) $filtros['registro']) ?>">
                <?php endif; ?>
                <label>
                    <span>De</span>
                    <input type="date" name="de" value="<?= e($filtros['de']) ?>">
                </label>
                <label>
                    <span>Até</span>
                    <input type="date" name="ate" value="<?= e($filtros['ate']) ?>">
                </label>
                <label>
                    <span>Quem</span>
                    <select name="funcionario">
                        <option value="">Todos</option>
                        <?php foreach ($nomesFuncionariosFiltro as $idOpcao => $nomeOpcao): ?>
                            <option value="<?= e((string) $idOpcao) ?>" <?= $filtros['funcionario'] === $idOpcao ? 'selected' : '' ?>><?= e($nomeOpcao !== '' ? $nomeOpcao : 'Funcionário #' . $idOpcao) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>O quê</span>
                    <select name="entidade">
                        <option value="">Tudo</option>
                        <?php foreach (HISTORICO_ENTIDADES as $chaveEntidade => $rotuloEntidade): ?>
                            <option value="<?= e($chaveEntidade) ?>" <?= $filtros['entidade'] === $chaveEntidade ? 'selected' : '' ?>><?= e($rotuloEntidade) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Ação</span>
                    <select name="acao">
                        <option value="">Todas</option>
                        <?php foreach (HISTORICO_ACOES as $chaveAcao => $rotuloAcao): ?>
                            <option value="<?= e($chaveAcao) ?>" <?= $filtros['acao'] === $chaveAcao ? 'selected' : '' ?>><?= e($rotuloAcao) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="historico-filtros-busca">
                    <span>Buscar</span>
                    <input type="search" name="busca" value="<?= e($filtros['busca']) ?>" placeholder="Nome do cliente, telefone, valor..." autocomplete="off">
                </label>
                <div class="historico-filtros-acoes">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filtrar</button>
                    <?php if ($filtrosAtivos): ?>
                        <a href="historico.php" class="btn btn-outline btn-sm">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>

            <p class="historico-resumo">
                <?= e((string) $totalRegistros) ?> alteraç<?= $totalRegistros === 1 ? 'ão' : 'ões' ?>
                <?= $filtrosAtivos ? 'encontrada' . ($totalRegistros === 1 ? '' : 's') . ' com estes filtros' : 'registrada' . ($totalRegistros === 1 ? '' : 's') ?>
                <?php if ($filtros['registro'] > 0 && $filtros['entidade'] !== ''): ?>
                    · só <?= e(mb_strtolower(HISTORICO_ENTIDADES[$filtros['entidade']])) ?> nº <?= e((string) $filtros['registro']) ?>
                    (<a href="<?= e(urlHistorico($filtros, ['registro' => 0, 'pagina' => 0])) ?>">ver todas</a>)
                <?php endif; ?>
            </p>

            <?php if (!$registros): ?>
                <p class="historico-vazio">
                    <?= $filtrosAtivos ? 'Nenhuma alteração encontrada com estes filtros.' : 'Ainda não há alterações registradas. A partir de agora, cada criação, edição ou exclusão aparece aqui.' ?>
                </p>
            <?php endif; ?>

            <ol class="historico-lista">
                <?php foreach ($registros as $registro): ?>
                    <?php
                    $alteracoes = json_decode((string) $registro['alteracoes'], true);
                    $alteracoes = is_array($alteracoes) ? $alteracoes : [];
                    $acaoRegistro = (string) $registro['acao'];
                    $entidadeRegistro = (string) $registro['entidade'];
                    $quando = DateTime::createFromFormat('Y-m-d H:i:s', (string) $registro['criado_em']);
                    ?>
                    <li class="historico-item historico-item--<?= e($acaoRegistro) ?>">
                        <div class="historico-item-topo">
                            <span class="historico-acao historico-acao--<?= e($acaoRegistro) ?>"><?= e(HISTORICO_ACOES[$acaoRegistro] ?? $acaoRegistro) ?></span>
                            <span class="historico-entidade">
                                <i class="fa-solid <?= e($iconesEntidade[$entidadeRegistro] ?? 'fa-circle') ?>"></i>
                                <?= e(HISTORICO_ENTIDADES[$entidadeRegistro] ?? $entidadeRegistro) ?>
                            </span>
                            <strong class="historico-descricao"><?= e((string) $registro['descricao']) ?></strong>
                            <?php if (!empty($registro['churrascaria'])): ?>
                                <span class="historico-casa"><?= e((string) $registro['churrascaria']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="historico-item-quem">
                            <span><i class="fa-solid fa-user"></i> <?= e($registro['funcionario_nome'] !== '' ? (string) $registro['funcionario_nome'] : 'Desconhecido') ?></span>
                            <span><i class="fa-regular fa-clock"></i> <?= e($quando ? $quando->format('d/m/Y \à\s H:i') : (string) $registro['criado_em']) ?></span>
                            <?php if (!empty($registro['entidade_id']) && $entidadeRegistro !== '' && $filtros['registro'] === 0): ?>
                                <a href="<?= e(urlHistorico([], ['entidade' => $entidadeRegistro, 'registro' => (int) $registro['entidade_id']])) ?>" class="historico-link-registro"><i class="fa-solid fa-list"></i> Histórico deste registro</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($alteracoes): ?>
                            <table class="historico-mudancas">
                                <thead>
                                    <tr>
                                        <th>Campo</th>
                                        <?php if ($acaoRegistro !== 'criou'): ?><th>Antes</th><?php endif; ?>
                                        <?php if ($acaoRegistro !== 'excluiu'): ?><th><?= $acaoRegistro === 'criou' ? 'Valor' : 'Depois' ?></th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($alteracoes as $mudanca): ?>
                                        <tr>
                                            <th scope="row"><?= e((string) ($mudanca['campo'] ?? '')) ?></th>
                                            <?php if ($acaoRegistro !== 'criou'): ?>
                                                <td class="historico-antes"><?= ($mudanca['antes'] ?? '') !== '' ? e((string) $mudanca['antes']) : '<em>vazio</em>' ?></td>
                                            <?php endif; ?>
                                            <?php if ($acaoRegistro !== 'excluiu'): ?>
                                                <td class="historico-depois"><?= ($mudanca['depois'] ?? '') !== '' ? e((string) $mudanca['depois']) : '<em>vazio</em>' ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($totalPaginas > 1): ?>
                <nav class="historico-paginacao" aria-label="Páginas do histórico">
                    <?php if ($paginaAtual > 1): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(urlHistorico($filtros, ['pagina' => $paginaAtual - 1])) ?>"><i class="fa-solid fa-chevron-left"></i> Mais novas</a>
                    <?php endif; ?>
                    <span>Página <?= e((string) $paginaAtual) ?> de <?= e((string) $totalPaginas) ?></span>
                    <?php if ($paginaAtual < $totalPaginas): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(urlHistorico($filtros, ['pagina' => $paginaAtual + 1])) ?>">Mais antigas <i class="fa-solid fa-chevron-right"></i></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </section>

    <?php renderizarControleSessao(); ?>
</body>
</html>
