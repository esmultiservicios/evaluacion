(()=>{
  'use strict';
  const btn=document.querySelector('[data-public-sound-toggle]');
  if(!btn)return;
  let enabled=true;
  try{enabled=localStorage.getItem('evaluacion:sound')!=='off'}catch(_){}
  const paint=()=>{const node=btn.firstChild;if(node)node.textContent=enabled?'🔊 ':'🔇 ';btn.classList.toggle('is-muted',!enabled)};
  paint();
  btn.addEventListener('click',()=>{enabled=!enabled;try{localStorage.setItem('evaluacion:sound',enabled?'on':'off')}catch(_){}paint();if(window.showNotify)showNotify('info',enabled?'Sonido activado.':'Sonido desactivado.')});
})();
