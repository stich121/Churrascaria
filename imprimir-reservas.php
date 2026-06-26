<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/../config.php';
exigirLogin();
garantirColunaChurrascaria($pdo);
garantirColunaTipoReserva($pdo);

$diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

$dataSelecionada = $_GET['data'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataSelecionada) || strtotime($dataSelecionada) === false) {
    $dataSelecionada = date('Y-m-d');
}

$opcoesChurrascaria = [
    'pampulha' => CHURRASCARIA_PADRAO,
    'casarao-itau' => 'Casarão Itau',
];

if (nivelFuncionario() >= NIVEL_SUPERIOR) {
    $opcoesChurrascaria['todas'] = 'Todos';
}

$churrascariaSelecionada = $_GET['churrascaria'] ?? 'pampulha';
if (!array_key_exists($churrascariaSelecionada, $opcoesChurrascaria)) {
    $churrascariaSelecionada = 'pampulha';
}

$rotuloChurrascaria = $opcoesChurrascaria[$churrascariaSelecionada];
$filtroChurrascariaSql = '';
$parametrosConsulta = [$dataSelecionada];

if ($churrascariaSelecionada !== 'todas') {
    $filtroChurrascariaSql = ' AND r.churrascaria = ?';
    $parametrosConsulta[] = $rotuloChurrascaria;
}

$stmt = $pdo->prepare(
    'SELECT r.id, r.nome_cliente, r.telefone, r.churrascaria, r.tipo_reserva, r.data_reserva, r.hora_reserva, r.pessoas,
            r.pessoas_compareceram, r.valor, r.status_reserva, r.confirmacao, r.observacao, f.nome AS atendente
     FROM reservas r
     LEFT JOIN funcionarios f ON f.id = r.funcionario_id
     WHERE r.data_reserva = ?' . $filtroChurrascariaSql . '
     ORDER BY r.hora_reserva'
);
$stmt->execute($parametrosConsulta);
$reservas = $stmt->fetchAll();

$totalPessoas = 0;
$totalConfirmadas = 0;
$reservasAlmoco = [];
$reservasJanta = [];

foreach ($reservas as $reserva) {
    if ($reserva['status_reserva'] === 'Reservado') {
        $totalPessoas += (int) $reserva['pessoas'];
    }
    if ($reserva['confirmacao'] === 'Confirmado') {
        $totalConfirmadas++;
    }

    if (substr($reserva['hora_reserva'], 0, 5) <= '16:00') {
        $reservasAlmoco[] = $reserva;
    } else {
        $reservasJanta[] = $reserva;
    }
}

function turnoResumo(array $reservasTurno): array
{
    $pessoas = 0;
    $confirmadas = 0;
    foreach ($reservasTurno as $reserva) {
        if ($reserva['status_reserva'] === 'Reservado') {
            $pessoas += (int) $reserva['pessoas'];
        }
        if ($reserva['confirmacao'] === 'Confirmado') {
            $confirmadas++;
        }
    }

    return ['pessoas' => $pessoas, 'confirmadas' => $confirmadas];
}

$dataFormatada = date('d/m/Y', strtotime($dataSelecionada));
$diaSemana = $diasSemana[(int) date('w', strtotime($dataSelecionada))];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservas do dia <?= e($dataFormatada) ?> - Churrascaria Pampulha</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --print-border: #ccc;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #222;
            margin: 0;
            padding: 2rem;
            background: #f4f4f4;
        }
        .relatorio-toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .relatorio-toolbar form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: auto;
        }
        .relatorio-toolbar input[type="date"] {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--print-border);
            border-radius: 6px;
            font-size: 0.95rem;
        }
        .relatorio-toolbar select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--print-border);
            border-radius: 6px;
            background: #fff;
            font-size: 0.95rem;
        }
        .relatorio-toolbar button,
        .relatorio-toolbar a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.1rem;
            border-radius: 6px;
            border: 1px solid var(--print-border);
            background: #fff;
            color: #222;
            text-decoration: none;
            font-size: 0.95rem;
            cursor: pointer;
        }
        .relatorio-toolbar .btn-imprimir-agora {
            background: #b03a2e;
            color: #fff;
            border-color: #b03a2e;
        }
        .relatorio-folha {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
        }
        .relatorio-cabecalho {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #b03a2e;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .relatorio-cabecalho h1 {
            margin: 0;
            font-size: 1.5rem;
        }
        .relatorio-cabecalho p {
            margin: 0.2rem 0 0;
            color: #555;
        }
        .relatorio-cabecalho img {
            height: 60px;
        }
        .relatorio-resumo {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .relatorio-resumo span {
            font-weight: bold;
            color: #b03a2e;
        }
        table.relatorio-tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        table.relatorio-tabela th,
        table.relatorio-tabela td {
            border: 1px solid var(--print-border);
            padding: 0.5rem 0.6rem;
            text-align: left;
        }
        table.relatorio-tabela th {
            background: #f1f1f1;
        }
        .relatorio-vazio {
            text-align: center;
            color: #777;
            padding: 2rem 0;
        }
        .relatorio-turno-titulo {
            margin: 2rem 0 0.5rem;
            font-size: 1.15rem;
            color: #b03a2e;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 0.4rem;
        }
        .relatorio-turno-titulo:first-of-type {
            margin-top: 0;
        }
        .relatorio-turno-resumo {
            margin: 0 0 0.75rem;
            color: #555;
            font-size: 0.9rem;
        }
        .relatorio-rodape {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #888;
            text-align: right;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .relatorio-toolbar {
                display: none;
            }
            .relatorio-folha {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="relatorio-toolbar">
        <form method="get" action="imprimir-reservas.php">
            <label for="data">Data:</label>
            <input type="date" id="data" name="data" value="<?= e($dataSelecionada) ?>">
            <label for="churrascaria">Churrascaria:</label>
            <select id="churrascaria" name="churrascaria">
                <?php foreach ($opcoesChurrascaria as $chaveOpcao => $rotuloOpcao): ?>
                    <option value="<?= e($chaveOpcao) ?>" <?= $churrascariaSelecionada === $chaveOpcao ? 'selected' : '' ?>><?= e($rotuloOpcao) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i>Ver</button>
        </form>
        <button type="button" class="btn-imprimir-agora" onclick="window.print()"><i class="fa-solid fa-print"></i>Imprimir / Salvar PDF</button>
        <a href="painel-reservas.php"><i class="fa-solid fa-arrow-left"></i>Voltar</a>
    </div>

    <div class="relatorio-folha">
        <div class="relatorio-cabecalho">
            <div>
                <h1>Relatório de Reservas do Dia</h1>
                <p><?= e($dataFormatada) ?> (<?= e($diaSemana) ?>)</p>
                <p><?= e($rotuloChurrascaria) ?></p>
            </div>
            <img src="logo-pampulha.png" alt="Churrascaria Pampulha">
        </div>

        <div class="relatorio-resumo">
            <p>Total de reservas: <span><?= e((string) count($reservas)) ?></span></p>
            <p>Confirmadas: <span><?= e((string) $totalConfirmadas) ?></span></p>
            <p>Pessoas esperadas: <span><?= e((string) $totalPessoas) ?></span></p>
        </div>

        <?php if (empty($reservas)): ?>
            <p class="relatorio-vazio">Nenhuma reserva encontrada para esta data.</p>
        <?php else: ?>
            <?php
            $turnos = [
                ['titulo' => 'Almoço (10:45 às 16:00)', 'reservas' => $reservasAlmoco],
                ['titulo' => 'Janta (16:01 às 23:00)', 'reservas' => $reservasJanta],
            ];
            ?>
            <?php foreach ($turnos as $turno): ?>
                <?php $resumoTurno = turnoResumo($turno['reservas']); ?>
                <h2 class="relatorio-turno-titulo"><?= e($turno['titulo']) ?></h2>
                <?php if (empty($turno['reservas'])): ?>
                    <p class="relatorio-vazio">Nenhuma reserva neste turno.</p>
                <?php else: ?>
                    <p class="relatorio-turno-resumo">
                        <?= e((string) count($turno['reservas'])) ?> reservas ·
                        <?= e((string) $resumoTurno['confirmadas']) ?> confirmadas ·
                        <?= e((string) $resumoTurno['pessoas']) ?> pessoas esperadas
                    </p>
                    <table class="relatorio-tabela">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Telefone</th>
                                <th>Churrascaria</th>
                                <th>Tipo</th>
                                <th>Pessoas</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Confirmação</th>
                                <th>Observação</th>
                                <th>Atendente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($turno['reservas'] as $reserva): ?>
                                <tr>
                                    <td><?= e(date('H:i', strtotime($reserva['hora_reserva']))) ?></td>
                                    <td><?= e($reserva['nome_cliente']) ?></td>
                                    <td><?= e($reserva['telefone']) ?></td>
                                    <td><?= e($reserva['churrascaria'] ?? CHURRASCARIA_PADRAO) ?></td>
                                    <td><?= $reserva['tipo_reserva'] ? e($reserva['tipo_reserva']) : '-' ?></td>
                                    <td><?= e((string) $reserva['pessoas']) ?></td>
                                    <td>R$ <?= e(number_format((float) $reserva['valor'], 2, ',', '.')) ?></td>
                                    <td><?= $reserva['status_reserva'] === 'Reservado' ? 'Reservado' : 'Cancelado' ?></td>
                                    <td><?= $reserva['confirmacao'] === 'Confirmado' ? 'Confirmado' : 'Pendente' ?></td>
                                    <td><?= $reserva['observacao'] ? e($reserva['observacao']) : '-' ?></td>
                                    <td><?= $reserva['atendente'] ? e($reserva['atendente']) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <p class="relatorio-rodape">Gerado em <?= e(date('d/m/Y H:i')) ?> por <?= e($_SESSION['funcionario_nome']) ?></p>
    </div>
</body>
</html>
