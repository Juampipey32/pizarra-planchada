const fs = require('fs');
const path = require('path');
const readline = require('readline');

const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

const question = (query) => new Promise((resolve) => rl.question(query, resolve));

async function createSkill() {
    console.log("--- Creador de Habilidades Antigravity ---");

    let skillDirName = await question("Nombre de la carpeta de la habilidad (kebab-case, ej: deploy-app): ");
    skillDirName = skillDirName.trim();
    if (!skillDirName) {
        console.log("El nombre de la carpeta es obligatorio.");
        rl.close();
        return;
    }

    let readableName = await question("Nombre legible de la habilidad (ej: Deploy App): ");
    readableName = readableName.trim();
    if (!readableName) {
        readableName = skillDirName;
    }

    let description = await question("Descripción de la habilidad: ");
    description = description.trim();
    if (!description) {
        description = "Descripción pendiente.";
    }

    // Definir rutas
    const basePath = path.join(".agent", "skills", skillDirName);
    const scriptsPath = path.join(basePath, "scripts");
    const resourcesPath = path.join(basePath, "resources");
    const skillMdPath = path.join(basePath, "SKILL.md");

    // Crear directorios
    try {
        fs.mkdirSync(scriptsPath, { recursive: true });
        fs.mkdirSync(resourcesPath, { recursive: true });
        console.log(`Directorios creados en: ${basePath}`);
    } catch (e) {
        console.log(`Error al crear directorios: ${e}`);
        rl.close();
        return;
    }

    // Crear SKILL.md
    const skillContent = `---
name: ${skillDirName}
description: ${description}
---

# ${readableName}

${description}

## Instrucciones

Describe aquí paso a paso cómo usar esta habilidad.
`;

    try {
        fs.writeFileSync(skillMdPath, skillContent, 'utf8');
        console.log(`Archivo SKILL.md creado en: ${skillMdPath}`);
    } catch (e) {
        console.log(`Error al escribir SKILL.md: ${e}`);
        rl.close();
        return;
    }

    console.log("\n¡Habilidad creada exitosamente!");
    console.log(`Ruta: ${basePath}`);
    console.log("No olvides agregar tus scripts en la carpeta 'scripts' y documentarlos en 'SKILL.md'.");
    rl.close();
}

createSkill();
