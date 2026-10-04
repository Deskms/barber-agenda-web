# Cómo contribuir

## Ramas

- `main` está protegida: solo recibe cambios por pull request con al menos una aprobación.
- Cada historia de Jira se trabaja en su propia rama, nombrada con la clave de la actividad:

```
feature/SCRUM-31-subdividir-hueco
fix/SCRUM-52-hora-mal-calculada
docs/SCRUM-20-glosario
```

## Commits

Mensajes en español, en imperativo, con la clave de Jira al inicio:

```
SCRUM-31 Calcula sub-bloques por duración de servicio
SCRUM-16 Responde 200 en el webhook aunque falle el parseo
```

## Pull requests

1. Abre el PR contra `main` y llena la plantilla.
2. Incluye la clave de Jira en el título para que quede la trazabilidad.
3. Pide revisión a otro integrante; nadie fusiona su propio PR.
4. Antes de fusionar, verifica el criterio de aceptación escrito en la historia de Jira.

## Secretos

Nunca subas tokens, contraseñas ni el archivo `.env`. Si agregas una variable nueva, agrégala vacía a `.env.example`.
