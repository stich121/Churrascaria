<?php
// Logger estruturado (JSON Lines), sem dependências externas.
// Compatível com a filosofia do Monolog/Winston/Pino: níveis, contexto,
// canais separados e sanitização de dados sensíveis antes da escrita.

final class Logger
{
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const WARN = 'WARN';
    public const ERROR = 'ERROR';
    public const FATAL = 'FATAL';

    private const SENSITIVE_KEYS = [
        'senha', 'senha_hash', 'senha_atual', 'senha_nova', 'confirmar_senha_nova',
        'password', 'csrf_token', 'token', 'authorization', 'secret',
        'db_pass', 'db_user', 'cpf', 'telefone', 'data_nascimento',
    ];

    private static ?string $requestId = null;
    private static ?string $logDir = null;
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
        self::$logDir = __DIR__ . '/../logs';

        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0750, true);
        }

        set_error_handler([self::class, 'handlePhpError']);
        set_exception_handler([self::class, 'handleUncaughtException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function requestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = bin2hex(random_bytes(8));
        }

        return self::$requestId;
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write(self::DEBUG, $message, $context, 'app');
    }

    public static function info(string $message, array $context = []): void
    {
        self::write(self::INFO, $message, $context, 'app');
    }

    public static function warn(string $message, array $context = []): void
    {
        self::write(self::WARN, $message, $context, 'app');
    }

    public static function error(string $message, array $context = []): void
    {
        self::write(self::ERROR, $message, $context, 'error');
    }

    public static function fatal(string $message, array $context = []): void
    {
        self::write(self::FATAL, $message, $context, 'error');
    }

    /**
     * Log de auditoria para ações sensíveis (login, CRUD de funcionários/reservas/clientes).
     */
    public static function audit(string $action, array $context = []): void
    {
        $context['action'] = $action;
        self::write(self::INFO, $action, $context, 'audit');
    }

    private static function write(string $level, string $message, array $context, string $channel): void
    {
        if (self::$logDir === null) {
            self::init();
        }

        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'request_id' => self::requestId(),
            'user_id' => $_SESSION['funcionario_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'action' => $context['action'] ?? null,
            'message' => $message,
            'context' => self::sanitize($context),
        ];

        $linha = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $arquivo = self::$logDir . '/' . ($channel === 'audit' ? 'audit.log' : ($channel === 'error' ? 'error.log' : 'app.log'));

        @file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private static function sanitize($data)
    {
        if ($data instanceof \Throwable) {
            return [
                'class' => get_class($data),
                'message' => $data->getMessage(),
                'file' => $data->getFile(),
                'line' => $data->getLine(),
            ];
        }

        if (is_array($data)) {
            $resultado = [];
            foreach ($data as $chave => $valor) {
                if (is_string($chave) && self::isSensitiveKey($chave)) {
                    $resultado[$chave] = '***REDACTED***';
                    continue;
                }
                $resultado[$chave] = self::sanitize($valor);
            }

            return $resultado;
        }

        return $data;
    }

    private static function isSensitiveKey(string $chave): bool
    {
        $chaveNormalizada = strtolower($chave);

        foreach (self::SENSITIVE_KEYS as $sensivel) {
            if (str_contains($chaveNormalizada, $sensivel)) {
                return true;
            }
        }

        return false;
    }

    public static function handlePhpError(int $nivel, string $mensagem, string $arquivo = '', int $linha = 0): bool
    {
        if (!(error_reporting() & $nivel)) {
            return false;
        }

        $nivelLog = in_array($nivel, [E_WARNING, E_USER_WARNING, E_DEPRECATED, E_USER_DEPRECATED], true)
            ? self::WARN
            : self::ERROR;

        self::write($nivelLog, $mensagem, ['file' => $arquivo, 'line' => $linha, 'php_error_level' => $nivel], 'error');

        return false;
    }

    public static function handleUncaughtException(\Throwable $e): void
    {
        self::write(self::FATAL, $e->getMessage(), ['exception' => $e], 'error');
    }

    public static function handleShutdown(): void
    {
        $erro = error_get_last();

        if ($erro !== null && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::write(self::FATAL, $erro['message'], ['file' => $erro['file'], 'line' => $erro['line']], 'error');
        }
    }
}
