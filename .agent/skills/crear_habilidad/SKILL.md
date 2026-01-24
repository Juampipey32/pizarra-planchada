---
name: crear_habilidad
description: Crea una nueva habilidad en el espacio de trabajo siguiendo los estándares.
---

# Crear Habilidad

Esta habilidad te guía en el proceso de creación de una nueva habilidad para Antigravity.

## Instrucciones

1.  Ejecuta el script de creación:
    ```bash
    node .agent/skills/crear_habilidad/scripts/crear.js
    ```
2.  Sigue las instrucciones en pantalla para proporcionar:
    -   **Nombre de la carpeta**: El nombre técnico de la habilidad (usar kebab-case, ej: `mi-nueva-habilidad`).
    -   **Nombre legible**: El nombre humano de la habilidad (ej: `Mi Nueva Habilidad`).
    -   **Descripción**: Una breve descripción de lo que hace la habilidad.

El script generará automáticamente la estructura de directorios y el archivo `SKILL.md` base.
