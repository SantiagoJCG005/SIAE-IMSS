<?php
/**
 *  * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Gestion de Usuarios
 * Permite al Superadmin ver, crear, editar y desactivar usuarios del sistema.
 * Incluye busqueda, filtros por rol, paginacion y estadisticas.
 */

// Titulo que aparecera en la pestana del navegador
$tituloPagina = 'Usuarios';

// Carga el archivo de autenticacion
require_once __DIR__ . '/../../includes/auth.php';

// Carga el archivo de funciones auxiliares
require_once __DIR__ . '/../../includes/functions.php';

// Verifica que solo el Superadmin pueda acceder
requerirRol([ROL_SUPERADMIN]);

// Establece conexion con la base de datos
$conexion = obtenerConexion();

// Lee parametros de la URL para busqueda y filtros
$busqueda = obtenerGet('search', '');      // Texto de busqueda
$filtroRol = obtenerGet('rol', '');      // Filtro por rol
$pagina = max(1, intval(obtenerGet('page', 1)));  // Pagina actual, minimo 1
$porPagina = 10;                    // Usuarios por pagina

// Inicia la construccion de la consulta SQL
// WHERE 1=1 permite agregar condiciones con AND facilmente
$condicionWhere = "WHERE 1=1";
$parametros = [];  // Arreglo para los parametros de la consulta

// Si hay texto de busqueda, agrega condicion
if (!empty($busqueda)) {
    // Busca en username, nombre_completo o email
    $condicionWhere .= " AND (u.username LIKE ? OR u.nombre_completo LIKE ? OR u.email LIKE ?)";
    // Agrega el valor 3 veces con comodines para busqueda parcial
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
}

// Si hay filtro de rol, agrega condicion
if (!empty($filtroRol)) {
    $condicionWhere .= " AND u.id_rol = ?";
    $parametros[] = $filtroRol;
}

// Cuenta el total de registros que coinciden con los filtros
$consultaConteo = $conexion->prepare("SELECT COUNT(*) as total FROM usuarios u $condicionWhere");
$consultaConteo->execute($parametros);
$totalRegistros = $consultaConteo->fetch()['total'];

// Calcula la paginacion usando funcion auxiliar
$paginacion = paginar($totalRegistros, $porPagina, $pagina);

// Consulta los usuarios con su rol
// LEFT JOIN incluye usuarios aunque no tengan rol asignado
// LIMIT y OFFSET controlan la paginacion
$sql = "
    SELECT u.*, r.nombre as rol_nombre 
    FROM usuarios u 
    LEFT JOIN roles r ON u.id_rol = r.id_rol 
    $condicionWhere 
    ORDER BY u.id_usuario DESC 
    LIMIT {$paginacion['per_page']} OFFSET {$paginacion['offset']}
";
$consulta = $conexion->prepare($sql);
$consulta->execute($parametros);
$listaUsuarios = $consulta->fetchAll();

// Obtiene todos los roles para el filtro desplegable
$rolesStmt = $conexion->query("SELECT * FROM roles ORDER BY nombre");
$listaRoles = $rolesStmt->fetchAll();

// Calcula estadisticas de usuarios
// SUM con condicion cuenta registros que cumplen la condicion
$statsStmt = $conexion->query("
    SELECT 
        SUM(activo = 1) as activos,
        SUM(activo = 0) as inactivos,
        COUNT(DISTINCT id_rol) as roles_unicos
    FROM usuarios
");
$estadisticas = $statsStmt->fetch();

// Incluye el header de la pagina
include __DIR__ . '/../layouts/header.php';

// Incluye el menu lateral del superadmin
include __DIR__ . '/../layouts/sidebar-superadmin.php';
?>

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

<div class="filters">
    <div class="filter-group" style="flex: 2;">
        <div class="header-search" style="width: 100%;">
            <i data-lucide="search"></i>
            <?php // Campo de busqueda con valor actual y evento de tecleo ?>
            
            <input type="text" 
                   id="searchInput"
                   placeholder="Buscar por nombre, usuario o email..." 
                   value="<?= htmlspecialchars($busqueda) ?>"
                   onkeyup="busquedaConRetraso(this.value)">
        </div>
    </div>
    <div class="filter-group">
        <?php // Desplegable de roles ?>
        <select class="form-control form-select" id="rolFilter" onchange="filtrarPorRol(this.value)">
            <option value="">Filtrar por Rol</option>
            <?php // Recorre cada rol para crear una opcion ?>
            <?php foreach ($listaRoles as $rol): ?>
            <?php // Marca como seleccionado si coincide con el filtro actual ?>
            <option value="<?= $rol['id_rol'] ?>" <?= $filtroRol == $rol['id_rol'] ? 'selected' : '' ?>>
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
                <?php // Si no hay usuarios, muestra mensaje ?>
                <?php if (empty($listaUsuarios)): ?>
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
                    <?php // Recorre cada usuario para crear su fila ?>
                    <?php foreach ($listaUsuarios as $usuario): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($usuario['username']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($usuario['nombre_completo']) ?></td>
                        <td>
                            <?php
                            // Determina el color de la etiqueta segun el rol
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
                            <?php // Solo estudiantes pueden estar vinculados a un alumno ?>
                            <?php if ($usuario['id_rol'] == ROL_ESTUDIANTE): ?>
                                <?php // Si tiene numero de control, esta vinculado ?>
                                <?php if (!empty($usuario['numero_control'])): ?>
                                    <?= htmlspecialchars($usuario['nombre_completo']) ?> (ID: <?= $usuario['numero_control'] ?>)
                                <?php else: ?>
                                    <span class="text-warning">⚠ SIN VINCULAR</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php // Para otros roles no aplica ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php // Muestra etiqueta verde si activo, roja si inactivo ?>
                            <?php if ($usuario['activo']): ?>
                                <span class="badge badge-success badge-dot">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-danger badge-dot">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <?php // Boton editar ?>
                                <button class="btn btn-ghost btn-icon" title="Editar" 
                                        onclick="editarUsuario(<?= $usuario['id_usuario'] ?>)">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <?php // Boton activar/desactivar, cambia icono segun estado ?>
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
    
    <?php // Solo muestra paginacion si hay mas de 1 pagina ?>
    <?php if ($paginacion['total_pages'] > 1): ?>
    <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center;">
        <div class="pagination-info">
            <?php // Muestra rango actual y total ?>
            Mostrando <?= $paginacion['offset'] + 1 ?> - <?= min($paginacion['offset'] + $porPagina, $totalRegistros) ?> de <?= number_format($totalRegistros) ?> usuarios registrados
        </div>
        <div class="pagination">
            <?php // Boton ir a primera pagina ?>
            <button class="pagination-btn" onclick="irAPagina(1)" <?= !$paginacion['has_prev'] ? 'disabled' : '' ?>>
                <i data-lucide="chevrons-left"></i>
            </button>
            <?php // Boton pagina anterior ?>
            <button class="pagination-btn" onclick="irAPagina(<?= $pagina - 1 ?>)" <?= !$paginacion['has_prev'] ? 'disabled' : '' ?>>
                <i data-lucide="chevron-left"></i>
            </button>
            
            <?php
            // Calcula el rango de paginas a mostrar (2 antes y 2 despues de la actual)
            $start = max(1, $pagina - 2);
            $end = min($paginacion['total_pages'], $pagina + 2);
            
            // Crea botones para cada pagina en el rango
            for ($i = $start; $i <= $end; $i++):
            ?>
            <?php // Marca la pagina actual con clase 'active' ?>
            <button class="pagination-btn <?= $i == $pagina ? 'active' : '' ?>" onclick="irAPagina(<?= $i ?>)">
                <?= $i ?>
            </button>
            <?php endfor; ?>
            
            <?php // Si hay mas paginas, muestra puntos suspensivos y ultima pagina ?>
            <?php if ($end < $paginacion['total_pages']): ?>
            <span style="padding: 0 8px;">...</span>
            <button class="pagination-btn" onclick="irAPagina(<?= $paginacion['total_pages'] ?>)">
                <?= $paginacion['total_pages'] ?>
            </button>
            <?php endif; ?>
            
            <?php // Boton pagina siguiente ?>
            <button class="pagination-btn" onclick="irAPagina(<?= $pagina + 1 ?>)" <?= !$paginacion['has_next'] ? 'disabled' : '' ?>>
                <i data-lucide="chevron-right"></i>
            </button>
            <?php // Boton ir a ultima pagina ?>
            <button class="pagination-btn" onclick="irAPagina(<?= $paginacion['total_pages'] ?>)" <?= !$paginacion['has_next'] ? 'disabled' : '' ?>>
                <i data-lucide="chevrons-right"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="stats-grid" style="margin-top: 24px;">
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="user-check"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Activos</div>
            <?php // Usa operador ?? para valor por defecto si es null ?>
            <div class="stat-value"><?= number_format($estadisticas['activos'] ?? 0) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger" style="background: var(--danger-bg); color: var(--danger);">
            <i data-lucide="user-x"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Inactivos</div>
            <div class="stat-value"><?= number_format($estadisticas['inactivos'] ?? 0) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info">
            <i data-lucide="shield"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Roles Únicos</div>
            <div class="stat-value"><?= $estadisticas['roles_unicos'] ?? 0 ?></div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalUsuario">
    <div class="modal" style="max-width: 650px;">
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
                        <input type="text" name="username" id="username" class="form-control" 
                               minlength="4" maxlength="50" pattern="[a-zA-Z0-9_]+" 
                               title="Solo letras, números y guion bajo" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="email" class="form-control" 
                               maxlength="100" required>
                    </div>
                </div>
                
                <!-- Nombres fragmentados -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Primer Nombre *</label>
                        <input type="text" name="primer_nombre" id="primer_nombre" class="form-control" 
                               maxlength="50" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+" 
                               title="Solo letras" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Segundo Nombre</label>
                        <input type="text" name="segundo_nombre" id="segundo_nombre" class="form-control" 
                               maxlength="50" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]*" 
                               title="Solo letras">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Apellido Paterno *</label>
                        <input type="text" name="apellido_paterno" id="apellido_paterno" class="form-control" 
                               maxlength="50" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+" 
                               title="Solo letras" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Apellido Materno *</label>
                        <input type="text" name="apellido_materno" id="apellido_materno" class="form-control" 
                               maxlength="50" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+" 
                               title="Solo letras" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Contraseña <span id="passHint">(requerida)</span></label>
                        <input type="password" name="password" id="password" class="form-control" 
                               minlength="8" maxlength="50">
                        <small class="form-text">Mínimo 8 caracteres</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmar Contraseña</label>
                        <input type="password" name="password_confirm" id="password_confirm" class="form-control" 
                               minlength="8" maxlength="50">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Rol *</label>
                        <select name="id_rol" id="id_rol" class="form-control form-select" required>
                            <option value="">Seleccionar rol</option>
                            <?php // Crea opciones para cada rol ?>
                            <?php foreach ($listaRoles as $rol): ?>
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
// Variable para controlar el tiempo de espera en la busqueda
let tiempoEsperaBusqueda;

// Funcion que espera 500ms despues de dejar de escribir antes de buscar
// Evita hacer busquedas con cada tecla presionada
function busquedaConRetraso(value) {
    // Cancela el timeout anterior si existe
    clearTimeout(tiempoEsperaBusqueda);
    
    // Programa una nueva busqueda en 500ms
    tiempoEsperaBusqueda = setTimeout(() => {
        // Obtiene la URL actual
        const url = new URL(window.location);
        
        // Si hay texto, agrega el parametro search
        if (value) {
            url.searchParams.set('search', value);
        } else {
            // Si esta vacio, quita el parametro
            url.searchParams.delete('search');
        }
        
        // Regresa a la pagina 1 al buscar
        url.searchParams.set('page', 1);
        
        // Redirige a la nueva URL
        window.location = url;
    }, 500);
}

// Funcion que filtra por rol seleccionado
function filtrarPorRol(value) {
    const url = new URL(window.location);
    
    if (value) {
        url.searchParams.set('rol', value);
    } else {
        url.searchParams.delete('rol');
    }
    
    // Regresa a pagina 1 al filtrar
    url.searchParams.set('page', 1);
    window.location = url;
}

// Funcion para navegar a una pagina especifica
function irAPagina(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    window.location = url;
}

// Funcion que abre el modal para crear un nuevo usuario
function abrirModalUsuario() {
    // Configura el modal para modo "crear"
    document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
    document.getElementById('formUsuario').reset();      // Limpia el formulario
    document.getElementById('id_usuario').value = '';    // Sin ID significa nuevo
    document.getElementById('passHint').textContent = '(requerida)';
    document.getElementById('password').required = true; // Contrasena obligatoria
    
    // Limpia los campos de nombre fragmentados
    document.getElementById('primer_nombre').value = '';
    document.getElementById('segundo_nombre').value = '';
    document.getElementById('apellido_paterno').value = '';
    document.getElementById('apellido_materno').value = '';
    
    // Muestra el modal agregando la clase 'active'
    document.getElementById('modalUsuario').classList.add('active');
    
    // Recarga los iconos de Lucide
    lucide.createIcons();
}

// Funcion asincrona que carga datos de un usuario para editar
async function editarUsuario(id) {
    // Obtiene los datos del usuario desde la API
    const response = await llamarApi(`<?= URL_BASE ?>api/usuarios.php?action=get&id=${id}`);
    
    if (response && response.success) {
        const user = response.data;
        
        // Configura el modal para modo "editar"
        document.getElementById('modalTitle').textContent = 'Editar Usuario';
        
        // Llena los campos con los datos del usuario
        document.getElementById('id_usuario').value = user.id_usuario;
        document.getElementById('username').value = user.username;
        document.getElementById('email').value = user.email;
        document.getElementById('id_rol').value = user.id_rol;
        document.getElementById('activo').value = user.activo;
        
        // Separa el nombre_completo en sus partes
        // Asume formato: Nombre(s) + Apellido Paterno + Apellido Materno
        const partesNombre = (user.nombre_completo || '').trim().split(' ');
        
        if (partesNombre.length >= 3) {
            // Si tiene 3 o mas partes: ultimo es apellido materno, penultimo es paterno
            const apellidoMaterno = partesNombre.pop();
            const apellidoPaterno = partesNombre.pop();
            
            // El resto son nombres
            if (partesNombre.length >= 2) {
                document.getElementById('primer_nombre').value = partesNombre[0];
                document.getElementById('segundo_nombre').value = partesNombre.slice(1).join(' ');
            } else {
                document.getElementById('primer_nombre').value = partesNombre.join(' ');
                document.getElementById('segundo_nombre').value = '';
            }
            
            document.getElementById('apellido_paterno').value = apellidoPaterno;
            document.getElementById('apellido_materno').value = apellidoMaterno;
        } else if (partesNombre.length === 2) {
            // Solo 2 partes: nombre y un apellido
            document.getElementById('primer_nombre').value = partesNombre[0];
            document.getElementById('segundo_nombre').value = '';
            document.getElementById('apellido_paterno').value = partesNombre[1];
            document.getElementById('apellido_materno').value = '';
        } else {
            // Solo 1 parte
            document.getElementById('primer_nombre').value = partesNombre[0] || '';
            document.getElementById('segundo_nombre').value = '';
            document.getElementById('apellido_paterno').value = '';
            document.getElementById('apellido_materno').value = '';
        }
        
        // Limpia los campos de contrasena
        document.getElementById('password').value = '';
        document.getElementById('password_confirm').value = '';
        
        // Contrasena no es obligatoria al editar
        document.getElementById('passHint').textContent = '(dejar vacío para mantener)';
        document.getElementById('password').required = false;
        
        // Muestra el modal
        document.getElementById('modalUsuario').classList.add('active');
        lucide.createIcons();
    }
}

// Funcion que cierra el modal
function cerrarModal() {
    document.getElementById('modalUsuario').classList.remove('active');
}

// Funcion asincrona que guarda un usuario (crear o editar)
async function guardarUsuario(e) {
    // Evita que el formulario se envie de forma tradicional
    e.preventDefault();
    
    // Obtiene los datos del formulario
    const form = document.getElementById('formUsuario');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);  // Convierte a objeto
    
    // Valida que las contrasenas coincidan
    if (data.password && data.password !== data.password_confirm) {
        mostrarNotificacion('Las contraseñas no coinciden', 'error');
        return;
    }
    
    // Valida la longitud minima de la contrasena
    if (data.password && data.password.length < 8) {
        mostrarNotificacion('La contraseña debe tener al menos 8 caracteres', 'error');
        return;
    }
    
    // Junta los campos de nombre para formar nombre_completo
    // Formato: Nombre(s) + Apellido Paterno + Apellido Materno
    let nombreCompleto = data.primer_nombre.trim();
    if (data.segundo_nombre && data.segundo_nombre.trim()) {
        nombreCompleto += ' ' + data.segundo_nombre.trim();
    }
    nombreCompleto += ' ' + data.apellido_paterno.trim();
    nombreCompleto += ' ' + data.apellido_materno.trim();
    
    // Agrega el nombre completo a los datos
    data.nombre_completo = nombreCompleto;
    
    // Envia los datos a la API
    const response = await llamarApi('<?= URL_BASE ?>api/usuarios.php', {
        method: 'POST',
        body: JSON.stringify({
            // Si tiene id_usuario es update, si no es create
            action: data.id_usuario ? 'update' : 'create',
            ...data  // Incluye todos los datos del formulario
        })
    });
    
    if (response && response.success) {
        mostrarNotificacion(response.message);
        cerrarModal();
        // Recarga la pagina despues de 1 segundo
        setTimeout(() => location.reload(), 1000);
    } else {
        mostrarNotificacion(response?.message || 'Error al guardar', 'error');
    }
}

// Funcion asincrona que activa o desactiva un usuario
async function toggleUsuario(id, nuevoEstado) {
    // Determina el texto de la accion
    const accion = nuevoEstado ? 'activar' : 'desactivar';
    
    // Pide confirmacion antes de proceder
    confirmarAccion(`¿Deseas ${accion} este usuario?`, async () => {
        // Envia la peticion a la API
        const response = await llamarApi('<?= URL_BASE ?>api/usuarios.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'toggle',
                id_usuario: id,
                activo: nuevoEstado
            })
        });
        
        if (response && response.success) {
            mostrarNotificacion(response.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacion(response?.message || 'Error al actualizar', 'error');
        }
    });
}

// Funcion que redirige a la exportacion de usuarios
function exportarUsuarios() {
    window.location = '<?= URL_BASE ?>api/usuarios.php?action=export';
}

// Cierra el modal al presionar la tecla Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') cerrarModal();
});

// Cierra el modal al hacer clic fuera de el (en el fondo oscuro)
document.getElementById('modalUsuario').addEventListener('click', (e) => {
    // Solo cierra si el clic fue en el overlay, no en el contenido del modal
    if (e.target === e.currentTarget) cerrarModal();
});
</script>

<?php // Incluye el footer ?>
<?php include __DIR__ . '/../layouts/footer.php'; ?>