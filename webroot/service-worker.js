var CACHE = 'cache-and-update';

self.addEventListener('install',function(evt){
	console.log('The service worker is being installed.');
	evt.waitUntil(precache());
});
self.addEventListener('fetch',function(evt){
	console.log('The service worker is serving the asset.');
	evt.waitUntil(update(evt.request));
});
function precache(){
	return caches.open(CACHE).then(function(cache){
	return cache.addAll([
			'/manifest.json',
			'/favicon.ico',
			'/css/w3.css',
			'/js/d64.js',
			'/img/live.png',
			'/offline/',
			'/'
		]);
	});
}
function fromCache(request){
	return caches.open(CACHE).then(function(cache){
		return cache.match(request).then(function(matching){
			return matching || Promise.reject('no-match');
		});
	});
}
function update(request){
	return caches.open(CACHE).then(function(cache){
		return fetch(request).then(function(response){
			return cache.put(request,response);
		});
	});
}
