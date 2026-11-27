<?php
// Histórico de alterações: guarda no banco quem mudou o quê, quando e o valor
// de antes e de depois de cada campo. É exibido em historico.php (gerente ou acima).
// Diferente do logs/audit.log (técnico, só desenvolvedor), aqui o texto é para leitura
// da equipe. Senhas nunca entram: a troca aparece só como "Senha: alterada".

// Horário de Brasília fixo: o servidor pode estar em UTC e o histórico mostraria 3 horas a mais.
const HISTORICO_FUSO = 'America/Sao_Paulo';

function historicoAgora(): string
{
    return (new DateTime('now', new DateTimeZone(HISTORICO_FUSO)))->format('Y-m-d H:i:s');
}

const HISTORICO_ENTIDADES = [
    'reserva' => 'Reserva',
    'cliente' => 'Cliente',
    'mesa' => 'Mesa',
    'tipo_reserva' => 'Tipo de reserva',
    'funcionario' => 'Funcionário',
];

const HISTORICO_ACOES = [
    'criou' => 'Criou',
    'editou' => 'Editou',
    'excluiu' => 'Excluiu',
];

// Tabela do banco de cada entidade e os campos que aparecem no histórico (coluna => rótulo).
const HISTORICO_CAMPOS = [
    'reserva' => [
        'tabela' => 'reservas',
        'campos' => [
            'nome_cliente' => 'Cliente',
            'telefone' => 'Telefone',
            'churrascaria' => 'Churrascaria',
            'tipo_reserva' => 'Tipo de reserva',
            'data_pedido' => 'Data do pedido',
            'data_reserva' => 'Data da reserva',
            'hora_reserva' => 'Hora',
            'pessoas' => 'Pessoas',
            'mesas_alocadas' => 'Mesas',
            'pessoas_compareceram' => 'Compareceram',
            'valor' => 'Valor',
            'status_reserva' => 'Status',
            'confirmacao' => 'Confirmação',
            'observacao' => 'Observação',
        ],
    ],
    'cliente' => [
        'tabela' => 'clientes',
        'campos' => [
            'nome' => 'Nome',
            'telefone' => 'Telefone',
            'churrascaria' => 'Churrascaria',
            'data_nascimento' => 'Aniversário',
        ],
    ],
    'mesa' => [
        'tabela' => 'mesas',
        'campos' => [
            'churrascaria' => 'Churrascaria',
            'capacidade' => 'Lugares por mesa',
            'quantidade' => 'Quantidade',
        ],
    ],
    'tipo_reserva' => [
        'tabela' => 'tipos_reserva',
        'campos' => [
            'nome' => 'Nome',
        ],
    ],
    'funcionario' => [
        'tabela' => 'funcionarios',
        'campos' => [
            'nome' => 'Nome',
            'usuario' => 'Usuário',
            'nivel' => 'Nível',
            'ativo' => 'Situação',
        ],
    ],
];

function garantirTabelaHistorico(PDO $pdo): void
{
    static $verificado = false;

    if ($verificado) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS historico_alteracoes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            criado_em DATETIME NOT NULL,
            funcionario_id INT UNSIGNED NULL,
            funcionario_nome VARCHAR(100) NOT NULL DEFAULT '',
            entidade VARCHAR(30) NOT NULL,
            entidade_id INT UNSIGNED NULL,
            acao VARCHAR(20) NOT NULL,
            descricao VARCHAR(255) NOT NULL DEFAULT '',
            churrascaria VARCHAR(60) NULL,
            alteracoes TEXT NULL,
            ip VARCHAR(45) NULL,
            KEY idx_historico_data (criado_em),
            KEY idx_historico_entidade (entidade, entidade_id),
            KEY idx_historico_funcionario (funcionario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $verificado = true;
}

/**
 * Lê a linha atual de uma entidade (para guardar o "antes"). Devolve null se não existir.
 */
function historicoBuscarRegistro(PDO $pdo, string $entidade, int $id): ?array
{
    if ($id < 1 || !isset(HISTORICO_CAMPOS[$entidade])) {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM ' . HISTORICO_CAMPOS[$entidade]['tabela'] . ' WHERE id = ?');
        $stmt->execute([$id]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        Logger::error('Falha ao ler registro para o histórico', ['entidade' => $entidade, 'id' => $id, 'exception' => $e]);

        return null;
    }

    return $linha ?: null;
}

function historicoFormatarValor(string $entidade, string $campo, $valor): string
{
    if ($valor === null || $valor === '') {
        return '';
    }

    $valor = (string) $valor;

    if (in_array($campo, ['data_reserva', 'data_pedido', 'data_nascimento'], true)) {
        $data = DateTime::createFromFormat('Y-m-d', substr($valor, 0, 10));

        return $data ? $data->format('d/m/Y') : $valor;
    }

    if ($campo === 'hora_reserva') {
        return substr($valor, 0, 5);
    }

    if ($campo === 'valor') {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    if ($entidade === 'funcionario' && $campo === 'nivel' && function_exists('nomeNivel')) {
        return nomeNivel((int) $valor);
    }

    if ($entidade === 'funcionario' && $campo === 'ativo') {
        return (int) $valor === 1 ? 'Ativo' : 'Inativo';
    }

    return $valor;
}

/**
 * Compara antes e depois campo a campo. Na criação ($antes null) lista os campos
 * preenchidos; na exclusão ($depois null) lista como o registro estava.
 */
function historicoDiferencas(string $entidade, ?array $antes, ?array $depois): array
{
    $diferencas = [];

    foreach (HISTORICO_CAMPOS[$entidade]['campos'] ?? [] as $campo => $rotulo) {
        $valorAntes = $antes !== null ? historicoFormatarValor($entidade, $campo, $antes[$campo] ?? null) : '';
        $valorDepois = $depois !== null ? historicoFormatarValor($entidade, $campo, $depois[$campo] ?? null) : '';

        if ($valorAntes === $valorDepois) {
            continue;
        }

        $diferencas[] = ['campo' => $rotulo, 'antes' => $valorAntes, 'depois' => $valorDepois];
    }

    return $diferencas;
}

/**
 * Grava uma linha no histórico. Nunca interrompe a ação do usuário: se falhar, só registra no log de erros.
 *
 * @param array $extras diferenças a mais, já prontas ([['campo' => ..., 'antes' => ..., 'depois' => ...]])
 */
function registrarHistorico(PDO $pdo, string $entidade, ?int $entidadeId, string $acao, string $descricao, ?array $antes = null, ?array $depois = null, array $extras = []): void
{
    try {
        $diferencas = array_merge(historicoDiferencas($entidade, $antes, $depois), $extras);

        // Edição que não mudou nada (ex.: salvou o formulário sem mexer) não vira histórico.
        if ($acao === 'editou' && $diferencas === []) {
            return;
        }

        garantirTabelaHistorico($pdo);

        $churrascaria = $depois['churrascaria'] ?? $antes['churrascaria'] ?? null;

        $pdo->prepare(
            'INSERT INTO historico_alteracoes
                (criado_em, funcionario_id, funcionario_nome, entidade, entidade_id, acao, descricao, churrascaria, alteracoes, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            historicoAgora(),
            isset($_SESSION['funcionario_id']) ? (int) $_SESSION['funcionario_id'] : null,
            mb_substr((string) ($_SESSION['funcionario_nome'] ?? ''), 0, 100),
            $entidade,
            $entidadeId,
            $acao,
            mb_substr($descricao, 0, 255),
            $churrascaria,
            json_encode($diferencas, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        Logger::error('Falha ao gravar histórico de alterações', [
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'acao' => $acao,
            'exception' => $e,
        ]);
    }
}

/**
 * Texto curto que identifica o registro na lista do histórico.
 */
function historicoDescrever(string $entidade, ?array $registro): string
{
    if ($registro === null) {
        return '';
    }

    switch ($entidade) {
        case 'reserva':
            $data = historicoFormatarValor('reserva', 'data_reserva', $registro['data_reserva'] ?? null);
            $hora = historicoFormatarValor('reserva', 'hora_reserva', $registro['hora_reserva'] ?? null);

            return trim(($registro['nome_cliente'] ?? '') . ' — ' . trim($data . ' ' . $hora), ' —');
        case 'mesa':
            return ($registro['quantidade'] ?? '?') . ' mesa(s) de ' . ($registro['capacidade'] ?? '?') . ' lugares';
        default:
            return (string) ($registro['nome'] ?? '');
    }
}
