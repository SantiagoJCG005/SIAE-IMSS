<?php
/**
 * SIAE-IMSS - Dashboard para roles personalizados
 * Muestra accesos rápidos según los permisos asignados al rol
 */

$tituloPagina = 'Dashboard';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirLogin();

// Solo roles personalizados (ID > 5) usan este dashboard
if (obtenerRolActual() <= 5) {
    header('Location: ' . obtenerPaginaInicio());
    exit;
}

$currentUser = obtenerUsuarioActual();
$permisos    = $currentUser['permisos'] ?? [];
$conexion    = obtenerConexion();

function tieneP(string $p): bool { global $permisos; return in_array($p, $permisos); }

// Estadísticas según permisos disponibles
$stats = [];

try {
    if (tieneP('ver_alumnos') || tieneP('altas_bajas')) {
        $r = $conexion->query("SELECT COUNT(*) as t FROM tabla_alumnos WHERE eliminado = 0");
        $stats['alumnos'] = $r->fetch()['t'] ?? 0;
    }
    if (tieneP('validar_movimientos')) {
        $r = $conexion->query("SELECT COUNT(*) as t FROM tablas_movimientos WHERE estado = 'borrador'");
        $stats['pendientes_validar'] = $r->fetch()['t'] ?? 0;
    }
    if (tieneP('importar_excel') || tieneP('altas_bajas')) {
        $r = $conexion->prepare(
            "SELECT COUNT(*) as t FROM tablas_movimientos WHERE id_usuario_creacion = ? AND MONTH(fecha_creacion) = MONTH(CURDATE())"
        );
        $r->execute([obtenerIdUsuarioActual()]);
        $stats['mis_tablas_mes'] = $r->fetch()['t'] ?? 0;
    }
    if (tieneP('atender_incidencias')) {
        $r = $conexion->prepare(
            "SELECT COUNT(*) as t FROM notificaciones WHERE id_usuario_destino = ? AND leida = 0"
        );
        $r->execute([obtenerIdUsuarioActual()]);
        $stats['notif_sin_leer'] = $r->fetch()['t'] ?? 0;
    }
} catch (Exception $e) {}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-custom.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Bienvenido, <?= htmlspecialchars(explode(' ', $currentUser['nombre_completo'])[0]) ?></h1>
        <p class="page-subtitle">
            Rol: <strong><?= htmlspecialchars($currentUser['rol_nombre']) ?></strong>
            &mdash; Aquí están tus accesos disponibles.
        </p>
    </div>
</div>

<?php if (!empty($stats)): ?>
<!-- Tarjetas de estadísticas -->
<div class="stats-grid" style="margin-bottom:24px;">

    <?php if (isset($stats['alumnos'])): ?>
    <div class="stat-card">
        <div class="stat-icon info">
            <i data-lucide="users"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Alumnos registrados</div>
            <div class="stat-value"><?= number_format($stats['alumnos']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($stats['pendientes_validar'])): ?>
    <div class="stat-card">
        <div class="stat-icon warning" style="background:var(--warning-bg);color:var(--warning);">
            <i data-lucide="clock"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Pendientes de validar</div>
            <div class="stat-value"><?= $stats['pendientes_validar'] ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($stats['mis_tablas_mes'])): ?>
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="file-check"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Mis tablas este mes</div>
            <div class="stat-value"><?= $stats['mis_tablas_mes'] ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($stats['notif_sin_leer'])): ?>
    <div class="stat-card">
        <div class="stat-icon <?= $stats['notif_sin_leer'] > 0 ? 'danger' : 'success' ?>"
             style="<?= $stats['notif_sin_leer'] > 0 ? 'background:var(--danger-bg);color:var(--danger);' : '' ?>">
            <i data-lucide="bell"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Notificaciones sin leer</div>
            <div class="stat-value"><?= $stats['notif_sin_leer'] ?></div>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>

<!-- Accesos rápidos por grupo de permisos -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">

<?php if (tieneP('ver_datos') || tieneP('reportar_falla')): ?>
<div class="card" style="border-top:3px solid #3B82F6;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#DBEAFE;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="user-circle" style="color:#1D4ED8;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Mi información</div>
                <div style="font-size:12px;color:var(--text-muted);">Ver tu estatus de afiliación IMSS</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/estudiante/dashboard.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Ver mi información
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('ver_alumnos') || tieneP('altas_bajas')): ?>
<div class="card" style="border-top:3px solid #6366F1;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#E0E7FF;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="shield-check" style="color:#4338CA;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Estatus IMSS</div>
                <div style="font-size:12px;color:var(--text-muted);">Consultar alumnos y su estatus de afiliación</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/jefa/estatus-imss.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Ver alumnos
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('altas_bajas') || tieneP('editar_datos')): ?>
<div class="card" style="border-top:3px solid #F97316;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#FFEDD5;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="folders" style="color:#C2410C;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Carpetas</div>
                <div style="font-size:12px;color:var(--text-muted);">Gestionar altas y bajas por ciclo y carrera</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/admin-se/carpetas.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Ir a carpetas
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('importar_excel')): ?>
<div class="card" style="border-top:3px solid #10B981;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#D1FAE5;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="file-spreadsheet" style="color:#047857;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Importar Excel</div>
                <div style="font-size:12px;color:var(--text-muted);">Cargar alumnos desde archivo SII</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/admin-se/importar.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Ir a importar
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('validar_movimientos')): ?>
<div class="card" style="border-top:3px solid #8B5CF6;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#EDE9FE;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="check-circle" style="color:#6D28D9;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Validar movimientos</div>
                <div style="font-size:12px;color:var(--text-muted);">Aprobar o rechazar tablas enviadas</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/jefa/validar.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Ir a validar
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('ver_reportes')): ?>
<div class="card" style="border-top:3px solid #0EA5E9;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#E0F2FE;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="bar-chart-3" style="color:#0369A1;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Reportes</div>
                <div style="font-size:12px;color:var(--text-muted);">Estadísticas e historial IMSS</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/jefa/reportes.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Ver reportes
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('exportar_txt')): ?>
<div class="card" style="border-top:3px solid #F59E0B;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#FEF3C7;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="file-output" style="color:#B45309;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Exportar TXT IMSS</div>
                <div style="font-size:12px;color:var(--text-muted);">Generar archivo de movimientos</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/admin-imss/exportar.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Ir a exportar
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('atender_incidencias')): ?>
<div class="card" style="border-top:3px solid #EF4444;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#FEE2E2;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="bell-ring" style="color:#B91C1C;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Incidencias</div>
                <div style="font-size:12px;color:var(--text-muted);">Atender alertas y notificaciones</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/admin-se/notificaciones.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Ver incidencias
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('ver_bitacora')): ?>
<div class="card" style="border-top:3px solid #64748B;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#F1F5F9;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="scroll-text" style="color:#475569;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Bitácora</div>
                <div style="font-size:12px;color:var(--text-muted);">Historial de acciones del sistema</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/superadmin/bitacora.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Ver bitácora
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('crud_usuarios')): ?>
<div class="card" style="border-top:3px solid #6366F1;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#E0E7FF;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="users" style="color:#4338CA;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Usuarios</div>
                <div style="font-size:12px;color:var(--text-muted);">Crear y gestionar cuentas</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/superadmin/usuarios.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Gestionar usuarios
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('gestion_catalogos')): ?>
<div class="card" style="border-top:3px solid #6366F1;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#E0E7FF;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="database" style="color:#4338CA;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Catálogos</div>
                <div style="font-size:12px;color:var(--text-muted);">Carreras, niveles, periodos</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/superadmin/catalogos.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Gestionar catálogos
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (tieneP('config_patronal')): ?>
<div class="card" style="border-top:3px solid #6366F1;">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:#E0E7FF;display:flex;align-items:center;justify-content:center;">
                <i data-lucide="settings" style="color:#4338CA;width:20px;height:20px;"></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:15px;">Configuración</div>
                <div style="font-size:12px;color:var(--text-muted);">Datos patronales y SMTP</div>
            </div>
        </div>
        <a href="<?= URL_BASE ?>views/superadmin/configuracion.php" class="btn btn-outline" style="width:100%;">
            <i data-lucide="arrow-right"></i> Ir a configuración
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (empty($permisos)): ?>
<div class="card" style="grid-column:1/-1;">
    <div class="card-body" style="text-align:center;padding:40px;">
        <i data-lucide="shield-off" style="width:48px;height:48px;color:var(--text-muted);display:block;margin:0 auto 16px;"></i>
        <h3 style="color:var(--text-secondary);margin-bottom:8px;">Sin permisos asignados</h3>
        <p style="color:var(--text-muted);font-size:14px;">
            Tu rol no tiene permisos configurados. Contacta al administrador del sistema.
        </p>
    </div>
</div>
<?php endif; ?>

</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
