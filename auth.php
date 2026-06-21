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
