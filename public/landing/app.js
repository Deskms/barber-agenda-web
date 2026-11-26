const $ = (selector) => document.querySelector(selector);
const cfg = window.JOSS_CONTACT;
const menu = $('.menu-btn');
const nav = $('#nav');
function setMenu(open) {
  nav.classList.toggle('open', open);
  menu.setAttribute('aria-expanded', String(open));
  menu.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
  menu.querySelector('use').setAttribute('href', open ? '#i-x' : '#i-menu');
}
menu.onclick = () => setMenu(!nav.classList.contains('open'));
nav.querySelectorAll('a').forEach(link => link.onclick = () => setMenu(false));
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && nav.classList.contains('open')) { setMenu(false); menu.focus(); }
});
const links = [...nav.querySelectorAll('a')];
function highlight() {
  const index = links.findLastIndex(link => $(link.hash).getBoundingClientRect().top < window.innerHeight * .42);
  const atEnd = Math.ceil(scrollY + innerHeight) >= document.documentElement.scrollHeight - 2;
  const current = atEnd ? links.at(-1) : links[Math.max(index,0)];
  links.forEach(link => {
    link.classList.toggle('active', link === current);
    if (link === current) link.setAttribute('aria-current','location'); else link.removeAttribute('aria-current');
  });
}
window.addEventListener('scroll',highlight,{passive:true});
window.addEventListener('resize', () => { if (innerWidth > 960) setMenu(false); highlight(); });
highlight();
$('#address').textContent = cfg.address || 'Dirección pendiente de publicación.';
$('#hours').textContent = cfg.hours || 'Horarios pendientes de publicación.';
if (cfg.mapsUrl) { $('#maps').href = cfg.mapsUrl; $('#maps').hidden = false; }
function openDialog(element) { setMenu(false); element.showModal(); document.body.classList.add('locked'); }
document.querySelectorAll('dialog').forEach(dialog => {
  dialog.querySelector('.close').onclick = () => dialog.close();
  dialog.addEventListener('close', () => document.body.classList.remove('locked'));
  dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
});
const finishes = {
  clasico: {
    title: 'Laterales cortos y volumen arriba', photo: 0, service: 'Corte de caballero',
    description: 'El cabello queda más corto en los lados y conserva largo arriba para peinarlo hacia atrás o de lado.',
    request: 'Quiero un degradado bajo y suficiente largo arriba para marcar la raya.'
  },
  textura: {
    title: 'Textura y movimiento', photo: 2, service: 'Corte de caballero',
    description: 'Los mechones de arriba conservan movimiento. Un contorno más corto deja que la textura del cabello sea lo que destaque.',
    request: 'Me gustaría conservar la textura arriba y limpiar los contornos alrededor de las orejas.'
  },
  ondas: {
    title: 'Ondas sueltas', photo: 3, service: 'Estética y peinado',
    description: 'Las ondas amplias aportan forma al cabello largo. Decide si las prefieres desde la raíz o solo de medios a puntas.',
    request: 'Busco ondas suaves de medios a puntas, con un acabado natural.'
  }
};
let finish = finishes.clasico;
document.querySelectorAll('[name="finish"]').forEach(input => {
  input.addEventListener('change', () => {
    finish = finishes[input.value];
    $('#guide-photo').src = 'assets/estilo-' + finish.photo + '.webp';
    $('#guide-photo').alt = 'Referencia de ' + finish.title.toLowerCase();
    $('#guide-name').textContent = finish.title;
    $('#guide-description').textContent = finish.description;
    $('#guide-request').textContent = '“' + finish.request + '”';
    $('#guide-book').dataset.service = finish.service;
  });
});
const service = $('#service'), message = $('#message'), wa = $('#wa');
function updateLink() {
  if (cfg.whatsapp) wa.href = 'https://wa.me/' + cfg.whatsapp + '?text=' + encodeURIComponent(message.value);
}
function draft() {
  message.value = 'Hola, Joss Barber. ' + (service.value ? 'Quiero consultar una cita para ' + service.value.toLowerCase() + '.' : 'Me gustaría agendar una cita.') + ' ¿Qué horarios tienen disponibles?';
  $('#status').textContent = ''; updateLink();
}
document.querySelectorAll('.booking,.service-book').forEach(button => button.onclick = () => {
  service.value = button.dataset.service || '';
  draft();
  if (button.id === 'guide-book') {
    message.value = 'Hola, Joss Barber. Quiero agendar una cita. ' + finish.request + ' ¿Qué horarios tienen disponibles?';
    updateLink();
  }
  openDialog($('#appointment'));
});
service.onchange = draft; message.oninput = updateLink;
wa.hidden = !cfg.whatsapp;
if (!cfg.whatsapp && cfg.mapsUrl) {
  $('#visit-link').href = cfg.mapsUrl;
  $('#visit-link').hidden = false;
}
$('#booking-info').textContent = cfg.whatsapp ? 'Al abrir WhatsApp, nuestro asistente te muestra los horarios libres y aparta tu cita al momento.' : 'Por ahora, consulta la disponibilidad en el local de La Loma. El contacto directo por WhatsApp estará disponible próximamente.';
$('#copy').onclick = async () => {
  try { await navigator.clipboard.writeText(message.value); $('#status').textContent = 'Mensaje copiado.'; }
  catch { message.focus(); message.select(); $('#status').textContent = 'Mensaje seleccionado. Usa la opción Copiar de tu dispositivo.'; }
};
const looks = ['Degradado clásico','Perfilado de barba','Corte con textura','Ondas y peinado','Manicura natural'];
let selected = 0;
function showLook(index) {
  selected = (index + looks.length) % looks.length;
  $('#large-photo').src = 'assets/estilo-' + selected + '.webp';
  $('#large-photo').alt = looks[selected];
  $('#light-title').textContent = looks[selected];
}
looks.forEach((name, i) => {
  const shot = document.createElement('button');
  shot.className = 'shot';
  shot.setAttribute('aria-label','Ampliar foto de ' + name.toLowerCase());
  shot.innerHTML = '<span class="frame"><img loading="lazy" alt="" src="assets/estilo-' + i + '.webp"><span class="expand"><svg aria-hidden="true"><use href="#i-plus"/></svg></span></span><strong></strong>';
  shot.querySelector('strong').textContent = name;
  shot.onclick = () => { showLook(i); openDialog($('#lightbox')); };
  $('#photos').append(shot);
});
$('#prev').onclick = () => showLook(selected - 1);
$('#next').onclick = () => showLook(selected + 1);
$('#lightbox').onkeydown = event => {
  if (event.key === 'ArrowLeft') showLook(selected - 1);
  if (event.key === 'ArrowRight') showLook(selected + 1);
};
