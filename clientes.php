<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();
garantirTabelaClientes($pdo);
garantirColunaChurrascariaClientes($pdo);

$nivel = nivelFuncionario();
$mensagemErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar_cliente') {
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $churrascariaCliente = trim($_POST['churrascaria'] ?? CHURRASCARIA_PADRAO);
        $dataNascimento = trim($_POST['data_nascimento'] ?? '');

        if ($nome === '' || $telefone === '') {
            $mensagemErro = 'Informe nome e telefone do cliente.';
        } elseif (!churrascariaReservaValida($churrascariaCliente)) {
            $mensagemErro = 'Escolha uma churrascaria válida.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM clientes WHERE telefone = ?');
            $stmt->execute([$telefone]);

            if ($stmt->fetch()) {
                $mensagemErro = 'Já existe um cliente cadastrado com esse telefone.';
            } else {
                $pdo->prepare('INSERT INTO clientes (nome, telefone, churrascaria, data_nascimento) VALUES (?, ?, ?, ?)')
                    ->execute([$nome, $telefone, $churrascariaCliente, $dataNascimento !== '' ? $dataNascimento : null]);
                Logger::audit('cliente_criado', ['cliente_id' => (int) $pdo->lastInsertId()]);
                header('Location: clientes.php');
                exit;
            }
        }
    }

    if ($acao === 'editar_cliente') {
        $idCliente = (int) ($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $churrascariaCliente = trim($_POST['churrascaria'] ?? CHURRASCARIA_PADRAO);
        $dataNascimento = trim($_POST['data_nascimento'] ?? '');

        if ($idCliente < 1 || $nome === '' || $telefone === '') {
            $mensagemErro = 'Informe nome e telefone do cliente.';
        } elseif (!churrascariaReservaValida($churrascariaCliente)) {
            $mensagemErro = 'Escolha uma churrascaria válida.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM clientes WHERE telefone = ? AND id <> ?');
            $stmt->execute([$telefone, $idCliente]);

            if ($stmt->fetch()) {
                $mensagemErro = 'Já existe um cliente cadastrado com esse telefone.';
            } else {
                $pdo->prepare('UPDATE clientes SET nome = ?, telefone = ?, churrascaria = ?, data_nascimento = ? WHERE id = ?')
                    ->execute([$nome, $telefone, $churrascariaCliente, $dataNascimento !== '' ? $dataNascimento : null, $idCliente]);
                Logger::audit('cliente_editado', ['cliente_id' => $idCliente]);
                header('Location: clientes.php');
                exit;
            }
        }
    }

    if ($acao === 'excluir_cliente' && $nivel >= NIVEL_GERENTE) {
        $idClienteExcluir = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = ?');
        $stmt->execute([$idClienteExcluir]);
        Logger::audit('cliente_excluido', ['cliente_id' => $idClienteExcluir]);
        header('Location: clientes.php');
        exit;
    }
}

$opcoesChurrascariaClientes = [
    'pampulha' => CHURRASCARIA_PADRAO,
    'casarao-itau' => 'Casarão Itau',
    'todas' => 'Todos',
];

$churrascariaFiltro = $_GET['churrascaria'] ?? 'todas';
if (!array_key_exists($churrascariaFiltro, $opcoesChurrascariaClientes)) {
    $churrascariaFiltro = 'todas';
}

$buscaCliente = trim($_GET['busca'] ?? '');
$condicoesClientes = [];
$parametrosClientes = [];

if ($churrascariaFiltro !== 'todas') {
    $condicoesClientes[] = 'churrascaria = ?';
    $parametrosClientes[] = $opcoesChurrascariaClientes[$churrascariaFiltro];
}

if ($buscaCliente !== '') {
    $condicoesClientes[] = '(nome LIKE ? OR telefone LIKE ?)';
    $parametrosClientes[] = '%' . $buscaCliente . '%';
    $parametrosClientes[] = '%' . $buscaCliente . '%';
}

$sqlClientes = 'SELECT id, nome, telefone, churrascaria, data_nascimento FROM clientes';
if (!empty($condicoesClientes)) {
    $sqlClientes .= ' WHERE ' . implode(' AND ', $condicoesClientes);
}
$sqlClientes .= ' ORDER BY nome';

$stmt = $pdo->prepare($sqlClientes);
$stmt->execute($parametrosClientes);
$clientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css?v=20260630-1">
    <?php include __DIR__ . '/pwa-head.php'; ?>
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
                <button class="nav-hamburger" aria-label="Menu" aria-expanded="false"><span></span><span></span><span></span></button>
                <ul class="funcionario-nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="painel-reservas.php">Painel de Reservas</a></li>
                    <li><a href="mesas.php">Mesas</a></li>
                    <li><a href="tipos-reserva.php">Tipos de Reserva</a></li>
                    <li><a href="clientes.php">Clientes</a></li>
                    <?php if ($nivel >= NIVEL_GERENTE): ?>
                        <li><a href="funcionarios.php">Funcionários</a></li>
                    <?php endif; ?>
                    <?php if (ehDesenvolvedorAutorizado()): ?>
                        <li><a href="logs.php">Logs</a></li>
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
                <div class="panel-header-icon"><i class="fa-solid fa-address-book"></i></div>
                <div>
                    <h2>Clientes</h2>
                    <p>Cadastro de clientes com nome, telefone e data de nascimento</p>
                </div>
            </div>

            <div class="dashboard-tabs dashboard-unit-tabs" aria-label="Selecionar churrascaria">
                <?php foreach ($opcoesChurrascariaClientes as $chaveOpcao => $rotuloOpcao): ?>
                    <a
                        href="clientes.php?<?= e(http_build_query(['churrascaria' => $chaveOpcao, 'busca' => $buscaCliente])) ?>"
                        class="dashboard-tab <?= $churrascariaFiltro === $chaveOpcao ? 'is-active' : '' ?>"
                        <?= $churrascariaFiltro === $chaveOpcao ? 'aria-current="page"' : '' ?>>
                        <?= e($rotuloOpcao) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="reserva-form-card">
                <div class="card-header-bar">
                    <i class="fa-solid fa-circle-plus"></i>
                    <h3>Cadastrar Cliente</h3>
                </div>
                <form method="post" action="clientes.php">
                    <input type="hidden" name="acao" value="criar_cliente">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="reserva-form-grid">
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-user"></i>Nome do cliente</span>
                            <input type="text" name="nome" placeholder="Nome do cliente" maxlength="100" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-phone"></i>Telefone</span>
                            <input type="tel" name="telefone" inputmode="numeric" placeholder="(00) 00000-0000" maxlength="15" required>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-store"></i>Churrascaria</span>
                            <select name="churrascaria" required>
                                <?php foreach (CHURRASCARIAS_RESERVA as $churrascariaOpcao): ?>
                                    <option value="<?= e($churrascariaOpcao) ?>" <?= $churrascariaOpcao === CHURRASCARIA_PADRAO ? 'selected' : '' ?>><?= e($churrascariaOpcao) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="reserva-form-label">
                            <span><i class="fa-solid fa-cake-candles"></i>Data de nascimento</span>
                            <input type="date" name="data_nascimento">
                        </label>
                    </div>
                    <?php if ($mensagemErro !== ''): ?>
                        <p class="login-erro"><?= e($mensagemErro) ?></p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i>Adicionar Cliente</button>
                </form>
            </div>

            <div class="reservas-lista-toolbar">
                <div>
                    <h3>Clientes cadastrados</h3>
                    <p><?= e((string) count($clientes)) ?> clientes</p>
                </div>
                <div class="reservas-lista-controles">
                    <form method="get" action="clientes.php" class="reservas-ordenacao-form">
                        <input type="hidden" name="churrascaria" value="<?= e($churrascariaFiltro) ?>">
                        <label for="busca_clientes"><i class="fa-solid fa-magnifying-glass"></i></label>
                        <input type="search" id="busca_clientes" name="busca" value="<?= e($buscaCliente) ?>" placeholder="Pesquisar nome ou telefone">
                        <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-magnifying-glass"></i>Buscar</button>
                        <?php if ($buscaCliente !== ''): ?>
                            <a href="clientes.php?churrascaria=<?= e($churrascariaFiltro) ?>" class="btn btn-outline btn-sm">Limpar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="reservas-lista-wrapper">
                <table class="reservas-tabela">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Churrascaria</th>
                            <th>Nascimento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?= e($cliente['nome']) ?></td>
                                <td><?= e($cliente['telefone']) ?></td>
                                <td><span class="badge badge-info"><i class="fa-solid fa-location-dot"></i><?= e($cliente['churrascaria'] ?? CHURRASCARIA_PADRAO) ?></span></td>
                                <td><?= $cliente['data_nascimento'] ? e(date('d/m/Y', strtotime($cliente['data_nascimento']))) : '-' ?></td>
                                <td class="reserva-acoes-col">
                                    <button
                                        type="button"
                                        class="btn-editar-reserva"
                                        title="Editar cliente"
                                        onclick="abrirEdicaoCliente(<?= e((string) $cliente['id']) ?>, '<?= e(addslashes($cliente['nome'])) ?>', '<?= e(addslashes($cliente['telefone'])) ?>', '<?= e(addslashes($cliente['churrascaria'] ?? CHURRASCARIA_PADRAO)) ?>', '<?= e($cliente['data_nascimento'] ?? '') ?>')">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <?php if ($nivel >= NIVEL_GERENTE): ?>
                                        <form method="post" action="clientes.php" onsubmit="return confirm('Remover o cliente &quot;<?= e($cliente['nome']) ?>&quot;?');">
                                            <input type="hidden" name="acao" value="excluir_cliente">
                                            <input type="hidden" name="id" value="<?= e((string) $cliente['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <button type="submit" class="btn-remover-reserva" title="Remover cliente">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($clientes)): ?>
                    <p class="reservas-vazio" style="display: block;">Nenhum cliente cadastrado ainda.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div id="modalEditarCliente" class="modal-overlay" onclick="if (event.target === this) fecharEdicaoCliente();">
        <div class="modal-card">
            <div class="card-header-bar">
                <i class="fa-solid fa-pen"></i>
                <h3>Editar Cliente</h3>
                <button type="button" class="modal-close" onclick="fecharEdicaoCliente()" title="Fechar"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="post" action="clientes.php">
                <input type="hidden" name="acao" value="editar_cliente">
                <input type="hidden" name="id" id="editar_cliente_id" value="">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="reserva-form-grid">
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-user"></i>Nome do cliente</span>
                        <input type="text" name="nome" id="editar_cliente_nome" maxlength="100" required>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-phone"></i>Telefone</span>
                        <input type="tel" name="telefone" id="editar_cliente_telefone" inputmode="numeric" placeholder="(00) 00000-0000" maxlength="15" required>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-store"></i>Churrascaria</span>
                        <select name="churrascaria" id="editar_cliente_churrascaria" required>
                            <?php foreach (CHURRASCARIAS_RESERVA as $churrascariaOpcao): ?>
                                <option value="<?= e($churrascariaOpcao) ?>"><?= e($churrascariaOpcao) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-cake-candles"></i>Data de nascimento</span>
                        <input type="date" name="data_nascimento" id="editar_cliente_data_nascimento">
                    </label>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="fecharEdicaoCliente()">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Salvar alterações</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderizarControleSessao(); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
        });

        function abrirEdicaoCliente(id, nome, telefone, churrascaria, dataNascimento) {
            document.getElementById('editar_cliente_id').value = id;
            document.getElementById('editar_cliente_nome').value = nome;
            document.getElementById('editar_cliente_telefone').value = telefone;
            document.getElementById('editar_cliente_churrascaria').value = churrascaria;
            document.getElementById('editar_cliente_data_nascimento').value = dataNascimento || '';
            document.getElementById('modalEditarCliente').classList.add('aberto');
        }

        function fecharEdicaoCliente() {
            document.getElementById('modalEditarCliente').classList.remove('aberto');
        }
    </script>
</body>
</html>
