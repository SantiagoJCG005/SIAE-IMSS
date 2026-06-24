<?php
/**
 * SIAE-IMSS - Sidebar Jefa de Servicios Escolares
 */

if (obtenerRolActual() > 5) {
    include __DIR__ . '/sidebar-custom.php';
    return;
}

$paginaActual = basename($_SERVER['PHP_SELF'], '.php');
$currentUser  = obtenerUsuarioActual();
$userInitials = obtenerIniciales($currentUser['nombre_completo'] ?? 'SA');
$avatarColor  = obtenerColorAvatar($currentUser['nombre_completo'] ?? 'Superadmin');
?>

<aside class="sidebar">
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
        <a href="<?= URL_BASE ?>views/jefa/dashboard.php" class="nav-item <?= $paginaActual === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i><span>Inicio</span>
        </a>
        <a href="<?= URL_BASE ?>views/jefa/carpetas.php" class="nav-item <?= $paginaActual === 'carpetas' ? 'active' : '' ?>">
            <i data-lucide="folders"></i><span>Carpetas</span>
        </a>
        <a href="<?= URL_BASE ?>views/jefa/importar.php" class="nav-item <?= $paginaActual === 'importar' ? 'active' : '' ?>">
            <i data-lucide="file-spreadsheet"></i><span>Importar Excel</span>
        </a>
        <a href="<?= URL_BASE ?>views/jefa/acuses.php" class="nav-item <?= $paginaActual === 'acuses' ? 'active' : '' ?>">
            <i data-lucide="file-badge"></i><span>Acuses IMSS</span>
        </a>
        <a href="<?= URL_BASE ?>views/jefa/estatus-imss.php" class="nav-item <?= $paginaActual === 'estatus-imss' ? 'active' : '' ?>">
            <i data-lucide="shield-check"></i><span>Estatus IMSS</span>
        </a>
        <a href="<?= URL_BASE ?>views/jefa/validar.php" class="nav-item <?= $paginaActual === 'validar' ? 'active' : '' ?>">
            <i data-lucide="check-circle"></i><span>Validar</span>
            <?php
            try {
                $conexion  = obtenerConexion();
                $consulta  = $conexion->query("SELECT COUNT(*) as total FROM tablas_movimientos WHERE estado = 'borrador' AND id_usuario_creacion != " . obtenerIdUsuarioActual());
                $pendientes = $consulta->fetch()['total'] ?? 0;
                if ($pendientes > 0) echo '<span class="nav-badge">' . $pendientes . '</span>';
            } catch (Exception $e) {}
            ?>
        </a>
        <a href="<?= URL_BASE ?>views/jefa/reportes.php" class="nav-item <?= $paginaActual === 'reportes' ? 'active' : '' ?>">
            <i data-lucide="bar-chart-3"></i><span>Reportes</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= URL_BASE ?>views/perfil/cambiar-password.php" class="nav-item <?= $paginaActual === 'cambiar-password' ? 'active' : '' ?>">
            <i data-lucide="key-round"></i><span>Cambiar contraseña</span>
        </a>
        <a href="<?= URL_BASE ?>api/auth.php?action=logout" class="nav-item">
            <i data-lucide="log-out"></i><span>Cerrar sesión</span>
        </a>
    </div>
</aside>

<?php
$conNotificaciones  = true;
$urlVerTodas        = URL_BASE . 'views/jefa/notificaciones.php';
$puedeAprobarExport = true;
$urlCorregirAlumno  = URL_BASE . 'views/jefa/corregir-alumno.php';
include __DIR__ . '/_main-header.php';
?>
