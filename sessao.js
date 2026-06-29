(function () {
    var config = window.AppSessaoConfig || {};
    var timeoutMs = Number(config.timeoutMs) || 3600000;
    var pingIntervalMs = Number(config.pingIntervalMs) || 60000;
    var pingUrl = config.pingUrl || 'atividade.php';
    var logoutUrl = config.logoutUrl || 'logout.php?timeout=1';
    var storageKey = config.storageKey || 'churrascaria:lastUserActivity';
    var serverLastActivity = Number(config.lastActivityMs) || Date.now();
    var storedLastActivity = 0;
    var now = Date.now();
    var lastActivity = serverLastActivity;
    var lastPing = serverLastActivity;
    var logoutTimer = null;

    try {
        storedLastActivity = Number(sessionStorage.getItem(storageKey)) || 0;
    } catch (e) {
        storedLastActivity = 0;
        if (window.AppLogger) {
            window.AppLogger.warn('Falha ao ler sessionStorage de atividade', { erro: String(e) });
        }
    }

    if (storedLastActivity > serverLastActivity && storedLastActivity <= now) {
        lastActivity = storedLastActivity;
    }

    function salvarUltimaAtividade() {
        try {
            sessionStorage.setItem(storageKey, String(lastActivity));
        } catch (e) {
            if (window.AppLogger) {
                window.AppLogger.warn('Falha ao salvar ultima atividade no sessionStorage', { erro: String(e) });
            }
        }
    }

    function desconectarPorInatividade() {
        window.location.href = logoutUrl;
    }

    function agendarDesconexao() {
        var restante = timeoutMs - (Date.now() - lastActivity);

        if (logoutTimer) {
            window.clearTimeout(logoutTimer);
        }

        if (restante <= 0) {
            desconectarPorInatividade();
            return;
        }

        logoutTimer = window.setTimeout(desconectarPorInatividade, restante + 100);
    }

    function pingAtividade() {
        if (!window.fetch || !pingUrl) {
            return;
        }

        window.fetch(pingUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            keepalive: true,
            headers: {
                'X-Requested-With': 'fetch'
            }
        }).then(function (response) {
            if (response.status === 401) {
                desconectarPorInatividade();
            }
        }).catch(function (erro) {
            if (window.AppLogger) {
                window.AppLogger.warn('Falha no ping de atividade da sessao', { erro: String(erro) });
            }
        });
    }

    function registrarAtividade() {
        var agora = Date.now();

        lastActivity = agora;
        salvarUltimaAtividade();
        agendarDesconexao();

        if (agora - lastPing >= pingIntervalMs) {
            lastPing = agora;
            pingAtividade();
        }
    }

    ['click', 'mousemove', 'keydown', 'scroll', 'touchstart', 'pointerdown'].forEach(function (evento) {
        window.addEventListener(evento, registrarAtividade, { passive: true });
    });

    window.addEventListener('focus', registrarAtividade);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            registrarAtividade();
        }
    });

    salvarUltimaAtividade();
    agendarDesconexao();
}());
