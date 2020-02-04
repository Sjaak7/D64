var C='v1.1';
self.addEventListener('install',(e)=>{
	console.log('Installing');
	e.waitUntil(preCache())
});
self.addEventListener('fetch',(e)=>{
	console.log('Serving asset.');
	e.waitUntil(update(e.request))
});
function preCache(){
	return caches.open(C).then((c)=>{
	return c.addAll([
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
function fromCache(r){
	return caches.open(C).then((c)=>{
		return c.match(r).then((matching)=>{
			return matching||Promise.reject('no-match')
		});
	});
}
function update(r){
	return caches.open(C).then((c)=>{
		return fetch(r).then((response)=>{
			return c.put(r,response)
		});
	});
}
