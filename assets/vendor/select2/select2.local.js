(function($,w,d){
  if(!$)return;
  const instances=new Set();
  function init(select,opts){
    if(!select||select.dataset.s2Ready)return;
    select.dataset.s2Ready='1';opts=opts||{};select.classList.add('s2-hidden-accessible');
    const box=d.createElement('div');box.className='s2-container';
    const btn=d.createElement('button');btn.type='button';btn.className='s2-selection';btn.setAttribute('aria-haspopup','listbox');btn.setAttribute('aria-expanded','false');btn.innerHTML='<span class="s2-label"></span><span class="s2-arrow" aria-hidden="true"></span>';
    box.appendChild(btn);select.insertAdjacentElement('afterend',box);
    const drop=d.createElement('div');drop.className='s2-dropdown s2-dropdown-portal';drop.setAttribute('role','listbox');d.body.appendChild(drop);
    let search=null,list=null;
    const selectedText=()=>{const o=select.options[select.selectedIndex];return o?o.text:''};
    const position=()=>{if(!box.classList.contains('open'))return;const r=btn.getBoundingClientRect(),gap=6,viewportH=w.innerHeight||d.documentElement.clientHeight;drop.style.width=Math.max(180,r.width)+'px';drop.style.left=Math.max(8,Math.min(r.left,(w.innerWidth||d.documentElement.clientWidth)-Math.max(180,r.width)-8))+'px';const h=Math.min(drop.scrollHeight||280,280),below=viewportH-r.bottom-gap,above=r.top-gap;if(below<Math.min(180,h)&&above>below){drop.classList.add('drop-up');drop.style.top='auto';drop.style.bottom=Math.max(8,viewportH-r.top+gap)+'px'}else{drop.classList.remove('drop-up');drop.style.bottom='auto';drop.style.top=Math.min(viewportH-70,r.bottom+gap)+'px'}};
    const fill=q=>{if(!list)return;list.innerHTML='';q=(q||'').toLowerCase();Array.from(select.options).forEach((o,i)=>{if(q&&!o.text.toLowerCase().includes(q))return;const item=d.createElement('button');item.type='button';item.className='s2-option'+(i===select.selectedIndex?' selected':'');item.textContent=o.text;item.disabled=o.disabled;item.setAttribute('role','option');item.setAttribute('aria-selected',i===select.selectedIndex?'true':'false');item.onclick=()=>{select.selectedIndex=i;select.dispatchEvent(new Event('change',{bubbles:true}));close()};list.appendChild(item)});if(!list.children.length){const empty=d.createElement('div');empty.className='s2-empty';empty.textContent='Sin resultados';list.appendChild(empty)};position()};
    const render=()=>{drop.innerHTML='';const threshold=Number(opts.minimumResultsForSearch??8);search=null;if(threshold===0||select.options.length>=threshold){search=d.createElement('input');search.type='search';search.className='s2-search';search.placeholder=select.dataset.select2Placeholder||'Buscar...';drop.appendChild(search)}list=d.createElement('div');list.className='s2-options';drop.appendChild(list);fill('');if(search)search.addEventListener('input',()=>fill(search.value))};
    function sync(){btn.querySelector('.s2-label').textContent=selectedText();btn.disabled=!!select.disabled;if(box.classList.contains('open'))render()}
    function open(){if(btn.disabled)return;instances.forEach(x=>{if(x!==api)x.close()});box.classList.add('open');drop.classList.add('open');btn.setAttribute('aria-expanded','true');render();position();setTimeout(()=>search?.focus(),10)}
    function close(){box.classList.remove('open');drop.classList.remove('open');btn.setAttribute('aria-expanded','false');if(search)search.value=''}
    btn.addEventListener('click',()=>box.classList.contains('open')?close():open());
    select.addEventListener('change',sync);
    const onDoc=e=>{if(!box.contains(e.target)&&!drop.contains(e.target))close()};d.addEventListener('mousedown',onDoc);
    const onViewport=()=>{if(box.classList.contains('open'))position()};w.addEventListener('resize',onViewport);w.addEventListener('scroll',onViewport,true);
    const api={close,position,select,box,drop};instances.add(api);sync();
  }
  $.fn.select2=function(opts){return this.each(function(){init(this,opts)})};
})(window.jQuery,window,document);
