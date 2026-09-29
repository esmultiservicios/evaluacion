(function(w,d){
  function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
  const glyph={warning:'!',success:'✓',error:'×',info:'i',question:'?'};
  function fire(input){const o=typeof input==='string'?{title:input}:(input||{});return new Promise(resolve=>{
    const back=d.createElement('div');back.className='swal2-local-backdrop';
    const pop=d.createElement('div');pop.className='swal2-local-popup';pop.setAttribute('role','dialog');pop.setAttribute('aria-modal','true');
    const icon=String(o.icon||'info');let html=`<div class="swal2-local-icon ${esc(icon)}">${esc(glyph[icon]||'i')}</div><h2 class="swal2-local-title">${esc(o.title||'')}</h2>`;
    if(o.html)html+=`<div class="swal2-local-text">${o.html}</div>`;else if(o.text)html+=`<div class="swal2-local-text">${esc(o.text)}</div>`;
    html+='<div class="swal2-local-actions"></div>';pop.innerHTML=html;back.appendChild(pop);d.body.appendChild(back);
    const actions=pop.querySelector('.swal2-local-actions');
    function done(isConfirmed){back.remove();resolve({isConfirmed,isDismissed:!isConfirmed})}
    if(o.showCancelButton){const cancel=d.createElement('button');cancel.type='button';cancel.className='swal2-local-btn swal2-local-cancel';cancel.textContent=o.cancelButtonText||'Cancelar';cancel.onclick=()=>done(false);actions.appendChild(cancel)}
    if(o.showConfirmButton!==false){const ok=d.createElement('button');ok.type='button';ok.className='swal2-local-btn swal2-local-confirm';ok.textContent=o.confirmButtonText||'Aceptar';ok.onclick=()=>done(true);actions.appendChild(ok);setTimeout(()=>ok.focus(),20)}
    if(o.reverseButtons&&actions.children.length>1)actions.insertBefore(actions.lastElementChild,actions.firstElementChild);
    if(o.allowOutsideClick!==false)back.addEventListener('mousedown',e=>{if(e.target===back)done(false)});
    const key=e=>{if(e.key==='Escape'&&o.allowEscapeKey!==false){d.removeEventListener('keydown',key);done(false)}};d.addEventListener('keydown',key,{once:true});
  })}
  w.Swal={fire};
})(window,document);
