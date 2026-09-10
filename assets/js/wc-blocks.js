(function(){
  if(!window.wc || !window.wc.wcBlocksRegistry || !window.wc.wcSettings) return;
  var settings=window.wc.wcSettings.getSetting('open_bunq_data',{});
  var el=window.wp.element.createElement;
  var decode=window.wp.htmlEntities.decodeEntities;
  var label=decode(settings.title||'bunq');
  var Content=function(){return el('div',null,decode(settings.description||'Pay securely using bunq.'));};
  window.wc.wcBlocksRegistry.registerPaymentMethod({name:'open_bunq',label:label,content:el(Content),edit:el(Content),canMakePayment:function(){return true;},ariaLabel:label,supports:{features:settings.supports||['products']}});
})();
