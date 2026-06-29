<?php
require __DIR__ . '/auth.php';
// Não inclui config.php de propósito: esta página precisa funcionar mesmo
// quando o banco de dados está fora do ar, pois é aqui que se descobre por quê.
exigirDesenvolvedor();

$nivel = nivelFuncionario();

$canalSolicitado = $_GET['canal'] ?? '';
$canaisValidos = ['audit', 'error', 'app'];
$canalAtual = in_array($canalSolicitado, $canaisValidos, true) ? $canalSolicitado : 'audit';

$nivelSolicitado = $_GET['nivel'] ?? '';
$niveisValidos = ['', 'DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'];
$nivelFiltro = in_array($nivelSolicitado, $niveisValidos, true) ? $nivelSolicitado : '';

$busca = trim($_GET['busca'] ?? '');

function tailJsonLines(string $caminho, int $maxLinhas): array
{
    if (!file_exists($caminho)) {
        return [];
    }

    $handle = fopen($caminho, 'r');
    if (!$handle) {
        return [];
    }

    $tamanho = filesize($caminho);
    $bloco = 8192;
    $posicao = $tamanho;
    $dados = '';
    $linhas = [];

    while ($posicao > 0 && count($linhas) <= $maxLinhas) {
        $leitura = min($bloco, $posicao);
        $posicao -= $leitura;
        fseek($handle, $posicao);
        $dados = fread($handle, $leitura) . $dados;
        $linhas = explode("\n", $dados);
    }

    fclose($handle);

    $linhas = array_values(array_filter($linhas, fn ($l) => trim($l) !== ''));
    $linhas = array_slice($linhas, -$maxLinhas);
    $linhas = array_reverse($linhas);

    $resultado = [];
    foreach ($linhas as $linha) {
        $decodificada = json_decode($linha, true);
        if (is_array($decodificada)) {
            $resultado[] = $decodificada;
        }
    }

    return $resultado;
}

function badgeNivel(string $nivelLog): string
{
    $classe = match ($nivelLog) {
        'WARN' => 'badge-warning',
        'ERROR', 'FATAL' => 'badge-danger',
        default => 'badge-info',
    };

    return '<span class="badge ' . $classe . '">' . e($nivelLog) . '</span>';
}

$arquivoLog = __DIR__ . '/logs/' . $canalAtual . '.log';
$entradas = tailJsonLines($arquivoLog, 500);

$statsPorNivel = ['DEBUG' => 0, 'INFO' => 0, 'WARN' => 0, 'ERROR' => 0, 'FATAL' => 0];
foreach ($entradas as $entradaStat) {
    $nivelStat = $entradaStat['level'] ?? '';
    if (isset($statsPorNivel[$nivelStat])) {
        $statsPorNivel[$nivelStat]++;
    }
}

if ($nivelFiltro !== '') {
    $entradas = array_filter($entradas, fn ($entrada) => ($entrada['level'] ?? '') === $nivelFiltro);
}

if ($busca !== '') {
    $buscaLower = mb_strtolower($busca);
    $entradas = array_filter($entradas, function ($entrada) use ($buscaLower) {
        $alvo = mb_strtolower(($entrada['message'] ?? '') . ' ' . ($entrada['action'] ?? '') . ' ' . json_encode($entrada['context'] ?? []));

        return mb_strpos($alvo, $buscaLower) !== false;
    });
}

$totalEntradas = count($entradas);

function tamanhoLegivel(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / (1024 * 1024), 2) . ' MB';
}

$diretorioLogs = __DIR__ . '/logs';
$infoCanais = [];
foreach ($canaisValidos as $canalInfo) {
    $caminhoInfo = $diretorioLogs . '/' . $canalInfo . '.log';
    $infoCanais[$canalInfo] = [
        'existe' => file_exists($caminhoInfo),
        'tamanho' => file_exists($caminhoInfo) ? tamanhoLegivel((int) filesize($caminhoInfo)) : '-',
        'modificado' => file_exists($caminhoInfo) ? date('d/m/Y H:i:s', (int) filemtime($caminhoInfo)) : '-',
    ];
}

$infoSistema = [
    'PHP' => PHP_VERSION,
    'SO' => PHP_OS_FAMILY,
    'Memória em uso' => tamanhoLegivel((int) memory_get_usage(true)),
    'Pico de memória' => tamanhoLegivel((int) memory_get_peak_usage(true)),
    'Espaço livre em disco' => function_exists('disk_free_space') && disk_free_space($diretorioLogs) !== false
        ? tamanhoLegivel((int) disk_free_space($diretorioLogs))
        : '-',
    'Hora do servidor' => date('d/m/Y H:i:s'),
    'display_errors' => ini_get('display_errors') ? 'ativo' : 'inativo',
    'error_reporting' => (string) error_reporting(),
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Sistema - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css?v=20260629-1">
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
                    <li><a href="funcionarios.php">Funcionários</a></li>
                    <li><a href="logs.php">Logs</a></li>
                    <li><a href="trocar-senha.php">Trocar senha</a></li>
                    <li><a href="logout.php" class="btn-voltar-site"><i class="fa-solid fa-right-from-bracket"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="painel-reservas">
        <div class="container">
            <div class="panel-header">
                <div class="panel-header-icon"><i class="fa-solid fa-code"></i></div>
                <div>
                    <h2>Logs do Sistema <span class="badge badge-info">Área de Desenvolvedor</span></h2>
                    <p>Últimas <?= e((string) $totalEntradas) ?> entradas do canal "<?= e($canalAtual) ?>" — dados sensíveis (senhas, tokens, telefones) já vêm mascarados na origem</p>
                </div>
            </div>

            <div class="stat-cards-row">
                <div class="stat">
                    <i class="fa-solid fa-circle-info"></i>
                    <h4><?= e($statsPorNivel['INFO']) ?></h4>
                    <p>INFO</p>
                </div>
                <div class="stat">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <h4><?= e($statsPorNivel['WARN']) ?></h4>
                    <p>WARN</p>
                </div>
                <div class="stat">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <h4><?= e($statsPorNivel['ERROR']) ?></h4>
                    <p>ERROR</p>
                </div>
                <div class="stat">
                    <i class="fa-solid fa-skull-crossbones"></i>
                    <h4><?= e($statsPorNivel['FATAL']) ?></h4>
                    <p>FATAL</p>
                </div>
            </div>

            <div class="reserva-form-card">
                <div class="card-header-bar">
                    <i class="fa-solid fa-server"></i>
                    <h3>Informações do sistema</h3>
                </div>
                <div class="reserva-form-grid">
                    <?php foreach ($infoSistema as $rotuloInfo => $valorInfo): ?>
                        <label class="reserva-form-label">
                            <span><?= e($rotuloInfo) ?></span>
                            <strong><?= e((string) $valorInfo) ?></strong>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="reserva-form-grid" style="margin-top: 1rem;">
                    <?php foreach ($infoCanais as $canalInfoNome => $dadosCanal): ?>
                        <label class="reserva-form-label">
                            <span><?= e($canalInfoNome) ?>.log</span>
                            <strong><?= $dadosCanal['existe'] ? e($dadosCanal['tamanho']) . ' · atualizado em ' . e($dadosCanal['modificado']) : 'não existe ainda' ?></strong>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="dashboard-tabs" aria-label="Selecionar canal de log">
                <?php foreach ($canaisValidos as $canalOpcao): ?>
                    <a
                        href="logs.php?<?= e(http_build_query(['canal' => $canalOpcao, 'nivel' => $nivelFiltro, 'busca' => $busca])) ?>"
                        class="dashboard-tab <?= $canalAtual === $canalOpcao ? 'is-active' : '' ?>"
                        <?= $canalAtual === $canalOpcao ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= $canalOpcao === 'audit' ? 'fa-user-shield' : ($canalOpcao === 'error' ? 'fa-triangle-exclamation' : 'fa-list') ?>"></i>
                        <?= $canalOpcao === 'audit' ? 'Auditoria' : ($canalOpcao === 'error' ? 'Erros' : 'Aplicação') ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="reservas-lista-toolbar">
                <div>
                    <h3>Filtrar</h3>
                </div>
                <div class="reservas-lista-controles">
                    <form method="get" action="logs.php" class="reservas-ordenacao-form">
                        <input type="hidden" name="canal" value="<?= e($canalAtual) ?>">
                        <label for="nivel_filtro"><i class="fa-solid fa-filter"></i>Nível</label>
                        <select name="nivel" id="nivel_filtro" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php foreach (['DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL'] as $opcaoNivel): ?>
                                <option value="<?= e($opcaoNivel) ?>" <?= $nivelFiltro === $opcaoNivel ? 'selected' : '' ?>><?= e($opcaoNivel) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="reservas-busca">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" name="busca" value="<?= e($busca) ?>" placeholder="Buscar mensagem, ação ou contexto" autocomplete="off">
                        </label>
                        <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-magnifying-glass"></i>Buscar</button>
                        <?php if ($nivelFiltro !== '' || $busca !== ''): ?>
                            <a href="logs.php?canal=<?= e($canalAtual) ?>" class="btn btn-outline btn-sm">Limpar</a>
                        <?php endif; ?>
                        <a href="logs.php?<?= e(http_build_query(['canal' => $canalAtual, 'nivel' => $nivelFiltro, 'busca' => $busca])) ?>" class="btn btn-outline btn-sm" title="Atualizar"><i class="fa-solid fa-rotate"></i>Atualizar</a>
                    </form>
                </div>
            </div>

            <div class="reservas-lista-wrapper">
                <table class="reservas-tabela">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Nível</th>
                            <th>Ação</th>
                            <th>Usuário</th>
                            <th>IP</th>
                            <th>Request ID</th>
                            <th>URI</th>
                            <th>Mensagem</th>
                            <th>Contexto / Trace</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entradas as $entrada): ?>
                            <tr>
                                <td><?= e((string) ($entrada['timestamp'] ?? '-')) ?></td>
                                <td><?= badgeNivel((string) ($entrada['level'] ?? '')) ?></td>
                                <td><?= $entrada['action'] ? '<span class="badge badge-info"><i class="fa-solid fa-tag"></i>' . e((string) $entrada['action']) . '</span>' : '-' ?></td>
                                <td><?= e((string) ($entrada['user_id'] ?? '-')) ?></td>
                                <td><?= e((string) ($entrada['ip'] ?? '-')) ?></td>
                                <td><code style="font-size: 0.8em;"><?= e((string) ($entrada['request_id'] ?? '-')) ?></code></td>
                                <td style="max-width: 220px; word-break: break-all;"><?= e((string) ($entrada['uri'] ?? '-')) ?></td>
                                <td><?= e((string) ($entrada['message'] ?? '-')) ?></td>
                                <td>
                                    <?php if (!empty($entrada['context'])): ?>
                                        <details>
                                            <summary>ver</summary>
                                            <pre style="white-space: pre-wrap; word-break: break-word; margin: 0.5rem 0 0; font-size: 0.85em;"><?= e((string) json_encode($entrada['context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
                                        </details>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($entradas)): ?>
                    <p class="reservas-vazio" style="display: block;">Nenhuma entrada encontrada neste canal.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php renderizarControleSessao(); ?>
</body>
</html>
