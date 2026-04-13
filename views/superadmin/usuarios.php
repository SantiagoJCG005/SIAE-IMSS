<?php
/**
 * SIAE-IMSS - Gestión de Usuarios
 */
$pageTitle = 'Usuarios';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar acceso
requireRole([ROL_SUPERADMIN]);

$pdo = getConnection();

// Parámetros de búsqueda y paginación
$search = get('search', '');
$rolFilter = get('rol', '');
$page = max(1, intval(get('page', 1)));
$perPage = 10;

// Construir query
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (u.username LIKE ? OR u.nombre_completo LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($rolFilter)) {
    $where .= " AND u.id_rol = ?";
    $params[] = $rolFilter;
}

// Contar total
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios u $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['total'];

$pagination = paginate($total, $perPage, $page);

// Obtener usuarios
$sql = "
    SELECT u.*, r.nombre as rol_nombre 
    FROM usuarios u 
    LEFT JOIN roles r ON u.id_rol = r.id_rol 
    $where 
    ORDER BY u.id_usuario DESC 
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Obtener roles para filtro
$rolesStmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
$roles = $rolesStmt->fetchAll();

// Estadísticas
$statsStmt = $pdo->query("
    SELECT 
        SUM(activo = 1) as activos,
        SUM(activo = 0) as inactivos,
        COUNT(DISTINCT id_rol) as roles_unicos
    FROM usuarios
");
$stats = $statsStmt->fetch();

// Incluir header y sidebar
include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-superadmin.php';
?>

<!-- Page Header -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1 class="page-title">Usuarios</h1>
        <p class="page-subtitle">Panel de administración central de accesos y perfiles.</p>
    </div>
    <button class="btn btn-primary" onclick="abrirModalUsuario()">
        <i data-lucide="user-plus"></i>
        Nuevo usuario
    </button>
</div>

<!-- Filtros -->
<div class="filters">
    <div class="filter-group" style="flex: 2;">
        <div class="header-search" style="width: 100%;">
            <i data-lucide="search"></i>
            <input type="text" 
                   id="searchInput"
                   placeholder="Buscar por nombre, usuario o email..." 
                   value="<?= htmlspecialchars($search) ?>"
                   onkeyup="debounceSearch(this.value)">
        </div>
    </div>
    <div class="filter-group">
        <select class="form-control form-select" id="rolFilter" onchange="filtrarPorRol(this.value)">
            <option value="">Filtrar por Rol</option>
            <?php foreach ($roles as $rol): ?>
            <option value="<?= $rol['id_rol'] ?>" <?= $rolFilter == $rol['id_rol'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($rol['nombre']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group" style="flex: 0;">
        <button class="btn btn-outline" onclick="exportarUsuarios()">
            <i data-lucide="download"></i>
            Exportar Datos Adm.
        </button>
    </div>
</div>

<!-- Tabla de usuarios -->
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre Completo</th>
                    <th>Rol</th>
                    <th>Vinculado a Alumno</th>
                    <th>Estatus</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i data-lucide="users"></i></div>
                            <div class="empty-state-title">No se encontraron usuarios</div>
                            <div class="empty-state-text">Intenta con otros criterios de búsqueda</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($usuario['username']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($usuario['nombre_completo']) ?></td>
                        <td>
                            <?php
                            $badgeClass = 'badge-secondary';
                            switch ($usuario['id_rol']) {
                                case ROL_SUPERADMIN: $badgeClass = 'badge-superadmin'; break;
                                case ROL_JEFA_SERVICIOS: $badgeClass = 'badge-jefa'; break;
                                case ROL_ADMIN_SERVICIOS: $badgeClass = 'badge-admin-se'; break;
                                case ROL_ADMIN_IMSS: $badgeClass = 'badge-admin-imss'; break;
                                case ROL_ESTUDIANTE: $badgeClass = 'badge-estudiante'; break;
                            }
                            ?>
                            <span class="badge <?= $badgeClass ?>">
                                <?= htmlspecialchars($usuario['rol_nombre']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($usuario['id_rol'] == ROL_ESTUDIANTE): ?>
                                <?php if (!empty($usuario['numero_control'])): ?>
                                    <?= htmlspecialchars($usuario['nombre_completo']) ?> (ID: <?= $usuario['numero_control'] ?>)
                                <?php else: ?>
                                    <span class="text-warning">⚠ SIN VINCULAR</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($usuario['activo']): ?>
                                <span class="badge badge-success badge-dot">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-danger badge-dot">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-ghost btn-icon" title="Editar" 
                                        onclick="editarUsuario(<?= $usuario['id_usuario'] ?>)">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <button class="btn btn-ghost btn-icon" title="<?= $usuario['activo'] ? 'Desactivar' : 'Activar' ?>"
                                        onclick="toggleUsuario(<?= $usuario['id_usuario'] ?>, <?= $usuario['activo'] ? 0 : 1 ?>)">
                                    <i data-lucide="<?= $usuario['activo'] ? 'lock' : 'unlock' ?>"></i>
                                </button>
                            </div>
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
            Mostrando <?= $pagination['offset'] + 1 ?> - <?= min($pagination['offset'] + $perPage, $total) ?> de <?= number_format($total) ?> usuarios registrados
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
            
            <?php if ($end < $pagination['total_pages']): ?>
            <span style="padding: 0 8px;">...</span>
            <button class="pagination-btn" onclick="goToPage(<?= $pagination['total_pages'] ?>)">
                <?= $pagination['total_pages'] ?>
            </button>
            <?php endif; ?>
            
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

<!-- Stats Cards -->
<div class="stats-grid" style="margin-top: 24px;">
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="user-check"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Activos</div>
            <div class="stat-value"><?= number_format($stats['activos'] ?? 0) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger" style="background: var(--danger-bg); color: var(--danger);">
            <i data-lucide="user-x"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Inactivos</div>
            <div class="stat-value"><?= number_format($stats['inactivos'] ?? 0) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info">
            <i data-lucide="shield"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Roles Únicos</div>
            <div class="stat-value"><?= $stats['roles_unicos'] ?? 0 ?></div>
        </div>
    </div>
</div>

<!-- Modal Nuevo/Editar Usuario -->
<div class="modal-overlay" id="modalUsuario">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Nuevo Usuario</h3>
            <button class="modal-close" onclick="cerrarModal()">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="formUsuario" onsubmit="guardarUsuario(event)">
            <div class="modal-body">
                <input type="hidden" name="id_usuario" id="id_usuario">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Usuario *</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nombre Completo *</label>
                    <input type="text" name="nombre_completo" id="nombre_completo" class="form-control" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Contraseña <span id="passHint">(requerida)</span></label>
                        <input type="password" name="password" id="password" class="form-control">
                        <small class="form-text">Mínimo 8 caracteres</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmar Contraseña</label>
                        <input type="password" name="password_confirm" id="password_confirm" class="form-control">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Rol *</label>
                        <select name="id_rol" id="id_rol" class="form-control form-select" required>
                            <option value="">Seleccionar rol</option>
                            <?php foreach ($roles as $rol): ?>
                            <option value="<?= $rol['id_rol'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="activo" id="activo" class="form-control form-select">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i>
                    Guardar
                </button>
            </div>
        </form>
    </div>
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

function filtrarPorRol(value) {
    const url = new URL(window.location);
    if (value) {
        url.searchParams.set('rol', value);
    } else {
        url.searchParams.delete('rol');
    }
    url.searchParams.set('page', 1);
    window.location = url;
}

function goToPage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location = url;
}

function abrirModalUsuario() {
    document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
    document.getElementById('formUsuario').reset();
    document.getElementById('id_usuario').value = '';
    document.getElementById('passHint').textContent = '(requerida)';
    document.getElementById('password').required = true;
    document.getElementById('modalUsuario').classList.add('active');
    lucide.createIcons();
}

async function editarUsuario(id) {
    const response = await fetchAPI(`<?= BASE_URL ?>api/usuarios.php?action=get&id=${id}`);
    if (response && response.success) {
        const user = response.data;
        document.getElementById('modalTitle').textContent = 'Editar Usuario';
        document.getElementById('id_usuario').value = user.id_usuario;
        document.getElementById('username').value = user.username;
        document.getElementById('email').value = user.email;
        document.getElementById('nombre_completo').value = user.nombre_completo;
        document.getElementById('id_rol').value = user.id_rol;
        document.getElementById('activo').value = user.activo;
        document.getElementById('password').value = '';
        document.getElementById('password_confirm').value = '';
        document.getElementById('passHint').textContent = '(dejar vacío para mantener)';
        document.getElementById('password').required = false;
        document.getElementById('modalUsuario').classList.add('active');
        lucide.createIcons();
    }
}

function cerrarModal() {
    document.getElementById('modalUsuario').classList.remove('active');
}

async function guardarUsuario(e) {
    e.preventDefault();
    
    const form = document.getElementById('formUsuario');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Validar contraseñas
    if (data.password && data.password !== data.password_confirm) {
        showToast('Las contraseñas no coinciden', 'error');
        return;
    }
    
    if (data.password && data.password.length < 8) {
        showToast('La contraseña debe tener al menos 8 caracteres', 'error');
        return;
    }
    
    const response = await fetchAPI('<?= BASE_URL ?>api/usuarios.php', {
        method: 'POST',
        body: JSON.stringify({
            action: data.id_usuario ? 'update' : 'create',
            ...data
        })
    });
    
    if (response && response.success) {
        showToast(response.message);
        cerrarModal();
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(response?.message || 'Error al guardar', 'error');
    }
}

async function toggleUsuario(id, nuevoEstado) {
    const accion = nuevoEstado ? 'activar' : 'desactivar';
    confirmAction(`¿Deseas ${accion} este usuario?`, async () => {
        const response = await fetchAPI('<?= BASE_URL ?>api/usuarios.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'toggle',
                id_usuario: id,
                activo: nuevoEstado
            })
        });
        
        if (response && response.success) {
            showToast(response.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response?.message || 'Error al actualizar', 'error');
        }
    });
}

function exportarUsuarios() {
    window.location = '<?= BASE_URL ?>api/usuarios.php?action=export';
}

// Cerrar modal con ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') cerrarModal();
});

// Cerrar modal al hacer clic fuera
document.getElementById('modalUsuario').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) cerrarModal();
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
