<script>
// Las citas que entran por WhatsApp aparecen solas: cada 20 s se compara
// una huella del rango mostrado y, si cambió, se recarga la página.
(function () {
    const firma = @json($firma);
    const url = @json(route('agenda.feed', ['desde' => $desde, 'hasta' => $hasta]));

    setInterval(async () => {
        if (document.hidden || document.querySelector('.dropdown-menu.show')) return;
        try {
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!r.ok) return;
            const datos = await r.json();
            if (datos.firma !== firma) location.reload();
        } catch (e) { /* sin conexión: se reintenta en el siguiente ciclo */ }
    }, 20000);
})();
</script>
