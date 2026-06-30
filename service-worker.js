/*
 * Service Worker da Área de Reservas (PWA).
 *
 * Estratégia pensada para um sistema com login por sessão e dados que mudam
 * o tempo todo (reservas):
 *  - Páginas .php e navegações: SEMPRE rede primeiro (nunca servir reserva
 *    desatualizada nem página de outro usuário a partir do cache). Sem rede,
 *    cai numa página offline amigável.
 *  - Arquivos estáticos (css, js, imagens, fontes): cache primeiro, com
 *    atualização em segundo plano. Deixa o app abrir rápido.
 *
 * Ao mudar arquivos estáticos importantes, suba a versão abaixo para forçar
 * a renovação do cache nos celulares já instalados.
 */
const VERSION = 'reservas-pampulha-v1';
const STATIC_CACHE = `${VERSION}-estatico`;
const OFFLINE_URL = 'offline.html';

const PRECACHE = [
  OFFLINE_URL,
  'manifest.json',
  'icons/icon-192.png',
  'icons/icon-512.png',
  'icons/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

function ehEstatico(url) {
  return /\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot)(\?.*)?$/i.test(url);
}

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Só lidamos com GET. POST (login, salvar reserva, etc.) sempre vai à rede.
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Navegações e páginas dinâmicas (.php): rede primeiro, offline como reserva.
  const ehNavegacao = req.mode === 'navigate';
  const ehPhp = url.origin === self.location.origin && url.pathname.endsWith('.php');
  if (ehNavegacao || ehPhp) {
    event.respondWith(
      fetch(req).catch(() => {
        if (ehNavegacao) return caches.match(OFFLINE_URL);
        return new Response('', { status: 503, statusText: 'Offline' });
      })
    );
    return;
  }

  // Estáticos (mesmo domínio ou Font Awesome do CDN): cache primeiro.
  if (ehEstatico(url.href)) {
    event.respondWith(
      caches.match(req).then((cacheado) => {
        const naRede = fetch(req)
          .then((resp) => {
            if (resp && (resp.ok || resp.type === 'opaque')) {
              const copia = resp.clone();
              caches.open(STATIC_CACHE).then((cache) => cache.put(req, copia));
            }
            return resp;
          })
          .catch(() => cacheado);
        return cacheado || naRede;
      })
    );
  }
});
