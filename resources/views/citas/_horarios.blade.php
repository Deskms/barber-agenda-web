<script>
// Los horarios los calcula el servidor con la misma lógica que usa WhatsApp;
// aquí solo se piden y se muestran.
(function () {
    const servicio = document.getElementById('servicio');
    const fecha = document.getElementById('fecha');
    const hora = document.getElementById('hora');
    const ayuda = document.getElementById('ayudaHora');
    const base = @json(route('citas.disponibilidad'));
    const ignorar = @json($ignorar);

    async function cargar() {
        const preferida = hora.value || hora.dataset.preferida;
        hora.innerHTML = '<option value="">Cargando…</option>';
        const params = new URLSearchParams({ servicio_id: servicio.value, fecha: fecha.value });
        if (ignorar) params.set('ignorar', ignorar);

        try {
            const r = await fetch(base + '?' + params, { headers: { 'Accept': 'application/json' } });
            const datos = await r.json();
            hora.innerHTML = '';
            if (!datos.horarios || datos.horarios.length === 0) {
                hora.innerHTML = '<option value="">Sin horarios libres ese día</option>';
                ayuda.textContent = 'Prueba con otro día o servicio.';
                return;
            }
            for (const h of datos.horarios) {
                const op = new Option(h.texto, h.valor);
                hora.add(op);
            }
            // Si la hora pedida no cuadra exacto, se elige la siguiente disponible.
            const elegida = datos.horarios.find(h => h.valor >= (preferida || '')) || datos.horarios[0];
            hora.value = elegida.valor;
            ayuda.textContent = datos.horarios.length + ' horarios libres.';
        } catch (e) {
            hora.innerHTML = '<option value="">No se pudieron cargar los horarios</option>';
        }
    }

    servicio.addEventListener('change', () => { hora.dataset.preferida = ''; cargar(); });
    fecha.addEventListener('change', () => { hora.dataset.preferida = ''; cargar(); });
    cargar();
})();
</script>
