(function () {
    var SENSITIVE_KEY_PATTERN = /senha|password|token|csrf|secret|telefone|nascimento|cpf/i;
    var ENDPOINT = 'log-cliente.php';

    function sanitize(valor) {
        if (Array.isArray(valor)) {
            return valor.map(sanitize);
        }

        if (valor && typeof valor === 'object') {
            var resultado = {};
            Object.keys(valor).forEach(function (chave) {
                resultado[chave] = SENSITIVE_KEY_PATTERN.test(chave) ? '***REDACTED***' : sanitize(valor[chave]);
            });
            return resultado;
        }

        return valor;
    }

    function enviarParaServidor(level, message, context) {
        if (!window.fetch) {
            return;
        }

        window.fetch(ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            keepalive: true,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ level: level, message: message, context: sanitize(context || {}) })
        }).catch(function () {
            // Falha ao registrar o log no servidor não deve gerar novo erro em cascata.
        });
    }

    function log(level, message, context) {
        var payload = sanitize(context || {});

        if (window.console && typeof window.console[level === 'warn' ? 'warn' : (level === 'error' ? 'error' : 'log')] === 'function') {
            window.console[level === 'warn' ? 'warn' : (level === 'error' ? 'error' : 'log')]('[' + level.toUpperCase() + '] ' + message, payload);
        }

        if (level === 'warn' || level === 'error') {
            enviarParaServidor(level, message, payload);
        }
    }

    window.AppLogger = {
        debug: function (message, context) { log('debug', message, context); },
        info: function (message, context) { log('info', message, context); },
        warn: function (message, context) { log('warn', message, context); },
        error: function (message, context) { log('error', message, context); }
    };
}());
