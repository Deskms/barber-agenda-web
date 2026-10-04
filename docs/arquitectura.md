# Arquitectura

```
Dueña ──► Panel web (Blade) ──┐
                              │
Clienta ─► App móvil (Expo) ──┼──► API Laravel ──► PostgreSQL
                              │   (reglas de       (única escritura
Clienta ─► WhatsApp ─► Meta ──┘    disponibilidad)   desde la API)
           Cloud API (webhook)
```

## Decisiones

- **Monolito modular, no microservicios.** Un equipo de cuatro con un mes de plazo no se beneficia de desplegar servicios por separado. Los módulos (Agenda, WhatsApp, Panel) se separan por carpetas y servicios de dominio.
- **La API es la única que escribe en la base de datos.** WhatsApp y, en el futuro, una capa de interpretación con IA solo envían intenciones; Laravel valida la disponibilidad y crea o cancela la cita.
- **Un aviso de espacio liberado no reserva.** Siempre se revalida la disponibilidad antes de crear la cita.
- **Pruebas solo con el número de Meta.** No se toca el WhatsApp real del negocio durante el cuatrimestre.

El diagrama completo está en el documento de la Práctica 1 (Figura 1).
