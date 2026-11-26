'use strict';

const slides = [
  {name:'Portada', type:'home'},
  {name:'Equipo Upgrade', topic:'El equipo', title:'Somos Upgrade.', summary:'Equipo de desarrollo de software del grupo IDGS 9°B.', type:'team', items:[
    ['Adrián Dominic Hernández Esqueda',''],['Rodrigo Salas Martínez',''],['José Manuel Castellanos Castro',''],['Jose Francisco Quezada Palomo','']]},
  {name:'Nuestro cliente', topic:'El cliente', title:'Joss Barber salon.', summary:'Barbería y estética en La Loma, Guadalajara. La dueña combina el trabajo en el salón con la organización de las citas.', image:'barberia.webp', alt:'Corte de cabello con máquina en una barbería', photo:true, items:[['La necesidad','Consultar los horarios y tomar reservas mientras atiende a sus clientes.']]},
  {name:'El problema', topic:'La agenda del salón', title:'Una cita entre mensajes.', summary:'Una solicitud puede llegar en medio de un corte. La dueña revisa el horario, responde y sigue pendiente de la confirmación.',items:[
    ['Interrupciones','Cada conversación requiere consultar la disponibilidad.'],['Horarios vacíos','Cuando una clienta cancela, hay que ofrecer ese espacio de nuevo.'],['Seguimiento','Las visitas y los servicios más pedidos son difíciles de consultar en un chat.']]},
  {name:'Nuestra solución',topic:'La propuesta',title:'Una agenda compartida.',summary:'Las citas de los tres canales aparecen en el mismo calendario.',items:[
    ['WhatsApp','La clienta elige un servicio y un horario desde el chat.'],['App Android','Puede reservar y consultar sus próximas visitas.'],['Panel web','La dueña consulta el día y agrega citas desde el mostrador.']]},
  {name:'Conclusión',topic:'Conclusión del reporte',title:'Lo que aprendimos.',summary:'Conocer la rutina de la dueña nos ayudó a decidir qué debía resolver primero la agenda.',items:[
    ['Escuchar al cliente','Identificamos las interrupciones y los horarios que se perdían.'],['Probar en teléfonos reales','Comparamos lo que recibe la clienta con lo que aparece en el panel.'],['Seguir en el salón','El siguiente paso es probar el sistema con el número del negocio.']]},
  {name:'Cómo haremos la demo',topic:'Demostración',title:'Los dos lados de una reserva.',summary:'Primero elige un servicio en la conversación. Después observa cómo la confirmación ocupa ese horario en la agenda.',items:[
    ['Vista de la clienta','Conversación y opciones de reserva.'],['Vista de la dueña','Disponibilidad y citas confirmadas.']],cta:'Abrir demostración'},
  {name:'Reserva por WhatsApp',type:'demo'},
  {name:'Agenda de la dueña',topic:'Panel web',title:'El día en el mostrador.',summary:'La dueña ve las citas por área, registra a quien llega al salón y marca la asistencia.',image:'agenda.png',alt:'Captura de la agenda del salón con columnas por área y citas por horario.',items:[['Seguimiento','Llegó, atendida o no asistió.']]},
  {name:'App para clientes',topic:'Android',title:'La cita en el celular.',summary:'La clienta entra con un código enviado por WhatsApp. Desde su cuenta puede reservar y revisar los datos de su próxima visita.',type:'app'},
  {name:'Cancelaciones',topic:'Disponibilidad',title:'Un horario vuelve a estar libre.',summary:'La dueña puede avisar a las personas que aceptaron recibir actualizaciones.',items:[
    ['Se cancela la cita','El espacio aparece disponible en el panel.'],['Se envía el aviso','Las clientas interesadas pueden responder «AGENDAR».'],['Se asigna el horario','La primera persona en responder obtiene la cita.']]},
  {name:'Tarjeta de sellos',topic:'Fidelización',title:'Una tarjeta que acompaña cada visita.',summary:'En el ejemplo del proyecto, cada visita suma un sello. Al completar diez, la clienta obtiene un corte gratis.',image:'sellos.png',alt:'Captura de la tarjeta digital de diez sellos.',items:[['Avisos y recompensas','La dueña controla el presupuesto de los mensajes promocionales.']]},
  {name:'Reportes',topic:'El negocio',title:'Las cifras del salón.',summary:'Desde el panel se consultan servicios populares, ventas y horarios con más actividad.',image:'reportes.png',alt:'Captura del tablero de reportes del negocio.',items:[['Consulta por periodo','Día, semana o mes. Descarga disponible en PDF y Excel.']]},
  {name:'Acceso y funcionamiento',topic:'Tecnología',title:'Reservas fuera de horario.',summary:'El sistema recibe solicitudes incluso cuando el salón está cerrado.',items:[
    ['WhatsApp Business','La reserva usa la plataforma oficial de Meta.'],['Panel en el navegador','La dueña puede entrar desde celular, tableta o computadora.'],['Pruebas automáticas','El equipo revisa los flujos antes de actualizar el sistema.']]},
  {name:'Puesta en marcha',topic:'Implementación',title:'El siguiente paso en el salón.',summary:'La versión de demostración del sistema usa un número de prueba.',items:[
    ['Configurar','Cargar servicios, áreas, precios y horarios.'],['Conectar','Vincular el número de WhatsApp del negocio.'],['Acompañar','Capacitar al equipo y hacer una prueba piloto con clientes.']]},
  {name:'Entregables',topic:'Resultados',title:'Qué entregamos.',summary:'Barber Agenda reúne las reservas y permite volver a ofrecer los espacios que se liberan.',items:[
    ['Tres formas de reservar','WhatsApp, app Android y panel web.'],['Disponibilidad continua','La clienta puede elegir un horario fuera de la jornada del salón.'],['Versión de prueba','Se desarrolló con herramientas gratuitas y un dominio existente.']]},
  {name:'Preguntas',type:'end'}
];

const $ = id => document.getElementById(id);
const stage = $('escena');
const dialog = $('panel');
let current = -1;
let appTab = 0;
let service = '';
let time = '';
let confirmed = false;
const times = ['10:00','12:30','16:00'];
const labels = ['Agendar','Mis citas','Tarjeta'];
const phoneImages = ['reservar.png','mis-citas.png','sellos.png'];
const number = i => String(i + 1).padStart(2, '0');
const details = items => (items || []).map(([title, body]) => '<div class="detail"><h3>'+title+'</h3>'+(body ? '<p>'+body+'</p>' : '')+'</div>').join('');
const chapters = [[0,'Inicio'],[1,'Equipo'],[2,'Cliente'],[3,'Problema'],[4,'Solución'],[5,'Conclusión'],[6,'Demostración'],[14,'Implementación']];
const rail = $('rail');
rail.innerHTML = chapters.map(([start,title], i) => '<button data-start="'+start+'" aria-label="'+title+'" title="'+title+'"><span>'+number(i)+'</span></button>').join('');
rail.onclick = e => {const b=e.target.closest('[data-start]'); if(b)show(Number(b.dataset.start));};
function opening(){
  const visits = [[2,'El cliente','Joss Barber salon','photo-link'],[7,'Demo interactiva','Reserva por WhatsApp','chat-link'],[8,'El producto','Panel de la dueña','panel-link']];
  const shortcuts=visits.map(([i,topic,title,cls])=>'<button class="shortcut" data-go="'+i+'"><span class="preview '+cls+'" aria-hidden="true"></span><span><small>'+topic+'</small><strong>'+title+'</strong></span><span class="link-chevron" aria-hidden="true">›</span></button>').join('');
  return '<section class="scene hero"><div class="hero-top"><div class="hero-copy"><p class="eyebrow">Upgrade presenta</p><h1>Barber<br><em>Agenda<span>.</span></em></h1><p class="project-kind">Joss Barber salon <span aria-hidden="true">/</span> Agenda digital</p><p class="lead">Los clientes reservan por WhatsApp o desde la app. La dueña ve todas las citas en una sola agenda.</p><div class="action-row"><button class="primary" data-go="1"><span class="play" aria-hidden="true"></span>Iniciar presentación</button><button class="secondary" data-go="7">Probar demo</button></div></div><figure class="visual orbit-art" aria-label="Logotipo de Upgrade con acabado metálico"><div class="orbit-deck" aria-hidden="true"><i></i><i></i><i></i><b></b></div><img class="sculpture" src="assets/emblema.svg" alt="Monograma de Upgrade"><img class="hero-signature" src="assets/firma.svg" alt="Upgrade"></figure></div><div class="shortcuts">'+shortcuts+'</div></section>';
}
const symbols = {
 'WhatsApp':'◌','App Android':'▣','Panel web':'▤','Interrupciones':'◷','Horarios vacíos':'▦','Seguimiento':'≋',
 'Tres formas de reservar':'3','Disponibilidad continua':'24/7','Versión de prueba':'$0'
};
function section(s){
  const app = s.type==='app';
  const media = s.image || app;
  const team = s.type==='team';
  const flow = [6,10,14].includes(current);
  const mode = media?'case':team?'people':flow?'process':current===5?'lesson':'gallery';
  const cards=(s.items||[]).map(([title,body],i)=>{
    const symbol=team?['DH','RS','MC','FQ'][i]:flow?number(i):symbols[title]||['◈','▤','◎'][i];
    return '<article class="feature"><span class="mark" aria-hidden="true">'+symbol+'</span><h3>'+title+'</h3>'+(body?'<p>'+body+'</p>':'')+'</article>';
  }).join('');
  const tabs = app?'<div class="screen-picker" aria-label="Pantallas de la app">'+labels.map((v,i)=>'<button class="choice" data-tab="'+i+'" aria-pressed="'+(appTab===i)+'">'+v+'</button>').join('')+'</div>':'';
  const narrative=notas[current].traduccion ? '<p class="audience-copy" lang="es">'+notas[current].traduccion+'</p>' : '<p class="summary">'+s.summary+'</p>';
  const copy='<div class="section-copy"><p class="eyebrow">'+s.topic+'</p><h1 class="section-title">'+s.title+'</h1>'+narrative+tabs+(s.image?details(s.items):'')+'</div>';
  const art=media?'<figure class="case-media"><img class="'+(s.photo?'photo':'product-image')+'" src="assets/'+(app?phoneImages[appTab]:s.image)+'" alt="'+(app?'Pantalla de la app: '+labels[appTab]:s.alt)+'"></figure>':'<div class="features">'+cards+'</div>';
  const cta=s.cta?'<div class="action-row"><button class="primary" data-go="7">'+s.cta+'</button></div>':'';
  return '<section class="scene spread '+mode+'"><div class="spread-inner">'+copy+art+cta+'</div></section>';
}
function chat(){
  let content='<div class="bubble">Hola, soy el asistente de Joss Barber salon. ¿Qué servicio quieres reservar?</div>';
  if(!service) return content+'<div class="choices">'+['Corte de caballero','Manicura'].map(s=>'<button class="choice" data-service="'+s+'">'+s+'</button>').join('')+'</div>';
  content+='<div class="bubble out">'+service+'</div>';
  if(!time) return content+'<div class="bubble">Estos son los horarios disponibles para el sábado. ¿Cuál prefieres?</div><div class="choices">'+times.map(t=>'<button class="choice" data-time="'+t+'">'+t+'</button>').join('')+'</div>';
  content+='<div class="bubble out">Sábado a las '+time+'</div>';
  return content+(confirmed?'<div class="bubble booked">Cita confirmada. Te esperamos en el salón.</div>':'<div class="bubble">¿Confirmas tu cita para '+service.toLowerCase()+'?</div><button class="primary" data-confirm>Confirmar cita</button>');
}
function agenda(){
  return '<span class="meta">Sábado · Agenda del salón</span>'+times.map(t=>{
    const filled=confirmed&&t===time;
    return '<div class="slot'+(filled?' booked':'')+'"><time>'+t+'</time><span>'+(filled?service+' · Confirmada':'Disponible')+'</span></div>';
  }).join('');
}
function demo(){
  return '<section class="scene"><div class="module"><div class="demo-head"><div><p class="eyebrow">Simulación local</p><h1 class="section-title">Reserva por WhatsApp.</h1></div><button class="small-button" data-reset>Reiniciar demo</button></div><div class="demo-grid"><div class="demo-card"><h3>Chat de la clienta</h3><div id="chat" class="card-body">'+chat()+'</div></div><div class="demo-card"><h3>Panel de la dueña</h3><div id="agenda" class="card-body">'+agenda()+'</div></div></div></div></section>';
}
function resetDemo(){ service=''; time=''; confirmed=false; }
function updateDemo(focus=true){
  $('chat').innerHTML=chat();
  $('agenda').innerHTML=agenda();
  if(focus) ($('chat').querySelector('button')||stage.querySelector('[data-reset]')).focus({preventScroll:true});
  $('aviso').textContent=confirmed?'Cita confirmada en la agenda para el sábado a las '+time:time?'Horario elegido. Confirma la cita.':service?'Elige un horario.':'Demostración reiniciada.';
}
function closing(){
  return '<section class="scene closing"><div class="layout"><div><p class="eyebrow">Barber Agenda</p><h1 class="final-title">¿Preguntas?</h1><p class="summary">Gracias por acompañar al equipo Upgrade.</p><div class="action-row"><button class="primary" data-go="7">Volver a la demo</button><button class="secondary" data-go="0">Ir a portada</button></div></div><div class="visual"><img src="assets/insignia.svg" alt="Upgrade"></div></div></section>';
}
function show(i, focus=true){
  if(!Number.isInteger(i)||i<0||i>=slides.length||i===current)return;
  current=i;
  const chapter=chapters.filter(([start])=>start<=i).at(-1)[0];
  rail.querySelectorAll('button').forEach(button=>button.setAttribute('aria-current',Number(button.dataset.start)===chapter?'step':'false'));
  const slide=slides[i];
  stage.innerHTML=i===0?opening():slide.type==='demo'?demo():i===16?closing():section(slide);
  $('contador').innerHTML=number(i)+' <small>/ '+slides.length+'</small>';
  $('progreso').innerHTML=slides.map((_,n)=>n<=i?'<i class="on"></i>':'<i></i>').join('');
  $('capitulo').textContent=slide.name;
  $('prev').disabled=i===0;
  $('next').disabled=i===slides.length-1;
  if(i>0||focus)location.replace('#seccion-'+(i+1));
  if(focus){stage.focus({preventScroll:true});window.scrollTo(0,0);}
  $('aviso').textContent='Diapositiva '+(i+1)+' de '+slides.length+'. '+slide.name;
}
stage.addEventListener('click',event=>{
  const b=event.target.closest('button');
  if(!b)return;
  if(b.hasAttribute('data-go'))return show(Number(b.dataset.go));
  if(b.hasAttribute('data-tab')){
    appTab=Number(b.dataset.tab);
    stage.querySelectorAll('[data-tab]').forEach(el=>el.setAttribute('aria-pressed',el===b));
    const img=stage.querySelector('.product-image');img.src='assets/'+phoneImages[appTab];img.alt='Pantalla de la app: '+labels[appTab];return;
  }
  if(slides[current].type!=='demo')return;
  if(b.hasAttribute('data-reset'))resetDemo();
  else if(b.dataset.service)service=b.dataset.service;
  else if(b.dataset.time)time=b.dataset.time;
  else if(b.hasAttribute('data-confirm')&&service&&time)confirmed=true;
  else return;
  updateDemo();
});
function openPanel(mode){
  $('panel-title').textContent=mode==='indice'?'Índice':'Guion de exposición';
  if(mode==='indice'){
    $('panel-body').innerHTML='<div class="toc">'+slides.map((s,i)=>'<button data-jump="'+i+'" aria-current="'+(i===current)+'"><span>'+number(i)+'</span>'+s.name+'</button>').join('')+'</div>';
  }else{
    const n=notas[current];
    $('panel-body').replaceChildren();
    [['speaker',n.voz+' · '+n.idioma],['script',n.texto],['cue',n.indicacion]].forEach(([cls,value])=>{
      if(!value)return;
      const p=document.createElement('p');p.className=cls;p.textContent=value;
      if(cls==='script')p.lang=n.idioma==='Inglés'?'en':'es';
      $('panel-body').append(p);
    });
  }
  dialog.setAttribute('aria-labelledby','panel-title');dialog.showModal();dialog.scrollTop=0;
}
$('indice').onclick=()=>openPanel('indice');$('notas').onclick=()=>openPanel('guion');
$('cerrar').onclick=()=>dialog.close();
dialog.addEventListener('click',e=>{
  const b=e.target.closest('[data-jump]');if(b){dialog.close();show(Number(b.dataset.jump));}
  else if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}
});
$('prev').onclick=()=>show(current-1);$('next').onclick=()=>show(current+1);$('inicio').onclick=()=>show(0);
$('pantalla').hidden=!document.fullscreenEnabled;
$('pantalla').onclick=async()=>{
  try{if(document.fullscreenElement)await document.exitFullscreen();else await document.documentElement.requestFullscreen();}
  catch{ $('aviso').textContent='La pantalla completa no está disponible en esta ventana.';}
};
document.addEventListener('fullscreenchange',()=>{const label=document.fullscreenElement?'Salir de pantalla completa':'Pantalla completa';$('pantalla').setAttribute('aria-label',label);$('pantalla').title=label;});
document.addEventListener('keydown',e=>{
  if(e.defaultPrevented||dialog.open||e.altKey||e.ctrlKey||e.metaKey)return;
  const key=e.key;
  if(key==='ArrowRight'||key==='PageDown'||(key===' '&&!e.target.closest('button'))){e.preventDefault();show(current+1);}
  else if(key==='ArrowLeft'||key==='PageUp'){e.preventDefault();show(current-1);}
  else if(key==='Home'||key==='End'){e.preventDefault();show(key==='Home'?0:slides.length-1);}
});
function readHash(){const m=/^#seccion-(\d+)$/.exec(location.hash);const i=m?Number(m[1])-1:0;return i>=0&&i<slides.length?i:0;}
window.addEventListener('hashchange',()=>show(readHash(),false));
show(readHash(),false);
