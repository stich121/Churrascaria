<?php /* pwa-head.php — inclua dentro do <head> de cada pagina da area de reservas */ ?>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#c41e3a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Reservas Pampulha">
<link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('service-worker.js').catch(function (e) {
                console.warn('Falha ao registrar o service worker:', e);
            });
        });
    }
</script>
