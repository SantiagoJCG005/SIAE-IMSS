<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Importar Excel
 * Permite cargar archivos Excel para crear tablas de movimientos
 */

// Titulo de la pagina
$tituloPagina = 'Importar Excel';

// Carga archivos necesarios
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verifica permisos
requerirRol([ROL_JEFA_SERVICIOS, ROL_ADMIN_SERVICIOS, ROL_SUPERADMIN]);

// Conexion a la base de datos
$conexion = obtenerConexion();

// Obtiene carpetas para el select
$carpetas = [];
try {
    $condicionAdicional = "";
    $parametros = [];
    
    if (tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
        $condicionAdicional = " AND id_usuario_creacion = ? ";
        $parametros[] = obtenerIdUsuarioActual();
    }
    
    $consulta = $conexion->prepare("SELECT id_carpeta, nombre FROM carpetas_imss WHERE activo = 1 $condicionAdicional ORDER BY nombre");
    $consulta->execute($parametros);
    $carpetas = $consulta->fetchAll();
} catch (Exception $e) {
    // Sin carpetas
}

// Incluye header
include __DIR__ . '/../layouts/header.php';


// Incluye sidebar de administrador de servicios
include __DIR__ . '/../layouts/sidebar-admin-se.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Importar Excel</h1>
        <p class="page-subtitle">Carga archivos Excel para crear tablas de altas o bajas</p>
    </div>
</div>

<?php if (empty($carpetas)): ?>
<div class="card">
    <div class="card-body" style="text-align: center; padding: 60px;">
        <i data-lucide="folder-plus" style="width: 64px; height: 64px; color: var(--text-muted); margin-bottom: 16px;"></i>
        <h3>No hay carpetas</h3>
        <p style="color: var(--text-secondary); margin-bottom: 16px;">Primero debes crear una carpeta y subcarpeta para poder importar.</p>
        <a href="<?= URL_BASE ?>views/admin-se/carpetas.php?nueva=1" class="btn btn-primary">
            <i data-lucide="folder-plus"></i>
            Crear Carpeta
        </a>
    </div>
</div>
<?php else: ?>

<div class="card">
    <form id="formImportar" enctype="multipart/form-data">
        <div class="card-body">
            
            <?php // Paso 1: Ubicacion ?>
            <div style="margin-bottom: 24px;">
                <h4 style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                    <span style="width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;">1</span>
                    Ubicación
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Carpeta *</label>
                        <select name="id_carpeta" id="selectCarpeta" class="form-control" required onchange="cargarSubcarpetas()">
                            <option value="">-- Selecciona carpeta --</option>
                            <?php foreach ($carpetas as $carpeta): ?>
                            <option value="<?= $carpeta['id_carpeta'] ?>"><?= htmlspecialchars($carpeta['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subcarpeta *</label>
                        <select name="id_subcarpeta" id="selectSubcarpeta" class="form-control" required disabled>
                            <option value="">-- Primero selecciona carpeta --</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <?php // Paso 2: Tipo ?>
            <div style="margin-bottom: 24px;">
                <h4 style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                    <span style="width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;">2</span>
                    Tipo de Movimiento
                </h4>
                <div style="display: flex; gap: 24px;">
                    <label class="tipo-opcion" style="display: flex; align-items: center; gap: 12px; padding: 16px 24px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                        <input type="radio" name="tipo" value="alta" required style="width: 20px; height: 20px;">
                        <span style="display: flex; align-items: center; gap: 8px; font-weight: 500;">
                            <span style="color: #22C55E; font-size: 24px;">▲</span>
                            Altas
                        </span>
                    </label>
                    <label class="tipo-opcion" style="display: flex; align-items: center; gap: 12px; padding: 16px 24px; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                        <input type="radio" name="tipo" value="baja" style="width: 20px; height: 20px;">
                        <span style="display: flex; align-items: center; gap: 8px; font-weight: 500;">
                            <span style="color: #EF4444; font-size: 24px;">▼</span>
                            Bajas
                        </span>
                    </label>
                </div>
            </div>
            
            <?php // Paso 3: Fecha ?>
            <div style="margin-bottom: 24px;">
                <h4 style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                    <span style="width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;">3</span>
                    Fecha del Movimiento
                </h4>
                <div class="form-group" style="max-width: 250px;">
                    <input type="date" name="fecha_movimiento" id="fechaMovimiento" class="form-control" required 
                           value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            
            <?php // Paso 4: Archivo ?>
            <div style="margin-bottom: 24px;">
                <h4 style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                    <span style="width: 28px; height: 28px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;">4</span>
                    Archivo Excel
                </h4>
                
                <div id="zonaArchivo" class="zona-archivo">
                    <i data-lucide="file-spreadsheet" style="width: 48px; height: 48px; color: var(--text-muted);"></i>
                    <p style="margin: 12px 0 4px; font-weight: 500;">Arrastra tu archivo Excel aquí</p>
                    <p style="color: var(--text-secondary); font-size: 14px;">o haz clic para seleccionar</p>
                    <p style="color: var(--text-muted); font-size: 12px; margin-top: 8px;">Formatos: .xlsx</p>
                    <input type="file" name="archivo" id="inputArchivo" accept=".xlsx" style="display: none;" required>
                </div>
                
                <div id="archivoSeleccionado" style="display: none; padding: 16px; background: var(--bg-secondary); border-radius: 8px; margin-top: 12px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i data-lucide="file-check" style="color: #22C55E;"></i>
                            <span id="nombreArchivo"></span>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="quitarArchivo()">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <?php // Columnas esperadas ?>
            <div class="alert-info" style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                <h5 style="margin: 0 0 8px; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="info" style="width: 18px; height: 18px; color: #2563EB;"></i>
                    Columnas esperadas en el Excel
                </h5>
                <p style="margin: 0; color: #1E40AF; font-size: 14px;">
                    NSS / NUMERO AFILIACION, DIGITO VERIFICADOR / DV, APELLIDO PATERNO, APELLIDO MATERNO, NOMBRE(S), CURP
                </p>
            </div>
            
        </div>
        
        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 12px;">
            <a href="<?= URL_BASE ?>views/admin-se/carpetas.php" class="btn btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary" id="btnImportar" disabled>
                <i data-lucide="upload"></i>
                Importar Excel
            </button>
        </div>
    </form>
</div>

<?php // Resultado de importacion ?>
<div id="resultadoImportacion" class="card" style="display: none; margin-top: 24px;">
    <div class="card-header">
        <h3 class="card-title">
            <i data-lucide="check-circle" style="width: 18px; height: 18px; margin-right: 8px; color: #22C55E;"></i>
            Importación Completada
        </h3>
    </div>
    <div class="card-body">
        <div id="resumenImportacion"></div>
    </div>
    <div class="card-footer">
        <a href="#" id="linkVerTabla" class="btn btn-primary">
            <i data-lucide="eye"></i>
            Ver Tabla Importada
        </a>
    </div>
</div>

<?php endif; ?>

<style>
.zona-archivo {
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.zona-archivo:hover,
.zona-archivo.dragover {
    border-color: var(--primary);
    background: #EFF6FF;
}

.tipo-opcion:has(input:checked) {
    border-color: var(--primary);
    background: #EFF6FF;
}

#btnImportar:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<script>
const API_CARPETAS = '<?= URL_BASE ?>api/carpetas.php';
const API_IMPORTAR = '<?= URL_BASE ?>api/importar.php';

// Inicializacion
document.addEventListener('DOMContentLoaded', function() {
    configurarZonaArchivo();
    validarFormulario();
});

// Carga subcarpetas cuando cambia la carpeta
async function cargarSubcarpetas() {
    const selectCarpeta = document.getElementById('selectCarpeta');
    const selectSubcarpeta = document.getElementById('selectSubcarpeta');
    
    const idCarpeta = selectCarpeta.value;
    
    if (!idCarpeta) {
        selectSubcarpeta.innerHTML = '<option value="">-- Primero selecciona carpeta --</option>';
        selectSubcarpeta.disabled = true;
        validarFormulario();
        return;
    }
    
    selectSubcarpeta.innerHTML = '<option value="">Cargando...</option>';
    selectSubcarpeta.disabled = true;
    
    const respuesta = await llamarApi(API_CARPETAS + '?action=listar_subcarpetas&id_carpeta=' + idCarpeta);
    
    if (respuesta && respuesta.success && respuesta.data.length > 0) {
        let opciones = '<option value="">-- Selecciona subcarpeta --</option>';
        respuesta.data.forEach(sub => {
            opciones += `<option value="${sub.id_subcarpeta}">${escapeHtml(sub.nombre)}</option>`;
        });
        selectSubcarpeta.innerHTML = opciones;
        selectSubcarpeta.disabled = false;
    } else {
        selectSubcarpeta.innerHTML = '<option value="">-- No hay subcarpetas, créa una primero --</option>';
        selectSubcarpeta.disabled = true;
    }
    
    validarFormulario();
}

// Configura la zona de arrastrar archivo
function configurarZonaArchivo() {
    const zona = document.getElementById('zonaArchivo');
    const input = document.getElementById('inputArchivo');
    
    // Click para seleccionar
    zona.addEventListener('click', () => input.click());
    
    // Drag and drop
    zona.addEventListener('dragover', (e) => {
        e.preventDefault();
        zona.classList.add('dragover');
    });
    
    zona.addEventListener('dragleave', () => {
        zona.classList.remove('dragover');
    });
    
    zona.addEventListener('drop', (e) => {
        e.preventDefault();
        zona.classList.remove('dragover');
        
        const archivos = e.dataTransfer.files;
        if (archivos.length > 0) {
            input.files = archivos;
            mostrarArchivoSeleccionado(archivos[0]);
        }
    });
    
    // Cuando selecciona archivo
    input.addEventListener('change', () => {
        if (input.files.length > 0) {
            mostrarArchivoSeleccionado(input.files[0]);
        }
    });
}

// Muestra el archivo seleccionado
function mostrarArchivoSeleccionado(archivo) {
    const extension = archivo.name.split('.').pop().toLowerCase();
    
    if (extension !== 'xlsx') {
        mostrarNotificacion('Solo se permiten archivos .xlsx', 'error');
        return;
    }
    
    document.getElementById('zonaArchivo').style.display = 'none';
    document.getElementById('archivoSeleccionado').style.display = 'block';
    document.getElementById('nombreArchivo').textContent = archivo.name;
    lucide.createIcons();
    
    validarFormulario();
}

// Quita el archivo seleccionado
function quitarArchivo() {
    document.getElementById('inputArchivo').value = '';
    document.getElementById('zonaArchivo').style.display = 'block';
    document.getElementById('archivoSeleccionado').style.display = 'none';
    validarFormulario();
}

// Valida si el formulario esta completo
function validarFormulario() {
    const carpeta = document.getElementById('selectCarpeta').value;
    const subcarpeta = document.getElementById('selectSubcarpeta').value;
    const tipo = document.querySelector('input[name="tipo"]:checked');
    const fecha = document.getElementById('fechaMovimiento').value;
    const archivo = document.getElementById('inputArchivo').files.length > 0;
    
    const completo = carpeta && subcarpeta && tipo && fecha && archivo;
    document.getElementById('btnImportar').disabled = !completo;
}

// Escucha cambios en el formulario
document.querySelectorAll('#formImportar input, #formImportar select').forEach(el => {
    el.addEventListener('change', validarFormulario);
});

// Enviar formulario
document.getElementById('formImportar').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btnImportar = document.getElementById('btnImportar');
    btnImportar.disabled = true;
    btnImportar.innerHTML = '<i data-lucide="loader-2" class="spin"></i> Importando...';
    lucide.createIcons();
    
    const formData = new FormData(this);
    formData.append('action', 'procesar');
    
    try {
        const respuesta = await fetch(API_IMPORTAR, {
            method: 'POST',
            body: formData
        });
        
        const resultado = await respuesta.json();
        
        if (resultado.success) {
            // Muestra resultado
            mostrarResultado(resultado.data);
            mostrarNotificacion(resultado.message);
        } else {
            mostrarNotificacion(resultado.message || 'Error al importar', 'error');
            btnImportar.disabled = false;
            btnImportar.innerHTML = '<i data-lucide="upload"></i> Importar Excel';
            lucide.createIcons();
        }
        
    } catch (error) {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión', 'error');
        btnImportar.disabled = false;
        btnImportar.innerHTML = '<i data-lucide="upload"></i> Importar Excel';
        lucide.createIcons();
    }
});

// Muestra el resultado de la importacion
function mostrarResultado(datos) {
    document.getElementById('formImportar').closest('.card').style.display = 'none';
    
    const divResultado = document.getElementById('resultadoImportacion');
    divResultado.style.display = 'block';
    
    document.getElementById('resumenImportacion').innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center;">
            <div style="padding: 20px; background: var(--bg-secondary); border-radius: 8px;">
                <div style="font-size: 32px; font-weight: 700; color: var(--primary);">${datos.total_registros}</div>
                <div style="color: var(--text-secondary);">Total registros</div>
            </div>
            <div style="padding: 20px; background: #D1FAE5; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: 700; color: #059669;">${datos.validos}</div>
                <div style="color: #065F46;">Válidos</div>
            </div>
            <div style="padding: 20px; background: ${datos.con_errores > 0 ? '#FEF2F2' : '#D1FAE5'}; border-radius: 8px;">
                <div style="font-size: 32px; font-weight: 700; color: ${datos.con_errores > 0 ? '#DC2626' : '#059669'};">${datos.con_errores}</div>
                <div style="color: ${datos.con_errores > 0 ? '#991B1B' : '#065F46'};">Con errores</div>
            </div>
        </div>
        <p style="margin-top: 20px; text-align: center; color: var(--text-secondary);">
            Tabla creada: <strong>${escapeHtml(datos.nombre_tabla)}</strong>
            ${datos.con_errores > 0 ? '<br><span style="color: #DC2626;">⚠️ Revisa y corrige los registros con errores antes de generar el TXT.</span>' : ''}
        </p>
    `;
    
    document.getElementById('linkVerTabla').href = '<?= URL_BASE ?>views/admin-se/tabla.php?id=' + datos.id_tabla;
    
    lucide.createIcons();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
