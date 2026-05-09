<?php
/**
 * Migración: amplía columnas de nombre en acuse_detalle.
 * Ejecutar UNA vez y luego eliminar este archivo.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requerirRol([ROL_SUPERADMIN]);

$pdo = obtenerConexion();

$alteraciones = [
    "ALTER TABLE acuse_detalle MODIFY COLUMN nombre_pdf VARCHAR(200) NULL",
    "ALTER TABLE acuse_detalle MODIFY COLUMN apellido_paterno_pdf VARCHAR(150) NULL",
];

$resultados = [];
foreach ($alteraciones as $sql) {
    try {
        $pdo->exec($sql);
        $resultados[] = "&#10004; <code>" . htmlspecialchars($sql) . "</code>";
    } catch (Exception $e) {
        $resultados[] = "&#10008; ERROR: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Migración resize</title>
<style>body{font-family:sans-serif;padding:40px;max-width:700px;margin:auto;} li{padding:6px 0;font-size:14px;}</style>
</head>
<body>
<h2>Migración — resize columnas nombre en acuse_detalle</h2>
<ul>
<?php foreach ($resultados as $r): ?>
    <li><?= $r ?></li>
<?php endforeach; ?>
</ul>
<p style="margin-top:24px;color:#666;">&#9888; Elimina este archivo una vez completada la migración.</p>
</body>
</html>
