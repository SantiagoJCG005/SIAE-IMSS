<?php
/**
 *  * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Sidebar Superadmin
 * Es el menu lateral que se muestra en todas las paginas del Superadmin.
 * Contiene los enlaces de navegacion, la informacion del usuario y el boton de cerrar sesion.
 */

// Obtiene el nombre del archivo PHP actual sin la extension
// un ejemplo: si estamos en "usuarios.php", guarda "usuarios"
// Esto sirve para saber que opcion del menu debe estar resaltada
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Obtiene los datos del usuario que esta logueado
$currentUser = obtenerUsuarioActual();

// Obtiene las iniciales del nombre para mostrar en el avatar
// Si no hay nombre, usa "SA" (Superadmin) como valor por defecto
$userInitials = obtenerIniciales($currentUser['nombre_completo'] ?? 'SA');

// Genera un color de fondo para el avatar basado en el nombre
$avatarColor = obtenerColorAvatar($currentUser['nombre_completo'] ?? 'Superadmin');
?>

<!-- Sidebar -->
<aside class="sidebar">
    <!-- Logo -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">S</div>
            <div>
                <div class="sidebar-logo-text">SIAE-IMSS</div>
                <div class="sidebar-logo-sub">Superadministrador</div>
            </div>
        </div>
    </div>
    
    <!-- Usuario -->
    <div class="sidebar-user">
        <?php // Muestra el avatar con el color generado y las iniciales del usuario ?>
        <div class="sidebar-user-avatar" style="background: <?= $avatarColor ?>">
            <?= $userInitials ?>
        </div>
        <div class="sidebar-user-info">
            <?php // htmlspecialchars protege contra ataques XSS al mostrar texto del usuario ?>
            <div class="sidebar-user-name"><?= htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario') ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($currentUser['rol_nombre'] ?? 'Superadmin') ?></div>
        </div>
    </div>
    
    <!-- Navegación -->
    <nav class="sidebar-nav">
        <?php // Cada enlace usa URL_BASE para formar la ruta completa ?>
        <?php // Si $currentPage coincide con el nombre de la pagina, agrega la clase 'active' para resaltarla ?>
        <a href="<?= URL_BASE ?>views/superadmin/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Inicio</span>
        </a>
        <a href="<?= URL_BASE ?>views/superadmin/usuarios.php" class="nav-item <?= $currentPage === 'usuarios' ? 'active' : '' ?>">
            <i data-lucide="users"></i>
            <span>Usuarios</span>
        </a>
        <a href="<?= URL_BASE ?>views/superadmin/roles.php" class="nav-item <?= $currentPage === 'roles' ? 'active' : '' ?>">
            <i data-lucide="shield"></i>
            <span>Roles</span>
        </a>
        <a href="<?= URL_BASE ?>views/superadmin/catalogos.php" class="nav-item <?= $currentPage === 'catalogos' ? 'active' : '' ?>">
            <i data-lucide="database"></i>
            <span>Catálogos</span>
        </a>
        <a href="<?= URL_BASE ?>views/superadmin/bitacora.php" class="nav-item <?= $currentPage === 'bitacora' ? 'active' : '' ?>">
            <i data-lucide="scroll-text"></i>
            <span>Bitácora</span>
        </a>
        <a href="<?= URL_BASE ?>views/superadmin/configuracion.php" class="nav-item <?= $currentPage === 'configuracion' ? 'active' : '' ?>">
            <i data-lucide="settings"></i>
            <span>Configuración</span>
        </a>
    </nav>
    

    <div class="sidebar-footer">
        <?php // Enlace que llama al API de autenticacion con la accion logout para cerrar sesion ?>
        <a href="<?= URL_BASE ?>api/auth.php?action=logout" class="nav-item">
            <i data-lucide="log-out"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>
</aside>

<!-- Main Content -->
<main class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-search">  
        </div>
        
        <div class="header-actions">
            <button class="header-icon-btn" title="Notificaciones">
                <i data-lucide="bell"></i>
            </button>
            <button class="header-icon-btn" title="Ayuda">
                <i data-lucide="help-circle"></i>
            </button>
            <div class="header-user">
                <div class="header-user-info">
                    <?php // Muestra el nombre y rol del usuario en el header ?>
                    <div class="header-user-name"><?= htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario') ?></div>
                    <div class="header-user-role"><?= htmlspecialchars($currentUser['rol_nombre'] ?? 'Superadmin') ?></div>
                </div>
                <?php // Avatar del usuario con color e iniciales ?>
                <div class="header-user-avatar" style="background: <?= $avatarColor ?>">
                    <?= $userInitials ?>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Page Content -->
    <div class="page-content">