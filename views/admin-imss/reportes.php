<?php
/**
 * SIAE-IMSS - Reportes Admin IMSS
 * Consulta de tablas validadas y enviadas (solo lectura)
 */

$tituloPagina = 'Reportes';
$currentPage = 'reportes';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirLogin();
requerirRol(ROL_ADMIN_IMSS);

$conexion = obtenerConexion();

// Filtros desde GET
$filtroTipo    = $_GET['tipo']    ?? '';
$filtroEstado  = $_GET['estado']  ?? '';
$filtroCarpeta = intval($_GET['carpeta'] ?? 0);
$filtroFechaDesde = $_GET['fecha_desde'] ?? '';
$filtroFechaHasta = $_GET['fecha_hasta'] ?? '';

// Carpetas disponibles para el filtro
$carpetas = [];
try {
    $carpetas = $conexion->query("SELECT id_carpeta, nombre FROM carpetas_imss WHERE activo = 1 ORDER BY nombre")->fetchAll();
} catch (Exception $e) {}

// Construir query con filtros
$where = ["t.estado IN ('validado', 'enviado')"];
$params = [];

if ($filtroTipo === 'alta' || $filtroTipo === 'baja') {
    $where[] = "t.tipo = ?";
    $params[] = $filtroTipo;
}
if ($filtroEstado === 'validado' || $filtroEstado === 'enviado') {
    $where[] = "t.estado = ?";
    $params[] = $filtroEstado;
}
if ($filtroCarpeta > 0) {
    $where[] = "c.id_carpeta = ?";
    $params[] = $filtroCarpeta;
}
if ($filtroFechaDesde) {
    $where[] = "t.fecha_movimiento >= ?";
    $params[] = $filtroFechaDesde;
}
if ($filtroFechaHasta) {
    $where[] = "t.fecha_movimiento <= ?";
    $params[] = $filtroFechaHasta;
}

$whereStr = implode(' AND ', $where);

$tablas = [];
try {
    $consulta = $conexion->prepare("
        SELECT t.id_tabla, t.nombre, t.tipo, t.estado, t.total_registros,
               t.fecha_movimiento, t.fecha_validacion, t.archivo_txt_generado,
               t.registros_con_errores,
               s.nombre as subcarpeta_nombre,
               c.nombre as carpeta_nombre,
               u.nombre_completo as creado_por,
               v.nombre_completo as validado_por
        FROM tablas_movimientos t
        INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
        INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
        LEFT JOIN usuarios u ON t.id_usuario_creacion = u.id_usuario
        LEFT JOIN usuarios v ON t.id_usuario_validacion = v.id_usuario
        WHERE $whereStr
        ORDER BY t.fecha_movimiento DESC, t.fecha_creacion DESC
    ");
    $consulta->execute($params);
    $tablas = $consulta->fetchAll();
} catch (Exception $e) {}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-admin-imss.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reportes</h1>
        <p class="page-subtitle">Consulta de tablas validadas y enviadas</p>
    </div>
    <span class="badge" style="background:#DBEAFE;color:#1E40AF;font-size:13px;padding:6px 14px;">
        <?= count($tablas) ?> resultado(s)
    </span>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">

            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Tipo</label>
                <select name="tipo" class="form-control" style="min-width: 120px;">
                    <option value="">Todos</option>
                    <option value="alta"  <?= $filtroTipo === 'alta'  ? 'selected' : '' ?>>Alta</option>
                    <option value="baja"  <?= $filtroTipo === 'baja'  ? 'selected' : '' ?>>Baja</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Estado</label>
                <select name="estado" class="form-control" style="min-width: 130px;">
                    <option value="">Todos</option>
                    <option value="validado" <?= $filtroEstado === 'validado' ? 'selected' : '' ?>>Validado</option>
                    <option value="enviado"  <?= $filtroEstado === 'enviado'  ? 'selected' : '' ?>>Enviado</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Carpeta</label>
                <select name="carpeta" class="form-control" style="min-width: 160px;">
                    <option value="0">Todas</option>
                    <?php foreach ($carpetas as $c): ?>
                    <option value="<?= $c['id_carpeta'] ?>" <?= $filtroCarpeta === $c['id_carpeta'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($filtroFechaDesde) ?>">
            </div>

            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-size: 12px; color: var(--text-muted); font-weight: 500;">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($filtroFechaHasta) ?>">
            </div>

            <div style="display: flex; gap: 8px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" style="width:15px;height:15px;"></i>
                    Filtrar
                </button>
                <a href="<?= URL_BASE ?>views/admin-imss/reportes.php" class="btn btn-outline">
                    Limpiar
                </a>
            </div>

        </form>
    </div>
</div>

<!-- Tabla de resultados -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($tablas)): ?>
        <div class="empty-state" style="padding: 60px 20px;">
            <div class="empty-state-icon"><i data-lucide="file-x"></i></div>
            <div class="empty-state-title">Sin resultados</div>
            <div class="empty-state-text">No hay tablas que coincidan con los filtros seleccionados</div>
        </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Carpeta / Tabla</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Alumnos</th>
                    <th>Fecha mov.</th>
                    <th>Validado por</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tablas as $t): ?>
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
                        <span class="badge" style="background:#DCFCE7;color:#166534;">📈 Alta</span>
                        <?php else: ?>
                        <span class="badge" style="background:#FEE2E2;color:#991B1B;">📉 Baja</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($t['estado'] === 'enviado'): ?>
                        <span class="badge" style="background:#DCFCE7;color:#166534;">✅ Enviado</span>
                        <?php else: ?>
                        <span class="badge" style="background:#DBEAFE;color:#1E40AF;">🟢 Validado</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= number_format($t['total_registros']) ?></strong>
                        <?php if ($t['registros_con_errores'] > 0): ?>
                        <br><small style="color: var(--danger);"><?= $t['registros_con_errores'] ?> con errores</small>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;"><?= date('d/m/Y', strtotime($t['fecha_movimiento'])) ?></td>
                    <td style="font-size:13px;color:var(--text-muted);">
                        <?= htmlspecialchars($t['validado_por'] ?? '—') ?>
                        <?php if ($t['fecha_validacion']): ?>
                        <br><small><?= date('d/m/Y', strtotime($t['fecha_validacion'])) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button onclick="verAlumnos(<?= $t['id_tabla'] ?>, '<?= htmlspecialchars(addslashes($t['nombre'])) ?>')"
                                class="btn btn-sm btn-outline" title="Ver alumnos">
                            <i data-lucide="users" style="width:15px;height:15px;"></i>
                            Ver
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de alumnos (solo lectura) -->
<div id="modalAlumnos" class="modal-overlay">
    <div class="modal" style="width: 95%; max-width: 900px;">
        <div class="modal-header">
            <h3 id="modalAlumnosTitulo">Alumnos</h3>
            <button onclick="cerrarModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="modalAlumnosBody" style="padding: 0; max-height: 65vh; overflow-y: auto;">
            <div style="padding: 40px; text-align: center; color: var(--text-muted);">Cargando...</div>
        </div>
        <div class="modal-footer" style="padding: 14px 20px; border-top: 1px solid var(--border-color); text-align: right;">
            <button onclick="cerrarModal()" class="btn btn-outline">Cerrar</button>
        </div>
    </div>
</div>

<style>
.badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
.table td { vertical-align: middle; padding: 10px 16px; }
.form-control { height: 36px; border: 1px solid var(--border-color); border-radius: var(--radius); padding: 0 10px; font-size: 13px; background: var(--bg-white); color: var(--text-primary); }
.form-control:focus { outline: none; border-color: var(--secondary); }
</style>

<script>
const API_TABLAS = '<?= URL_BASE ?>api/tablas.php';

async function verAlumnos(idTabla, nombreTabla) {
    document.getElementById('modalAlumnosTitulo').textContent = 'Alumnos — ' + nombreTabla;
    document.getElementById('modalAlumnosBody').innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-muted);">Cargando...</div>';
    document.getElementById('modalAlumnos').classList.add('active');

    try {
        const resp = await llamarApi(`${API_TABLAS}?action=obtener_resultados&id_tabla=${idTabla}`);

        if (resp.success && resp.data && resp.data.resultados) {
            const alumnos = resp.data.resultados;
            if (alumnos.length === 0) {
                document.getElementById('modalAlumnosBody').innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-muted);">Sin alumnos registrados</div>';
                return;
            }
            let html = `<table class="table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>NSS</th>
                        <th>Apellido Paterno</th>
                        <th>Apellido Materno</th>
                        <th>Nombre(s)</th>
                        <th>CURP</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>`;
            alumnos.forEach(a => {
                const estadoBadge = a.tiene_errores == 1
                    ? '<span style="color:#DC2626;font-size:11px;">⚠ Error</span>'
                    : '<span style="color:#16A34A;font-size:11px;">✓ OK</span>';
                html += `<tr>
                    <td style="color:var(--text-muted);">${a.numero_cuenta}</td>
                    <td><code style="background:var(--bg-secondary);padding:2px 6px;border-radius:4px;">${escHtml(a.numero_afiliacion)}</code></td>
                    <td>${escHtml(a.apellido_paterno)}</td>
                    <td>${escHtml(a.apellido_materno || '—')}</td>
                    <td>${escHtml(a.nombres)}</td>
                    <td style="font-size:12px;font-family:monospace;">${escHtml(a.curp || '—')}</td>
                    <td>${estadoBadge}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            document.getElementById('modalAlumnosBody').innerHTML = html;
        } else {
            document.getElementById('modalAlumnosBody').innerHTML = '<div style="padding:40px;text-align:center;color:var(--danger);">No se pudieron cargar los alumnos</div>';
        }
    } catch (e) {
        document.getElementById('modalAlumnosBody').innerHTML = '<div style="padding:40px;text-align:center;color:var(--danger);">Error de conexión</div>';
    }
}

function cerrarModal() {
    document.getElementById('modalAlumnos').classList.remove('active');
}

function escHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarModal();
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
