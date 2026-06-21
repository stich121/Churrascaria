<?php
// Controle de sessão e permissões da área do funcionário.
// Inclua este arquivo antes de qualquer saída HTML nas páginas protegidas.

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

    $pdo->prepare('UPDATE login_attempts SET tentativas = ?, ultima_tentativa = NOW(), bloqueado_until = ? WHERE ip = ?')
        ->execute([$novasTentativas, $bloqueadoUntil, $ip]);
}

function limparTentativasLogin(PDO $pdo, string $ip): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
}

function funcionarioLogado(): bool
{
    return !empty($_SESSION['funcionario_id']);
}

function nivelFuncionario(): int
{
    return (int) ($_SESSION['funcionario_nivel'] ?? 0);
}

function exigirLogin(): void
{
    if (!funcionarioLogado()) {
        header('Location: area-reservas.php');
        exit;
    }
}

function exigirNivel(int $nivelMinimo): void
{
    exigirLogin();

    if (nivelFuncionario() < $nivelMinimo) {
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
        http_response_code(403);
        die('Token de segurança inválido. Volte e atualize a página.');
    }
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
