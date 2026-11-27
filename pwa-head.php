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

    document.addEventListener('DOMContentLoaded', function () {
        inicializarGruposMenu();

        var btn = document.querySelector('.nav-hamburger');
        var nav = document.querySelector('.funcionario-nav-links');
        if (!btn || !nav) return;

        btn.addEventListener('click', function () {
            var aberto = nav.classList.toggle('aberto');
            btn.classList.toggle('aberto');
            btn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });

        nav.addEventListener('click', function (e) {
            if (e.target.closest('a')) {
                nav.classList.remove('aberto');
                btn.classList.remove('aberto');
                btn.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('click', function (e) {
            if (!btn.contains(e.target) && !nav.contains(e.target) && !e.target.closest('.fab-nova-reserva')) {
                nav.classList.remove('aberto');
                btn.classList.remove('aberto');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Grupos do menu ("Configurações" e o do usuário): abre/fecha no clique,
    // fecha ao clicar fora, com Esc e quando outro grupo abre.
    function inicializarGruposMenu() {
        function fecharGrupos(exceto) {
            document.querySelectorAll('.menu-grupo.aberto').forEach(function (grupo) {
                if (grupo !== exceto) {
                    grupo.classList.remove('aberto');
                    var botao = grupo.querySelector('.menu-grupo-botao');
                    if (botao) botao.setAttribute('aria-expanded', 'false');
                }
            });
        }

        document.addEventListener('click', function (e) {
            var botao = e.target.closest('.menu-grupo-botao');
            if (botao) {
                var grupo = botao.closest('.menu-grupo');
                var aberto = grupo.classList.toggle('aberto');
                botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
                fecharGrupos(grupo);
                return;
            }
            if (!e.target.closest('.menu-submenu')) {
                fecharGrupos(null);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') fecharGrupos(null);
        });
    }
</script>
