(function(){
  function init(){
    var root=document.getElementById('bhSystemChat'); if(!root||root.dataset.ready==='1')return; root.dataset.ready='1';
    var launcher=document.getElementById('bhChatLauncher'),panel=document.getElementById('bhChatPanel'),close=document.getElementById('bhChatClose'),body=document.getElementById('bhChatBody'),form=document.getElementById('bhChatForm'),input=document.getElementById('bhChatInput'),quick=document.getElementById('bhChatQuick');
    var endpoint=root.dataset.endpoint;
    function openChat(){panel.classList.add('open');launcher.setAttribute('aria-expanded','true');setTimeout(function(){input.focus();},80)}
    function closeChat(){panel.classList.remove('open');launcher.setAttribute('aria-expanded','false')}
    function addMsg(who,text){var row=document.createElement('div');row.className='bh-msg '+who;var b=document.createElement('div');b.className='bh-bubble';b.textContent=text;row.appendChild(b);body.appendChild(row);body.scrollTop=body.scrollHeight;return row}
    function thinking(){var row=document.createElement('div');row.className='bh-msg bot';row.innerHTML='<div class="bh-bubble"><span class="bh-chat-thinking"><i></i><i></i><i></i></span></div>';body.appendChild(row);body.scrollTop=body.scrollHeight;return row}
    function setQuick(items){quick.innerHTML='';(items||[]).slice(0,4).forEach(function(t){var b=document.createElement('button');b.type='button';b.className='bh-chip';b.textContent=t;b.addEventListener('click',function(){ask(t)});quick.appendChild(b)})}
    async function ask(text){text=(text||'').trim();if(!text)return;openChat();addMsg('user',text);input.value='';input.style.height='auto';var wait=thinking();try{var r=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',body:JSON.stringify({message:text})});var d=await r.json();wait.remove();addMsg('bot',d.answer||'I could not answer that right now.');setQuick(d.quick_replies||[])}catch(e){wait.remove();addMsg('bot','I could not reach the system assistant. Please check that Apache and MySQL are running.');}}
    launcher.addEventListener('click',function(){panel.classList.contains('open')?closeChat():openChat()});close.addEventListener('click',closeChat);form.addEventListener('submit',function(e){e.preventDefault();ask(input.value)});input.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();form.requestSubmit()}});input.addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,92)+'px'});
    root.querySelectorAll('[data-bh-question]').forEach(function(el){el.addEventListener('click',function(){ask(el.dataset.bhQuestion)})});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();