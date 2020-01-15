var 	cacheName = 'shell-content-v1',
	filesToCache = [
		'/manifest.json',
		'/favicon.ico',
		'/css/w3.css',
		'/js/d64.js',
		'/img/live.png',

		'/offline/',
		'/',
	];
self.addEventListener('install',(e)=>{
	console.log('[ServiceWorker] Install');
	e.waitUntil(
		caches.open(cacheName).then((cache)=>{
			console.log('[ServiceWorker] Caching app shell');
			return cache.addAll(filesToCache);
		})
	);
});
self.addEventListener('activate',(e)=>{
	e.waitUntil(
		caches.keys().then((keyList)=>{
			return Promise.all(keyList.map((key)=>{
				if(key!==cacheName){
					console.log('[ServiceWorker] Removing old cache',key);
					return caches.delete(key);
				}
			}));
		})
	);
});
self.addEventListener('fetch',(e)=>{
	e.respondWith(
		caches.match(e.request).then((response)=>{
			return response || fetch(e.request);
		}).catch(()=>{
			return caches.match('/offline/');
		})
	);
});
