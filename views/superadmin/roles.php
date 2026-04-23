<?php
/**
 *  * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Gestion de Roles
 * Muestra todos los roles del sistema (Superadmin, Jefa SE, Admin SE, etc.)
 * con una tarjeta para cada uno y una matriz de permisos que indica
 * que puede hacer cada rol.
 */

// Titulo que aparecera en la pestana del navegador
$tituloPagina = 'Roles';

// Carga el archivo de autenticacion
require_once __DIR__ . '/../../includes/auth.php';

// Carga el archivo de funciones auxiliares
require_once __DIR__ . '/../../includes/functions.php';

// Verifica que solo el Superadmin pueda acceder
requerirRol([ROL_SUPERADMIN]);

// Establece conexion con la base de datos
$conexion = obtenerConexion();

// Consulta todos los roles con el conteo de usuarios asignados a cada uno
// LEFT JOIN incluye roles aunque no tengan usuarios
// COUNT cuenta cuantos usuarios activos tiene cada rol
// GROUP BY agrupa los resultados por rol
$consulta = $conexion->query("
    SELECT r.*, COUNT(u.id_usuario) as total_usuarios
    FROM roles r
    LEFT JOIN usuarios u ON r.id_rol = u.id_rol AND u.activo = 1
    GROUP BY r.id_rol
    ORDER BY r.id_rol
");
$listaRoles = $consulta->fetchAll();

// Incluye el header de la pagina
include __DIR__ . '/../layouts/header.php';

// Incluye el menu lateral del superadmin
include __DIR__ . '/../layouts/sidebar-superadmin.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1 class="page-title">Roles del Sistema</h1>
        <p class="page-subtitle">Configuración de perfiles y permisos de usuarios en el sistema.</p>
    </div>
    <button class="btn btn-primary" onclick="abrirModalRol()">
        <i data-lucide="plus"></i> Nuevo Rol
    </button>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
    <?php // Recorre cada rol para crear su tarjeta ?>
    <?php foreach ($listaRoles as $rol): ?>
    <?php
    // Valores por defecto para el estilo de la tarjeta
    $badgeClass = 'badge-secondary';  // Clase CSS para la etiqueta
    $iconColor = '#64748B';           // Color del icono (gris)
    $icon = 'shield';                 // Icono por defecto
    
    // Segun el tipo de rol, asigna diferentes colores e iconos
    switch ($rol['id_rol']) {
        case ROL_SUPERADMIN:
            // Superadmin: dorado con corona
            $badgeClass = 'badge-superadmin';
            $iconColor = '#92400E';
            $icon = 'crown';
            break;
        case ROL_JEFA_SERVICIOS:
            // Jefa SE: rosa con engrane de usuario
            $badgeClass = 'badge-jefa';
            $iconColor = '#9D174D';
            $icon = 'user-cog';
            break;
        case ROL_ADMIN_SERVICIOS:
            // Admin SE: naranja con grupo de usuarios
            $badgeClass = 'badge-admin-se';
            $iconColor = '#9A3412';
            $icon = 'users';
            break;
        case ROL_ADMIN_IMSS:
            // Admin IMSS: morado con edificio
            $badgeClass = 'badge-admin-imss';
            $iconColor = '#3730A3';
            $icon = 'building';
            break;
        case ROL_ESTUDIANTE:
            // Estudiante: azul con birrete
            $badgeClass = 'badge-estudiante';
            $iconColor = '#1E40AF';
            $icon = 'graduation-cap';
            break;
    }
    ?>
    <div class="card">
        <div class="card-body">
            <div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 16px;">
                <?php // Color de fondo del icono segun el tipo de rol ?>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: <?= $badgeClass === 'badge-superadmin' ? '#FEF3C7' : ($badgeClass === 'badge-jefa' ? '#FCE7F3' : ($badgeClass === 'badge-admin-se' ? '#FFEDD5' : ($badgeClass === 'badge-admin-imss' ? '#E0E7FF' : '#DBEAFE'))) ?>; display: flex; align-items: center; justify-content: center;">
                    <?php // Icono con el color asignado ?>
                    <i data-lucide="<?= $icon ?>" style="color: <?= $iconColor ?>; width: 24px; height: 24px;"></i>
                </div>
                <div style="flex: 1;">
                    <?php // Nombre del rol protegido contra XSS ?>
                    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($rol['nombre']) ?></h3>
                    <?php // Etiqueta con el conteo de usuarios ?>
                    <span class="badge <?= $badgeClass ?>"><?= $rol['total_usuarios'] ?> usuarios asignados</span>
                </div>
            </div>
            
            <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 16px;">
                <?php // Descripcion del rol, o texto por defecto si no tiene ?>
                <?= htmlspecialchars($rol['descripcion'] ?? 'Sin descripción') ?>
            </p>
            
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php // Boton para ver permisos, pasa el ID y nombre del rol ?>
                <button class="btn btn-ghost btn-sm" onclick="verPermisos(<?= $rol['id_rol'] ?>, '<?= htmlspecialchars($rol['nombre']) ?>')">
                    <i data-lucide="eye"></i>
                    Ver permisos
                </button>
                <?php // Boton para ver usuarios de este rol ?>
                <button class="btn btn-ghost btn-sm" onclick="verUsuarios(<?= $rol['id_rol'] ?>, '<?= htmlspecialchars($rol['nombre']) ?>')">
                    <i data-lucide="users"></i>
                    Ver usuarios
                </button>
                <?php // Boton editar disponible para TODOS los roles ?>
                <button class="btn btn-ghost btn-sm" onclick='editarRol(<?= $rol["id_rol"] ?>, <?= json_encode($rol["nombre"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($rol["descripcion"] ?? "", JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                    <i data-lucide="pencil"></i>
                </button>
                <?php // Boton eliminar SOLO para roles personalizados (id > 5) ?>
                <?php if ($rol['id_rol'] > 5): ?>
                <button class="btn btn-ghost btn-sm text-danger" onclick="eliminarRol(<?= $rol['id_rol'] ?>, '<?= htmlspecialchars(addslashes($rol['nombre'])) ?>')">
                    <i data-lucide="trash-2"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

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
// Lista de permisos disponibles para los roles
const listaPermisos = [
    { id: 'ver_datos', nombre: 'Ver sus datos' },
    { id: 'reportar_falla', nombre: 'Reportar falla de datos' },
    { id: 'ver_alumnos', nombre: 'Ver alumnos' },
    { id: 'importar_excel', nombre: 'Importar Excel' },
    { id: 'altas_bajas', nombre: 'Altas / Bajas' },
    { id: 'editar_datos', nombre: 'Editar datos' },
    { id: 'validar_movimientos', nombre: 'Validar movimientos' },
    { id: 'exportar_txt', nombre: 'Exportar TXT IMSS' },
    { id: 'ver_reportes', nombre: 'Ver reportes' },
    { id: 'atender_incidencias', nombre: 'Atender incidencias' },
    { id: 'ver_bitacora', nombre: 'Ver bitácora' },
    { id: 'crud_usuarios', nombre: 'CRUD usuarios' },
    { id: 'gestion_catalogos', nombre: 'Gestión catálogos' },
    { id: 'config_patronal', nombre: 'Configuración patronal' }
];

// Funcion para abrir el modal de crear/editar rol
// permisosActuales es un array de IDs de permisos que ya tiene el rol
function abrirModalRol(idRol = null, nombre = '', descripcion = '', permisosActuales = []) {
    
    // Genera el HTML de los checkboxes de permisos
    let permisosHtml = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; max-height: 200px; overflow-y: auto; padding: 8px; background: var(--bg-secondary); border-radius: 8px;">';
    listaPermisos.forEach(permiso => {
        // Verifica si este permiso ya está asignado al rol
        const checked = permisosActuales.includes(permiso.id) ? 'checked' : '';
        permisosHtml += `
            <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                <input type="checkbox" name="permisos" value="${permiso.id}" ${checked} style="cursor: pointer;">
                ${permiso.nombre}
            </label>
        `;
    });
    permisosHtml += '</div>';
    
    Swal.fire({
        title: idRol ? 'Editar Rol' : 'Nuevo Rol',
        html: `
            <div style="text-align: left;">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 500;">Nombre del Rol *</label>
                    <input type="text" id="swal-nombre" class="swal2-input" value="${nombre}" 
                           placeholder="Ej: Coordinador Académico" maxlength="50" required
                           style="margin: 0; width: 100%;" ${idRol && idRol <= 5 ? 'disabled' : ''}>
                    ${idRol && idRol <= 5 ? '<small style="color: var(--text-muted);">Los roles del sistema no pueden cambiar de nombre</small>' : ''}
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 500;">Descripción</label>
                    <textarea id="swal-descripcion" class="swal2-textarea" 
                              placeholder="Describe las funciones de este rol..." maxlength="255"
                              style="margin: 0; width: 100%; min-height: 80px;">${descripcion}</textarea>
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 6px; font-weight: 500;">Permisos</label>
                    ${permisosHtml}
                </div>
            </div>
        `,
        width: 500,
        showCancelButton: true,
        confirmButtonText: '<i data-lucide="save"></i> Guardar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#2563EB',
        didOpen: () => {
            lucide.createIcons();
        },
        preConfirm: () => {
            const nombreValor = document.getElementById('swal-nombre').value.trim();
            
            // Valida nombre solo si no es rol del sistema
            if (!idRol || idRol > 5) {
                if (!nombreValor) {
                    Swal.showValidationMessage('El nombre del rol es obligatorio');
                    return false;
                }
                if (nombreValor.length < 3) {
                    Swal.showValidationMessage('El nombre debe tener al menos 3 caracteres');
                    return false;
                }
            }
            
            // Obtiene los permisos seleccionados
            const permisosSeleccionados = [];
            document.querySelectorAll('input[name="permisos"]:checked').forEach(cb => {
                permisosSeleccionados.push(cb.value);
            });
            
            return {
                nombre: nombreValor,
                descripcion: document.getElementById('swal-descripcion').value.trim(),
                permisos: permisosSeleccionados
            };
        }
    }).then(async (resultado) => {
        if (resultado.isConfirmed) {
            
            // Envia los datos al servidor
            const respuesta = await llamarApi('<?= URL_BASE ?>api/roles.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: idRol ? 'editar' : 'crear',
                    id_rol: idRol,
                    nombre: resultado.value.nombre,
                    descripcion: resultado.value.descripcion,
                    permisos: resultado.value.permisos
                })
            });
            
            if (respuesta && respuesta.success) {
                mostrarNotificacion(respuesta.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                mostrarNotificacion(respuesta?.message || 'Error al guardar', 'error');
            }
        }
    });
}

// Funcion para editar un rol existente
// Primero obtiene los permisos actuales del rol desde la API
async function editarRol(idRol, nombre, descripcion) {
    
    // Obtiene los permisos actuales del rol
    const respuesta = await llamarApi('<?= URL_BASE ?>api/roles.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'obtener',
            id_rol: idRol
        })
    });
    
    // Si obtuvo los datos, abre el modal con los permisos
    if (respuesta && respuesta.success) {
        const permisosActuales = respuesta.data.permisos || [];
        abrirModalRol(idRol, nombre, descripcion, permisosActuales);
    } else {
        // Si falla, abre el modal sin permisos
        abrirModalRol(idRol, nombre, descripcion, []);
    }
}

// Funcion para eliminar un rol
function eliminarRol(idRol, nombreRol) {
    Swal.fire({
        title: '¿Eliminar rol?',
        html: `Se eliminará permanentemente el rol <strong>"${nombreRol}"</strong>.<br><br>
               <span style="color: var(--danger);">Esta acción no se puede deshacer.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#EF4444'
    }).then(async (resultado) => {
        if (resultado.isConfirmed) {
            
            const respuesta = await llamarApi('<?= URL_BASE ?>api/roles.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'eliminar',
                    id_rol: idRol
                })
            });
            
            if (respuesta && respuesta.success) {
                mostrarNotificacion(respuesta.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                mostrarNotificacion(respuesta?.message || 'Error al eliminar', 'error');
            }
        }
    });
}

// Funcion que muestra un dialogo informativo sobre los permisos de un rol
function verPermisos(id, nombre) {
    Swal.fire({
        title: 'Permisos: ' + nombre,
        html: 'Consulta la matriz de permisos en la tabla inferior para ver los permisos detallados de este rol.',
        icon: 'info',
        confirmButtonColor: '#2563EB'
    });
}

// Funcion que redirige a la pagina de usuarios filtrada por rol
async function verUsuarios(id, nombre) {
    window.location = `<?= URL_BASE ?>views/superadmin/usuarios.php?rol=${id}`;
}
</script>

<?php // Incluye el footer ?>
<?php include __DIR__ . '/../layouts/footer.php'; ?>