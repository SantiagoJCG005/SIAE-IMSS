<?php
/**
 * SIAE-IMSS - Exportar TXT (Admin Servicios Escolares)
 * Permite exportar tablas validadas a formato IMSS
 */

$tituloPagina = 'Exportar TXT';
$currentPage = 'exportar';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirLogin();
requerirRol(ROL_ADMIN_SERVICIOS);

$conexion = obtenerConexion();

// Obtener tablas validadas (listas para exportar)
try {
    $consulta = $conexion->query("
        SELECT 
            t.*,
            s.nombre as subcarpeta_nombre,
            c.nombre as carpeta_nombre,
            u.nombre_completo as creado_por,
            (SELECT COUNT(*) FROM tabla_alumnos WHERE id_tabla = t.id_tabla) as total_alumnos
        FROM tablas_movimientos t
        INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
        INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
        LEFT JOIN usuarios u ON t.id_usuario_creacion = u.id_usuario
        WHERE t.estado = 'validado'
        ORDER BY t.fecha_movimiento DESC
    ");
    $tablas = $consulta->fetchAll();
} catch (Exception $e) {
    $tablas = [];
}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-admin-se.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Exportar TXT para IMSS</h1>
        <p class="page-subtitle">Genera archivos TXT en formato IMSS para altas y bajas</p>
    </div>
</div>

<!-- Aviso importante -->
<div class="alert alert-info" style="margin-bottom: 24px; display: flex; align-items: flex-start; gap: 12px;">
    <i data-lucide="info" style="width: 24px; height: 24px; flex-shrink: 0; margin-top: 2px;"></i>
    <div>
        <strong>Nota importante</strong><br>
        <span style="font-size: 14px;">Al exportar un archivo, la Jefa de Servicios Escolares recibirá una notificación automática para su conocimiento y revisión.</span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Tablas validadas disponibles</h3>
        <span class="badge" style="background: #DCFCE7; color: #166534;"><?= count($tablas) ?> tablas</span>
    </div>
    <div class="card-body">
        <?php if (count($tablas) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Carpeta</th>
                    <th>Tabla</th>
                    <th>Tipo</th>
                    <th>Fecha Mov.</th>
                    <th>Alumnos</th>
                    <th>Creado por</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tablas as $tabla): ?>
                <tr>
                    <td>
                        <div style="font-size: 12px; color: #64748B;"><?= htmlspecialchars($tabla['carpeta_nombre']) ?></div>
                        <div><?= htmlspecialchars($tabla['subcarpeta_nombre']) ?></div>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($tabla['nombre']) ?></strong>
                    </td>
                    <td>
                        <?php if ($tabla['tipo'] === 'alta'): ?>
                            <span class="badge" style="background: #DCFCE7; color: #166534;">📈 ALTA</span>
                        <?php else: ?>
                            <span class="badge" style="background: #FEE2E2; color: #991B1B;">📉 BAJA</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y', strtotime($tabla['fecha_movimiento'])) ?></td>
                    <td>
                        <strong><?= $tabla['total_alumnos'] ?></strong> registros
                    </td>
                    <td style="font-size: 13px; color: #64748B;">
                        <?= htmlspecialchars($tabla['creado_por'] ?? 'N/A') ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="previsualizarTXT(<?= $tabla['id_tabla'] ?>)" class="btn btn-sm btn-secondary" title="Ver preview">
                                <i data-lucide="eye" style="width: 16px; height: 16px;"></i>
                            </button>
                            <button onclick="confirmarExportacion(<?= $tabla['id_tabla'] ?>, '<?= htmlspecialchars($tabla['nombre']) ?>', <?= $tabla['total_alumnos'] ?>)" class="btn btn-sm btn-primary" title="Exportar TXT">
                                <i data-lucide="download" style="width: 16px; height: 16px;"></i>
                                Exportar
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 60px 20px; color: #64748B;">
            <i data-lucide="file-x" style="width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.3;"></i>
            <h3 style="margin-bottom: 8px; color: #1E293B;">No hay tablas listas para exportar</h3>
            <p>Las tablas deben ser validadas por la Jefa de Servicios antes de poder exportarlas.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de preview -->
<div id="modalPreview" class="modal-overlay">
    <div class="modal" style="width: 95%; max-width: 1400px;">
        <div class="modal-header">
            <h3>Vista previa del TXT</h3>
            <button onclick="cerrarModal('modalPreview')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="previewContent">
            <p>Cargando...</p>
        </div>
    </div>
</div>

<style>
.table td { vertical-align: middle; }
.badge { 
    display: inline-flex; 
    align-items: center; 
    padding: 4px 10px; 
    border-radius: 20px; 
    font-size: 12px; 
    font-weight: 500; 
}
.alert-info {
    background: #DBEAFE;
    border: 1px solid #93C5FD;
    border-radius: 8px;
    padding: 16px 20px;
    color: #1E40AF;
}
.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
</style>

<script>
const API_EXPORTAR = '<?= URL_BASE ?>api/exportar-txt.php';

async function previsualizarTXT(idTabla) {
    const modal = document.getElementById('modalPreview');
    const content = document.getElementById('previewContent');
    
    modal.classList.add('active');
    content.innerHTML = '<p style="text-align:center;padding:40px;">Cargando vista previa...</p>';
    
    try {
        const resp = await fetch(`${API_EXPORTAR}?action=preview&id_tabla=${idTabla}`);
        const data = await resp.json();
        
        if (data.success) {
            const d = data.data;
            content.innerHTML = `
                <div style="margin-bottom: 20px;">
                    <p><strong>Tipo:</strong> ${d.tipo === 'alta' ? '📈 ALTA' : '📉 BAJA'}</p>
                    <p><strong>Fecha movimiento:</strong> ${d.fecha}</p>
                    <p><strong>Registro patronal:</strong> ${d.registro_patronal}</p>
                    <p><strong>Código institución:</strong> ${d.codigo_institucion}</p>
                </div>
                <h4 style="margin-bottom: 12px;">Vista previa del contenido TXT (Primeros 5 registros):</h4>
                <div style="background: #1E293B; color: #E2E8F0; padding: 16px; border-radius: 8px; overflow-x: auto; font-family: monospace; font-size: 13px; white-space: pre;">
${d.preview.join('\n')}
                </div>
            `;
        } else {
            content.innerHTML = `<p style="color: #DC2626;">Error: ${data.message}</p>`;
        }
    } catch (e) {
        content.innerHTML = '<p style="color: #DC2626;">Error al cargar la vista previa</p>';
    }
}

function confirmarExportacion(idTabla, nombre, totalAlumnos) {
    Swal.fire({
        title: '¿Exportar archivo TXT?',
        html: `
            <p>Se generará el archivo TXT para la tabla:</p>
            <p><strong>${nombre}</strong></p>
            <p style="color: #64748B; font-size: 14px;">${totalAlumnos} registros</p>
            <div style="margin-top: 16px; padding: 12px; background: #FEF3C7; border-radius: 8px; text-align: left; font-size: 13px;">
                <strong>⚠️ Nota:</strong> La Jefa de Servicios recibirá una notificación de esta exportación.
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563EB',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Sí, exportar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            exportarTXT(idTabla);
        }
    });
}

async function exportarTXT(idTabla) {
    // Mostrar loading
    Swal.fire({
        title: 'Generando archivo...',
        text: 'Esto puede tomar unos segundos (notificando a la Jefa)',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const response = await fetch(`${API_EXPORTAR}?action=generar&id_tabla=${idTabla}`);
        
        if (!response.ok) {
            throw new Error('Error en el servidor');
        }
        
        // Verificar si la respuesta es JSON (significa que hubo un error lógico)
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json();
            Swal.fire('Error', data.message || 'No se pudo generar el archivo', 'error');
            return;
        }
        
        // Si no es JSON, es el archivo TXT
        const blob = await response.blob();
        
        // Intentar extraer el nombre del archivo desde las cabeceras
        let filename = 'IMSS_export.txt';
        const disposition = response.headers.get('content-disposition');
        if (disposition && disposition.includes('filename=')) {
            const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
            if (matches != null && matches[1]) { 
                filename = matches[1].replace(/['"]/g, '');
            }
        }
        
        // Forzar la descarga del blob en el navegador
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
        
        // Cerrar loading y mostrar éxito
        Swal.fire({
            title: '¡Exportación exitosa!',
            html: `
                <p>El archivo TXT ha sido generado y descargado.</p>
                <p style="color: #64748B; font-size: 14px; margin-top: 12px;">
                    La Jefa de Servicios ha sido notificada.
                </p>
            `,
            icon: 'success',
            confirmButtonColor: '#2563EB'
        }).then(() => {
            // Recargar para actualizar la lista
            location.reload();
        });
        
    } catch (error) {
        Swal.fire('Error', 'Hubo un problema de conexión al generar el archivo', 'error');
    }
}

function cerrarModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Cerrar modal con Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
    }
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
