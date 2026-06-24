<?php
/**
 * SIAE-IMSS - Sidebar Admin Servicios Escolares (ROL 3)
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
            <div class="sidebar-logo-icon">A</div>
            <div>
                <div class="sidebar-logo-text">SIAE-IMSS</div>
                <div class="sidebar-logo-sub">Admin Servicios</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= URL_BASE ?>views/admin-se/dashboard.php" class="nav-item <?= $paginaActual === 'dashboard' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i><span>Inicio</span>
        </a>
        <a href="<?= URL_BASE ?>views/admin-se/carpetas.php" class="nav-item <?= $paginaActual === 'carpetas' ? 'active' : '' ?>">
            <i data-lucide="folders"></i><span>Carpetas</span>
        </a>
        <a href="<?= URL_BASE ?>views/admin-se/importar.php" class="nav-item <?= $paginaActual === 'importar' ? 'active' : '' ?>">
            <i data-lucide="file-spreadsheet"></i><span>Importar Excel</span>
        </a>
        <a href="<?= URL_BASE ?>views/admin-se/historial.php" class="nav-item <?= $paginaActual === 'historial' ? 'active' : '' ?>">
            <i data-lucide="history"></i><span>Mi Historial</span>
            <?php
            try {
                $conexion = obtenerConexion();
                $consulta = $conexion->prepare("SELECT COUNT(*) as total FROM notificaciones WHERE id_usuario_destino = ? AND tipo = 'alerta_problema' AND leida = 0");
                $consulta->execute([obtenerIdUsuarioActual()]);
                $alertas = $consulta->fetch()['total'] ?? 0;
                if ($alertas > 0) echo '<span class="nav-badge" style="background:#EF4444;">' . $alertas . '</span>';
            } catch (Exception $e) {}
            ?>
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
$urlVerTodas        = URL_BASE . 'views/admin-se/notificaciones.php';
$puedeAprobarExport = false;
$urlCorregirAlumno  = URL_BASE . 'views/jefa/corregir-alumno.php';
include __DIR__ . '/_main-header.php';
?>
