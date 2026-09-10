(function(){
  if(window.__openBunqFetchWrapped) return;
  window.__openBunqFetchWrapped=true;
  var original=window.fetch;
  if(typeof original!=='function') return;
  function findUrl(node,depth){
    if(depth>8 || !node) return '';
    if(typeof node==='object'){
      if(typeof node.open_bunq_payment_url==='string' && node.open_bunq_payment_url.indexOf('http')===0) return node.open_bunq_payment_url;
      for(var k in node){ if(Object.prototype.hasOwnProperty.call(node,k)){ var u=findUrl(node[k],depth+1); if(u)return u; } }
    }
    return '';
  }
  window.fetch=function(){
    return original.apply(this,arguments).then(function(response){
      try{
        var clone=response.clone();
        clone.json().then(function(data){ var url=findUrl(data,0); if(url){ setTimeout(function(){ window.location.assign(url); },25); } }).catch(function(){});
      }catch(e){}
      return response;
    });
  };
})();
