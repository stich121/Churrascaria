<?php
// Tags do PWA (Progressive Web App) compartilhadas pela Área de Reservas.
// Incluir dentro do <head> de cada página da área:  <?php include __DIR__ . '/pwa-head.php'; ?>
// Como todas as páginas ficam na mesma pasta, os caminhos abaixo são relativos.
?>
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
