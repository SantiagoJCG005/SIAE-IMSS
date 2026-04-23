<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Reportes
 * Estadisticas y reportes de movimientos IMSS
 */

// Titulo de la pagina
$tituloPagina = 'Reportes';

// Carga archivos necesarios
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verifica permisos
requerirRol([ROL_JEFA_SERVICIOS, ROL_SUPERADMIN]);

// Conexion a la base de datos
$conexion = obtenerConexion();

// Obtiene estadisticas generales
$estadisticas = [
    'total_altas' => 0,
    'total_bajas' => 0,
    'total_carpetas' => 0,
    'total_tablas' => 0
];

try {
    // Total de altas enviadas
    $consulta = $conexion->query("
        SELECT COALESCE(SUM(total_registros), 0) as total 
        FROM tablas_movimientos 
        WHERE tipo = 'alta' AND estado = 'enviado'
    ");
    $estadisticas['total_altas'] = $consulta->fetch()['total'];
    
    // Total de bajas enviadas
    $consulta = $conexion->query("
        SELECT COALESCE(SUM(total_registros), 0) as total 
        FROM tablas_movimientos 
        WHERE tipo = 'baja' AND estado = 'enviado'
    ");
    $estadisticas['total_bajas'] = $consulta->fetch()['total'];
    
    // Total de carpetas
    $consulta = $conexion->query("SELECT COUNT(*) as total FROM carpetas_imss WHERE activo = 1");
    $estadisticas['total_carpetas'] = $consulta->fetch()['total'];
    
    // Total de tablas
    $consulta = $conexion->query("SELECT COUNT(*) as total FROM tablas_movimientos");
    $estadisticas['total_tablas'] = $consulta->fetch()['total'];
    
} catch (Exception $e) {
    // Si las tablas no existen
}

// Obtiene carpetas para filtro
$carpetas = [];
try {
    $carpetas = $conexion->query("SELECT id_carpeta, nombre FROM carpetas_imss WHERE activo = 1 ORDER BY nombre")->fetchAll();
} catch (Exception $e) {
    // Sin carpetas
}

// Incluye header
include __DIR__ . '/../layouts/header.php';

// Incluye sidebar
include __DIR__ . '/../layouts/sidebar-jefa.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reportes</h1>
        <p class="page-subtitle">Estadísticas y reportes de movimientos IMSS</p>
    </div>
</div>

<?php // Tarjetas de estadisticas ?>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #D1FAE5;">
            <i data-lucide="trending-up" style="color: #059669;"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Altas</div>
            <div class="stat-value"><?= number_format($estadisticas['total_altas']) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEE2E2;">
            <i data-lucide="trending-down" style="color: #DC2626;"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Bajas</div>
            <div class="stat-value"><?= number_format($estadisticas['total_bajas']) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #DBEAFE;">
            <i data-lucide="folders" style="color: #2563EB;"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Carpetas</div>
            <div class="stat-value"><?= $estadisticas['total_carpetas'] ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEF3C7;">
            <i data-lucide="table" style="color: #D97706;"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Tablas</div>
            <div class="stat-value"><?= $estadisticas['total_tablas'] ?></div>
        </div>
    </div>
    
</div>

<?php // Filtros ?>
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <form id="formFiltros" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
            
            <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
                <label class="form-label">Periodo</label>
                <select name="periodo" id="filtroPeriodo" class="form-control">
                    <option value="todo">Todo el tiempo</option>
                    <option value="mes" selected>Este mes</option>
                    <option value="trimestre">Último trimestre</option>
                    <option value="anio">Este año</option>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
                <label class="form-label">Carpeta</label>
                <select name="carpeta" id="filtroCarpeta" class="form-control">
                    <option value="">Todas</option>
                    <?php foreach ($carpetas as $carpeta): ?>
                    <option value="<?= $carpeta['id_carpeta'] ?>"><?= htmlspecialchars($carpeta['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
                <label class="form-label">Tipo</label>
                <select name="tipo" id="filtroTipo" class="form-control">
                    <option value="">Todos</option>
                    <option value="alta">Solo Altas</option>
                    <option value="baja">Solo Bajas</option>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
                <label class="form-label">Estado</label>
                <select name="estado" id="filtroEstado" class="form-control">
                    <option value="">Todos</option>
                    <option value="borrador">Borrador</option>
                    <option value="validado">Validado</option>
                    <option value="enviado">Enviado</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search"></i>
                    Filtrar
                </button>
                <button type="button" class="btn btn-ghost" onclick="exportarReporte()">
                    <i data-lucide="download"></i>
                    Exportar
                </button>
            </div>
            
        </form>
    </div>
</div>

<?php // Tabla de resultados ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i data-lucide="list" style="width: 18px; height: 18px; margin-right: 8px;"></i>
            Detalle de Movimientos
        </h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table" id="tablaReporte">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Carpeta</th>
                        <th>Subcarpeta</th>
                        <th>Tabla</th>
                        <th>Tipo</th>
                        <th style="text-align: center;">Alumnos</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="cuerpoReporte">
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <i data-lucide="loader-2" class="spin"></i>
                            Cargando...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <div id="resumenReporte" style="color: var(--text-secondary);"></div>
    </div>
</div>

<style>
.stat-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 14px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.spin {
    animation: spin 1s linear infinite;
}
</style>

<script>
// Carga el reporte al iniciar
document.addEventListener('DOMContentLoaded', function() {
    cargarReporte();
});

// Formulario de filtros
document.getElementById('formFiltros').addEventListener('submit', function(e) {
    e.preventDefault();
    cargarReporte();
});

// Carga el reporte con filtros
async function cargarReporte() {
    const tbody = document.getElementById('cuerpoReporte');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align: center; padding: 40px;">
                <i data-lucide="loader-2" class="spin"></i>
                Cargando...
            </td>
        </tr>
    `;
    lucide.createIcons();
    
    // Obtiene filtros
    const periodo = document.getElementById('filtroPeriodo').value;
    const carpeta = document.getElementById('filtroCarpeta').value;
    const tipo = document.getElementById('filtroTipo').value;
    const estado = document.getElementById('filtroEstado').value;
    
    // Construye URL con parametros
    let url = '<?= URL_BASE ?>api/tablas.php?action=reporte';
    url += `&periodo=${periodo}`;
    if (carpeta) url += `&carpeta=${carpeta}`;
    if (tipo) url += `&tipo=${tipo}`;
    if (estado) url += `&estado=${estado}`;
    
    // Hace la consulta directa ya que no tenemos la accion reporte en la API
    // Por ahora consultamos todas las tablas
    const respuesta = await llamarApi('<?= URL_BASE ?>api/carpetas.php?action=listar');
    
    if (!respuesta || !respuesta.success) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px; color: var(--danger);">
                    Error al cargar el reporte
                </td>
            </tr>
        `;
        return;
    }
    
    // Extrae todas las tablas de todas las carpetas
    const todasTablas = [];
    respuesta.data.forEach(carpeta => {
        carpeta.subcarpetas.forEach(subcarpeta => {
            subcarpeta.tablas.forEach(tabla => {
                tabla.carpeta_nombre = carpeta.nombre;
                tabla.subcarpeta_nombre = subcarpeta.nombre;
                todasTablas.push(tabla);
            });
        });
    });
    
    // Aplica filtros
    let tablasFiltradas = todasTablas;
    
    if (tipo) {
        tablasFiltradas = tablasFiltradas.filter(t => t.tipo === tipo);
    }
    
    if (estado) {
        tablasFiltradas = tablasFiltradas.filter(t => t.estado === estado);
    }
    
    if (carpeta) {
        // Filtraria por carpeta si tuvieramos el id
    }
    
    // Filtra por periodo
    const ahora = new Date();
    if (periodo === 'mes') {
        tablasFiltradas = tablasFiltradas.filter(t => {
            const fecha = new Date(t.fecha_creacion);
            return fecha.getMonth() === ahora.getMonth() && fecha.getFullYear() === ahora.getFullYear();
        });
    } else if (periodo === 'trimestre') {
        const hace3Meses = new Date();
        hace3Meses.setMonth(hace3Meses.getMonth() - 3);
        tablasFiltradas = tablasFiltradas.filter(t => new Date(t.fecha_creacion) >= hace3Meses);
    } else if (periodo === 'anio') {
        tablasFiltradas = tablasFiltradas.filter(t => {
            const fecha = new Date(t.fecha_creacion);
            return fecha.getFullYear() === ahora.getFullYear();
        });
    }
    
    // Ordena por fecha descendente
    tablasFiltradas.sort((a, b) => new Date(b.fecha_creacion) - new Date(a.fecha_creacion));
    
    if (tablasFiltradas.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    No se encontraron resultados
                </td>
            </tr>
        `;
        document.getElementById('resumenReporte').textContent = '0 registros encontrados';
        return;
    }
    
    // Genera filas
    let html = '';
    let totalAlumnos = 0;
    
    tablasFiltradas.forEach(tabla => {
        const esAlta = tabla.tipo === 'alta';
        totalAlumnos += parseInt(tabla.total_alumnos || 0);
        
        const estadoClase = {
            'borrador': 'badge-warning',
            'validado': 'badge-info',
            'enviado': 'badge-success'
        };
        
        const estadoTexto = {
            'borrador': '📝 Borrador',
            'validado': '✅ Validado',
            'enviado': '✅ Enviado'
        };
        
        html += `
            <tr>
                <td>${formatearFecha(tabla.fecha_movimiento)}</td>
                <td>${escapeHtml(tabla.carpeta_nombre)}</td>
                <td>${escapeHtml(tabla.subcarpeta_nombre)}</td>
                <td>
                    <a href="<?= URL_BASE ?>views/jefa/tabla.php?id=${tabla.id_tabla}" style="color: var(--primary); text-decoration: none;">
                        ${escapeHtml(tabla.nombre)}
                    </a>
                </td>
                <td>
                    <span class="badge ${esAlta ? 'badge-success' : 'badge-danger'}">
                        ${esAlta ? '▲ Alta' : '▼ Baja'}
                    </span>
                </td>
                <td style="text-align: center;">${tabla.total_alumnos || 0}</td>
                <td>
                    <span class="badge ${estadoClase[tabla.estado] || 'badge-secondary'}">
                        ${estadoTexto[tabla.estado] || tabla.estado}
                    </span>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    
    // Resumen
    const altas = tablasFiltradas.filter(t => t.tipo === 'alta').length;
    const bajas = tablasFiltradas.filter(t => t.tipo === 'baja').length;
    document.getElementById('resumenReporte').textContent = 
        `${tablasFiltradas.length} tabla(s) | ${altas} altas, ${bajas} bajas | ${totalAlumnos} alumno(s) en total`;
}

// Exportar reporte a CSV
function exportarReporte() {
    // Obtiene datos de la tabla
    const tabla = document.getElementById('tablaReporte');
    const filas = tabla.querySelectorAll('tbody tr');
    
    if (filas.length === 0 || filas[0].cells.length === 1) {
        mostrarNotificacion('No hay datos para exportar', 'warning');
        return;
    }
    
    let csv = 'Fecha,Carpeta,Subcarpeta,Tabla,Tipo,Alumnos,Estado\n';
    
    filas.forEach(fila => {
        const celdas = fila.querySelectorAll('td');
        const valores = [];
        celdas.forEach(celda => {
            let valor = celda.textContent.trim().replace(/"/g, '""');
            valores.push(`"${valor}"`);
        });
        csv += valores.join(',') + '\n';
    });
    
    // Descarga
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `reporte_movimientos_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    URL.revokeObjectURL(url);
    
    mostrarNotificacion('Reporte exportado');
}

// Utilidades
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatearFecha(fecha) {
    if (!fecha) return '';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
