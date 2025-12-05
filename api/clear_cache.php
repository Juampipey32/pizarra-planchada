<?php
// Script to clear PHP opcache - Run once and delete
header('Content-Type: text/plain');

echo "=== Limpieza de Caché PHP ===\n\n";

// Clear opcache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✅ OPcache limpiado exitosamente\n";
    } else {
        echo "❌ No se pudo limpiar OPcache\n";
    }

    echo "\nEstado de OPcache:\n";
    if (function_exists('opcache_get_status')) {
        $status = opcache_get_status(false);
        echo "- Habilitado: " . ($status ? 'Sí' : 'No') . "\n";
        if ($status) {
            echo "- Archivos en caché: " . $status['num_cached_scripts'] . "\n";
            echo "- Memoria usada: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
        }
    }
} else {
    echo "⚠️  OPcache no está habilitado en este servidor\n";
}

// Clear realpath cache
clearstatcache(true);
echo "\n✅ Realpath cache limpiado\n";

echo "\n=== Verificación de archivos ===\n";
echo "upload.php modificado: " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/bookings/upload.php')) . "\n";

$uploadContent = file_get_contents(__DIR__ . '/bookings/upload.php');
if (strpos($uploadContent, 'Fixed PDF parsing logic - v2.0') !== false) {
    echo "✅ upload.php tiene el código actualizado (v2.0)\n";
} else {
    echo "❌ upload.php NO tiene el código actualizado\n";
}

if (strpos($uploadContent, 'Extract client code and name from line like:') !== false) {
    echo "✅ upload.php tiene el parser mejorado\n";
} else {
    echo "❌ upload.php NO tiene el parser mejorado\n";
}

echo "\n✅ Limpieza completada\n";
echo "\n🔄 Recarga tu navegador (Ctrl+Shift+R) y prueba de nuevo\n";
echo "\n⚠️  IMPORTANTE: Elimina este archivo después de usarlo por seguridad\n";
