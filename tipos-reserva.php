<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();
garantirTabelaTiposReserva($pdo);

$nivel = nivelFuncionario();
$mensagemErroTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar_tipo_reserva') {
        $nomeTipo = trim($_POST['nome_tipo'] ?? '');

        if ($nomeTipo === '') {
            $mensagemErroTipo = 'Informe um nome para o tipo de reserva.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM tipos_reserva WHERE nome = ?');
            $stmt->execute([$nomeTipo]);

            if ($stmt->fetch()) {
                $mensagemErroTipo = 'Esse tipo de reserva já existe.';
            } else {
                $pdo->prepare('INSERT INTO tipos_reserva (nome, criado_por) VALUES (?, ?)')
                    ->execute([$nomeTipo, $_SESSION['funcionario_id']]);
                Logger::audit('tipo_reserva_criado', ['tipo_id' => (int) $pdo->lastInsertId(), 'nome' => $nomeTipo]);
                header('Location: tipos-reserva.php');
                exit;
            }
        }
    }

    if ($acao === 'excluir_tipo_reserva' && $nivel >= NIVEL_GERENTE) {
        $idTipoExcluir = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM tipos_reserva WHERE id = ?');
        $stmt->execute([$idTipoExcluir]);
        Logger::audit('tipo_reserva_excluido', ['tipo_id' => $idTipoExcluir]);
        header('Location: tipos-reserva.php');
        exit;
    }
}

$tiposReserva = $pdo->query(
    'SELECT t.id, t.nome, t.criado_em, f.nome AS criado_por
     FROM tipos_reserva t
     LEFT JOIN funcionarios f ON f.id = t.criado_por
     ORDER BY t.nome'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Reserva - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css?v=20260626-2">
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
                <div class="panel-header-icon"><i class="fa-solid fa-cake-candles"></i></div>
                <div>
                    <h2>Tipos de Reserva</h2>
                    <p>Olá, <?= e($_SESSION['funcionario_nome']) ?> (<?= e(nomeNivel($nivel)) ?>) — cadastre os tipos de reserva usados no painel (aniversário, casamento, etc.)</p>
                </div>
            </div>

            <div class="reserva-form-card">
                <div class="card-header-bar">
                    <i class="fa-solid fa-circle-plus"></i>
                    <h3>Novo Tipo de Reserva</h3>
                </div>
                <form method="post" action="tipos-reserva.php" class="tipo-reserva-form">
                    <input type="hidden" name="acao" value="criar_tipo_reserva">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <label class="reserva-form-label">
                        <span><i class="fa-solid fa-tag"></i>Nome do tipo</span>
                        <input type="text" name="nome_tipo" placeholder="Ex: Aniversário, Casamento..." maxlength="60" required>
                    </label>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i>Adicionar tipo</button>
                </form>
                <?php if ($mensagemErroTipo !== ''): ?>
                    <p class="login-erro"><?= e($mensagemErroTipo) ?></p>
                <?php endif; ?>
            </div>

            <div class="reservas-lista-wrapper">
                <table class="reservas-tabela">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Criado por</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tiposReserva as $tipo): ?>
                            <tr>
                                <td><span class="badge badge-info"><i class="fa-solid fa-tag"></i><?= e($tipo['nome']) ?></span></td>
                                <td><?= $tipo['criado_por'] ? e($tipo['criado_por']) : '-' ?></td>
                                <td><?= e(date('d/m/Y H:i', strtotime($tipo['criado_em']))) ?></td>
                                <td>
                                    <?php if ($nivel >= NIVEL_GERENTE): ?>
                                        <form method="post" action="tipos-reserva.php" onsubmit="return confirm('Remover o tipo de reserva &quot;<?= e($tipo['nome']) ?>&quot;?');">
                                            <input type="hidden" name="acao" value="excluir_tipo_reserva">
                                            <input type="hidden" name="id" value="<?= e((string) $tipo['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <button type="submit" class="btn-remover-reserva" title="Remover tipo">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($tiposReserva)): ?>
                    <p class="reservas-vazio" style="display: block;">Nenhum tipo de reserva cadastrado ainda.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php renderizarControleSessao(); ?>
</body>
</html>
