<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Vista de Resultados TXT
 * Muestra líneas RESULTADO generadas para cada alumno
 * Con paginación de 100 registros
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirRol([ROL_JEFA_SERVICIOS, ROL_ADMIN_SERVICIOS, ROL_SUPERADMIN]);

$idTabla = intval(obtenerGet('id', 0));

if ($idTabla <= 0) {
    header('Location: ' . URL_BASE . 'views/jefa/carpetas.php');
    exit;
}

$conexion = obtenerConexion();

$consulta = $conexion->prepare("
    SELECT t.*, s.nombre as subcarpeta_nombre, c.nombre as carpeta_nombre
    FROM tablas_movimientos t
    INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
    INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
    WHERE t.id_tabla = ?
");
$consulta->execute([$idTabla]);
$tabla = $consulta->fetch();

if (!$tabla) {
    header('Location: ' . URL_BASE . 'views/jefa/carpetas.php');
    exit;
}

$configPatronal = $conexion->query("SELECT * FROM configuracion_patronal WHERE activo = 1 LIMIT 1")->fetch();

$tituloPagina = 'Resultados TXT - ' . $tabla['nombre'];
$esAlta = $tabla['tipo'] === 'alta';

$datosPatronales = [
    'registro_patronal' => $configPatronal['registro_patronal'] ?? '',
    'digito_verificador_patronal' => $configPatronal['digito_verificador'] ?? '',
    'umf' => $esAlta ? ($configPatronal['umf_alta'] ?? '001') : ($configPatronal['umf_baja'] ?? '000'),
    'codigo_operacion' => $esAlta ? '08' : '02',
    'fecha_movimiento' => date('d/m/Y', strtotime($tabla['fecha_movimiento']))
];

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-jefa.php';
?>

<!-- Título arriba -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
    <div>
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
            <a href="<?= URL_BASE ?>views/jefa/tabla.php?id=<?= $idTabla ?>" class="btn btn-ghost btn-sm">
                <i data-lucide="arrow-left"></i> Volver a Tabla
            </a>
        </div>
        <h1 class="page-title">
            <i data-lucide="file-text" style="color: #2563EB;"></i>
            Resultados TXT
        </h1>
        <p class="page-subtitle">
            <?= htmlspecialchars($tabla['nombre']) ?> • 
            <?= date('d-M-Y', strtotime($tabla['fecha_movimiento'])) ?> •
            <span style="color: <?= $esAlta ? '#22C55E' : '#EF4444' ?>; font-weight: 600;">
                <?= $esAlta ? '▲ ALTA' : '▼ BAJA' ?>
            </span>
        </p>
    </div>
    
    <a href="<?= URL_BASE ?>api/exportar-txt.php?action=generar&id_tabla=<?= $idTabla ?>" class="btn btn-primary">
        <i data-lucide="download"></i> Descargar TXT
    </a>
    
</div>

<!-- Datos Patronales -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="padding: 12px 20px; background: #F1F5F9;">
        <h3 class="card-title" style="font-size: 13px;">
            <i data-lucide="building" style="width: 16px; height: 16px;"></i>
            Datos Patronales (Configuración IMSS)
        </h3>
    </div>
    <div class="card-body">
        <div class="datos-patronales-grid">
            <div class="dato-patronal">
                <span class="dato-label">REGISTRO PATRONAL</span>
                <span class="dato-valor"><?= $configPatronal['registro_patronal'] ?></span>
            </div>
            <div class="dato-patronal">
                <span class="dato-label">DV PATRONAL</span>
                <span class="dato-valor"><?= $configPatronal['digito_verificador'] ?></span>
            </div>
            <div class="dato-patronal">
                <span class="dato-label">UMF</span>
                <span class="dato-valor"><?= $datosPatronales['umf'] ?></span>
            </div>
            <div class="dato-patronal">
                <span class="dato-label">CÓDIGO OP.</span>
                <span class="dato-valor"><?= $datosPatronales['codigo_operacion'] ?></span>
            </div>
            <div class="dato-patronal">
                <span class="dato-label">FECHA MOV.</span>
                <span class="dato-valor"><?= $datosPatronales['fecha_movimiento'] ?></span>
            </div>
            <div class="dato-patronal">
                <span class="dato-label">LONG. LÍNEA</span>
                <span class="dato-valor">168 caracteres</span>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de Resultados -->
<div class="card">
    <div class="card-header">
        <div class="header-row">
            <h3 class="card-title">
                <i data-lucide="list"></i>
                Líneas RESULTADO (<span id="conteoTotal">0</span>)
            </h3>
            <div class="buscador-container">
                <i data-lucide="search" class="buscador-icono"></i>
                <input type="text" id="inputBuscar" class="form-control buscador-input" 
                       placeholder="Buscar NSS, nombre..." oninput="filtrarResultados()">
            </div>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table" id="tablaResultados">
                <thead>
                    <tr>
                        <th style="width: 110px;">CUENTA</th>
                        <th style="width: 200px;">NOMBRE COMPLETO</th>
                        <th style="width: 130px;">NSS</th>
                        <th>RESULTADO TXT</th>
                        <th style="width: 60px;">VER</th>
                    </tr>
                </thead>
                <tbody id="cuerpoTabla">
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <i data-lucide="loader-2" class="spin"></i> Cargando...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <div class="footer-info">
            <span id="infoPaginacion">Mostrando 0-0 de 0</span>
        </div>
        <div id="controlesPaginacion" class="paginacion-controles"></div>
    </div>
</div>

<!-- Modal Visualizar -->
<div class="modal-overlay" id="modalVisualizar">
    <div class="modal" style="max-width: 900px;">
        <div class="modal-header">
            <h3 class="modal-title"><i data-lucide="eye"></i> Detalle del Registro</h3>
            <button class="modal-close" onclick="cerrarModal()"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <!-- Datos Patronales -->
            <div class="seccion-modal">
                <div class="seccion-titulo"><i data-lucide="building"></i> Datos Patronales (solo lectura)</div>
                <div class="seccion-contenido">
                    <div class="campos-grid">
                        <div class="campo"><span class="campo-label">Registro Patronal</span><div class="campo-valor bloqueado" id="vizRegPatronal"></div></div>
                        <div class="campo"><span class="campo-label">DV Patronal</span><div class="campo-valor bloqueado" id="vizDvPatronal"></div></div>
                        <div class="campo"><span class="campo-label">UMF</span><div class="campo-valor bloqueado" id="vizUmf"></div></div>
                        <div class="campo"><span class="campo-label">Código Op.</span><div class="campo-valor bloqueado" id="vizCodOp"></div></div>
                        <div class="campo"><span class="campo-label">Fecha Mov.</span><div class="campo-valor bloqueado" id="vizFechaMov"></div></div>
                        <div class="campo"><span class="campo-label">Número Cuenta</span><div class="campo-valor bloqueado cuenta" id="vizCuenta"></div></div>
                    </div>
                </div>
            </div>
            
            <!-- Datos del Alumno -->
            <div class="seccion-modal">
                <div class="seccion-titulo"><i data-lucide="user"></i> Datos del Alumno</div>
                <div class="seccion-contenido">
                    <div class="campos-grid">
                        <div class="campo"><span class="campo-label">NSS</span><div class="campo-valor" id="vizNss"></div></div>
                        <div class="campo"><span class="campo-label">DV Alumno</span><div class="campo-valor" id="vizDvAlumno"></div></div>
                        <div class="campo"><span class="campo-label">Apellido Paterno</span><div class="campo-valor" id="vizApPaterno"></div></div>
                        <div class="campo"><span class="campo-label">Apellido Materno</span><div class="campo-valor" id="vizApMaterno"></div></div>
                        <div class="campo"><span class="campo-label">Nombre(s)</span><div class="campo-valor" id="vizNombres"></div></div>
                        <div class="campo"><span class="campo-label">CURP</span><div class="campo-valor" id="vizCurp"></div></div>
                    </div>
                </div>
            </div>
            
            <!-- Normalización -->
            <div class="seccion-modal" id="seccionNormalizacion" style="display: none;">
                <div class="seccion-titulo warning"><i data-lucide="alert-circle"></i> Normalización Aplicada</div>
                <div class="seccion-contenido" id="vizNormalizacion"></div>
            </div>
            
            <!-- Línea RESULTADO -->
            <div class="seccion-modal">
                <div class="seccion-titulo resultado"><i data-lucide="file-code"></i> Línea RESULTADO TXT (<span id="contadorCaracteres">0</span> caracteres)</div>
                <div class="seccion-contenido">
                    <div class="linea-txt" id="vizLineaTxt"></div>
                    <button class="btn btn-sm btn-outline" style="margin-top: 12px;" onclick="copiarLinea()">
                        <i data-lucide="copy"></i> Copiar línea
                    </button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="cerrarModal()">Cerrar</button>
        </div>
    </div>
</div>

<style>
.datos-patronales-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 16px;
}
.dato-patronal {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.dato-label {
    font-size: 10px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.dato-valor {
    font-weight: 600;
    font-size: 14px;
    font-family: 'Courier New', monospace;
}

.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.buscador-container { position: relative; min-width: 250px; }
.buscador-icono { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted); }
.buscador-input { padding-left: 38px; }

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding: 16px 20px;
    border-top: 1px solid var(--border-color);
}
.footer-info { font-size: 13px; color: var(--text-secondary); }

.paginacion-controles { display: flex; gap: 4px; align-items: center; }
.paginacion-btn {
    min-width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color);
    background: var(--bg-primary);
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    color: var(--text-primary);
    transition: all 0.2s;
}
.paginacion-btn:hover { background: var(--bg-secondary); border-color: var(--primary); }
.paginacion-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
.paginacion-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.paginacion-ellipsis { padding: 0 8px; color: var(--text-muted); }

.linea-truncada {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    background: #F1F5F9;
    padding: 6px 10px;
    border-radius: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 350px;
}

.seccion-modal { background: var(--bg-secondary); border-radius: 8px; margin-bottom: 16px; overflow: hidden; }
.seccion-titulo { background: #E2E8F0; padding: 12px 16px; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 8px; }
.seccion-titulo i { width: 16px; height: 16px; }
.seccion-titulo.warning { background: #FEF3C7; color: #92400E; }
.seccion-titulo.resultado { background: #DBEAFE; color: #1E40AF; }
.seccion-contenido { padding: 16px; }

.campos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
.campo-label { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
.campo-valor { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px 12px; font-family: 'Courier New', monospace; font-size: 13px; }
.campo-valor.bloqueado { background: #F1F5F9; color: #64748B; }
.campo-valor.cuenta { background: #DBEAFE; border-color: #93C5FD; color: #1E40AF; font-weight: 600; }

.linea-txt {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    background: #1E293B;
    color: #22D3EE;
    padding: 16px;
    border-radius: 8px;
    word-break: break-all;
    line-height: 1.6;
    letter-spacing: 0.5px;
}

.fila-error { background: #FEF2F2 !important; }
.fila-normalizado { background: #FFFBEB !important; }

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.spin { animation: spin 1s linear infinite; }
</style>

<script>
const ID_TABLA = <?= $idTabla ?>;
const API_URL = '<?= URL_BASE ?>api/tablas.php';
const REGISTROS_POR_PAGINA = 100;

const CONFIG_PATRONAL = {
    registro_patronal: '<?= $datosPatronales['registro_patronal'] ?>',
    dv_patronal: '<?= $datosPatronales['digito_verificador_patronal'] ?>',
    umf: '<?= $datosPatronales['umf'] ?>',
    codigo_operacion: '<?= $datosPatronales['codigo_operacion'] ?>',
    fecha_movimiento: '<?= $datosPatronales['fecha_movimiento'] ?>'
};

let resultadosData = [];
let resultadosFiltrados = [];
let paginaActual = 1;
let lineaActual = '';

document.addEventListener('DOMContentLoaded', cargarResultados);

async function cargarResultados() {
    const resp = await llamarApi(API_URL + '?action=obtener_resultados&id_tabla=' + ID_TABLA);
    if (!resp || !resp.success) {
        document.getElementById('cuerpoTabla').innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--danger);">Error al cargar</td></tr>';
        return;
    }
    resultadosData = resp.data.resultados || [];
    resultadosFiltrados = [...resultadosData];
    document.getElementById('conteoTotal').textContent = resultadosData.length;
    renderizar();
}

function filtrarResultados() {
    const busqueda = document.getElementById('inputBuscar').value.toLowerCase().trim();
    resultadosFiltrados = resultadosData.filter(r => {
        if (!busqueda) return true;
        const texto = [r.numero_afiliacion, r.apellido_paterno, r.apellido_materno, r.nombres].join(' ').toLowerCase();
        return texto.includes(busqueda);
    });
    paginaActual = 1;
    renderizar();
}

function renderizar() {
    const tbody = document.getElementById('cuerpoTabla');
    const total = resultadosFiltrados.length;
    const totalPaginas = Math.ceil(total / REGISTROS_POR_PAGINA);
    const inicio = (paginaActual - 1) * REGISTROS_POR_PAGINA;
    const fin = Math.min(inicio + REGISTROS_POR_PAGINA, total);
    const pagina = resultadosFiltrados.slice(inicio, fin);

    document.getElementById('infoPaginacion').textContent = total > 0 ? `Mostrando ${inicio + 1}-${fin} de ${total}` : 'Sin resultados';

    if (pagina.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);">Sin resultados</td></tr>';
        document.getElementById('controlesPaginacion').innerHTML = '';
        return;
    }

    let html = '';
    pagina.forEach(r => {
        const tieneError = r.tiene_errores == 1;
        const normalizado = r.datos_originales != null && !tieneError;
        const clase = tieneError ? 'fila-error' : (normalizado ? 'fila-normalizado' : '');
        const estado = tieneError ? '🔴' : (normalizado ? '🟡' : '🟢');
        
        const nombreCompleto = [r.apellido_paterno, r.apellido_materno, r.nombres].filter(Boolean).join(' ');
        const lineaTruncada = r.linea_resultado ? r.linea_resultado.substring(0, 50) + '...' : '---';
        const cuenta = String(r.numero_cuenta).padStart(10, '0');
        
        html += `<tr class="${clase}">
            <td style="font-family:'Courier New',monospace; font-weight: 600; color: #1E40AF;">${cuenta}</td>
            <td>${estado} ${escapeHtml(nombreCompleto)}</td>
            <td style="font-family:'Courier New',monospace;">${r.numero_afiliacion}-${r.digito_verificador}</td>
            <td><div class="linea-truncada">${escapeHtml(lineaTruncada)}</div></td>
            <td><button class="btn btn-ghost btn-sm btn-icon" onclick="visualizar(${r.id_registro})" title="Ver detalle"><i data-lucide="eye"></i></button></td>
        </tr>`;
    });
    
    tbody.innerHTML = html;
    renderizarPaginacion(totalPaginas);
    lucide.createIcons();
}

function renderizarPaginacion(totalPaginas) {
    const contenedor = document.getElementById('controlesPaginacion');
    if (totalPaginas <= 1) { contenedor.innerHTML = ''; return; }
    
    let html = `<button class="paginacion-btn" onclick="irAPagina(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}><i data-lucide="chevron-left" style="width:16px;height:16px;"></i></button>`;
    
    const rango = 2;
    let paginas = [1];
    for (let i = Math.max(2, paginaActual - rango); i <= Math.min(totalPaginas - 1, paginaActual + rango); i++) paginas.push(i);
    if (totalPaginas > 1) paginas.push(totalPaginas);
    paginas = [...new Set(paginas)].sort((a,b) => a-b);
    
    let ultima = 0;
    paginas.forEach(p => {
        if (p - ultima > 1) html += '<span class="paginacion-ellipsis">...</span>';
        html += `<button class="paginacion-btn ${p === paginaActual ? 'active' : ''}" onclick="irAPagina(${p})">${p}</button>`;
        ultima = p;
    });
    
    html += `<button class="paginacion-btn" onclick="irAPagina(${paginaActual + 1})" ${paginaActual === totalPaginas ? 'disabled' : ''}><i data-lucide="chevron-right" style="width:16px;height:16px;"></i></button>`;
    contenedor.innerHTML = html;
    lucide.createIcons();
}

function irAPagina(p) {
    const totalPaginas = Math.ceil(resultadosFiltrados.length / REGISTROS_POR_PAGINA);
    if (p < 1 || p > totalPaginas) return;
    paginaActual = p;
    renderizar();
}

function visualizar(id) {
    const r = resultadosData.find(x => x.id_registro == id);
    if (!r) return;
    
    // Datos Patronales
    document.getElementById('vizRegPatronal').textContent = CONFIG_PATRONAL.registro_patronal;
    document.getElementById('vizDvPatronal').textContent = CONFIG_PATRONAL.dv_patronal;
    document.getElementById('vizUmf').textContent = CONFIG_PATRONAL.umf;
    document.getElementById('vizCodOp').textContent = CONFIG_PATRONAL.codigo_operacion;
    document.getElementById('vizFechaMov').textContent = CONFIG_PATRONAL.fecha_movimiento;
    document.getElementById('vizCuenta').textContent = String(r.numero_cuenta).padStart(10, '0');
    
    // Datos del Alumno
    document.getElementById('vizNss').textContent = r.numero_afiliacion || '---';
    document.getElementById('vizDvAlumno').textContent = r.digito_verificador || '---';
    document.getElementById('vizApPaterno').textContent = r.apellido_paterno || '---';
    document.getElementById('vizApMaterno').textContent = r.apellido_materno || '---';
    document.getElementById('vizNombres').textContent = r.nombres || '---';
    document.getElementById('vizCurp').textContent = r.curp || '---';
    
    // Normalización
    const secNorm = document.getElementById('seccionNormalizacion');
    if (r.datos_originales) {
        let datosOriginales = typeof r.datos_originales === 'string' ? JSON.parse(r.datos_originales) : r.datos_originales;
        secNorm.style.display = 'block';
        let html = '';
        for (const [campo, original] of Object.entries(datosOriginales)) {
            html += `<div><strong>${campo}:</strong> <span style="color:#EF4444;">${escapeHtml(original)}</span> → <span style="color:#22C55E;">${escapeHtml(r[campo])}</span></div>`;
        }
        document.getElementById('vizNormalizacion').innerHTML = html;
    } else {
        secNorm.style.display = 'none';
    }
    
    // Línea RESULTADO
    lineaActual = r.linea_resultado || '';
    document.getElementById('vizLineaTxt').textContent = lineaActual;
    document.getElementById('contadorCaracteres').textContent = lineaActual.length;
    
    document.getElementById('modalVisualizar').classList.add('active');
    lucide.createIcons();
}

function cerrarModal() {
    document.getElementById('modalVisualizar').classList.remove('active');
}

function copiarLinea() {
    navigator.clipboard.writeText(lineaActual).then(() => {
        mostrarNotificacion('Línea copiada al portapapeles');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });
document.getElementById('modalVisualizar').addEventListener('click', e => { if (e.target === e.currentTarget) cerrarModal(); });
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
