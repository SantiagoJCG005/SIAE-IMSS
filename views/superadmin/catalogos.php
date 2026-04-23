<?php
/**
 *  * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Gestion de Catalogos
 * Permite administrar las tablas de referencia del sistema.
 * Los catalogos son listas de opciones que se usan en otras partes del sistema.
 * Por ejemplo: lista de carreras, niveles educativos, modalidades, etc.
 */

// Nombre que aparecera en la pestana del navegador
$tituloPagina = 'Catálogos';

// Carga el archivo que verifica que el usuario este logueado
require_once __DIR__ . '/../../includes/auth.php';

// Carga el archivo con funciones utiles
require_once __DIR__ . '/../../includes/functions.php';

// Verifica que solo el Superadmin pueda entrar a esta pagina
requerirRol([ROL_SUPERADMIN]);

// Establece conexion con la base de datos
$conexion = obtenerConexion();

// Lee cual pestana quiere ver el usuario, si no hay usa 'carreras' por defecto
$pestana = obtenerGet('tab', 'carreras');

// Arreglo donde guardaremos los datos de la base de datos
$datos = [];

// Arreglo donde guardaremos los nombres de las columnas
$columnas = [];

// Segun la pestana seleccionada, ejecuta un bloque de codigo diferente
switch ($pestana) {
    
    // Pestana de carreras
    case 'carreras':
        // Consulta carreras uniendo con niveles para obtener el nombre del nivel
        $consulta = $conexion->query("SELECT c.*, n.nombre as nivel_nombre FROM carreras c LEFT JOIN nivel n ON c.id_nivel = n.id_nivel ORDER BY c.nombre");
        $datos = $consulta->fetchAll();
        $columnas = ['#', 'Clave', 'Nombre', 'Nivel'];
        break;
    
    // Pestana de niveles educativos
    case 'niveles':
        $consulta = $conexion->query("SELECT * FROM nivel ORDER BY nombre");
        $datos = $consulta->fetchAll();
        $columnas = ['#', 'Nombre', 'Descripción'];
        break;
    
    // Pestana de modalidades
    case 'modalidades':
        $consulta = $conexion->query("SELECT * FROM modalidad ORDER BY nombre");
        $datos = $consulta->fetchAll();
        $columnas = ['#', 'Nombre', 'Descripción'];
        break;
    
    // Pestana de periodos escolares
    case 'periodos':
        $consulta = $conexion->query("SELECT * FROM periodo_escolar ORDER BY fecha_inicio DESC");
        $datos = $consulta->fetchAll();
        $columnas = ['#', 'Nombre', 'Fecha Inicio', 'Fecha Fin', 'Estado'];
        break;
    
    // Pestana de semestres
    case 'semestres':
        $consulta = $conexion->query("SELECT * FROM semestre ORDER BY numero");
        $datos = $consulta->fetchAll();
        $columnas = ['#', 'Número', 'Nombre'];
        break;
    
    // Pestana de motivos de baja
    case 'motivos':
        $consulta = $conexion->query("SELECT * FROM motivos_bajas ORDER BY descripcion");
        $datos = $consulta->fetchAll();
        $columnas = ['#', 'Clave', 'Descripción'];
        break;
}

// Obtiene los niveles activos para el formulario de carreras
$listaNiveles = $conexion->query("SELECT * FROM nivel WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Incluye el header de la pagina
include __DIR__ . '/../layouts/header.php';

// Incluye el menu lateral del superadmin
include __DIR__ . '/../layouts/sidebar-superadmin.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1 class="page-title">Catálogos del Sistema</h1>
        <p class="page-subtitle">Administración de tablas de referencia y parámetros académicos.</p>
    </div>
    <button class="btn btn-primary" onclick="abrirModal()">
        <i data-lucide="plus"></i> Nuevo registro
    </button>
</div>

<div class="tabs">
    <?php // Cada pestana verifica si es la actual para agregar la clase 'active' ?>
    <a href="?tab=carreras" class="tab <?= $pestana === 'carreras' ? 'active' : '' ?>">
        <i data-lucide="book-open" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Carreras
    </a>
    <a href="?tab=niveles" class="tab <?= $pestana === 'niveles' ? 'active' : '' ?>">
        <i data-lucide="layers" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Niveles
    </a>
    <a href="?tab=modalidades" class="tab <?= $pestana === 'modalidades' ? 'active' : '' ?>">
        <i data-lucide="layout-grid" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Modalidades
    </a>
    <a href="?tab=periodos" class="tab <?= $pestana === 'periodos' ? 'active' : '' ?>">
        <i data-lucide="calendar" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Periodos
    </a>
    <a href="?tab=semestres" class="tab <?= $pestana === 'semestres' ? 'active' : '' ?>">
        <i data-lucide="hash" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Semestres
    </a>
    <a href="?tab=motivos" class="tab <?= $pestana === 'motivos' ? 'active' : '' ?>">
        <i data-lucide="file-x" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Motivos de Baja
    </a>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <?php // Recorre las columnas y crea un encabezado por cada una ?>
                    <?php foreach ($columnas as $col): ?>
                    <th><?= $col ?></th>
                    <?php endforeach; ?>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php // Si no hay datos, muestra mensaje de tabla vacia ?>
                <?php if (empty($datos)): ?>
                <tr>
                    <td colspan="<?= count($columnas) + 1 ?>">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i data-lucide="database"></i></div>
                            <div class="empty-state-title">No hay registros</div>
                            <div class="empty-state-text">Agrega el primer registro usando el botón superior</div>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php // Variable para numeracion secuencial ?>
                    <?php $numero = 1; ?>
                    <?php // Recorre cada registro y crea una fila ?>
                    <?php foreach ($datos as $fila): ?>
                    <tr>
                        <?php // Muestra columnas segun el tipo de catalogo ?>
                        <?php if ($pestana === 'carreras'): ?>
                            <td><?= $numero++ ?></td>
                            <td><strong><?= htmlspecialchars($fila['clave'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($fila['nombre']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($fila['nivel_nombre'] ?? 'N/A') ?></span></td>
                        <?php elseif ($pestana === 'niveles'): ?>
                            <td><?= $numero++ ?></td>
                            <td><strong><?= htmlspecialchars($fila['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($fila['descripcion'] ?? '-') ?></td>
                        <?php elseif ($pestana === 'modalidades'): ?>
                            <td><?= $numero++ ?></td>
                            <td><strong><?= htmlspecialchars($fila['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($fila['descripcion'] ?? '-') ?></td>
                        <?php elseif ($pestana === 'periodos'): ?>
                            <td><?= $numero++ ?></td>
                            <td><strong><?= htmlspecialchars($fila['nombre']) ?></strong></td>
                            <?php // formatearFecha convierte la fecha a formato legible ?>
                            <td><?= formatearFecha($fila['fecha_inicio']) ?></td>
                            <td><?= formatearFecha($fila['fecha_fin']) ?></td>
                            <td>
                                <?php if ($fila['activo']): ?>
                                    <span class="badge badge-success badge-dot">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary badge-dot">Cerrado</span>
                                <?php endif; ?>
                            </td>
                        <?php elseif ($pestana === 'semestres'): ?>
                            <td><?= $numero++ ?></td>
                            <td><strong><?= $fila['numero'] ?></strong></td>
                            <td><?= htmlspecialchars($fila['nombre']) ?></td>
                        <?php elseif ($pestana === 'motivos'): ?>
                            <td><?= $numero++ ?></td>
                            <td><strong><?= htmlspecialchars($fila['clave'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($fila['descripcion']) ?></td>
                        <?php endif; ?>
                        <td>
                            <div class="table-actions">
                                <?php // json_encode convierte los datos PHP a JSON para JavaScript ?>
                                <?php // Usamos comillas simples en onclick y JSON_HEX para escapar correctamente ?>
                                <button class="btn btn-ghost btn-icon" title="Editar" onclick='editarRegistro(<?= json_encode($fila, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                                    <i data-lucide="pencil"></i>
                                </button>
                                <?php // array_key_first obtiene el ID del registro ?>
                                <button class="btn btn-ghost btn-icon text-danger" title="Eliminar" onclick="eliminarRegistro(<?= $fila[array_key_first($fila)] ?>)">
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
// Guarda la pestana actual que viene de PHP
const currentTab = '<?= $pestana ?>';

// Convierte el arreglo de niveles de PHP a JavaScript
const niveles = <?= json_encode($listaNiveles) ?>;

// Variable que guarda el ID del registro en edicion, null si es nuevo
let editingId = null;

// Funcion que genera el HTML del formulario segun el tipo de catalogo
// Recibe data con los datos del registro o null si es nuevo
function getFormHTML(data = null) {
    // Si hay datos, guarda el ID del primer campo
    editingId = data ? Object.values(data)[0] : null;
    
    // Segun la pestana, retorna un formulario diferente
    switch (currentTab) {
        case 'carreras':
            return `
                <input type="hidden" name="id" value="${editingId || ''}">
                <div class="form-group">
                    <label class="form-label">Clave</label>
                    <input type="text" name="clave" class="form-control" value="${data?.clave || ''}" maxlength="20" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nombre de la Carrera</label>
                    <input type="text" name="nombre" class="form-control" value="${data?.nombre || ''}" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nivel</label>
                    <select name="id_nivel" class="form-control form-select" required>
                        <option value="">Seleccionar nivel</option>
                        ${niveles.map(n => `<option value="${n.id_nivel}" ${data?.id_nivel == n.id_nivel ? 'selected' : ''}>${n.nombre}</option>`).join('')}
                    </select>
                </div>
            `;

        case 'niveles':
            return `
                <input type="hidden" name="id" value="${editingId || ''}">
                <div class="form-group">
                    <label class="form-label">Nombre del Nivel</label>
                    <input type="text" name="nombre" class="form-control" value="${data?.nombre || ''}" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3" maxlength="255">${data?.descripcion || ''}</textarea>
                </div>
            `;

        case 'modalidades':
            return `
                <input type="hidden" name="id" value="${editingId || ''}">
                <div class="form-group">
                    <label class="form-label">Nombre de la Modalidad</label>
                    <input type="text" name="nombre" class="form-control" value="${data?.nombre || ''}" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3" maxlength="255">${data?.descripcion || ''}</textarea>
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
                    <input type="text" name="nombre" class="form-control" value="${data?.nombre || ''}" placeholder="Ej: Primer Semestre" maxlength="50" required>
                </div>
            `;

        case 'motivos':
            return `
                <input type="hidden" name="id" value="${editingId || ''}">
                <div class="form-group">
                    <label class="form-label">Clave</label>
                    <input type="text" name="clave" class="form-control" value="${data?.clave || ''}" placeholder="Ej: BAJ001" maxlength="20">
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción del Motivo</label>
                    <textarea name="descripcion" class="form-control" rows="3" maxlength="255" required>${data?.descripcion || ''}</textarea>
                </div>
            `;
    }
}

// Funcion que abre el modal para crear un nuevo registro
function abrirModal() {
    // Cambia el titulo a "Nuevo Registro"
    document.getElementById('modalTitle').textContent = 'Nuevo Registro';
    
    // Genera el formulario vacio
    document.getElementById('modalBody').innerHTML = getFormHTML();
    
    // Muestra el modal agregando la clase active
    document.getElementById('modalCatalogo').classList.add('active');
    
    // Activa los iconos de Lucide
    lucide.createIcons();
}

// Funcion que abre el modal para editar un registro existente
// Recibe data con los datos del registro
function editarRegistro(data) {
    // Cambia el titulo a "Editar Registro"
    document.getElementById('modalTitle').textContent = 'Editar Registro';
    
    // Genera el formulario con los datos del registro
    document.getElementById('modalBody').innerHTML = getFormHTML(data);
    
    // Muestra el modal
    document.getElementById('modalCatalogo').classList.add('active');
    
    // Activa los iconos
    lucide.createIcons();
}

// Funcion que cierra el modal
function cerrarModal() {
    // Oculta el modal quitando la clase active
    document.getElementById('modalCatalogo').classList.remove('active');
    
    // Limpia la variable de edicion
    editingId = null;
}

// Funcion asincrona que guarda el registro en la base de datos
// Recibe e que es el evento del formulario
async function guardarRegistro(e) {
    // Evita que el formulario recargue la pagina
    e.preventDefault();
    
    // Obtiene el formulario
    const form = document.getElementById('formCatalogo');
    
    // Recolecta todos los campos del formulario
    const formData = new FormData(form);
    
    // Convierte a objeto simple
    const data = Object.fromEntries(formData);
    
    // Agrega la pestana actual
    data.tab = currentTab;
    
    // Define la accion: update si estamos editando, create si es nuevo
    data.action = editingId ? 'update' : 'create';
    
    // Si estamos editando, agrega el ID
    if (editingId) data.id = editingId;

    // Envia los datos al servidor
    const response = await llamarApi('<?= URL_BASE ?>api/catalogos.php', {
        method: 'POST',
        body: JSON.stringify(data)
    });
    
    // Si fue exitoso
    if (response && response.success) {
        // Muestra mensaje de exito
        mostrarNotificacion(response.message);
        
        // Cierra el modal
        cerrarModal();
        
        // Recarga la pagina en 1 segundo
        setTimeout(() => location.reload(), 1000);
    } else {
        // Si hubo error, muestra el mensaje
        mostrarNotificacion(response?.message || 'Error al guardar', 'error');
    }
}

// Funcion asincrona que elimina un registro
// Recibe id del registro a eliminar
async function eliminarRegistro(id) {
    // Muestra dialogo de confirmacion
    confirmarAccion('¿Deseas eliminar este registro?', async () => {
        
        // Envia solicitud de eliminacion al servidor
        const response = await llamarApi('<?= URL_BASE ?>api/catalogos.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'delete',
                tab: currentTab,
                id: id
            })
        });
        
        // Si se elimino correctamente
        if (response && response.success) {
            mostrarNotificacion(response.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            mostrarNotificacion(response?.message || 'Error al eliminar', 'error');
        }
    });
}

// Cierra el modal al presionar ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') cerrarModal();
});

// Cierra el modal al hacer clic fuera de el
document.getElementById('modalCatalogo').addEventListener('click', (e) => {
    // Si el clic fue en el fondo oscuro, cierra el modal
    if (e.target === e.currentTarget) cerrarModal();
});
</script>

<?php // Incluye el footer ?>
<?php include __DIR__ . '/../layouts/footer.php'; ?>