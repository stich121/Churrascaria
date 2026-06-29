<?php
require __DIR__ . '/auth.php';
// Não inclui config.php de propósito: esta página precisa funcionar mesmo
// quando o banco de dados está fora do ar, pois é aqui que se descobre por quê.
exigirNivel(NIVEL_SUPERIOR);

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
                <div class="panel-header-icon"><i class="fa-solid fa-file-lines"></i></div>
                <div>
                    <h2>Logs do Sistema</h2>
                    <p>Últimas <?= e((string) $totalEntradas) ?> entradas do canal "<?= e($canalAtual) ?>" — dados sensíveis (senhas, tokens, telefones) já vêm mascarados</p>
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
                            <th>Mensagem</th>
                            <th>Contexto</th>
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
