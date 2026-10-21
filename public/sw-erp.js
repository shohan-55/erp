/* Phase 20 safe mobile experience: intentionally NEVER cache authenticated ERP pages. */
const OFFLINE='/offline-erp.html';
self.addEventListener('install',event=>{
  event.waitUntil(caches.open('erp-offline-shell-v1')
    .then(cache=>cache.add(OFFLINE)).then(()=>self.skipWaiting()));
});
self.addEventListener('activate',event=>{
  event.waitUntil((async()=>{
    for(const key of await caches.keys()){
      if(key.startsWith('erp-offline-shell-')&&key!=='erp-offline-shell-v1'){
        await caches.delete(key);
      }
    }
    await self.clients.claim();
  })());
});
self.addEventListener('fetch',event=>{
  const request=event.request;
  if(request.mode!=='navigate' || request.method!=='GET')return;
  const url=new URL(request.url);
  if(url.origin!==self.location.origin||!url.pathname.startsWith(new URL(self.registration.scope).pathname))return;
  event.respondWith(fetch(request,{cache:'no-store'}).catch(async()=>{
    const cache=await caches.open('erp-offline-shell-v1');
    return (await cache.match(OFFLINE))||new Response('You are offline. Reconnect to access ERP.',{
      status:503,headers:{'Content-Type':'text/plain','Cache-Control':'no-store'}
    });
  }));
});
