<?php
/**
 * SIAE-IMSS - Bitácora del Sistema
 */
$pageTitle = 'Bitácora';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole([ROL_SUPERADMIN, ROL_JEFA_SERVICIOS]);

$pdo = getConnection();

// Filtros
$search = get('search', '');
$accionFilter = get('accion', '');
$fechaDesde = get('fecha_desde', '');
$fechaHasta = get('fecha_hasta', '');
$page = max(1, intval(get('page', 1)));
$perPage = 20;

// Construir query
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (u.username LIKE ? OR u.nombre_completo LIKE ? OR b.detalle LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($accionFilter)) {
    $where .= " AND b.accion = ?";
    $params[] = $accionFilter;
}

if (!empty($fechaDesde)) {
    $where .= " AND DATE(b.fecha) >= ?";
    $params[] = $fechaDesde;
}

if (!empty($fechaHasta)) {
    $where .= " AND DATE(b.fecha) <= ?";
    $params[] = $fechaHasta;
}

// Contar total
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM bitacora b LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['total'];

$pagination = paginate($total, $perPage, $page);

// Obtener registros
$sql = "
    SELECT b.*, u.nombre_completo, u.username 
    FROM bitacora b 
    LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario 
    $where 
    ORDER BY b.fecha DESC 
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

// Obtener acciones únicas para filtro
$accionesStmt = $pdo->query("SELECT DISTINCT accion FROM bitacora ORDER BY accion");
$acciones = $accionesStmt->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-superadmin.php';
?>

<!-- Page Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1 class="page-title">Bitácora de Auditoría</h1>
        <p class="page-subtitle">Historial de acciones ejecutadas en el sistema.</p>
    </div>
    <button class="btn btn-outline" onclick="exportarBitacora()">
        <i data-lucide="download"></i>
        Exportar CSV
    </button>
</div>

<!-- Filtros -->
<div class="filters">
    <div class="filter-group" style="flex: 2;">
        <div class="header-search" style="width: 100%;">
            <i data-lucide="search"></i>
            <input type="text" 
                   id="searchInput"
                   placeholder="Buscar por usuario o detalle..." 
                   value="<?= htmlspecialchars($search) ?>"
                   onkeyup="debounceSearch(this.value)">
        </div>
    </div>
    <div class="filter-group">
        <select class="form-control form-select" id="accionFilter" onchange="filtrar()">
            <option value="">Todas las acciones</option>
            <?php foreach ($acciones as $accion): ?>
            <option value="<?= htmlspecialchars($accion) ?>" <?= $accionFilter === $accion ? 'selected' : '' ?>>
                <?= htmlspecialchars($accion) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <input type="date" class="form-control" id="fechaDesde" value="<?= $fechaDesde ?>" onchange="filtrar()" placeholder="Desde">
    </div>
    <div class="filter-group">
        <input type="date" class="form-control" id="fechaHasta" value="<?= $fechaHasta ?>" onchange="filtrar()" placeholder="Hasta">
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Detalle</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registros)): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i data-lucide="scroll-text"></i></div>
                            <div class="empty-state-title">No hay registros</div>
                            <div class="empty-state-text">No se encontraron acciones con los filtros aplicados</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($registros as $registro): ?>
                    <tr>
                        <td>
                            <div style="font-size: 13px;"><?= formatDateTime($registro['fecha']) ?></div>
                        </td>
                        <td>
                            <?php if ($registro['nombre_completo']): ?>
                            <div class="d-flex align-center gap-2">
                                <div class="avatar avatar-sm" style="background: <?= getAvatarColor($registro['nombre_completo']) ?>">
                                    <?= getInitials($registro['nombre_completo']) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($registro['nombre_completo']) ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($registro['username']) ?></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">Sistema</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $badgeClass = 'badge-secondary';
                            if (strpos($registro['accion'], 'LOGIN') !== false) $badgeClass = 'badge-success';
                            elseif (strpos($registro['accion'], 'CREAR') !== false || strpos($registro['accion'], 'ALTA') !== false) $badgeClass = 'badge-info';
                            elseif (strpos($registro['accion'], 'EDITAR') !== false) $badgeClass = 'badge-warning';
                            elseif (strpos($registro['accion'], 'ELIMINAR') !== false || strpos($registro['accion'], 'BAJA') !== false) $badgeClass = 'badge-danger';
                            elseif (strpos($registro['accion'], 'EXPORTAR') !== false) $badgeClass = 'badge-info';
                            elseif (strpos($registro['accion'], 'LOGOUT') !== false) $badgeClass = 'badge-secondary';
                            elseif (strpos($registro['accion'], 'FALLIDO') !== false) $badgeClass = 'badge-danger';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($registro['accion']) ?></span>
                        </td>
                        <td>
                            <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($registro['detalle']) ?>">
                                <?= htmlspecialchars($registro['detalle'] ?: '-') ?>
                            </div>
                        </td>
                        <td>
                            <code style="font-size: 12px; background: var(--bg-secondary); padding: 2px 6px; border-radius: 4px;">
                                <?= htmlspecialchars($registro['ip_address'] ?: '-') ?>
                            </code>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginación -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center;">
        <div class="pagination-info">
            Mostrando <?= $pagination['offset'] + 1 ?> - <?= min($pagination['offset'] + $perPage, $total) ?> de <?= number_format($total) ?> registros
        </div>
        <div class="pagination">
            <button class="pagination-btn" onclick="goToPage(1)" <?= !$pagination['has_prev'] ? 'disabled' : '' ?>>
                <i data-lucide="chevrons-left"></i>
            </button>
            <button class="pagination-btn" onclick="goToPage(<?= $page - 1 ?>)" <?= !$pagination['has_prev'] ? 'disabled' : '' ?>>
                <i data-lucide="chevron-left"></i>
            </button>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($pagination['total_pages'], $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <button class="pagination-btn <?= $i == $page ? 'active' : '' ?>" onclick="goToPage(<?= $i ?>)">
                <?= $i ?>
            </button>
            <?php endfor; ?>
            
            <button class="pagination-btn" onclick="goToPage(<?= $page + 1 ?>)" <?= !$pagination['has_next'] ? 'disabled' : '' ?>>
                <i data-lucide="chevron-right"></i>
            </button>
            <button class="pagination-btn" onclick="goToPage(<?= $pagination['total_pages'] ?>)" <?= !$pagination['has_next'] ? 'disabled' : '' ?>>
                <i data-lucide="chevrons-right"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
let searchTimeout;

function debounceSearch(value) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const url = new URL(window.location);
        if (value) {
            url.searchParams.set('search', value);
        } else {
            url.searchParams.delete('search');
        }
        url.searchParams.set('page', 1);
        window.location = url;
    }, 500);
}

function filtrar() {
    const url = new URL(window.location);
    
    const accion = document.getElementById('accionFilter').value;
    const fechaDesde = document.getElementById('fechaDesde').value;
    const fechaHasta = document.getElementById('fechaHasta').value;
    
    if (accion) url.searchParams.set('accion', accion);
    else url.searchParams.delete('accion');
    
    if (fechaDesde) url.searchParams.set('fecha_desde', fechaDesde);
    else url.searchParams.delete('fecha_desde');
    
    if (fechaHasta) url.searchParams.set('fecha_hasta', fechaHasta);
    else url.searchParams.delete('fecha_hasta');
    
    url.searchParams.set('page', 1);
    window.location = url;
}

function goToPage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location = url;
}

function exportarBitacora() {
    const url = new URL(window.location);
    url.pathname = '<?= BASE_URL ?>api/bitacora.php';
    url.searchParams.set('action', 'export');
    window.location = url;
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
