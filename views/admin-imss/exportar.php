<?php
/**
 * SIAE-IMSS - Exportar TXT (Admin IMSS, solo lectura)
 * Descarga de archivos TXT ya generados
 */

$tituloPagina = 'Exportar TXT';
$currentPage = 'exportar';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requerirLogin();
requerirRol(ROL_ADMIN_IMSS);

$conexion = obtenerConexion();

// Solo tablas enviadas con archivo TXT generado
try {
    $consulta = $conexion->query("
        SELECT t.id_tabla, t.nombre, t.tipo, t.total_registros,
               t.fecha_movimiento, t.archivo_txt_generado, t.fecha_validacion,
               s.nombre as subcarpeta_nombre,
               c.nombre as carpeta_nombre,
               u.nombre_completo as creado_por,
               v.nombre_completo as validado_por
        FROM tablas_movimientos t
        INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
        INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
        LEFT JOIN usuarios u ON t.id_usuario_creacion = u.id_usuario
        LEFT JOIN usuarios v ON t.id_usuario_validacion = v.id_usuario
        WHERE t.estado = 'enviado'
          AND t.archivo_txt_generado IS NOT NULL
        ORDER BY t.fecha_movimiento DESC
    ");
    $tablas = $consulta->fetchAll();
} catch (Exception $e) {
    $tablas = [];
}

include __DIR__ . '/../layouts/header.php';
include __DIR__ . '/../layouts/sidebar-admin-imss.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Exportar TXT</h1>
        <p class="page-subtitle">Descarga de archivos TXT generados (solo lectura)</p>
    </div>
    <span class="badge" style="background:#F3E8FF;color:#5B21B6;font-size:13px;padding:6px 14px;">
        <?= count($tablas) ?> archivo(s) disponible(s)
    </span>
</div>

<!-- Aviso de solo lectura -->
<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:14px 18px;margin-bottom:24px;display:flex;align-items:center;gap:12px;color:#1E40AF;font-size:13px;">
    <i data-lucide="info" style="width:18px;height:18px;flex-shrink:0;"></i>
    <span>Puedes descargar los archivos TXT que ya fueron generados y enviados. No se modificará ningún dato del sistema.</span>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($tablas)): ?>
        <div class="empty-state" style="padding: 60px 20px;">
            <div class="empty-state-icon"><i data-lucide="file-x"></i></div>
            <div class="empty-state-title">Sin archivos disponibles</div>
            <div class="empty-state-text">Aún no se han generado archivos TXT enviados</div>
        </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Carpeta / Tabla</th>
                    <th>Tipo</th>
                    <th>Alumnos</th>
                    <th>Fecha mov.</th>
                    <th>Nombre del archivo</th>
                    <th>Validado por</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tablas as $t): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($t['nombre']) ?></strong>
                        <br>
                        <small style="color: var(--text-muted);">
                            <?= htmlspecialchars($t['carpeta_nombre']) ?> › <?= htmlspecialchars($t['subcarpeta_nombre']) ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($t['tipo'] === 'alta'): ?>
                        <span class="badge" style="background:#DCFCE7;color:#166534;">📈 Alta</span>
                        <?php else: ?>
                        <span class="badge" style="background:#FEE2E2;color:#991B1B;">📉 Baja</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= number_format($t['total_registros']) ?></strong> registros</td>
                    <td style="font-size:13px;"><?= date('d/m/Y', strtotime($t['fecha_movimiento'])) ?></td>
                    <td>
                        <code style="background:var(--bg-secondary);padding:3px 8px;border-radius:4px;font-size:12px;">
                            <?= htmlspecialchars($t['archivo_txt_generado']) ?>
                        </code>
                    </td>
                    <td style="font-size:13px;color:var(--text-muted);">
                        <?= htmlspecialchars($t['validado_por'] ?? '—') ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="previsualizarTXT(<?= $t['id_tabla'] ?>)"
                                    class="btn btn-sm btn-outline" title="Vista previa">
                                <i data-lucide="eye" style="width:15px;height:15px;"></i>
                            </button>
                            <button onclick="descargarTXT(<?= $t['id_tabla'] ?>, '<?= htmlspecialchars(addslashes($t['nombre'])) ?>', <?= $t['total_registros'] ?>)"
                                    class="btn btn-sm btn-primary" title="Descargar TXT">
                                <i data-lucide="download" style="width:15px;height:15px;"></i>
                                Descargar
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de preview -->
<div id="modalPreview" class="modal-overlay">
    <div class="modal" style="width: 95%; max-width: 1200px;">
        <div class="modal-header">
            <h3>Vista previa del TXT</h3>
            <button onclick="document.getElementById('modalPreview').classList.remove('active')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="previewContent">
            <div style="padding:40px;text-align:center;color:var(--text-muted);">Cargando...</div>
        </div>
    </div>
</div>

<style>
.badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
.table td { vertical-align: middle; padding: 10px 16px; }
.btn-sm { padding: 6px 12px; font-size: 13px; }
</style>

<script>
const API_EXPORTAR = '<?= URL_BASE ?>api/exportar-txt.php';

async function previsualizarTXT(idTabla) {
    document.getElementById('previewContent').innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-muted);">Cargando vista previa...</div>';
    document.getElementById('modalPreview').classList.add('active');

    try {
        const resp = await fetch(`${API_EXPORTAR}?action=preview&id_tabla=${idTabla}`);
        const data = await resp.json();

        if (data.success) {
            const d = data.data;
            document.getElementById('previewContent').innerHTML = `
                <div style="margin-bottom:16px;display:flex;gap:24px;flex-wrap:wrap;font-size:13px;">
                    <span><strong>Tipo:</strong> ${d.tipo === 'alta' ? '📈 ALTA' : '📉 BAJA'}</span>
                    <span><strong>Fecha mov.:</strong> ${d.fecha}</span>
                    <span><strong>Registro patronal:</strong> ${d.registro_patronal}</span>
                    <span><strong>Código institución:</strong> ${d.codigo_institucion}</span>
                </div>
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">Primeros 5 registros:</p>
                <div style="background:#1E293B;color:#E2E8F0;padding:16px;border-radius:8px;overflow-x:auto;font-family:monospace;font-size:12px;white-space:pre;">${d.preview.map(l => escHtml(l)).join('\n')}</div>
            `;
        } else {
            document.getElementById('previewContent').innerHTML = `<p style="color:var(--danger);padding:20px;">Error: ${data.message}</p>`;
        }
    } catch (e) {
        document.getElementById('previewContent').innerHTML = '<p style="color:var(--danger);padding:20px;">Error al cargar la vista previa</p>';
    }
}

function descargarTXT(idTabla, nombre, totalAlumnos) {
    Swal.fire({
        title: 'Descargar archivo TXT',
        html: `
            <p style="margin-bottom:8px;">Se descargará el archivo para:</p>
            <p><strong>${escHtml(nombre)}</strong></p>
            <p style="color:var(--text-muted);font-size:13px;">${totalAlumnos} registros</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7C3AED',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Descargar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) ejecutarDescarga(idTabla);
    });
}

async function ejecutarDescarga(idTabla) {
    Swal.fire({
        title: 'Generando archivo...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch(`${API_EXPORTAR}?action=descargar&id_tabla=${idTabla}`);

        if (!response.ok) throw new Error('Error en servidor');

        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json();
            Swal.fire('Error', data.message || 'No se pudo generar el archivo', 'error');
            return;
        }

        const blob = await response.blob();
        let filename = 'IMSS_export.txt';
        const disposition = response.headers.get('content-disposition');
        if (disposition && disposition.includes('filename=')) {
            const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
            if (matches && matches[1]) filename = matches[1].replace(/['"]/g, '');
        }

        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();

        Swal.fire({
            title: '¡Descarga exitosa!',
            text: 'El archivo TXT ha sido descargado.',
            icon: 'success',
            confirmButtonColor: '#7C3AED'
        });

    } catch (error) {
        Swal.fire('Error', 'Hubo un problema al descargar el archivo', 'error');
    }
}

function escHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
