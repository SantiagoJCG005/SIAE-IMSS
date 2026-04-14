<?php
/**
 *  * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Bitacora del Sistema
 * 
 * QUE HACE ESTE ARCHIVO:
 * Muestra el historial de todas las acciones realizadas en el sistema.
 * Permite buscar, filtrar por fechas y tipo de accion, y exportar a CSV.
 * Solo pueden verlo el Superadmin y la Jefa de Servicios.
 */

// Titulo que aparecera en la pestana del navegador
$tituloPagina = 'Bitácora';

// Carga el archivo de autenticacion para verificar permisos
require_once __DIR__ . '/../../includes/auth.php';

// Carga el archivo de funciones auxiliares
require_once __DIR__ . '/../../includes/functions.php';

// Verifica que el usuario tenga uno de los roles permitidos
// Si no tiene permiso, lo redirige automaticamente
requerirRol([ROL_SUPERADMIN, ROL_JEFA_SERVICIOS]);

// Establece conexion con la base de datos
$conexion = obtenerConexion();

// Lee el texto de busqueda de la URL, si no hay queda vacio
$busqueda = obtenerGet('search', '');

// Lee el filtro de accion (LOGIN, CREAR, EDITAR, etc.)
$filtroAccion = obtenerGet('accion', '');

// Lee las fechas del filtro de rango
$fechaDesde = obtenerGet('fecha_desde', '');
$fechaHasta = obtenerGet('fecha_hasta', '');

// Lee el numero de pagina actual, minimo 1
$pagina = max(1, intval(obtenerGet('page', 1)));

// Cantidad de registros a mostrar por pagina
$porPagina = 20;

// Inicia la construccion del WHERE con una condicion siempre verdadera
// Esto facilita agregar mas condiciones con AND
$condicionWhere = "WHERE 1=1";

// Arreglo para guardar los valores de los parametros (evita SQL injection)
$parametros = [];

// Si hay texto de busqueda, agrega condicion para buscar en usuario y detalle
if (!empty($busqueda)) {
    $condicionWhere .= " AND (u.username LIKE ? OR u.nombre_completo LIKE ? OR b.detalle LIKE ?)";
    // Los % permiten buscar el texto en cualquier parte del campo
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
    $parametros[] = "%$busqueda%";
}

// Si hay filtro de accion, agrega la condicion
if (!empty($filtroAccion)) {
    $condicionWhere .= " AND b.accion = ?";
    $parametros[] = $filtroAccion;
}

// Si hay fecha desde, filtra registros desde esa fecha
if (!empty($fechaDesde)) {
    $condicionWhere .= " AND DATE(b.fecha) >= ?";
    $parametros[] = $fechaDesde;
}

// Si hay fecha hasta, filtra registros hasta esa fecha
if (!empty($fechaHasta)) {
    $condicionWhere .= " AND DATE(b.fecha) <= ?";
    $parametros[] = $fechaHasta;
}

// Prepara consulta para contar el total de registros con los filtros aplicados
$consultaConteo = $conexion->prepare("SELECT COUNT(*) as total FROM bitacora b LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario $condicionWhere");

// Ejecuta la consulta con los parametros
$consultaConteo->execute($parametros);

// Obtiene el total de registros
$totalRegistros = $consultaConteo->fetch()['total'];

// Calcula los datos de paginacion (pagina actual, total de paginas, offset, etc.)
$paginacion = paginar($totalRegistros, $porPagina, $pagina);

// Construye la consulta principal para obtener los registros
// LEFT JOIN une con usuarios para obtener nombre y username
// ORDER BY fecha DESC ordena del mas reciente al mas antiguo
// LIMIT y OFFSET controlan la paginacion
$sql = "
    SELECT b.*, u.nombre_completo, u.username 
    FROM bitacora b 
    LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario 
    $condicionWhere 
    ORDER BY b.fecha DESC 
    LIMIT {$paginacion['per_page']} OFFSET {$paginacion['offset']}
";

// Prepara y ejecuta la consulta
$consulta = $conexion->prepare($sql);
$consulta->execute($parametros);

// Obtiene todos los registros como arreglo
$listaRegistros = $consulta->fetchAll();

// Obtiene la lista de acciones unicas para el menu desplegable de filtro
// DISTINCT evita duplicados
$accionesStmt = $conexion->query("SELECT DISTINCT accion FROM bitacora ORDER BY accion");

// Convierte los resultados en un arreglo simple de valores
$listaAcciones = $accionesStmt->fetchAll(PDO::FETCH_COLUMN);

// Incluye el header de la pagina (head, estilos, scripts)
include __DIR__ . '/../layouts/header.php';

// Incluye el menu lateral del superadmin
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
            <?php // El valor actual de busqueda se muestra en el campo ?>
            <?php // onkeyup ejecuta la busqueda con retraso cada vez que se escribe ?>
            <input type="text" 
                   id="searchInput"
                   placeholder="Buscar por usuario o detalle..." 
                   value="<?= htmlspecialchars($busqueda) ?>"
                   onkeyup="busquedaConRetraso(this.value)">
        </div>
    </div>
    <div class="filter-group">
        <?php // Menu desplegable con las acciones disponibles ?>
        <select class="form-control form-select" id="accionFilter" onchange="filtrar()">
            <option value="">Todas las acciones</option>
            <?php // Recorre cada accion y crea una opcion, marcando como selected la actual ?>
            <?php foreach ($listaAcciones as $accion): ?>
            <option value="<?= htmlspecialchars($accion) ?>" <?= $filtroAccion === $accion ? 'selected' : '' ?>>
                <?= htmlspecialchars($accion) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <?php // Campo de fecha inicial con el valor actual ?>
        <input type="date" class="form-control" id="fechaDesde" value="<?= $fechaDesde ?>" onchange="filtrar()" placeholder="Desde">
    </div>
    <div class="filter-group">
        <?php // Campo de fecha final con el valor actual ?>
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
                <?php // Si no hay registros, muestra mensaje de tabla vacia ?>
                <?php if (empty($listaRegistros)): ?>
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
                    <?php // Recorre cada registro y crea una fila en la tabla ?>
                    <?php foreach ($listaRegistros as $registro): ?>
                    <tr>
                        <td>
                            <?php // Formatea la fecha a un formato legible ?>
                            <div style="font-size: 13px;"><?= formatearFechaTime($registro['fecha']) ?></div>
                        </td>
                        <td>
                            <?php // Si hay nombre de usuario, muestra avatar e info ?>
                            <?php if ($registro['nombre_completo']): ?>
                            <div class="d-flex align-center gap-2">
                                <?php // Avatar con color generado e iniciales ?>
                                <div class="avatar avatar-sm" style="background: <?= obtenerColorAvatar($registro['nombre_completo']) ?>">
                                    <?= obtenerIniciales($registro['nombre_completo']) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($registro['nombre_completo']) ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($registro['username']) ?></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <?php // Si no hay usuario, fue una accion del sistema ?>
                            <span class="text-muted">Sistema</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            // Determina el color de la etiqueta segun el tipo de accion
                            $badgeClass = 'badge-secondary';  // Color por defecto (gris)

                            // Verde para acciones de login
                            if (strpos($registro['accion'], 'LOGIN') !== false) $badgeClass = 'badge-success';

                            // Azul para acciones de crear
                            elseif (strpos($registro['accion'], 'CREAR') !== false || strpos($registro['accion'], 'ALTA') !== false) $badgeClass = 'badge-info';

                            // Amarillo para acciones de editar
                            elseif (strpos($registro['accion'], 'EDITAR') !== false) $badgeClass = 'badge-warning';

                            // Rojo para acciones de eliminar o bajas
                            elseif (strpos($registro['accion'], 'ELIMINAR') !== false || strpos($registro['accion'], 'BAJA') !== false) $badgeClass = 'badge-danger';

                            // Azul para exportaciones
                            elseif (strpos($registro['accion'], 'EXPORTAR') !== false) $badgeClass = 'badge-info';

                            // Gris para logout
                            elseif (strpos($registro['accion'], 'LOGOUT') !== false) $badgeClass = 'badge-secondary';

                            // Rojo para intentos fallidos
                            elseif (strpos($registro['accion'], 'FALLIDO') !== false) $badgeClass = 'badge-danger';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($registro['accion']) ?></span>
                        </td>
                        <td>
                            <?php // Muestra el detalle truncado, el title muestra el texto completo al pasar el mouse ?>
                            <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($registro['detalle']) ?>">
                                <?= htmlspecialchars($registro['detalle'] ?: '-') ?>
                            </div>
                        </td>
                        <td>
                            <?php // Muestra la direccion IP con estilo de codigo ?>
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
    <?php // Solo muestra paginacion si hay mas de una pagina ?>
    <?php if ($paginacion['total_pages'] > 1): ?>
    <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center;">
        <div class="pagination-info">
            <?php // Muestra el rango de registros actual y el total ?>
            Mostrando <?= $paginacion['offset'] + 1 ?> - <?= min($paginacion['offset'] + $porPagina, $totalRegistros) ?> de <?= number_format($totalRegistros) ?> registros
        </div>
        <div class="pagination">
            <?php // Boton para ir a la primera pagina ?>
            <button class="pagination-btn" onclick="irAPagina(1)" <?= !$paginacion['has_prev'] ? 'disabled' : '' ?>>
                <i data-lucide="chevrons-left"></i>
            </button>
            <?php // Boton para ir a la pagina anterior ?>
            <button class="pagination-btn" onclick="irAPagina(<?= $pagina - 1 ?>)" <?= !$paginacion['has_prev'] ? 'disabled' : '' ?>>
                <i data-lucide="chevron-left"></i>
            </button>
            
            <?php
            // Calcula el rango de paginas a mostrar (2 antes y 2 despues de la actual)
            $start = max(1, $pagina - 2);
            $end = min($paginacion['total_pages'], $pagina + 2);

            // Crea un boton por cada pagina en el rango
            for ($i = $start; $i <= $end; $i++):
            ?>
            <?php // La pagina actual tiene la clase 'active' ?>
            <button class="pagination-btn <?= $i == $pagina ? 'active' : '' ?>" onclick="irAPagina(<?= $i ?>)">
                <?= $i ?>
            </button>
            <?php endfor; ?>
            
            <?php // Boton para ir a la pagina siguiente ?>
            <button class="pagination-btn" onclick="irAPagina(<?= $pagina + 1 ?>)" <?= !$paginacion['has_next'] ? 'disabled' : '' ?>>
                <i data-lucide="chevron-right"></i>
            </button>
            <?php // Boton para ir a la ultima pagina ?>
            <button class="pagination-btn" onclick="irAPagina(<?= $paginacion['total_pages'] ?>)" <?= !$paginacion['has_next'] ? 'disabled' : '' ?>>
                <i data-lucide="chevrons-right"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Variable para guardar el temporizador de busqueda
let tiempoEsperaBusqueda;

// Funcion que retrasa la busqueda para no ejecutarla con cada tecla
// Espera 500ms despues de que el usuario deja de escribir
function busquedaConRetraso(value) {

    // Cancela cualquier busqueda pendiente anterior
    clearTimeout(tiempoEsperaBusqueda);

    // Programa una nueva busqueda en 500 milisegundos
    tiempoEsperaBusqueda = setTimeout(() => {

        // Obtiene la URL actual de la pagina
        const url = new URL(window.location);

        // Si hay texto, agrega el parametro search a la URL
        if (value) {
            url.searchParams.set('search', value);
        } else {
            // Si esta vacio, quita el parametro
            url.searchParams.delete('search');
        }

        // Regresa a la pagina 1 porque los resultados cambiaron
        url.searchParams.set('page', 1);

        // Recarga la pagina con los nuevos parametros
        window.location = url;
    }, 500);
}

// Funcion que aplica los filtros de accion y fechas
function filtrar() {

    // Obtiene la URL actual
    const url = new URL(window.location);
    
    // Lee los valores de los campos de filtro
    const accion = document.getElementById('accionFilter').value;
    const fechaDesde = document.getElementById('fechaDesde').value;
    const fechaHasta = document.getElementById('fechaHasta').value;
    
    // Si hay accion seleccionada, la agrega a la URL, si no la quita
    if (accion) url.searchParams.set('accion', accion);
    else url.searchParams.delete('accion');
    
    // Lo mismo para fecha desde
    if (fechaDesde) url.searchParams.set('fecha_desde', fechaDesde);
    else url.searchParams.delete('fecha_desde');
    
    // Lo mismo para fecha hasta
    if (fechaHasta) url.searchParams.set('fecha_hasta', fechaHasta);
    else url.searchParams.delete('fecha_hasta');
    
    // Regresa a la pagina 1
    url.searchParams.set('page', 1);

    // Recarga la pagina con los filtros aplicados
    window.location = url;
}

// Funcion que navega a una pagina especifica
function irAPagina(page) {

    // Obtiene la URL actual
    const url = new URL(window.location);

    // Cambia el numero de pagina
    url.searchParams.set('page', page);

    // Navega a esa pagina
    window.location = url;
}

// Funcion que inicia la descarga del archivo CSV
function exportarBitacora() {

    // Obtiene la URL actual con los filtros aplicados
    const url = new URL(window.location);

    // Cambia la ruta al archivo de la API que genera el CSV
    url.pathname = '<?= URL_BASE ?>api/bitacora.php';

    // Agrega el parametro que indica que queremos exportar
    url.searchParams.set('action', 'export');

    // Navega a esa URL, lo que inicia la descarga
    window.location = url;
}
</script>

<?php // Incluye el footer de la pagina ?>
<?php include __DIR__ . '/../layouts/footer.php'; ?>