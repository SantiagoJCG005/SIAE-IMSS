<?php
/**
 * SIAE-IMSS - Sin acceso
 */
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
        <a href="<?= BASE_URL ?>" class="btn">
            <i data-lucide="arrow-left"></i>
            Volver al inicio
        </a>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
