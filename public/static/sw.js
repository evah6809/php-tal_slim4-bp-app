const CACHE_NAME = 'bp-memo-cache-v1';
const urlsToCache = [
  '/'
];

// インストール時にキャッシュ
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(urlsToCache);
    })
  );
});

// リクエスト時の処理（完全解決版）
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // 💡 1. ログインや認証に関するURL（/login や /login/authorize）はすべてスルー
  // 💡 2. Googleの認証サーバーへの通信もすべてスルー
  if (
    url.pathname.includes('/login') || 
    url.hostname.includes('google.com')
  ) {
    return; // サービスワーカーは何もせず、通常のブラウザ通信に任せる
  }

  // 💡 3. 画面の切り替え（リダイレクトを伴うページ移動）時のエラーを完全に防止する
  if (event.request.mode === 'navigate') {
    return; // ページ全体の移動・リダイレクトはキャッシュを通さずブラウザに任せる
  }

  // それ以外の画像や通常の通信のみ、キャッシュがあれば返す
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});