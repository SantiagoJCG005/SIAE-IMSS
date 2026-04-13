<?php
/**
 * SIAE-IMSS - Gestión de Catálogos
 */
$pageTitle = 'Catálogos';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole([ROL_SUPERADMIN]);

$pdo = getConnection();
$tab = get('tab', 'carreras');

// Obtener datos según tab
$data = [];
$columns = [];

switch ($tab) {
    case 'carreras':
        $stmt = $pdo->query("SELECT c.*, n.nombre as nivel_nombre FROM carreras c LEFT JOIN nivel n ON c.id_nivel = n.id_nivel ORDER BY c.nombre");
        $data = $stmt->fetchAll();
        $columns = ['ID', 'Clave', 'Nombre', 'Nivel', 'Activo'];
        break;
    case 'niveles':
        $stmt = $pdo->query("SELECT * FROM nivel ORDER BY nombre");
        $data = $stmt->fetchAll();
        $columns = ['ID', 'Nombre', 'Descripción', 'Activo'];
        break;
    case 'modalidades':
        $stmt = $pdo->query("SELECT * FROM modalidad ORDER BY nombre");
        $data = $stmt->fetchAll();
        $columns = ['ID', 'Nombre', 'Descripción', 'Activo'];
        break;
    case 'periodos':
        $stmt = $pdo->query("SELECT * FROM periodo_escolar ORDER BY fecha_inicio DESC");
        $data = $stmt->fetchAll();
        $columns = ['ID', 'Nombre', 'Fecha Inicio', 'Fecha Fin', 'Activo'];
        break;
    case 'semestres':
        $stmt = $pdo->query("SELECT * FROM semestre ORDER BY numero");
        $data = $stmt->fetchAll();
        $columns = ['ID', 'Número', 'Nombre', 'Activo'];
        break;
    case 'motivos':
        $stmt = $pdo->query("SELECT * FROM motivos_bajas ORDER BY descripcion");
        $data = $stmt->fetchAll();
        $columns = ['ID', 'Clave', 'Descripción', 'Activo'];
        break;
}

// Para selects en formularios
$niveles = $pdo->query("SELECT * FROM nivel WHERE activo = 1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-superadmin.php';
?>

<!-- Page Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1 class="page-title">Catálogos del Sistema</h1>
        <p class="page-subtitle">Administración de tablas de referencia y parámetros académicos.</p>
    </div>
    <button class="btn btn-primary" onclick="abrirModal()">
        <i data-lucide="plus"></i> Nuevo registro
    </button>
</div>

<!-- Tabs -->
<div class="tabs">
    <a href="?tab=carreras" class="tab <?= $tab === 'carreras' ? 'active' : '' ?>">
        <i data-lucide="book-open" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Carreras
    </a>
    <a href="?tab=niveles" class="tab <?= $tab === 'niveles' ? 'active' : '' ?>">
        <i data-lucide="layers" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Niveles
    </a>
    <a href="?tab=modalidades" class="tab <?= $tab === 'modalidades' ? 'active' : '' ?>">
        <i data-lucide="layout-grid" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Modalidades
    </a>
    <a href="?tab=periodos" class="tab <?= $tab === 'periodos' ? 'active' : '' ?>">
        <i data-lucide="calendar" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Periodos
    </a>
    <a href="?tab=semestres" class="tab <?= $tab === 'semestres' ? 'active' : '' ?>">
        <i data-lucide="hash" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Semestres
    </a>
    <a href="?tab=motivos" class="tab <?= $tab === 'motivos' ? 'active' : '' ?>">
        <i data-lucide="file-x" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Motivos de Baja
    </a>
</div>

<!-- Tabla de datos -->
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <?php foreach ($columns as $col): ?>
                    <th><?= $col ?></th>
                    <?php endforeach; ?>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                <tr>
                    <td colspan="<?= count($columns) + 1 ?>">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i data-lucide="database"></i></div>
                            <div class="empty-state-title">No hay registros</div>
                            <div class="empty-state-text">Agrega el primer registro usando el botón superior</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($data as $row): ?>
                    <tr>
                        <?php if ($tab === 'carreras'): ?>
                            <td><?= $row['id_carrera'] ?></td>
                            <td><strong><?= htmlspecialchars($row['clave'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($row['nombre']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($row['nivel_nombre'] ?? 'N/A') ?></span></td>
                            <td>
                                <?php if ($row['activo']): ?>
                                    <span class="badge badge-success badge-dot">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger badge-dot">Inactivo</span>
                                <?php endif; ?>
                            </td>
                        <?php elseif ($tab === 'niveles'): ?>
                            <td><?= $row['id_nivel'] ?></td>
                            <td><strong><?= htmlspecialchars($row['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($row['descripcion'] ?? '-') ?></td>
                            <td>
                                <?php if ($row['activo']): ?>
                                    <span class="badge badge-success badge-dot">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger badge-dot">Inactivo</span>
                                <?php endif; ?>
                            </td>
                        <?php elseif ($tab === 'modalidades'): ?>
                            <td><?= $row['id_modalidad'] ?></td>
                            <td><strong><?= htmlspecialchars($row['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($row['descripcion'] ?? '-') ?></td>
                            <td>
                                <?php if ($row['activo']): ?>
                                    <span class="badge badge-success badge-dot">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger badge-dot">Inactivo</span>
                                <?php endif; ?>
                            </td>
                        <?php elseif ($tab === 'periodos'): ?>
                            <td><?= $row['id_periodo'] ?></td>
                            <td><strong><?= htmlspecialchars($row['nombre']) ?></strong></td>
                            <td><?= formatDate($row['fecha_inicio']) ?></td>
                            <td><?= formatDate($row['fecha_fin']) ?></td>
                            <td>
                                <?php if ($row['activo']): ?>
                                    <span class="badge badge-success badge-dot">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary badge-dot">Cerrado</span>
                                <?php endif; ?>
                            </td>
                        <?php elseif ($tab === 'semestres'): ?>
                            <td><?= $row['id_semestre'] ?></td>
                            <td><strong><?= $row['numero'] ?></strong></td>
                            <td><?= htmlspecialchars($row['nombre']) ?></td>
                            <td>
                                <?php if ($row['activo']): ?>
                                    <span class="badge badge-success badge-dot">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger badge-dot">Inactivo</span>
                                <?php endif; ?>
                            </td>
                        <?php elseif ($tab === 'motivos'): ?>
                            <td><?= $row['id_motivo'] ?></td>
                            <td><strong><?= htmlspecialchars($row['clave'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($row['descripcion']) ?></td>
                            <td>
                                <?php if ($row['activo']): ?>
                                    <span class="badge badge-success badge-dot">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger badge-dot">Inactivo</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-ghost btn-icon" title="Editar" onclick="editarRegistro(<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE)) ?>)">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <button class="btn btn-ghost btn-icon" title="Eliminar" onclick="eliminarRegistro(<?= $row[array_key_first($row)] ?>)">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalCatalogo">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Nuevo Registro</h3>
            <button class="modal-close" onclick="cerrarModal()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="formCatalogo" onsubmit="guardarRegistro(event)">
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const currentTab = '<?= $tab ?>';
const niveles = <?= json_encode($niveles) ?>;
let editingId = null;

function getFormHTML(data = null) {
    editingId = data ? Object.values(data)[0] : null;
    
    switch (currentTab) {
        case 'carreras':
            return `
                <input type="hidden" name="id" value="${editingId || ''}">
                <div class="form-group">
                    <label class="form-label">Clave</label>
                    <input type="text" name="clave" class="form-control" value="${data?.clave || ''}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nombre de la Carrera</label>
                    <input type="text" name="nombre" class="form-control" value="${data?.nombre || ''}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nivel</label>
                    <select name="id_nivel" class="form-control form-select" required>
                        <option value="">Seleccionar nivel</option>
                        ${niveles.map(n => `<option value="${n.id_nivel}" ${data?.id_nivel == n.id_nivel ? 'selected' : ''}>${n.nombre}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-control form-select">
                        <option value="1" ${data?.activo == 1 ? 'selected' : ''}>Activo</option>
                        <option value="0" ${data?.activo == 0 ? 'selected' : ''}>Inactivo</option>
                    </select>
                </div>
            `;

        case 'niveles':
            return `
                <input type="hidden" name="id" value="${editingId || ''}">
                <div class="form-group">
                    <label class="form-label">Nombre del Nivel</label>
                    <input type="text" name="nombre" class="form-control" value="${data?.nombre || ''}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3">${data?.descripcion || ''}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-control form-select">
                        <option value="1" ${data?.activo == 1 ? 'selected' : ''}>Activo</option>
                        <option value="0" ${data?.activo == 0 ? 'selected' : ''}>Inactivo</option>
                    </select>
                </div>
            `;

        case 'modalidades':
            return `
                <input type="hidden" name="id" value="${editingId || ''}">
                <div class="form-group">
                    <label class="form-label">Nombre de la Modalidad</label>
                    <input type="text" name="nombre" class="form-control" value="${data?.nombre || ''}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3">${data?.descripcion || ''}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-control form-select">
                        <option value="1" ${data?.activo == 1 ? 'selected' : ''}>Activo</option>
                        <option value="0" ${data?.activo == 0 ? 'selected' : ''}>Inactivo</option>
                    </select>
                </div>
            `;

        case 'periodos':
            return `
                <input type="hidden" name="id" value="${editingId || ''}">
                <div class="form-group">
                    <label class="form-label">Nombre del Periodo</label>
                    <input type="text" name="nombre" class="form-control" value="${data?.nombre || ''}" placeholder="Ej: Enero-Junio 2025" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="${data?.fecha_inicio || ''}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="${data?.fecha_fin || ''}" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-control form-select">
                        <option value="1" ${data?.activo == 1 ? 'selected' : ''}>Activo</option>
                        <option value="0" ${data?.activo == 0 ? 'selected' : ''}>Cerrado</option>
                    </select>
                </div>
            `;

        case 'semestres':
            return `
                <input type="hidden" name="id" value="${editingId || ''}">
                <div class="form-group">
                    <label class="form-label">Número de Semestre</label>
                    <input type="number" name="numero" class="form-control" value="${data?.numero || ''}" min="1" max="12" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" value="${data?.nombre || ''}" placeholder="Ej: Primer Semestre" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-control form-select">
                        <option value="1" ${data?.activo == 1 ? 'selected' : ''}>Activo</option>
                        <option value="0" ${data?.activo == 0 ? 'selected' : ''}>Inactivo</option>
                    </select>
                </div>
            `;

        case 'motivos':
            return `
                <input type="hidden" name="id" value="${editingId || ''}">
                <div class="form-group">
                    <label class="form-label">Clave</label>
                    <input type="text" name="clave" class="form-control" value="${data?.clave || ''}" placeholder="Ej: BAJ001">
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción del Motivo</label>
                    <textarea name="descripcion" class="form-control" rows="3" required>${data?.descripcion || ''}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-control form-select">
                        <option value="1" ${data?.activo == 1 ? 'selected' : ''}>Activo</option>
                        <option value="0" ${data?.activo == 0 ? 'selected' : ''}>Inactivo</option>
                    </select>
                </div>
            `;
    }
}

function abrirModal() {
    document.getElementById('modalTitle').textContent = 'Nuevo Registro';
    document.getElementById('modalBody').innerHTML = getFormHTML();
    document.getElementById('modalCatalogo').classList.add('active');
    lucide.createIcons();
}

function editarRegistro(data) {
    document.getElementById('modalTitle').textContent = 'Editar Registro';
    document.getElementById('modalBody').innerHTML = getFormHTML(data);
    document.getElementById('modalCatalogo').classList.add('active');
    lucide.createIcons();
}

function cerrarModal() {
    document.getElementById('modalCatalogo').classList.remove('active');
    editingId = null;
}

async function guardarRegistro(e) {
    e.preventDefault();
    
    const form = document.getElementById('formCatalogo');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    data.tab = currentTab;
    data.action = editingId ? 'update' : 'create';
    if (editingId) data.id = editingId;

    const response = await fetchAPI('<?= BASE_URL ?>api/catalogos.php', {
        method: 'POST',
        body: JSON.stringify(data)
    });
    
    if (response && response.success) {
        showToast(response.message);
        cerrarModal();
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(response?.message || 'Error al guardar', 'error');
    }
}

async function eliminarRegistro(id) {
    confirmAction('¿Deseas eliminar este registro?', async () => {
        const response = await fetchAPI('<?= BASE_URL ?>api/catalogos.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'delete',
                tab: currentTab,
                id: id
            })
        });
        
        if (response && response.success) {
            showToast(response.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response?.message || 'Error al eliminar', 'error');
        }
    });
}

// Cerrar modal con ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') cerrarModal();
});

document.getElementById('modalCatalogo').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) cerrarModal();
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>