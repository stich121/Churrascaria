<?php
// Controle de sessão e permissões da área do funcionário.
// Inclua este arquivo antes de qualquer saída HTML nas páginas protegidas.

require_once __DIR__ . '/lib/Logger.php';
Logger::init();

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

const NIVEL_ATENDENTE = 1;
const NIVEL_GERENTE = 2;
const NIVEL_SUPERIOR = 3;

const CHURRASCARIA_PADRAO = 'Churrascaria Pampulha';
const CHURRASCARIAS_RESERVA = [
    CHURRASCARIA_PADRAO,
    'Casarão Itau',
];

const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_JANELA_SEGUNDOS = 900;   // 15 minutos para acumular tentativas
const LOGIN_BLOQUEIO_SEGUNDOS = 900; // 15 minutos de bloqueio ao atingir o limite
const SESSAO_INATIVIDADE_SEGUNDOS = 3600; // 1 hora sem atividade real do usuario
const SESSAO_PING_ATIVIDADE_SEGUNDOS = 60;

function clienteIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Retorna quantos segundos faltam de bloqueio para o IP informado (0 = liberado).
 */
function verificarBloqueioLogin(PDO $pdo, string $ip): int
{
    $stmt = $pdo->prepare('SELECT bloqueado_until FROM login_attempts WHERE ip = ?');
    $stmt->execute([$ip]);
    $tentativa = $stmt->fetch();

    if (!$tentativa || $tentativa['bloqueado_until'] === null) {
        return 0;
    }

    $restante = strtotime($tentativa['bloqueado_until']) - time();

    return $restante > 0 ? $restante : 0;
}

function registrarTentativaFalhaLogin(PDO $pdo, string $ip): void
{
    $stmt = $pdo->prepare('SELECT tentativas, primeira_tentativa FROM login_attempts WHERE ip = ?');
    $stmt->execute([$ip]);
    $tentativa = $stmt->fetch();

    if (!$tentativa || strtotime($tentativa['primeira_tentativa']) < time() - LOGIN_JANELA_SEGUNDOS) {
        $pdo->prepare('
            INSERT INTO login_attempts (ip, tentativas, primeira_tentativa, ultima_tentativa, bloqueado_until)
            VALUES (?, 1, NOW(), NOW(), NULL)
            ON DUPLICATE KEY UPDATE tentativas = 1, primeira_tentativa = NOW(), ultima_tentativa = NOW(), bloqueado_until = NULL
        ')->execute([$ip]);

        return;
    }

    $novasTentativas = (int) $tentativa['tentativas'] + 1;
    $bloqueadoUntil = $novasTentativas >= LOGIN_MAX_TENTATIVAS
        ? date('Y-m-d H:i:s', time() + LOGIN_BLOQUEIO_SEGUNDOS)
        : null;

    if ($bloqueadoUntil !== null) {
        Logger::warn('IP bloqueado por excesso de tentativas de login', [
            'action' => 'login_ip_blocked',
            'ip' => $ip,
            'tentativas' => $novasTentativas,
            'bloqueado_until' => $bloqueadoUntil,
        ]);
    }

    $pdo->prepare('UPDATE login_attempts SET tentativas = ?, ultima_tentativa = NOW(), bloqueado_until = ? WHERE ip = ?')
        ->execute([$novasTentativas, $bloqueadoUntil, $ip]);
}

function limparTentativasLogin(PDO $pdo, string $ip): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
}

function encerrarSessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function sessaoExpiradaPorInatividade(): bool
{
    $ultimaAtividade = (int) ($_SESSION['ultima_atividade_usuario'] ?? 0);

    return $ultimaAtividade > 0 && time() - $ultimaAtividade > SESSAO_INATIVIDADE_SEGUNDOS;
}

function registrarAtividadeUsuario(): void
{
    if (!empty($_SESSION['funcionario_id'])) {
        $_SESSION['ultima_atividade_usuario'] = time();
    }
}

function requisicaoAtualizacaoAutomaticaDashboard(): bool
{
    return ($_SERVER['HTTP_X_DASHBOARD_AUTO_REFRESH'] ?? '') === '1';
}

function funcionarioLogado(): bool
{
    if (empty($_SESSION['funcionario_id'])) {
        return false;
    }

    if (sessaoExpiradaPorInatividade()) {
        Logger::audit('session_expired_by_inactivity', ['user_id' => $_SESSION['funcionario_id']]);
        encerrarSessao();

        return false;
    }

    return true;
}

function nivelFuncionario(): int
{
    return (int) ($_SESSION['funcionario_nivel'] ?? 0);
}

function exigirLogin(): void
{
    if (!funcionarioLogado()) {
        header('Location: area-reservas.php?timeout=1');
        exit;
    }

    if (!requisicaoAtualizacaoAutomaticaDashboard()) {
        registrarAtividadeUsuario();
    }
}

function exigirNivel(int $nivelMinimo): void
{
    exigirLogin();

    if (nivelFuncionario() < $nivelMinimo) {
        Logger::warn('Acesso negado por nivel insuficiente', [
            'action' => 'access_denied',
            'nivel_atual' => nivelFuncionario(),
            'nivel_exigido' => $nivelMinimo,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
        ]);
        http_response_code(403);
        die('Acesso negado: seu nível de permissão não tem acesso a esta página.');
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verificarCsrf(): void
{
    $tokenEnviado = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $tokenEnviado)) {
        Logger::warn('Token CSRF invalido ou ausente', [
            'action' => 'csrf_invalid',
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
        ]);
        http_response_code(403);
        die('Token de segurança inválido. Volte e atualize a página.');
    }
}

function renderizarControleSessao(): void
{
    $config = [
        'timeoutMs' => SESSAO_INATIVIDADE_SEGUNDOS * 1000,
        'pingIntervalMs' => SESSAO_PING_ATIVIDADE_SEGUNDOS * 1000,
        'lastActivityMs' => ((int) ($_SESSION['ultima_atividade_usuario'] ?? time())) * 1000,
        'pingUrl' => 'atividade.php',
        'logoutUrl' => 'logout.php?timeout=1',
        'storageKey' => 'churrascaria:lastUserActivity:' . (int) ($_SESSION['funcionario_id'] ?? 0),
    ];

    echo '<script>window.AppSessaoConfig = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>' . PHP_EOL;
    echo '<script src="logger.js?v=20260629-1"></script>' . PHP_EOL;
    echo '<script src="sessao.js?v=20260629-1"></script>' . PHP_EOL;
}

function churrascariaReservaValida(?string $churrascaria): bool
{
    return in_array($churrascaria ?? '', CHURRASCARIAS_RESERVA, true);
}

function garantirColunaChurrascaria(PDO $pdo): void
{
    static $verificado = false;

    if ($verificado) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'churrascaria'");
    if (!$stmt->fetch()) {
        $pdo->exec(
            "ALTER TABLE reservas
             ADD COLUMN churrascaria VARCHAR(60) NOT NULL DEFAULT 'Churrascaria Pampulha' AFTER telefone"
        );
    }

    $verificado = true;
}

function garantirColunaChurrascariaMesas(PDO $pdo): void
{
    static $verificado = false;

    if ($verificado) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM mesas LIKE 'churrascaria'");
    if (!$stmt->fetch()) {
        $pdo->exec(
            "ALTER TABLE mesas
             ADD COLUMN churrascaria VARCHAR(60) NOT NULL DEFAULT 'Churrascaria Pampulha' AFTER capacidade"
        );
    }

    $verificado = true;
}

function garantirTabelaTiposReserva(PDO $pdo): void
{
    static $verificado = false;

    if ($verificado) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS tipos_reserva (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(60) NOT NULL,
            criado_por INT UNSIGNED NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tipos_reserva_nome (nome),
            CONSTRAINT fk_tipos_reserva_funcionario FOREIGN KEY (criado_por) REFERENCES funcionarios(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $stmt = $pdo->query('SELECT COUNT(*) AS total FROM tipos_reserva');
    if ((int) $stmt->fetch()['total'] === 0) {
        $padrao = ['Aniversário', 'Casamento', 'Confraternização', 'Reunião de negócios', 'Outro'];
        $insercao = $pdo->prepare('INSERT INTO tipos_reserva (nome) VALUES (?)');
        foreach ($padrao as $nomeTipo) {
            $insercao->execute([$nomeTipo]);
        }
    }

    $verificado = true;
}

function garantirColunaTipoReserva(PDO $pdo): void
{
    static $verificado = false;

    if ($verificado) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'tipo_reserva'");
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE reservas ADD COLUMN tipo_reserva VARCHAR(60) NULL AFTER churrascaria');
    }

    $verificado = true;
}

function garantirTabelaClientes(PDO $pdo): void
{
    static $verificado = false;

    if ($verificado) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS clientes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            telefone VARCHAR(20) NOT NULL,
            data_nascimento DATE NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_clientes_telefone (telefone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $verificado = true;
}

function garantirColunaChurrascariaClientes(PDO $pdo): void
{
    static $verificado = false;

    if ($verificado) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'churrascaria'");
    if (!$stmt->fetch()) {
        $pdo->exec(
            "ALTER TABLE clientes
             ADD COLUMN churrascaria VARCHAR(60) NOT NULL DEFAULT 'Churrascaria Pampulha' AFTER telefone"
        );
    }

    $verificado = true;
}

function salvarClienteAutomatico(PDO $pdo, string $nome, string $telefone, ?string $dataNascimento = null, ?string $churrascaria = null): void
{
    if ($nome === '' || $telefone === '') {
        return;
    }

    garantirTabelaClientes($pdo);
    garantirColunaChurrascariaClientes($pdo);

    $churrascaria = $churrascaria !== null && $churrascaria !== '' ? $churrascaria : CHURRASCARIA_PADRAO;

    if ($dataNascimento !== null) {
        $pdo->prepare(
            'INSERT INTO clientes (nome, telefone, churrascaria, data_nascimento) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE nome = VALUES(nome), churrascaria = VALUES(churrascaria), data_nascimento = VALUES(data_nascimento)'
        )->execute([$nome, $telefone, $churrascaria, $dataNascimento]);

        return;
    }

    $pdo->prepare(
        'INSERT INTO clientes (nome, telefone, churrascaria) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE nome = VALUES(nome), churrascaria = VALUES(churrascaria)'
    )->execute([$nome, $telefone, $churrascaria]);
}

function garantirColunaMesasAlocadasReservas(PDO $pdo): void
{
    static $verificado = false;

    if ($verificado) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'mesas_alocadas'");
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE reservas ADD COLUMN mesas_alocadas VARCHAR(120) NULL AFTER pessoas');
    }

    $verificado = true;
}

/**
 * Calcula quais mesas usar para uma reserva: prioriza mesa de 2 lugares para 2 pessoas,
 * mesa de 4 lugares para 3 ou 4 pessoas, e combina mesas maiores->menores para grupos maiores.
 */
function calcularMesasReserva(PDO $pdo, string $churrascaria, int $pessoas): string
{
    if ($pessoas < 1) {
        return '';
    }

    $stmt = $pdo->prepare('SELECT capacidade, quantidade FROM mesas WHERE churrascaria = ? ORDER BY capacidade ASC');
    $stmt->execute([$churrascaria]);
    $disponiveis = [];
    foreach ($stmt->fetchAll() as $mesa) {
        $disponiveis[(int) $mesa['capacidade']] = (int) $mesa['quantidade'];
    }

    if (empty($disponiveis)) {
        return 'Nenhuma mesa cadastrada';
    }

    $usadas = [];

    if ($pessoas <= 2 && !empty($disponiveis[2])) {
        $disponiveis[2]--;
        $usadas[2] = ($usadas[2] ?? 0) + 1;
    } elseif ($pessoas <= 4 && !empty($disponiveis[4])) {
        $disponiveis[4]--;
        $usadas[4] = ($usadas[4] ?? 0) + 1;
    } else {
        $restante = $pessoas;
        $capacidadesDesc = array_keys($disponiveis);
        rsort($capacidadesDesc);

        while ($restante > 0) {
            $alocou = false;

            foreach ($capacidadesDesc as $capacidade) {
                if ($disponiveis[$capacidade] > 0) {
                    $disponiveis[$capacidade]--;
                    $usadas[$capacidade] = ($usadas[$capacidade] ?? 0) + 1;
                    $restante -= $capacidade;
                    $alocou = true;
                    break;
                }
            }

            if (!$alocou) {
                $usadas['extra'] = ($usadas['extra'] ?? 0) + 1;
                $restante -= max($capacidadesDesc ?: [1]);
            }
        }
    }

    if (empty($usadas)) {
        $maiorCapacidade = max(array_keys($disponiveis));
        $usadas[$maiorCapacidade] = 1;
    }

    $descricoes = [];
    foreach ($usadas as $capacidade => $quantidade) {
        if ($capacidade === 'extra') {
            $descricoes[] = $quantidade . ' mesa(s) extra';
            continue;
        }
        $descricoes[] = $quantidade . ' mesa(s) de ' . $capacidade;
    }

    return implode(' + ', $descricoes);
}

function logoChurrascaria(?string $churrascaria): string
{
    return $churrascaria === 'Casarão Itau' ? 'logo-casarao-itau.png' : 'logo-pampulha.png';
}

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function nomeNivel(int $nivel): string
{
    switch ($nivel) {
        case NIVEL_SUPERIOR:
            return 'Nível Superior';
        case NIVEL_GERENTE:
            return 'Gerente';
        case NIVEL_ATENDENTE:
            return 'Atendente';
        default:
            return 'Desconhecido';
    }
}
