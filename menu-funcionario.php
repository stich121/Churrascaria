<?php
// Menu único da área do funcionário. Inclua depois de exigirLogin()/exigirNivel(),
// no lugar do <nav>, com: include __DIR__ . '/menu-funcionario.php';
// A página pode definir $churrascariaTela antes do include quando mostra uma escolha
// própria (ex.: "Todos" em clientes.php para quem não é nível superior).

if (!function_exists('nivelFuncionario')) {
    http_response_code(404);
    exit;
}

$churrascariaMenu = $churrascariaTela ?? churrascariaSelecionada();
$opcoesChurrascariaMenu = opcoesChurrascariaNavegacao();
$paginaAtualMenu = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nomeChurrascariaMenu = $churrascariaMenu === 'casarao-itau' ? 'Casarão Itau' : CHURRASCARIA_PADRAO;

$itensMenu = [
    ['dashboard.php', 'Dashboard', true],
    ['painel-reservas.php', 'Painel de Reservas', true],
    ['mesas.php', 'Mesas', true],
    ['tipos-reserva.php', 'Tipos de Reserva', true],
    ['clientes.php', 'Clientes', true],
    ['funcionarios.php', 'Funcionários', nivelFuncionario() >= NIVEL_GERENTE],
    ['logs.php', 'Logs', ehDesenvolvedorAutorizado()],
    ['trocar-senha.php', 'Trocar senha', true],
];

$rotulosCurtosMenu = [
    'pampulha' => 'Pampulha',
    'casarao-itau' => 'Casarão Itaú',
    'todas' => 'Todos',
];
?>
    <nav class="navbar navbar-funcionario<?= $churrascariaMenu === 'casarao-itau' ? ' navbar--casarao' : '' ?>">
        <div class="container">
            <div class="navbar-content">
                <div class="logo">
                    <a href="dashboard.php" title="Ir para o Dashboard">
                        <img src="<?= e(logoChurrascaria($nomeChurrascariaMenu)) ?>" alt="<?= e($nomeChurrascariaMenu) ?>" class="logo-img">
                    </a>
                </div>
                <div class="menu-unidade" role="group" aria-label="Churrascaria">
                    <?php foreach ($opcoesChurrascariaMenu as $chaveMenu => $rotuloMenu): ?>
                        <a href="<?= e(urlTrocarChurrascaria($chaveMenu)) ?>"
                           class="menu-unidade-opcao<?= $churrascariaMenu === $chaveMenu ? ' is-active' : '' ?>"
                           <?= $churrascariaMenu === $chaveMenu ? 'aria-current="true"' : '' ?>><?= e($rotulosCurtosMenu[$chaveMenu] ?? $rotuloMenu) ?></a>
                    <?php endforeach; ?>
                </div>
                <button class="nav-hamburger" aria-label="Menu" aria-expanded="false"><span></span><span></span><span></span></button>
                <ul class="funcionario-nav-links">
                    <?php foreach ($itensMenu as [$arquivoMenu, $rotuloItemMenu, $visivelMenu]): ?>
                        <?php if ($visivelMenu): ?>
                            <li><a href="<?= e($arquivoMenu) ?>"<?= $paginaAtualMenu === $arquivoMenu ? ' class="is-atual" aria-current="page"' : '' ?>><?= e($rotuloItemMenu) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li><a href="logout.php" class="btn-voltar-site"><i class="fa-solid fa-right-from-bracket"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
