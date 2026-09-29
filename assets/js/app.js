(()=>{
  'use strict';
  const $=s=>document.querySelector(s);
  const badge=$('#badge'),continueBtn=$('#continueBtn'),preview=$('#employeePreview'),identify=$('#identifyStep'),confirmStep=$('#identityConfirmStep'),quiz=$('#quizStep');
  let timer=null,employee=null,evaluation=null,current=0,resumeHint=false;
  const answers={};
  const escapeHtml=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const initials=name=>{const p=String(name||'').trim().split(/\s+/).filter(Boolean);return ((p[0]?.[0]||'U')+(p.length>1?p[p.length-1][0]:(p[0]?.[1]||''))).toUpperCase()};
  const resetContinue=()=>{continueBtn.disabled=!employee;continueBtn.innerHTML='<span>Continuar</span><span>→</span>'};

  badge?.addEventListener('input',()=>{
    clearTimeout(timer);employee=null;resumeHint=false;resetContinue();preview.classList.add('hidden');
    const v=badge.value.trim();if(!v)return;
    timer=setTimeout(async()=>{
      try{
        const r=await fetch('api/employee.php?badge='+encodeURIComponent(v),{headers:{'X-Requested-With':'fetch'}});const j=await r.json();if(!r.ok)throw new Error(j.message||'No encontrado');
        employee=j.employee;resumeHint=!!j.resume;
        preview.innerHTML=`<div class="avatar">${escapeHtml(initials(employee.name))}</div><div><strong>${escapeHtml(employee.name)}</strong><span>Gafete ${escapeHtml(employee.badge)}${employee.department?' · '+escapeHtml(employee.department):''}</span></div><span class="check">✓</span>`;
        preview.classList.remove('hidden');resetContinue();
      }catch(e){employee=null;resetContinue();preview.innerHTML=`<div class="error-box">${escapeHtml(e.message)}</div>`;preview.classList.remove('hidden');}
    },300);
  });

  continueBtn?.addEventListener('click',()=>{
    if(!employee)return;
    renderIdentityConfirmation(employee,resumeHint);
  });

  function renderIdentityConfirmation(emp,resumed){
    identify.classList.add('hidden');quiz.classList.add('hidden');confirmStep.classList.remove('hidden');
    confirmStep.innerHTML=`<div class="step-head"><span class="step-pill">Paso 2 de 3</span><h2>Identidad confirmada</h2><p>Verifica que tus datos sean correctos antes de comenzar.</p></div><div class="identity-confirm-card"><span class="identity-avatar">${escapeHtml(initials(emp.name))}</span><div class="identity-copy"><span class="hello-label">HOLA</span><h3>${escapeHtml(emp.name)}</h3><p>Gafete ${escapeHtml(emp.badge)}${emp.department?' · '+escapeHtml(emp.department):''}</p></div><span class="identity-check">✓</span></div><div class="evaluation-summary"><div><strong>✓</strong><span>identidad confirmada</span></div><div><strong>1</strong><span>participación</span></div><div><strong>${resumed?'Continuar':'Nuevo'}</strong><span>${resumed?'intento iniciado':'intento'}</span></div></div>${resumed?'<div class="resume-note">Ya habías iniciado esta evaluación. Continuarás con exactamente las mismas preguntas.</div>':''}<div class="wizard-actions"><button type="button" class="btn btn-light" id="backToBadge">← Corregir gafete</button><button type="button" class="btn btn-primary no-top" id="startQuiz">${resumed?'Continuar evaluación':'Comenzar evaluación'} <span>→</span></button></div>`;
    $('#backToBadge')?.addEventListener('click',()=>{confirmStep.classList.add('hidden');identify.classList.remove('hidden');resetContinue();badge.focus();});
    $('#startQuiz')?.addEventListener('click',async e=>{
      const start=e.currentTarget;start.disabled=true;start.innerHTML='<span class="spinner"></span><span>Preparando...</span>';
      try{const fd=new FormData();fd.append('csrf',APP.csrf);fd.append('badge',emp.badge);const r=await fetch('api/start.php',{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}});const j=await r.json();if(!r.ok)throw new Error(j.message||'No se pudo iniciar');evaluation=j;current=0;renderWizard();}
      catch(err){showNotify('error',err.message);start.disabled=false;start.innerHTML=`${resumed?'Continuar evaluación':'Comenzar evaluación'} <span>→</span>`;}
    });
    document.querySelector('#evaluacion')?.scrollIntoView({behavior:'smooth',block:'start'});
  }

  function renderWizard(){
    if(!evaluation?.questions?.length)return;
    confirmStep.classList.add('hidden');identify.classList.add('hidden');quiz.classList.remove('hidden');renderQuestion();
  }

  function renderQuestion(){
    const questions=evaluation.questions,q=questions[current],total=questions.length,answered=Object.keys(answers).length,pending=Math.max(0,total-answered),progress=Math.round((answered/total)*100),selected=answers[q.id]||'';
    quiz.innerHTML=`<div class="quiz-wizard-head"><div class="quiz-user"><span class="quiz-avatar">${escapeHtml(initials(evaluation.employee.name))}</span><div><strong>${escapeHtml(evaluation.employee.name)}</strong><small>Gafete ${escapeHtml(evaluation.employee.badge)}</small></div></div><div class="quiz-progress-copy"><span>Pregunta <strong>${current+1}</strong> de <strong>${total}</strong></span><small>${answered} respondida${answered===1?'':'s'} · ${pending} pendiente${pending===1?'':'s'}</small></div></div><div class="quiz-progress"><i style="width:${progress}%"></i></div><div class="question-stage"><div class="question-num">Pregunta ${current+1} de ${total}</div><h3>${escapeHtml(q.text)}</h3><div class="options">${q.options.map((o,i)=>`<label class="option"><input type="radio" name="wizard_answer" value="${o.id}" ${String(selected)===String(o.id)?'checked':''}><span class="radio"></span><span><b class="option-letter">${String.fromCharCode(65+i)}</b>${escapeHtml(o.text)}</span></label>`).join('')}</div></div><div class="wizard-footer"><button type="button" class="btn btn-light" id="prevQuestion" ${current===0?'disabled':''}>← Anterior</button><div class="wizard-step-dots">${total<=12?questions.map((item,i)=>`<span class="${i===current?'current':''} ${answers[item.id]?'done':''}" title="Pregunta ${i+1}"></span>`).join(''):`<small>${answered} de ${total} respondidas</small>`}</div><button type="button" class="btn btn-primary no-top" id="nextQuestion" ${selected?'':'disabled'}>${current===total-1?'Finalizar evaluación':'Siguiente'} <span>${current===total-1?'✓':'→'}</span></button></div>`;
    document.querySelectorAll('input[name="wizard_answer"]').forEach(input=>input.addEventListener('change',()=>{answers[q.id]=input.value;renderQuestion();}));
    $('#prevQuestion')?.addEventListener('click',()=>{if(current>0){current--;renderQuestion();}});
    $('#nextQuestion')?.addEventListener('click',async()=>{
      if(!answers[q.id]){showNotify('warning','Selecciona una respuesta para continuar.');return;}
      if(current<total-1){current++;renderQuestion();return;}
      if(Object.keys(answers).length!==total){showNotify('warning','Aún faltan preguntas por responder.');return;}
      const result=await Swal.fire({icon:'question',title:'¿Enviar evaluación?',text:'Tus respuestas quedarán registradas y no podrás volver a contestar esta evaluación.',showCancelButton:true,confirmButtonText:'Sí, enviar',cancelButtonText:'Revisar respuestas',allowOutsideClick:false});
      if(result.isConfirmed)submitQuiz();
    });
  }

  async function submitQuiz(){
    const questions=evaluation.questions,total=questions.length,next=$('#nextQuestion');if(next){next.disabled=true;next.innerHTML='<span class="spinner"></span><span>Guardando...</span>';}
    const fd=new FormData();fd.append('csrf',APP.csrf);fd.append('token',evaluation.token);questions.forEach(q=>fd.append(`answers[${q.id}]`,answers[q.id]||''));
    try{
      const r=await fetch('api/submit.php',{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}});const j=await r.json();if(!r.ok)throw new Error(j.message||'No se pudo guardar');
      quiz.innerHTML=`<div class="success-screen"><div class="success-icon">✓</div><p class="eyebrow">EVALUACIÓN COMPLETADA</p><h2>${escapeHtml(j.message)}</h2><div class="score-ring"><strong>${Number(j.score).toFixed(0)}</strong><span>/100</span></div><p>Respuestas correctas: <strong>${j.correct} de ${j.total}</strong></p><small>Gracias, ${escapeHtml(evaluation.employee.name)}. Tu participación quedó registrada.</small></div>`;
      showNotify('success','Tu evaluación fue registrada correctamente.');document.querySelector('#evaluacion')?.scrollIntoView({behavior:'smooth',block:'start'});
    }catch(e){showNotify('error',e.message);renderQuestion();}
  }
})();
