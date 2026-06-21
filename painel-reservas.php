<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/config.php';
exigirLogin();

$nivel = nivelFuncionario();
$mensagemErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $pessoas = (int) ($_POST['pessoas'] ?? 0);
        $statusReserva = $_POST['status_reserva'] ?? 'Reservado';
        $confirmacao = $_POST['confirmacao'] ?? 'Pendente';
        $observacao = trim($_POST['observacao'] ?? '');

        if ($pessoas < 1) {
            $mensagemErro = 'Informe um número de pessoas válido.';
        } elseif (!in_array($statusReserva, ['Reservado', 'Cancelado'], true)) {
            $mensagemErro = 'Escolha um status de reserva válido.';
        } elseif (!in_array($confirmacao, ['Pendente', 'Confirmado'], true)) {
            $mensagemErro = 'Escolha uma confirmação válida.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO reservas (nome_cliente, telefone, data_pedido, data_reserva, hora_reserva, pessoas, valor, status_reserva, confirmacao, observacao, funcionario_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim($_POST['nome'] ?? ''),
                trim($_POST['telefone'] ?? ''),
                $_POST['data_pedido'] ?? null,
                $_POST['data'] ?? '',
                $_POST['hora'] ?? '',
                $pessoas,
                (float) ($_POST['valor'] ?? 0),
                $statusReserva,
                $confirmacao,
                $observacao !== '' ? $observacao : null,
                $_SESSION['funcionario_id'],
            ]);
            header('Location: painel-reservas.php');
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
        header('Location: painel-reservas.php');
        exit;
    }

    if ($acao === 'excluir' && $nivel >= NIVEL_GERENTE) {
        $stmt = $pdo->prepare('DELETE FROM reservas WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        header('Location: painel-reservas.php');
        exit;
    }
}

$diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

$reservas = $pdo->query(
    'SELECT r.id, r.nome_cliente, r.telefone, r.data_pedido, r.data_reserva, r.hora_reserva, r.pessoas,
            r.pessoas_compareceram, r.valor, r.status_reserva, r.confirmacao, r.observacao, f.nome AS criado_por
     FROM reservas r
     JOIN funcionarios f ON f.id = r.funcionario_id
     ORDER BY r.data_reserva, r.hora_reserva'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Reservas - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="style.css?v=20260621-3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-content">
                <div class="logo">
                    <a href="index.html">
                        <img src="Logo Branca carroça MC.png" alt="Churrascaria Pampulha" class="logo-img">
                    </a>
                </div>
                <ul class="funcionario-nav-links">
                    <li><a href="mesas.php">Mesas</a></li>
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
            <h2>Painel de Reservas</h2>
            <p class="section-subtitle">
                Olá, <?= e($_SESSION['funcionario_nome']) ?> (<?= e(nomeNivel($nivel)) ?>) — cadastre e acompanhe as reservas da casa
            </p>

            <div class="reserva-form-card">
                <form method="post" action="painel-reservas.php">
                    <input type="hidden" name="acao" value="criar">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <div class="reserva-form-grid">
                        <input type="text" name="nome" placeholder="Nome do cliente" required>
                        <input type="tel" name="telefone" placeholder="Telefone" required>
                        <label class="reserva-form-label">
                            Data do pedido
                            <input type="date" name="data_pedido" value="<?= e(date('Y-m-d')) ?>" required>
                        </label>
                        <label class="reserva-form-label">
                            Data da reserva
                            <input type="date" name="data" required>
                        </label>
                        <label class="reserva-form-label">
                            Horário
                            <input type="time" name="hora" required>
                        </label>
                        <input type="number" name="pessoas" placeholder="Nº de pessoas" min="1" required>
                        <input type="number" name="valor" placeholder="Valor (R$)" min="0" step="0.01" required>
                        <select name="status_reserva" required>
                            <option value="Reservado" selected>Reservado</option>
                            <option value="Cancelado">Cancelado</option>
                        </select>
                        <select name="confirmacao" required>
                            <option value="Pendente" selected>Confirmação pendente</option>
                            <option value="Confirmado">Confirmado</option>
                        </select>
                        <input type="text" name="observacao" placeholder="Observação (opcional)">
                    </div>
                    <?php if ($mensagemErro !== ''): ?>
                        <p class="login-erro"><?= e($mensagemErro) ?></p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">Adicionar Reserva</button>
                </form>
            </div>

            <div class="reservas-lista-wrapper">
                <table class="reservas-tabela">
                    <thead>
                        <tr>
                            <th>Cliente</th>
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
                            <?php if ($nivel >= NIVEL_GERENTE): ?>
                                <th></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservas as $reserva): ?>
                            <tr>
                                <td><?= e($reserva['nome_cliente']) ?></td>
                                <td><?= e($reserva['telefone']) ?></td>
                                <td><?= $reserva['data_pedido'] ? e(date('d/m/Y', strtotime($reserva['data_pedido']))) : '-' ?></td>
                                <td><?= e(date('d/m/Y', strtotime($reserva['data_reserva']))) ?></td>
                                <td><?= e($diasSemana[(int) date('w', strtotime($reserva['data_reserva']))]) ?></td>
                                <td><?= e(date('H:i', strtotime($reserva['hora_reserva']))) ?></td>
                                <td><?= e((string) $reserva['pessoas']) ?></td>
                                <td>R$ <?= e(number_format((float) $reserva['valor'], 2, ',', '.')) ?></td>
                                <td><?= e($reserva['status_reserva']) ?></td>
                                <td>
                                    <form method="post" action="painel-reservas.php" class="reserva-comparecimento-form">
                                        <input type="hidden" name="acao" value="atualizar_comparecimento">
                                        <input type="hidden" name="id" value="<?= e((string) $reserva['id']) ?>">
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
                                <?php if ($nivel >= NIVEL_GERENTE): ?>
                                    <td>
                                        <form method="post" action="painel-reservas.php" onsubmit="return confirm('Remover esta reserva?');">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?= e((string) $reserva['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <button type="submit" class="btn-remover-reserva" title="Remover reserva">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($reservas)): ?>
                    <p class="reservas-vazio" style="display: block;">Nenhuma reserva cadastrada ainda.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
