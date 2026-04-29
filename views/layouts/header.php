<?php
/**
 *  * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Layout Header
 * Es la parte superior de todas las paginas del sistema.
 * Contiene el DOCTYPE, las etiquetas head con los estilos y scripts,
 * y prepara la informacion del usuario logueado para mostrarla.
 */

// Carga el archivo de autenticacion para poder obtener datos del usuario actual
require_once __DIR__ . '/../../includes/auth.php';

// Carga el archivo de funciones auxiliares que usaremos
require_once __DIR__ . '/../../includes/functions.php';

// Obtiene los datos del usuario que esta logueado actualmente
// Retorna un arreglo con id, nombre, email, rol, etc.
$currentUser = obtenerUsuarioActual();

// Obtiene las iniciales del nombre del usuario para mostrar en el avatar
// Por ejemplo: "Juan Perez" se convierte en "JP"
// Si no hay nombre, usa "U" como valor por defecto
$userInitials = obtenerIniciales($currentUser['nombre_completo'] ?? 'U');

// Genera un color de fondo para el avatar basado en el nombre del usuario
// Cada usuario tendra un color diferente pero consistente
$avatarColor = obtenerColorAvatar($currentUser['nombre_completo'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="styles.css">
    <?php // Muestra el titulo de la pagina actual o 'SIAE-IMSS' si no esta definido, seguido del nombre del sistema ?>
    <title><?= $tituloPagina ?? 'SIAE-IMSS' ?> - <?= NOMBRE_SISTEMA ?></title>
    
    <!-- Favicon -->
    <?php // URL_RECURSOS contiene la ruta a la carpeta de recursos estaticos ?>
    <link rel="icon" type="image/png" href="<?= URL_RECURSOS ?>img/favicon.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Estilos principales -->
    <?php // Carga la hoja de estilos principal del sistema ?>
    <link rel="stylesheet" href="<?= URL_RECURSOS ?>css/styles.css">
    
    <?php // Si existe la variable $extraStyles, la imprime (permite agregar estilos adicionales por pagina) ?>
    <?php if (isset($extraStyles)): ?>
        <?= $extraStyles ?>
    <?php endif; ?>
</head>
<body>
    <div class="app-container">