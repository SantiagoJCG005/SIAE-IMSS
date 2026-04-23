<?php
/**
 *  * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Sin acceso
 * Muestra una pagina de error cuando el usuario intenta entrar a una seccion
 * del sistema para la cual no tiene permisos. Es como una puerta cerrada
 * que le dice "no puedes pasar" y le da un boton para regresar al inicio.
 */

// Carga el archivo de configuracion que contiene constantes como URL_BASE
// Esto es necesario para poder crear el enlace de "Volver al inicio"
require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin Acceso - SIAE-IMSS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #F8FAFC;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            padding: 40px;
        }
        .icon {
            width: 80px;
            height: 80px;
            background: #FEE2E2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .icon i { color: #EF4444; width: 40px; height: 40px; }
        h1 { font-size: 24px; color: #1E293B; margin-bottom: 8px; }
        p { color: #64748B; margin-bottom: 24px; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #2563EB;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
        }
        .btn:hover { background: #1D4ED8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <i data-lucide="shield-x"></i>
        </div>
        <h1>Acceso Denegado</h1>
        <p>No tienes permisos para acceder a esta sección del sistema.</p>

        <!-- Enlace para volver al inicio, usa URL_BASE que viene del archivo de configuracion de PHP -->
        <a href="<?= URL_BASE ?>" class="btn">
            <i data-lucide="arrow-left"></i>
            Volver al inicio
        </a>
    </div>

    <script>
        // Activa todos los iconos de Lucide en la pagina
        // Esta funcion busca todas las etiquetas <i> con el atributo data-lucide
        // y las convierte en iconos SVG visibles
        lucide.createIcons();
    </script>
</body>
</html>