<?php
/**
 * Migración: agrega columnas de datos PDF a acuse_detalle
 * Ejecutar UNA vez desde el navegador y luego eliminar este archivo.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requerirRol([ROL_SUPERADMIN]);

$pdo = obtenerConexion();

$columnas = [
    'nombre_pdf'           => "VARCHAR(80)  NULL AFTER nss",
    'apellido_paterno_pdf' => "VARCHAR(60)  NULL AFTER nombre_pdf",
    'apellido_materno_pdf' => "VARCHAR(60)  NULL AFTER apellido_paterno_pdf",
    'curp_pdf'             => "CHAR(18)     NULL AFTER apellido_materno_pdf",
];

$resultados = [];
foreach ($columnas as $col => $def) {
    // Verificar si ya existe
    $chk = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'acuse_detalle' AND COLUMN_NAME = ?");
    $chk->execute([$col]);
    if ($chk->fetchColumn() > 0) {
        $resultados[] = "&#10004; <b>$col</b> ya existe";
        continue;
    }
    try {
        $pdo->exec("ALTER TABLE acuse_detalle ADD COLUMN `$col` $def");
        $resultados[] = "&#10004; <b>$col</b> agregada correctamente";
    } catch (Exception $e) {
        $resultados[] = "&#10008; <b>$col</b> ERROR: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Migración</title>
<style>body{font-family:sans-serif;padding:40px;max-width:600px;margin:auto;} li{padding:6px 0;font-size:15px;}</style>
</head>
<body>
<h2>Migración — columnas PDF en acuse_detalle</h2>
<ul>
<?php foreach ($resultados as $r): ?>
    <li><?= $r ?></li>
<?php endforeach; ?>
</ul>
<p style="margin-top:24px;color:#666;">&#9888; Elimina este archivo del servidor una vez completada la migración.</p>
</body>
</html>
