<?php
/**
 * SIAE-IMSS - Gestión de Roles
 */
$pageTitle = 'Roles';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole([ROL_SUPERADMIN]);

$pdo = getConnection();

// Obtener roles con conteo de usuarios
$stmt = $pdo->query("
    SELECT r.*, COUNT(u.id_usuario) as total_usuarios
    FROM roles r
    LEFT JOIN usuarios u ON r.id_rol = u.id_rol AND u.activo = 1
    GROUP BY r.id_rol
    ORDER BY r.id_rol
");
$roles = $stmt->fetchAll();

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-superadmin.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">Roles del Sistema</h1>
    <p class="page-subtitle">Configuración de perfiles y permisos de usuarios en el sistema.</p>
</div>

<!-- Roles Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
    <?php foreach ($roles as $rol): ?>
    <?php
    $badgeClass = 'badge-secondary';
    $iconColor = '#64748B';
    $icon = 'shield';
    
    switch ($rol['id_rol']) {
        case ROL_SUPERADMIN:
            $badgeClass = 'badge-superadmin';
            $iconColor = '#92400E';
            $icon = 'crown';
            break;
        case ROL_JEFA_SERVICIOS:
            $badgeClass = 'badge-jefa';
            $iconColor = '#9D174D';
            $icon = 'user-cog';
            break;
        case ROL_ADMIN_SERVICIOS:
            $badgeClass = 'badge-admin-se';
            $iconColor = '#9A3412';
            $icon = 'users';
            break;
        case ROL_ADMIN_IMSS:
            $badgeClass = 'badge-admin-imss';
            $iconColor = '#3730A3';
            $icon = 'building';
            break;
        case ROL_ESTUDIANTE:
            $badgeClass = 'badge-estudiante';
            $iconColor = '#1E40AF';
            $icon = 'graduation-cap';
            break;
    }
    ?>
    <div class="card">
        <div class="card-body">
            <div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 16px;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: <?= $badgeClass === 'badge-superadmin' ? '#FEF3C7' : ($badgeClass === 'badge-jefa' ? '#FCE7F3' : ($badgeClass === 'badge-admin-se' ? '#FFEDD5' : ($badgeClass === 'badge-admin-imss' ? '#E0E7FF' : '#DBEAFE'))) ?>; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="<?= $icon ?>" style="color: <?= $iconColor ?>; width: 24px; height: 24px;"></i>
                </div>
                <div style="flex: 1;">
                    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($rol['nombre']) ?></h3>
                    <span class="badge <?= $badgeClass ?>"><?= $rol['total_usuarios'] ?> usuarios asignados</span>
                </div>
            </div>
            
            <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 16px;">
                <?= htmlspecialchars($rol['descripcion'] ?? 'Sin descripción') ?>
            </p>
            
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-ghost btn-sm" onclick="verPermisos(<?= $rol['id_rol'] ?>, '<?= htmlspecialchars($rol['nombre']) ?>')">
                    <i data-lucide="eye"></i>
                    Ver permisos
                </button>
                <button class="btn btn-ghost btn-sm" onclick="verUsuarios(<?= $rol['id_rol'] ?>, '<?= htmlspecialchars($rol['nombre']) ?>')">
                    <i data-lucide="users"></i>
                    Ver usuarios
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabla de permisos -->
<div class="card" style="margin-top: 32px;">
    <div class="card-header">
        <h3 class="card-title">
            <i data-lucide="shield-check" style="width: 18px; height: 18px; margin-right: 8px;"></i>
            Matriz de Permisos
        </h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Módulo / Permiso</th>
                    <th style="text-align: center;">Estudiante</th>
                    <th style="text-align: center;">Admin IMSS</th>
                    <th style="text-align: center;">Admin SE</th>
                    <th style="text-align: center;">Jefa SE</th>
                    <th style="text-align: center;">Superadmin</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Ver sus datos</strong></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-muted">—</span></td>
                    <td style="text-align: center;"><span class="text-muted">—</span></td>
                    <td style="text-align: center;"><span class="text-muted">—</span></td>
                    <td style="text-align: center;"><span class="text-muted">—</span></td>
                </tr>
                <tr>
                    <td><strong>Reportar falla de datos</strong></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-muted">—</span></td>
                    <td style="text-align: center;"><span class="text-muted">—</span></td>
                    <td style="text-align: center;"><span class="text-muted">—</span></td>
                    <td style="text-align: center;"><span class="text-muted">—</span></td>
                </tr>
                <tr>
                    <td><strong>Ver alumnos</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>Importar Excel</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>Altas / Bajas</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>Editar datos</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>Validar movimientos</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>Exportar TXT IMSS</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-warning">◐</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>Ver reportes</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>Atender incidencias</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>Ver bitácora</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>CRUD usuarios</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>Gestión catálogos</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
                <tr>
                    <td><strong>Configuración patronal</strong></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-danger">✗</span></td>
                    <td style="text-align: center;"><span class="text-success">✓</span></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <small class="text-muted">
            <span class="text-success">✓</span> Permitido &nbsp;&nbsp;
            <span class="text-danger">✗</span> No permitido &nbsp;&nbsp;
            <span class="text-warning">◐</span> Opcional/Configurable
        </small>
    </div>
</div>

<script>
function verPermisos(id, nombre) {
    Swal.fire({
        title: 'Permisos: ' + nombre,
        html: 'Consulta la matriz de permisos en la tabla inferior para ver los permisos detallados de este rol.',
        icon: 'info',
        confirmButtonColor: '#2563EB'
    });
}

async function verUsuarios(id, nombre) {
    const response = await fetchAPI(`<?= BASE_URL ?>api/usuarios.php?action=get&rol=${id}`);
    // Por simplicidad, redirigimos a la página de usuarios con filtro
    window.location = `<?= BASE_URL ?>views/superadmin/usuarios.php?rol=${id}`;
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
