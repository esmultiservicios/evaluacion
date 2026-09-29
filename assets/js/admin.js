(()=>{
  'use strict';
  const d=document,$=(s,r=d)=>r.querySelector(s),$$=(s,r=d)=>[...r.querySelectorAll(s)];
  const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));

  // Provider email
  const mailRadios=$$('input[name="method"]');
  const syncMail=()=>{const method=$('input[name="method"]:checked')?.value||'SMTP';$$('.mail-group').forEach(x=>x.classList.toggle('hidden',x.dataset.mail!==method));};
  mailRadios.forEach(r=>r.addEventListener('change',syncMail));syncMail();

  // Professional confirmations
  $$('.js-confirm-form').forEach(form=>form.addEventListener('submit',async e=>{
    if(form.dataset.confirmed==='1')return;
    e.preventDefault();
    const result=await Swal.fire({icon:'warning',title:form.dataset.confirmTitle||'Confirmar acción',text:form.dataset.confirmText||'¿Deseas continuar?',showCancelButton:true,confirmButtonText:'Sí, continuar',cancelButtonText:'Cancelar',allowOutsideClick:false});
    if(result.isConfirmed){form.dataset.confirmed='1';form.submit();}
  }));
  if(window.PAGE_NOTIFY?.message)setTimeout(()=>showNotify(window.PAGE_NOTIFY.type||'info',window.PAGE_NOTIFY.message),90);

  // Sidebar responsive
  const sidebar=$('#adminSidebar'),backdrop=$('[data-sidebar-backdrop]');
  const closeSidebar=()=>{sidebar?.classList.remove('open');backdrop?.classList.remove('show')};
  $('[data-sidebar-toggle]')?.addEventListener('click',()=>{sidebar?.classList.toggle('open');backdrop?.classList.toggle('show')});
  backdrop?.addEventListener('click',closeSidebar);

  // User menu with logout in the profile chip
  const userMenu=$('[data-user-menu]');
  $('[data-user-trigger]')?.addEventListener('click',e=>{e.stopPropagation();userMenu?.classList.toggle('open')});
  d.addEventListener('click',e=>{if(userMenu&&!userMenu.contains(e.target))userMenu.classList.remove('open')});

  // Global search
  const global=$('[data-global-search]'),globalInput=$('[data-global-search-input]'),globalResults=$('[data-global-search-results]'),globalClear=$('[data-global-search-clear]');
  let globalTimer=null,globalController=null;
  const resultIcon=type=>type==='Empleado'?'EM':(type==='Pregunta'?'PR':'US');
  const closeGlobal=()=>global?.classList.remove('open');
  const runGlobal=()=>{
    const q=globalInput?.value.trim()||'';
    global?.classList.toggle('has-value',q.length>0);
    if(q.length<2){closeGlobal();if(globalResults)globalResults.innerHTML='';return;}
    clearTimeout(globalTimer);globalTimer=setTimeout(async()=>{
      try{globalController?.abort();globalController=new AbortController();const r=await fetch('?ajax=global_search&q='+encodeURIComponent(q),{signal:globalController.signal,headers:{'X-Requested-With':'fetch'}});const j=await r.json();if(!r.ok)throw new Error(j.message||'No se pudo buscar');
        globalResults.innerHTML=(j.results||[]).length?(j.results||[]).map(x=>`<a class="global-result" href="${esc(x.url)}"><span class="global-result-icon">${resultIcon(x.type)}</span><span><strong>${esc(x.title)}</strong><small>${esc(x.type)} · ${esc(x.meta)}</small></span></a>`).join(''):'<div class="global-empty">No encontramos coincidencias.</div>';global?.classList.add('open');
      }catch(err){if(err.name!=='AbortError'){globalResults.innerHTML='<div class="global-empty">No se pudo completar la búsqueda.</div>';global?.classList.add('open')}}
    },260);
  };
  globalInput?.addEventListener('input',runGlobal);globalInput?.addEventListener('focus',()=>{if(globalInput.value.trim().length>=2)runGlobal()});
  globalClear?.addEventListener('click',()=>{globalInput.value='';runGlobal();globalInput.focus()});
  d.addEventListener('click',e=>{if(global&&!global.contains(e.target))closeGlobal()});

  // Modals live directly under <body> so the sticky topbar can never cover them.
  $$('.ui-modal').forEach(modal=>d.body.appendChild(modal));

  // Modals: no close on outside click; X, Cancel or ESC only.
  let activeModal=null;
  const openModal=(id)=>{const modal=d.getElementById(id);if(!modal)return;modal.classList.add('open');modal.setAttribute('aria-hidden','false');d.body.style.overflow='hidden';activeModal=modal;setTimeout(()=>modal.querySelector('input:not([type=hidden]),textarea,select,button')?.focus(),60)};
  const closeModal=(modal)=>{if(!modal)return;modal.classList.remove('open');modal.setAttribute('aria-hidden','true');if(activeModal===modal)activeModal=null;if(!$('.ui-modal.open'))d.body.style.overflow=''};
  $$('[data-modal-open]').forEach(btn=>btn.addEventListener('click',()=>openModal(btn.dataset.modalOpen)));
  $$('[data-modal-close]').forEach(btn=>btn.addEventListener('click',()=>closeModal(btn.closest('.ui-modal'))));
  d.addEventListener('keydown',e=>{if(e.key==='Escape'&&activeModal)closeModal(activeModal)});

  // Reusable searchable / paginated DIV lists.
  $$('[data-list]').forEach(list=>{
    const records=$$('[data-record]',list),search=$('[data-list-search]',list),clear=$('[data-list-clear]',list),sizeSelect=$('[data-list-size]',list),items=$('[data-list-items]',list),pager=$('[data-list-pagination]',list),visibleCount=$('[data-list-visible]',list),empty=$('[data-list-empty]',list),viewButtons=$$('[data-list-view]',list);
    let page=1,size=parseInt(sizeSelect?.value||'12',10)||12,view='mini';
    const matches=()=>{const q=(search?.value||'').trim().toLowerCase();return records.filter(r=>!q||(r.dataset.search||r.textContent||'').toLowerCase().includes(q));};
    const buttons=(pages)=>{if(!pager)return;pager.innerHTML='';if(pages<=1)return;const make=(label,target,disabled=false,active=false)=>{const b=d.createElement('button');b.type='button';b.className='page-btn'+(active?' active':'');b.textContent=label;b.disabled=disabled;b.addEventListener('click',()=>{page=target;render();list.scrollIntoView({behavior:'smooth',block:'start'})});pager.appendChild(b)};make('‹',Math.max(1,page-1),page===1);const slots=[];for(let i=1;i<=pages;i++){if(i===1||i===pages||Math.abs(i-page)<=1)slots.push(i);else if(slots[slots.length-1]!=='…')slots.push('…')}slots.forEach(x=>{if(x==='…'){const s=d.createElement('span');s.className='page-ellipsis';s.textContent='…';pager.appendChild(s)}else make(String(x),x,false,x===page)});make('›',Math.min(pages,page+1),page===pages)};
    const render=()=>{const filtered=matches(),pages=Math.max(1,Math.ceil(filtered.length/size));if(page>pages)page=pages;records.forEach(r=>r.classList.add('hidden'));filtered.slice((page-1)*size,page*size).forEach(r=>r.classList.remove('hidden'));if(visibleCount)visibleCount.textContent=String(filtered.length);empty?.classList.toggle('show',filtered.length===0);if(search)search.closest('.list-search')?.classList.toggle('has-value',search.value.length>0);buttons(pages);items?.classList.toggle('view-detail',view==='detail');items?.classList.toggle('view-mini',view==='mini');viewButtons.forEach(b=>b.classList.toggle('active',b.dataset.listView===view));};
    search?.addEventListener('input',()=>{page=1;render()});clear?.addEventListener('click',()=>{search.value='';page=1;search.focus();render()});sizeSelect?.addEventListener('change',()=>{size=parseInt(sizeSelect.value,10)||12;page=1;render()});viewButtons.forEach(b=>b.addEventListener('click',()=>{view=b.dataset.listView||'mini';render()}));render();
  });

  // Premium file drop zones: choose, drag/drop and paste.
  $$('[data-file-drop]').forEach(zone=>{
    const input=$('input[type=file]',zone),nameEl=$('[data-file-name]',zone),selectBtn=$('[data-file-select]',zone),previewId=zone.dataset.previewTarget,preview=previewId?d.getElementById(previewId):null;
    const update=()=>{const f=input?.files?.[0];if(!f)return;if(nameEl)nameEl.textContent=`${f.name} · ${(f.size/1024/1024).toFixed(f.size>1024*1024?2:3)} MB`;zone.classList.add('has-file');if(preview&&f.type.startsWith('image/')){const reader=new FileReader();reader.onload=()=>{preview.src=String(reader.result);preview.classList.remove('hidden')};reader.readAsDataURL(f)}};
    const setFiles=files=>{if(!input||!files?.length)return;const accept=(zone.dataset.accept||'').toLowerCase();const file=files[0];if(accept&&accept!=='image/*'){const ext='.'+(file.name.split('.').pop()||'').toLowerCase();if(!accept.split(',').map(x=>x.trim()).includes(ext)){showNotify('warning','El archivo no corresponde a un formato permitido.');return}}else if(accept==='image/*'&&!file.type.startsWith('image/')){showNotify('warning','Pega o selecciona una imagen válida.');return}try{const dt=new DataTransfer();dt.items.add(file);input.files=dt.files;update()}catch{showNotify('info','El navegador no permitió pegar el archivo. Usa Seleccionar archivo.')}};
    input?.addEventListener('change',update);selectBtn?.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();input?.click()});
    ['dragenter','dragover'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.add('dragover')}));['dragleave','drop'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.remove('dragover')}));zone.addEventListener('drop',e=>setFiles(e.dataTransfer?.files));zone.addEventListener('paste',e=>{const files=[...(e.clipboardData?.files||[])];if(files.length){e.preventDefault();setFiles(files)}});zone.addEventListener('keydown',e=>{if((e.key==='Enter'||e.key===' ')&&e.target===zone){e.preventDefault();input?.click()}});
  });

  // Employee create/edit modal.
  const employeeForm=$('#employeeForm');
  $('[data-new-employee]')?.addEventListener('click',()=>{if(!employeeForm)return;employeeForm.reset();employeeForm.elements.id.value='';$('[data-employee-modal-title]')?.replaceChildren(d.createTextNode('Nuevo empleado'));employeeForm.querySelector('select[name=status]').value='active';employeeForm.querySelector('select[name=status]').dispatchEvent(new Event('change',{bubbles:true}))});
  $$('[data-edit-employee]').forEach(btn=>btn.addEventListener('click',()=>{if(!employeeForm)return;const x=JSON.parse(btn.dataset.editEmployee||'{}');employeeForm.elements.id.value=x.id||'';employeeForm.elements.badge.value=x.badge||'';employeeForm.elements.name.value=x.name||'';employeeForm.elements.department.value=x.department||'';employeeForm.elements.email.value=x.email||'';employeeForm.elements.status.value=x.status||'active';employeeForm.elements.status.dispatchEvent(new Event('change',{bubbles:true}));$('[data-employee-modal-title]')?.replaceChildren(d.createTextNode('Editar empleado'));openModal('employeeModal')}));

  // User create/edit modal.
  const userForm=$('#userForm');
  $('[data-new-user]')?.addEventListener('click',()=>{if(!userForm)return;userForm.reset();userForm.elements.id.value='';userForm.elements.password.required=true;$$('.new-only',userForm).forEach(x=>x.classList.remove('hidden'));$('[data-password-help]',userForm).textContent='Mínimo 8 caracteres.';$('[data-user-modal-title]')?.replaceChildren(d.createTextNode('Nuevo usuario'));['role','status'].forEach(n=>userForm.elements[n]?.dispatchEvent(new Event('change',{bubbles:true})))});
  $$('[data-edit-user]').forEach(btn=>btn.addEventListener('click',()=>{if(!userForm)return;const x=JSON.parse(btn.dataset.editUser||'{}');userForm.reset();userForm.elements.id.value=x.id||'';userForm.elements.name.value=x.name||'';userForm.elements.email.value=x.email||'';userForm.elements.role.value=x.role||'manager';userForm.elements.status.value=x.status||'active';userForm.elements.password.value='';userForm.elements.password.required=false;$$('.new-only',userForm).forEach(el=>el.classList.add('hidden'));$('[data-password-help]',userForm).textContent='Déjala vacía para conservar la contraseña actual.';['role','status'].forEach(n=>userForm.elements[n]?.dispatchEvent(new Event('change',{bubbles:true})));$('[data-user-modal-title]')?.replaceChildren(d.createTextNode('Editar usuario'));openModal('userModal')}));

  // Question create/edit + public-style preview.
  const questionForm=$('#questionForm');
  const resetQuestion=()=>{if(!questionForm)return;questionForm.reset();questionForm.elements.id.value='';questionForm.elements.points.value='1';questionForm.elements.active.checked=true;const radios=$$('input[name=correct_index]',questionForm);radios.forEach(r=>r.checked=false);const opts=$$('input[name="options[]"]',questionForm);opts.forEach((o,i)=>{o.value='';o.placeholder='Opción '+(i+1)});$('[data-question-modal-title]')?.replaceChildren(d.createTextNode('Nueva pregunta'))};
  $('[data-new-question]')?.addEventListener('click',resetQuestion);
  $$('[data-edit-question]').forEach(btn=>btn.addEventListener('click',()=>{if(!questionForm)return;const x=JSON.parse(btn.dataset.editQuestion||'{}');resetQuestion();questionForm.elements.id.value=x.id||'';questionForm.elements.question_text.value=x.text||'';questionForm.elements.points.value=x.points||1;questionForm.elements.active.checked=!!Number(x.active);const opts=$$('input[name="options[]"]',questionForm),radios=$$('input[name=correct_index]',questionForm);(x.options||[]).forEach((o,i)=>{if(opts[i])opts[i].value=o.option_text||''});if(radios[x.correct_index??0])radios[x.correct_index??0].checked=true;$('[data-question-modal-title]')?.replaceChildren(d.createTextNode('Editar pregunta'));openModal('questionModal')}));
  $$('[data-preview-question]').forEach(btn=>btn.addEventListener('click',()=>{const x=JSON.parse(btn.dataset.previewQuestion||'{}'),root=$('[data-preview-content]');if(root){root.innerHTML=`<div class="preview-shell"><span class="preview-number">PREGUNTA ${esc(x.id||'')}</span><h3>${esc(x.text||'')}</h3><div>${(x.options||[]).map((o,i)=>`<div class="preview-option ${i===Number(x.correct_index)?'correct':''}"><i></i><span>${esc(o.option_text||'')}</span>${i===Number(x.correct_index)?'<small style="margin-left:auto;color:#08765b;font-weight:800">Correcta</small>':''}</div>`).join('')}</div></div>`}openModal('questionPreviewModal')}));

  // Keep switch labels synchronized.
  $$('.premium-switch input').forEach(input=>input.addEventListener('change',()=>{const b=input.closest('.premium-switch')?.querySelector('b');if(b)b.textContent=input.checked?'Activa':'Inactiva'}));

  // Lightweight KPI count-up for numeric values.
  $$('[data-counter]').forEach(el=>{const text=el.textContent.trim();const m=text.match(/^([0-9]+(?:[.,][0-9]+)?)(.*)$/);if(!m)return;const target=parseFloat(m[1].replace(',','.'));if(!Number.isFinite(target)||target>100000)return;const suffix=m[2],decimals=(m[1].split(/[.,]/)[1]||'').length;const start=performance.now(),dur=380;const tick=now=>{const p=Math.min(1,(now-start)/dur),v=target*(1-Math.pow(1-p,3));el.textContent=v.toFixed(decimals)+suffix;if(p<1)requestAnimationFrame(tick)};requestAnimationFrame(tick)});
})();
