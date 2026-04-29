<?php
/**
 * SIAE-IMSS - Dashboard Admin IMSS
 * Panel de monitoreo de datos procesados
 */

$tituloPagina = 'Dashboard';
$currentPage = 'dashboard';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirLogin();
requerirRol(ROL_ADMIN_IMSS);

$conexion = obtenerConexion();

// Estadísticas principales
$stats = [
    'altas_mes'       => 0,
    'bajas_mes'       => 0,
    'tablas_enviadas' => 0,
    'txt_disponibles' => 0,
];

try {
    $mesActual = date('n');
    $anioActual = date('Y');

    // Alumnos con alta procesados este mes (tablas enviadas)
    $consulta = $conexion->query("
        SELECT COALESCE(SUM(total_registros), 0) as total
        FROM tablas_movimientos
        WHERE tipo = 'alta' AND estado = 'enviado'
          AND MONTH(fecha_movimiento) = $mesActual
          AND YEAR(fecha_movimiento) = $anioActual
    ");
    $stats['altas_mes'] = $consulta->fetch()['total'];

    // Alumnos con baja procesados este mes
    $consulta = $conexion->query("
        SELECT COALESCE(SUM(total_registros), 0) as total
        FROM tablas_movimientos
        WHERE tipo = 'baja' AND estado = 'enviado'
          AND MONTH(fecha_movimiento) = $mesActual
          AND YEAR(fecha_movimiento) = $anioActual
    ");
    $stats['bajas_mes'] = $consulta->fetch()['total'];

    // Total tablas enviadas (histórico)
    $consulta = $conexion->query("SELECT COUNT(*) as total FROM tablas_movimientos WHERE estado = 'enviado'");
    $stats['tablas_enviadas'] = $consulta->fetch()['total'];

    // TXT disponibles para descarga (enviadas con archivo generado)
    $consulta = $conexion->query("SELECT COUNT(*) as total FROM tablas_movimientos WHERE estado = 'enviado' AND archivo_txt_generado IS NOT NULL");
    $stats['txt_disponibles'] = $consulta->fetch()['total'];

} catch (Exception $e) {
    // Sin datos
}

// Últimas 8 tablas enviadas
$ultimasTablas = [];
try {
    $consulta = $conexion->query("
        SELECT t.id_tabla, t.nombre, t.tipo, t.estado, t.total_registros,
               t.fecha_movimiento, t.archivo_txt_generado,
               s.nombre as subcarpeta_nombre,
               c.nombre as carpeta_nombre,
               u.nombre_completo as creado_por,
               v.nombre_completo as validado_por
        FROM tablas_movimientos t
        INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
        INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
        LEFT JOIN usuarios u ON t.id_usuario_creacion = u.id_usuario
        LEFT JOIN usuarios v ON t.id_usuario_validacion = v.id_usuario
        WHERE t.estado = 'enviado'
        ORDER BY t.fecha_movimiento DESC
        LIMIT 8
    ");
    $ultimasTablas = $consulta->fetchAll();
} catch (Exception $e) {}

// Resumen por carpeta: cuántas tablas enviadas tiene cada carpeta
$resumenCarpetas = [];
try {
    $consulta = $conexion->query("
        SELECT c.nombre as carpeta,
               COUNT(*) as total,
               SUM(t.tipo = 'alta') as altas,
               SUM(t.tipo = 'baja') as bajas,
               COALESCE(SUM(t.total_registros), 0) as alumnos
        FROM tablas_movimientos t
        INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
        INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
        WHERE t.estado = 'enviado'
        GROUP BY c.id_carpeta, c.nombre
        ORDER BY total DESC
        LIMIT 6
    ");
    $resumenCarpetas = $consulta->fetchAll();
} catch (Exception $e) {}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-admin-imss.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Panel de monitoreo — Admin IMSS</p>
    </div>
    <div style="font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
        <i data-lucide="calendar" style="width:15px;height:15px;"></i>
        <?= date('F Y', mktime(0, 0, 0, date('n'), 1)) ?>
    </div>
</div>

<!-- Tarjetas de estadísticas -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 28px;">

    <div class="stat-card">
        <div class="stat-icon" style="background: #DCFCE7; color: #16A34A;">
            <i data-lucide="user-plus"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Alumnos con ALTA este mes</div>
            <div class="stat-value" style="font-size: 28px;"><?= number_format($stats['altas_mes']) ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: #FEE2E2; color: #DC2626;">
            <i data-lucide="user-minus"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Alumnos con BAJA este mes</div>
            <div class="stat-value" style="font-size: 28px;"><?= number_format($stats['bajas_mes']) ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: #DBEAFE; color: #2563EB;">
            <i data-lucide="check-circle-2"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Tablas enviadas (total)</div>
            <div class="stat-value" style="font-size: 28px;"><?= number_format($stats['tablas_enviadas']) ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: #F3E8FF; color: #7C3AED;">
            <i data-lucide="file-down"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">TXT disponibles</div>
            <div class="stat-value" style="font-size: 28px;"><?= number_format($stats['txt_disponibles']) ?></div>
        </div>
    </div>

</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">

    <!-- Últimas tablas enviadas -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="clock" style="width:17px;height:17px;margin-right:8px;"></i>
                Últimas tablas procesadas
            </h3>
            <a href="<?= URL_BASE ?>views/admin-imss/reportes.php" class="btn btn-ghost btn-sm">Ver todas →</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($ultimasTablas)): ?>
            <div class="empty-state" style="padding: 50px 20px;">
                <div class="empty-state-icon"><i data-lucide="inbox"></i></div>
                <div class="empty-state-title">Sin tablas enviadas</div>
                <div class="empty-state-text">Aún no hay tablas con estado enviado</div>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Tabla</th>
                        <th>Tipo</th>
                        <th>Alumnos</th>
                        <th>Fecha mov.</th>
                        <th>TXT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimasTablas as $t): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($t['nombre']) ?></strong>
                            <br>
                            <small style="color: var(--text-muted);">
                                <?= htmlspecialchars($t['carpeta_nombre']) ?> › <?= htmlspecialchars($t['subcarpeta_nombre']) ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($t['tipo'] === 'alta'): ?>
                            <span class="badge" style="background:#DCFCE7;color:#166534;font-size:11px;">ALTA</span>
                            <?php else: ?>
                            <span class="badge" style="background:#FEE2E2;color:#991B1B;font-size:11px;">BAJA</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= number_format($t['total_registros']) ?></strong></td>
                        <td style="font-size:13px;"><?= date('d/m/Y', strtotime($t['fecha_movimiento'])) ?></td>
                        <td>
                            <?php if ($t['archivo_txt_generado']): ?>
                            <a href="<?= URL_BASE ?>views/admin-imss/exportar.php" title="Disponible para descarga"
                               style="color: var(--secondary);">
                                <i data-lucide="file-down" style="width:16px;height:16px;"></i>
                            </a>
                            <?php else: ?>
                            <span style="color: var(--text-muted);" title="Sin archivo">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Resumen por carpeta -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="folders" style="width:17px;height:17px;margin-right:8px;"></i>
                Por carpeta
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($resumenCarpetas)): ?>
            <div style="text-align: center; padding: 30px; color: var(--text-muted); font-size: 13px;">
                Sin datos disponibles
            </div>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach ($resumenCarpetas as $c): ?>
                <div style="border: 1px solid var(--border-color); border-radius: var(--radius); padding: 12px 14px;">
                    <div style="font-weight: 600; font-size: 13px; margin-bottom: 8px; color: var(--text-primary);">
                        <?= htmlspecialchars($c['carpeta']) ?>
                    </div>
                    <div style="display: flex; gap: 16px; font-size: 12px;">
                        <span style="color: #16A34A;">
                            <strong><?= $c['altas'] ?></strong> altas
                        </span>
                        <span style="color: #DC2626;">
                            <strong><?= $c['bajas'] ?></strong> bajas
                        </span>
                        <span style="color: var(--text-muted); margin-left: auto;">
                            <?= number_format($c['alumnos']) ?> alumnos
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.stat-card {
    background: var(--bg-white);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.stat-icon {
    width: 48px; height: 48px;
    border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.stat-icon i { width: 24px; height: 24px; }
.stat-content { flex: 1; }
.stat-label { font-size: 12px; color: var(--text-muted); margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; color: var(--text-primary); }
.badge {
    display: inline-flex; align-items: center;
    padding: 3px 8px; border-radius: 20px;
    font-size: 11px; font-weight: 500;
}
.table td { vertical-align: middle; padding: 10px 16px; }
</style>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
