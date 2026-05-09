<?php
$tituloPagina = 'Detalle de Acuse IMSS';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirRol([ROL_JEFA_SERVICIOS, ROL_SUPERADMIN]);

$idAcuse = intval($_GET['id'] ?? 0);
if ($idAcuse <= 0) {
    header('Location: ' . URL_BASE . 'views/admin-se/acuses.php');
    exit;
}

$conexion = obtenerConexion();

$stmt = $conexion->prepare("
    SELECT a.*, u.nombre_completo AS subido_por
    FROM acuses_imss a
    INNER JOIN usuarios u ON u.id_usuario = a.id_usuario
    WHERE a.id_acuse = ?
");
$stmt->execute([$idAcuse]);
$acuse = $stmt->fetch();

if (!$acuse) {
    header('Location: ' . URL_BASE . 'views/admin-se/acuses.php');
    exit;
}

$stmt = $conexion->prepare("
    SELECT d.*,
           CONCAT(a.apellido_paterno, ' ', a.apellido_materno, ' ', a.nombre) AS nombre_alumno,
           a.numero_control,
           CONCAT(a2.apellido_paterno, ' ', a2.apellido_materno, ' ', a2.nombre) AS nombre_actual,
           a2.numero_control AS nc_actual
    FROM acuse_detalle d
    LEFT JOIN alumnos a   ON a.id_alumno  = d.id_alumno
    LEFT JOIN datos_imss di ON di.nss      = d.nss
    LEFT JOIN alumnos a2  ON a2.id_alumno  = di.id_alumno
    WHERE d.id_acuse = ?
    ORDER BY d.resultado, d.nss
");
$stmt->execute([$idAcuse]);
$detalle = $stmt->fetchAll();

// Búsqueda
$buscar = trim($_GET['buscar'] ?? '');
if ($buscar !== '') {
    $buscarUp = mb_strtoupper($buscar, 'UTF-8');
    $detalle  = array_values(array_filter($detalle, function($d) use ($buscarUp) {
        return str_contains(mb_strtoupper($d['nss'] ?? '', 'UTF-8'), $buscarUp)
            || str_contains(mb_strtoupper($d['apellido_paterno_pdf'] ?? '', 'UTF-8'), $buscarUp)
            || str_contains(mb_strtoupper($d['nombre_pdf'] ?? '', 'UTF-8'), $buscarUp)
            || str_contains(mb_strtoupper($d['curp_pdf'] ?? '', 'UTF-8'), $buscarUp);
    }));
}

$porPagina    = 100;
$totalDetalle = count($detalle);
$totalPaginas = max(1, (int) ceil($totalDetalle / $porPagina));
$pagina       = max(1, min($totalPaginas, intval($_GET['pagina'] ?? 1)));
$detalleVis   = array_slice($detalle, ($pagina - 1) * $porPagina, $porPagina);
$offsetNum    = ($pagina - 1) * $porPagina;

$dupCalc = intval($acuse['total_registros'])
         - intval($acuse['procesados'])
         - intval($acuse['no_encontrados'])
         - intval($acuse['omitidos_por_fecha']);
$dupCalc = max(0, $dupCalc);

$hayNuevosEnSistema = false;
foreach ($detalle as $fila) {
    if ($fila['resultado'] === 'no_encontrado' && $fila['nombre_actual']) {
        $hayNuevosEnSistema = true;
        break;
    }
}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-admin-se.php';
?>

<style>
.detalle-stat { display:flex; flex-direction:column; align-items:center; gap:4px; padding:20px 16px; }
.detalle-stat-num { font-size:26px; font-weight:700; line-height:1; }
.detalle-stat-label { font-size:12px; color:var(--text-secondary); text-align:center; display:flex; align-items:center; justify-content:center; gap:3px; }
.detalle-stats-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:0; border:1px solid var(--border-color); border-radius:10px; overflow:visible; margin-bottom:24px; }
.detalle-stats-grid .detalle-stat + .detalle-stat { border-left:1px solid var(--border-color); }
.detalle-stats-grid .detalle-stat:first-child { border-radius:9px 0 0 9px; }
.detalle-stats-grid .detalle-stat:last-child { border-radius:0 9px 9px 0; }
.tipo-badge-lg { display:inline-flex; align-items:center; gap:8px; padding:6px 18px; border-radius:99px; font-size:16px; font-weight:700; }
.tipo-badge-alta { background:#DCFCE7; color:#166534; }
.tipo-badge-baja { background:#FEE2E2; color:#991B1B; }
.tip-icon { display:inline-flex; cursor:help; color:var(--text-muted); vertical-align:middle; }
.tip-icon i, .tip-icon svg { width:12px!important; height:12px!important; display:block; }
</style>

<div class="page-header">
    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <div>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:4px;">
                <h1 class="page-title" style="margin:0;">Lote <?= htmlspecialchars($acuse['numero_lote']) ?></h1>
                <?php if ($acuse['tipo'] === 'alta'): ?>
                    <span class="tipo-badge-lg tipo-badge-alta">&#9650; Alta</span>
                <?php else: ?>
                    <span class="tipo-badge-lg tipo-badge-baja">&#9660; Baja</span>
                <?php endif; ?>
            </div>
            <p class="page-subtitle">
                Fecha acuse: <strong><?= date('d/m/Y', strtotime($acuse['fecha_recepcion_imss'])) ?></strong>
                &nbsp;·&nbsp; Subido por: <strong><?= htmlspecialchars($acuse['subido_por']) ?></strong>
                &nbsp;·&nbsp; <?= date('d/m/Y H:i', strtotime($acuse['fecha_subida'])) ?>
            </p>
        </div>
        <button class="btn btn-ghost" style="margin-left:auto;" onclick="window.close()">
            <i data-lucide="x"></i> Cerrar ventana
        </button>
    </div>
</div>

<!-- Estadísticas -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:0;">
        <div class="detalle-stats-grid">
            <div class="detalle-stat">
                <div class="detalle-stat-num"><?= intval($acuse['total_registros']) ?></div>
                <div class="detalle-stat-label">Total en PDF <span class="tip-icon" data-tip="Número total de NSS en el documento PDF del IMSS, incluyendo duplicados."><i data-lucide="info"></i></span></div>
            </div>
            <div class="detalle-stat">
                <div class="detalle-stat-num" style="color:#16A34A;"><?= intval($acuse['procesados']) ?></div>
                <div class="detalle-stat-label">Procesados <span class="tip-icon" data-tip="NSS que sí estaban en el sistema y cuyo estatus IMSS fue actualizado correctamente con este acuse."><i data-lucide="info"></i></span></div>
            </div>
            <div class="detalle-stat">
                <div class="detalle-stat-num" style="color:#DC2626;"><?= intval($acuse['no_encontrados']) ?></div>
                <div class="detalle-stat-label">No encontrados <span class="tip-icon" data-tip="NSS del PDF que no tienen ningún alumno registrado en el sistema con ese número de seguridad social."><i data-lucide="info"></i></span></div>
            </div>
            <div class="detalle-stat">
                <div class="detalle-stat-num" style="color:#D97706;"><?= intval($acuse['omitidos_por_fecha']) ?></div>
                <div class="detalle-stat-label">Omitidos (fecha) <span class="tip-icon" data-tip="NSS encontrados en el sistema pero cuya fecha del acuse no es más reciente que la ya registrada, por lo que no se actualizó su estatus."><i data-lucide="info"></i></span></div>
            </div>
        </div>
    </div>
</div>

<!-- Alertas -->
<?php if ($dupCalc > 0): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
    <i data-lucide="alert-triangle"></i>
    Este acuse tenía <strong><?= $dupCalc ?></strong> NSS duplicado(s) en el PDF. Solo se procesó la primera ocurrencia de cada uno.
</div>
<?php endif; ?>

<?php if ($hayNuevosEnSistema): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
    <i data-lucide="info"></i>
    Algunos NSS marcados como <strong>No encontrado</strong> ahora sí existen en el sistema (migraciones posteriores).
</div>
<?php endif; ?>

<!-- Tabla detalle -->
<div class="card">
    <div class="card-header" style="flex-wrap:wrap; gap:12px;">
        <h3 class="card-title"><i data-lucide="list"></i> Registros del acuse</h3>
        <form method="get" action="" style="display:flex; gap:8px; align-items:center; margin-left:auto;">
            <input type="hidden" name="id" value="<?= $idAcuse ?>">
            <div style="position:relative;">
                <i data-lucide="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;"></i>
                <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>"
                       placeholder="NSS, nombre, CURP…"
                       class="form-control" style="padding-left:32px; width:220px; font-size:13px;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
            <?php if ($buscar !== ''): ?>
                <a href="?id=<?= $idAcuse ?>" class="btn btn-ghost btn-sm" title="Limpiar búsqueda">
                    <i data-lucide="x" style="width:15px;height:15px;"></i>
                </a>
            <?php endif; ?>
        </form>
        <span style="font-size:13px; color:var(--text-secondary); width:100%;">
            <?php if ($buscar !== ''): ?>
                <?= $totalDetalle ?> resultado(s) para <strong>"<?= htmlspecialchars($buscar) ?>"</strong>
            <?php else: ?>
                <?= $totalDetalle ?> registros totales
            <?php endif; ?>
            <?php if ($totalPaginas > 1): ?>
                &nbsp;·&nbsp; Página <?= $pagina ?> de <?= $totalPaginas ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-container">
            <table class="table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>NSS</th>
                        <th>Apellido(s)</th>
                        <th>Nombre(s)</th>
                        <th>CURP</th>
                        <th><span class="tip-icon tip-icon-down" data-tip="✔ Procesado: estatus actualizado · ✘ No encontrado: NSS no está en el sistema · Omitido (fecha): fecha no más reciente que la registrada · ⚠ Duplicado NSS: repetido en el PDF, solo se procesó la primera ocurrencia">Resultado <i data-lucide="info"></i></span></th>
                        <th><span class="tip-icon tip-icon-down" data-tip="Muestra si ese NSS actualmente tiene un alumno en el sistema, incluyendo migraciones posteriores a la subida de este acuse.">En sistema hoy <i data-lucide="info"></i></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($detalleVis as $i => $d): ?>
                    <?php
                    $iconoRes = match($d['resultado']) {
                        'procesado'         => '<span style="color:#16A34A;">&#10004; Procesado</span>',
                        'no_encontrado'     => '<span style="color:#DC2626;">&#10008; No encontrado</span>',
                        'omitido_por_fecha' => '<span style="color:#D97706;">&#9197; Omitido (fecha)</span>',
                        'duplicado_nss'     => '<span style="color:#92400E;">&#9888; Duplicado NSS</span>',
                        default             => htmlspecialchars($d['resultado']),
                    };
                    $enSistema = $d['nombre_actual']
                        ? '<span style="color:#16A34A;">&#10004; ' . htmlspecialchars($d['nombre_actual']) . '</span>'
                          . '<br><span style="font-size:11px;color:var(--text-muted);">NC: ' . htmlspecialchars($d['nc_actual'] ?? '—') . '</span>'
                        : '<span style="color:var(--text-muted);">&#10008; No en sistema</span>';
                    $dash = '<span style="color:var(--text-muted);">—</span>';
                    ?>
                    <tr>
                        <td style="color:var(--text-muted);"><?= $offsetNum + $i + 1 ?></td>
                        <td style="font-family:monospace;"><?= htmlspecialchars($d['nss']) ?></td>
                        <td><?= $d['apellido_paterno_pdf'] ? htmlspecialchars($d['apellido_paterno_pdf']) : $dash ?></td>
                        <td><?= $d['nombre_pdf'] ? htmlspecialchars($d['nombre_pdf']) : $dash ?></td>
                        <td style="font-family:monospace;font-size:11px;"><?= $d['curp_pdf'] ? htmlspecialchars($d['curp_pdf']) : $dash ?></td>
                        <td><?= $iconoRes ?></td>
                        <td><?= $enSistema ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPaginas > 1): ?>
    <?php $qBuscar = $buscar !== '' ? '&buscar=' . urlencode($buscar) : ''; ?>
    <div class="card-footer" style="display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:13px; color:var(--text-secondary);">
            Mostrando <?= $offsetNum + 1 ?>–<?= min($offsetNum + $porPagina, $totalDetalle) ?> de <?= $totalDetalle ?>
        </span>
        <div style="display:flex; gap:4px;">
            <?php if ($pagina > 1): ?>
                <a href="?id=<?= $idAcuse ?>&pagina=<?= $pagina - 1 ?><?= $qBuscar ?>" class="btn btn-ghost btn-sm">
                    <i data-lucide="chevron-left" style="width:16px;height:16px;"></i>
                </a>
            <?php endif; ?>
            <?php for ($p = max(1, $pagina - 2); $p <= min($totalPaginas, $pagina + 2); $p++): ?>
                <a href="?id=<?= $idAcuse ?>&pagina=<?= $p ?><?= $qBuscar ?>"
                   class="btn btn-sm <?= $p === $pagina ? 'btn-primary' : 'btn-ghost' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
            <?php if ($pagina < $totalPaginas): ?>
                <a href="?id=<?= $idAcuse ?>&pagina=<?= $pagina + 1 ?><?= $qBuscar ?>" class="btn btn-ghost btn-sm">
                    <i data-lucide="chevron-right" style="width:16px;height:16px;"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const tt = document.createElement('div');
    tt.style.cssText = 'position:fixed;background:#1E293B;color:#F8FAFC;font-size:11px;line-height:1.5;padding:7px 10px;border-radius:6px;max-width:220px;white-space:normal;pointer-events:none;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.2);display:none;';
    document.body.appendChild(tt);

    function place(e) {
        const w = tt.offsetWidth, h = tt.offsetHeight;
        let x = e.clientX - w / 2;
        let y = e.clientY - h - 10;
        x = Math.max(8, Math.min(x, window.innerWidth  - w - 8));
        y = y < 8 ? e.clientY + 16 : y;
        tt.style.left = x + 'px';
        tt.style.top  = y + 'px';
    }

    document.querySelectorAll('.tip-icon[data-tip]').forEach(el => {
        el.addEventListener('mouseenter', e => { tt.textContent = el.dataset.tip; tt.style.display = 'block'; place(e); });
        el.addEventListener('mousemove',  place);
        el.addEventListener('mouseleave', () => { tt.style.display = 'none'; });
    });
})();
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
