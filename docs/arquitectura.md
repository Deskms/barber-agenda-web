# Arquitectura

```
Dueña ───► Página web (panel) ──┐
                                ├──► API Laravel ──► PostgreSQL
Clienta ─► Página web ──────────┤    (reglas de       (única escritura
                                │    disponibilidad)   desde la API)
Clienta ─► App Android ─────────┘
```

El sistema tiene dos canales: la página web y la app Android. **No usa WhatsApp.**

## Decisiones

- **Monolito modular, no microservicios.** Un equipo de cuatro con un mes de plazo no se beneficia de desplegar servicios por separado. Los módulos (Agenda, Panel) se separan por carpetas y servicios de dominio.
- **La API es la única que escribe en la base de datos.** La web y la app solo envían peticiones; Laravel valida la disponibilidad y crea o cancela la cita.
- **Un aviso de espacio liberado no reserva.** Siempre se revalida la disponibilidad antes de crear la cita.
