<?php
// Menu único da área do funcionário. Inclua depois de exigirLogin()/exigirNivel(),
// no lugar do <nav>, com: include __DIR__ . '/menu-funcionario.php';
// A página pode definir $churrascariaTela antes do include quando mostra uma escolha
// própria (ex.: "Todos" em clientes.php para quem não é nível superior) e
// $dataNovaReserva (Y-m-d) para o "+ Nova reserva" já abrir nessa data.

if (!function_exists('nivelFuncionario')) {
    http_response_code(404);
    exit;
}

$churrascariaMenu = $churrascariaTela ?? churrascariaSelecionada();
$opcoesChurrascariaMenu = opcoesChurrascariaNavegacao();
$paginaAtualMenu = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nomeChurrascariaMenu = $churrascariaMenu === 'casarao-itau' ? 'Casarão Itau' : CHURRASCARIA_PADRAO;

// Itens soltos no menu; o resto fica nos grupos "Configurações" e do usuário.
$itensMenu = [
    ['dashboard.php', 'Dashboard', 'fa-gauge-high'],
    ['painel-reservas.php', 'Reservas', 'fa-calendar-check'],
    ['clientes.php', 'Clientes', 'fa-address-book'],
];

$itensConfiguracoesMenu = array_values(array_filter([
    ['mesas.php', 'Mesas', 'fa-chair', true],
    ['tipos-reserva.php', 'Tipos de Reserva', 'fa-tags', true],
    ['funcionarios.php', 'Funcionários', 'fa-users', nivelFuncionario() >= NIVEL_GERENTE],
    ['historico.php', 'Histórico de alterações', 'fa-clock-rotate-left', nivelFuncionario() >= NIVEL_GERENTE],
    ['logs.php', 'Logs', 'fa-file-lines', ehDesenvolvedorAutorizado()],
], function ($item) {
    return $item[3];
}));

$itensUsuarioMenu = [
    ['trocar-senha.php', 'Trocar senha', 'fa-key'],
];

$grupoAtualMenu = function (array $itens) use ($paginaAtualMenu): bool {
    return in_array($paginaAtualMenu, array_column($itens, 0), true);
};

// "+ Nova reserva": na própria tela de reservas só rola até o formulário (mantém os filtros);
// nas outras abre o painel, levando a data da tela quando a página define $dataNovaReserva.
if ($paginaAtualMenu === 'painel-reservas.php') {
    $urlNovaReservaMenu = '#nova-reserva';
} else {
    $urlNovaReservaMenu = 'painel-reservas.php' . (!empty($dataNovaReserva) ? '?' . http_build_query(['data' => $dataNovaReserva]) : '') . '#nova-reserva';
}

$primeiroNomeMenu = trim((string) ($_SESSION['funcionario_nome'] ?? ''));
$primeiroNomeMenu = $primeiroNomeMenu !== '' ? explode(' ', $primeiroNomeMenu)[0] : 'Minha conta';

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
                    <?php foreach ($itensMenu as [$arquivoMenu, $rotuloItemMenu, $iconeMenu]): ?>
                        <li><a href="<?= e($arquivoMenu) ?>"<?= $paginaAtualMenu === $arquivoMenu ? ' class="is-atual" aria-current="page"' : '' ?>><i class="fa-solid <?= e($iconeMenu) ?> menu-icone"></i><?= e($rotuloItemMenu) ?></a></li>
                    <?php endforeach; ?>
                    <li class="menu-item-nova-reserva"><a href="<?= e($urlNovaReservaMenu) ?>" class="btn-nova-reserva-menu"><i class="fa-solid fa-plus"></i> Nova reserva</a></li>
                    <?php foreach ([
                        ['menu-configuracoes', 'fa-gear', 'Configurações', $itensConfiguracoesMenu, false],
                        ['menu-usuario', 'fa-circle-user', $primeiroNomeMenu, $itensUsuarioMenu, true],
                    ] as [$idGrupoMenu, $iconeGrupoMenu, $rotuloGrupoMenu, $itensGrupoMenu, $grupoComSair]): ?>
                        <li class="menu-grupo<?= $grupoComSair ? ' menu-grupo--usuario' : '' ?>">
                            <button type="button" class="menu-grupo-botao<?= $grupoAtualMenu($itensGrupoMenu) ? ' is-atual' : '' ?>" aria-expanded="false" aria-controls="<?= e($idGrupoMenu) ?>">
                                <i class="fa-solid <?= e($iconeGrupoMenu) ?> menu-icone"></i><?= e($rotuloGrupoMenu) ?> <i class="fa-solid fa-chevron-down menu-grupo-seta"></i>
                            </button>
                            <ul class="menu-submenu" id="<?= e($idGrupoMenu) ?>">
                                <?php foreach ($itensGrupoMenu as $itemGrupoMenu): ?>
                                    <li><a href="<?= e($itemGrupoMenu[0]) ?>"<?= $paginaAtualMenu === $itemGrupoMenu[0] ? ' class="is-atual" aria-current="page"' : '' ?>><i class="fa-solid <?= e($itemGrupoMenu[2]) ?> menu-icone"></i><?= e($itemGrupoMenu[1]) ?></a></li>
                                <?php endforeach; ?>
                                <?php if ($grupoComSair): ?>
                                    <li><a href="logout.php" class="menu-sair"><i class="fa-solid fa-right-from-bracket menu-icone"></i>Sair</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>
    <!-- Botão flutuante do celular (o CSS só mostra em telas de até 768px) -->
    <a href="<?= e($urlNovaReservaMenu) ?>" class="fab-nova-reserva<?= $churrascariaMenu === 'casarao-itau' ? ' fab-nova-reserva--casarao' : '' ?>" aria-label="Nova reserva" title="Nova reserva"><i class="fa-solid fa-plus"></i></a>
