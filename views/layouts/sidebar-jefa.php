<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Sidebar para Jefa de Servicios Escolares
 * Menu lateral con navegacion del modulo
 */

// Obtiene la pagina actual para marcar el menu activo
$paginaActual = basename($_SERVER['PHP_SELF'], '.php');

// Obtiene los datos del usuario que esta logueado
$currentUser = obtenerUsuarioActual();
?>

<aside class="sidebar">
    <!-- Logo -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">J</div>
            <div>
                <div class="sidebar-logo-text">SIAE-IMSS</div>
                <div class="sidebar-logo-sub">Jefa de Servicios</div>
            </div>
        </div>
    </div>
    

    <nav class="sidebar-nav">
        <?php // Cada enlace usa URL_BASE para formar la ruta completa ?>
        <?php // Si $currentPage coincide con el nombre de la pagina, agrega la clase 'active' para resaltarla ?>
            <a href="<?= URL_BASE ?>views/jefa/dashboard.php"  class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i>
            <span>Inicio</span>
        </a>
        <a href="<?= URL_BASE ?>views/jefa/carpetas.php" class="nav-item <?= $currentPage === 'carpetas' ? 'active' : '' ?>">
        <i data-lucide="folders"></i>
        <span>Carpetas</span>
         </a>
            
        <a href="<?= URL_BASE ?>views/jefa/importar.php"  class="nav-item <?= $currentPage === 'importar' ? 'active' : '' ?>">
        <i data-lucide="file-spreadsheet"></i>
        <span>Importar Excel</span>
        </a>
        <a href="<?= URL_BASE ?>views/jefa/validar.php"  class="nav-item <?= $currentPage === 'validar' ? 'active' : '' ?>">
        <i data-lucide="check-circle"></i>
        <span>Validar</span>
                    <?php
                    // Cuenta las tablas pendientes de validacion
                    try {
                        $conexion = obtenerConexion();
                        $consulta = $conexion->query("SELECT COUNT(*) as total FROM tablas_movimientos WHERE estado = 'borrador' AND id_usuario_creacion != " . obtenerIdUsuarioActual());
                        $pendientes = $consulta->fetch()['total'] ?? 0;
                        if ($pendientes > 0):
                    ?>
                    <span class="nav-badge"><?= $pendientes ?></span>
                    <?php 
                        endif;
                    } catch (Exception $e) {
                        // Si la tabla no existe aun, no muestra nada
                    }
                    ?>
             </a>
       <a href="<?= URL_BASE ?>views/jefa/reportes.php"  class="nav-item <?= $currentPage === 'reportes' ? 'active' : '' ?>">
     <i data-lucide="bar-chart-3"></i>
     <span>Reportes</span>

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
