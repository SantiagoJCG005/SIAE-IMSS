<?php
$tituloPagina = 'Estatus IMSS Alumnos';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirRol([ROL_JEFA_SERVICIOS, ROL_SUPERADMIN]);

$conexion = obtenerConexion();

$buscar  = trim($_GET['buscar'] ?? '');
$filtro  = $_GET['estatus'] ?? '';   // alta | baja | proceso | ''
$pagina  = max(1, intval($_GET['pagina'] ?? 1));
$porPagina = 50;

// ── Contadores para los tabs ──────────────────────────────────────────────────
$counts = $conexion->query("
    SELECT
        SUM(CASE WHEN e.estatus = 'alta'  THEN 1 ELSE 0 END) AS altas,
        SUM(CASE WHEN e.estatus = 'baja'  THEN 1 ELSE 0 END) AS bajas,
        SUM(CASE WHEN e.id_alumno IS NULL THEN 1 ELSE 0 END) AS en_proceso,
        COUNT(a.id_alumno) AS total
    FROM alumnos a
    LEFT JOIN estatus_imss_alumnos e ON e.id_alumno = a.id_alumno
    WHERE a.activo = 1
")->fetch();

// ── Query principal ───────────────────────────────────────────────────────────
$where  = ['a.activo = 1'];
$params = [];

if ($buscar !== '') {
    $where[]  = '(a.numero_control LIKE ? OR a.nombre LIKE ? OR a.apellido_paterno LIKE ? OR a.apellido_materno LIKE ? OR di.nss LIKE ?)';
    $like     = "%$buscar%";
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($filtro === 'alta') {
    $where[] = "e.estatus = 'alta'";
} elseif ($filtro === 'baja') {
    $where[] = "e.estatus = 'baja'";
} elseif ($filtro === 'proceso') {
    $where[] = 'e.id_alumno IS NULL';
}

$whereSql = implode(' AND ', $where);

$totalStmt = $conexion->prepare("
    SELECT COUNT(*) FROM alumnos a
    LEFT JOIN datos_imss di         ON di.id_alumno = a.id_alumno
    LEFT JOIN estatus_imss_alumnos e ON e.id_alumno = a.id_alumno
    WHERE $whereSql
");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();

$totalPaginas = max(1, (int) ceil($total / $porPagina));
$pagina       = min($pagina, $totalPaginas);
$offset       = ($pagina - 1) * $porPagina;

$stmt = $conexion->prepare("
    SELECT
        a.id_alumno,
        a.numero_control,
        a.nombre,
        a.apellido_paterno,
        a.apellido_materno,
        a.curp,
        a.email,
        c.nombre        AS carrera,
        di.nss,
        e.estatus       AS estatus_actual,
        e.fecha_movimiento,
        ai.numero_lote  AS lote_origen,
        ai.tipo         AS tipo_lote
    FROM alumnos a
    LEFT JOIN carreras c             ON c.id_carrera  = a.id_carrera
    LEFT JOIN datos_imss di          ON di.id_alumno  = a.id_alumno
    LEFT JOIN estatus_imss_alumnos e ON e.id_alumno   = a.id_alumno
    LEFT JOIN acuses_imss ai         ON ai.id_acuse   = e.id_acuse_origen
    WHERE $whereSql
    ORDER BY
        FIELD(e.estatus, 'baja', NULL, 'alta') DESC,
        a.apellido_paterno, a.apellido_materno, a.nombre
    LIMIT $porPagina OFFSET $offset
");
$stmt->execute($params);
$alumnos = $stmt->fetchAll();

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-jefa.php';
?>

<style>
.estatus-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.estatus-tab {
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 18px; border-radius:99px; font-size:13px; font-weight:500;
    border:1px solid var(--border-color); background:white;
    cursor:pointer; text-decoration:none; color:var(--text-secondary);
    transition:all .15s;
}
.estatus-tab:hover { border-color:#94A3B8; color:var(--text-primary); }
.estatus-tab.activo-alta  { background:#DCFCE7; border-color:#86EFAC; color:#166534; }
.estatus-tab.activo-baja  { background:#FEE2E2; border-color:#FCA5A5; color:#991B1B; }
.estatus-tab.activo-proceso { background:#FEF3C7; border-color:#FCD34D; color:#92400E; }
.estatus-tab.activo-todos { background:#EFF6FF; border-color:#93C5FD; color:#1D4ED8; }
.estatus-tab .count {
    background:rgba(0,0,0,.08); border-radius:99px;
    padding:1px 8px; font-size:11px; font-weight:600;
}
.badge-alta    { background:#DCFCE7; color:#166534; padding:3px 12px; border-radius:99px; font-size:12px; font-weight:600; white-space:nowrap; }
.badge-baja    { background:#FEE2E2; color:#991B1B; padding:3px 12px; border-radius:99px; font-size:12px; font-weight:600; white-space:nowrap; }
.badge-proceso { background:#FEF3C7; color:#92400E; padding:3px 12px; border-radius:99px; font-size:12px; font-weight:600; white-space:nowrap; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Estatus IMSS Alumnos</h1>
        <p class="page-subtitle">Estado actual de registro IMSS de cada alumno según los acuses confirmados.</p>
    </div>
</div>

<!-- Tabs de filtro -->
<div class="estatus-tabs">
    <?php
    $qBase = $buscar ? '&buscar=' . urlencode($buscar) : '';
    $tabs  = [
        ['', 'Todos', $counts['total'], 'activo-todos'],
        ['alta', '▲ Alta', $counts['altas'], 'activo-alta'],
        ['baja', '▼ Baja', $counts['bajas'], 'activo-baja'],
        ['proceso', 'En proceso', $counts['en_proceso'], 'activo-proceso'],
    ];
    foreach ($tabs as [$val, $label, $count, $cls]):
        $activo = $filtro === $val ? $cls : '';
    ?>
    <a href="?estatus=<?= $val ?><?= $qBase ?>" class="estatus-tab <?= $activo ?>">
        <?= $label ?>
        <span class="count"><?= number_format((int)$count) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Buscador -->
<form method="get" action="" style="display:flex;gap:8px;margin-bottom:20px;">
    <input type="hidden" name="estatus" value="<?= htmlspecialchars($filtro) ?>">
    <div style="position:relative;flex:1;max-width:360px;">
        <i data-lucide="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-muted);pointer-events:none;"></i>
        <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>"
               placeholder="Nombre, No. Control, NSS…"
               class="form-control" style="padding-left:32px;">
    </div>
    <button type="submit" class="btn btn-primary">Buscar</button>
    <?php if ($buscar !== ''): ?>
        <a href="?estatus=<?= htmlspecialchars($filtro) ?>" class="btn btn-ghost">
            <i data-lucide="x" style="width:15px;height:15px;"></i>
        </a>
    <?php endif; ?>
</form>

<!-- Tabla -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-lucide="users"></i> Alumnos</h3>
        <span style="font-size:13px;color:var(--text-secondary);margin-left:auto;">
            <?= number_format($total) ?> resultado(s)
            <?php if ($totalPaginas > 1): ?> · Página <?= $pagina ?>/<?= $totalPaginas ?><?php endif; ?>
        </span>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-container">
            <table class="table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>No. Control</th>
                        <th>Nombre</th>
                        <th>Carrera</th>
                        <th>NSS</th>
                        <th>Estatus IMSS</th>
                        <th>Fecha acuse</th>
                        <th>Lote origen</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($alumnos)): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i data-lucide="users"></i></div>
                            <div class="empty-state-title">Sin resultados</div>
                            <div class="empty-state-text">Intenta con otros filtros o búsqueda</div>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($alumnos as $a): ?>
                    <tr>
                        <td style="font-family:monospace;font-weight:600;">
                            <?= htmlspecialchars($a['numero_control']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars(trim($a['apellido_paterno'] . ' ' . $a['apellido_materno'] . ' ' . $a['nombre'])) ?>
                            <?php if ($a['curp']): ?>
                                <div style="font-size:11px;color:var(--text-muted);font-family:monospace;">
                                    <?= htmlspecialchars($a['curp']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;"><?= htmlspecialchars($a['carrera'] ?? '—') ?></td>
                        <td style="font-family:monospace;"><?= htmlspecialchars($a['nss'] ?? '—') ?></td>
                        <td>
                            <?php if ($a['estatus_actual'] === 'alta'): ?>
                                <span class="badge-alta">▲ Alta</span>
                            <?php elseif ($a['estatus_actual'] === 'baja'): ?>
                                <span class="badge-baja">▼ Baja</span>
                            <?php else: ?>
                                <span class="badge-proceso">En proceso</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;">
                            <?= $a['fecha_movimiento'] ? date('d/m/Y', strtotime($a['fecha_movimiento'])) : '—' ?>
                        </td>
                        <td style="font-size:12px;">
                            <?php if ($a['lote_origen']): ?>
                                <span style="font-family:monospace;"><?= htmlspecialchars($a['lote_origen']) ?></span>
                                <div style="font-size:11px;color:var(--text-muted);">
                                    <?= $a['tipo_lote'] === 'alta' ? '▲ Alta' : '▼ Baja' ?>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <?php $qExtra = ($buscar ? '&buscar=' . urlencode($buscar) : '') . ($filtro ? '&estatus=' . $filtro : ''); ?>
    <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:13px;color:var(--text-secondary);">
            Mostrando <?= $offset + 1 ?>–<?= min($offset + $porPagina, $total) ?> de <?= number_format($total) ?>
        </span>
        <div style="display:flex;gap:4px;">
            <?php if ($pagina > 1): ?>
                <a href="?pagina=<?= $pagina - 1 ?><?= $qExtra ?>" class="btn btn-ghost btn-sm">
                    <i data-lucide="chevron-left" style="width:16px;height:16px;"></i>
                </a>
            <?php endif; ?>
            <?php for ($p = max(1, $pagina - 2); $p <= min($totalPaginas, $pagina + 2); $p++): ?>
                <a href="?pagina=<?= $p ?><?= $qExtra ?>"
                   class="btn btn-sm <?= $p === $pagina ? 'btn-primary' : 'btn-ghost' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
            <?php if ($pagina < $totalPaginas): ?>
                <a href="?pagina=<?= $pagina + 1 ?><?= $qExtra ?>" class="btn btn-ghost btn-sm">
                    <i data-lucide="chevron-right" style="width:16px;height:16px;"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
