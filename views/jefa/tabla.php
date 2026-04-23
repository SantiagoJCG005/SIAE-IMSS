<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Vista de Tabla de Movimientos
 * Ver y editar alumnos de una tabla de altas o bajas
 * Con paginación de 100 registros y ESCÁNER DE TARJETA NSS
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
    SELECT t.*, s.nombre as subcarpeta_nombre, c.nombre as carpeta_nombre,
           u.nombre_completo as creador
    FROM tablas_movimientos t
    INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
    INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
    LEFT JOIN usuarios u ON t.id_usuario_creacion = u.id_usuario
    WHERE t.id_tabla = ?
");
$consulta->execute([$idTabla]);
$tabla = $consulta->fetch();

if (!$tabla) {
    header('Location: ' . URL_BASE . 'views/jefa/carpetas.php');
    exit;
}

$configPatronal = $conexion->query("SELECT * FROM configuracion_patronal WHERE activo = 1 LIMIT 1")->fetch();

$tituloPagina = $tabla['nombre'];
$esAlta = $tabla['tipo'] === 'alta';
$esEnviado = $tabla['estado'] === 'enviado';
$esBorrador = $tabla['estado'] === 'borrador';

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

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
    <div>
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
            <a href="<?= URL_BASE ?>views/jefa/carpetas.php" class="btn btn-ghost btn-sm">
                <i data-lucide="arrow-left"></i> Volver
            </a>
        </div>
        <h1 class="page-title" style="display: flex; align-items: center; gap: 12px;">
            <i data-lucide="<?= $esAlta ? 'trending-up' : 'trending-down' ?>" 
               style="color: <?= $esAlta ? '#22C55E' : '#EF4444' ?>;"></i>
            <?= htmlspecialchars($tabla['nombre']) ?>
        </h1>
        <p class="page-subtitle">
            📍 <?= htmlspecialchars($tabla['carpeta_nombre']) ?> › <?= htmlspecialchars($tabla['subcarpeta_nombre']) ?>
        </p>
    </div>
    <span class="badge <?= $tabla['estado'] === 'borrador' ? 'badge-warning' : 'badge-success' ?>" style="font-size: 14px; padding: 8px 16px;">
        <?= $tabla['estado'] === 'borrador' ? '📝 BORRADOR' : '🟢 ENVIADO' ?>
    </span>
</div>

<!-- Info de la tabla -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px;">
            <div>
                <div class="info-label">Tipo</div>
                <div class="info-valor" style="color: <?= $esAlta ? '#22C55E' : '#EF4444' ?>;">
                    <?= $esAlta ? '▲ Alta' : '▼ Baja' ?>
                </div>
            </div>
            <div>
                <div class="info-label">Fecha Movimiento</div>
                <div class="info-valor"><?= date('d/m/Y', strtotime($tabla['fecha_movimiento'])) ?></div>
            </div>
            <div>
                <div class="info-label">Creado por</div>
                <div class="info-valor"><?= htmlspecialchars($tabla['creador']) ?></div>
            </div>
            <div>
                <div class="info-label">Fecha Creación</div>
                <div class="info-valor"><?= date('d/m/Y H:i', strtotime($tabla['fecha_creacion'])) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Alerta de errores -->
<div id="alertaErrores" class="alert alert-danger" style="display: none; margin-bottom: 24px;">
    <i data-lucide="alert-triangle"></i>
    <span id="textoErrores"></span>
</div>

<?php if ($esEnviado): ?>
<!-- Mensaje TXT ya generado (informativo) -->
<div class="alert alert-info" style="margin-bottom: 24px;">
    <i data-lucide="info"></i>
    <span><strong>Nota:</strong> Esta tabla ya generó un archivo TXT. Si realizas cambios, deberás generar el TXT nuevamente.</span>
</div>
<?php endif; ?>

<!-- Card de Alumnos -->
<div class="card">
    <div class="card-header">
        <div class="header-row">
            <h3 class="card-title">
                <i data-lucide="users"></i>
                Alumnos (<span id="conteoTotal">0</span>)
            </h3>
            <div class="header-acciones">
                <div class="buscador-container">
                    <i data-lucide="search" class="buscador-icono"></i>
                    <input type="text" id="inputBuscar" class="form-control buscador-input" 
                           placeholder="Buscar NSS, nombre, CURP..." oninput="filtrarAlumnos()">
                </div>
                <div class="filtros-container">
                    <button class="filtro-btn active" data-filtro="todos" onclick="cambiarFiltro('todos')">Todos</button>
                    <button class="filtro-btn" data-filtro="validos" onclick="cambiarFiltro('validos')"> 🟢 Válidos</button>
                    <button class="filtro-btn" data-filtro="normalizados" onclick="cambiarFiltro('normalizados')">🟡 Normalizados</button>
                    <button class="filtro-btn" data-filtro="errores" onclick="cambiarFiltro('errores')">🔴 Errores</button>
                </div>
                <button class="btn btn-primary" onclick="abrirModalAgregar()">
                    <i data-lucide="plus"></i> Agregar
                </button>
            </div>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table" id="tablaAlumnos">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="width: 130px;">NSS</th>
                        <th>APELLIDO PATERNO</th>
                        <th>APELLIDO MATERNO</th>
                        <th>NOMBRE(S)</th>
                        <th style="width: 180px;">CURP</th>
                        <th style="width: 80px;">ESTADO</th>
                        <th style="width: 100px;">ACCIONES</th>
                    </tr>
                </thead>
                <tbody id="cuerpoTabla">
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
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
            <span id="resumenEstado"></span>
        </div>
        <div id="controlesPaginacion" class="paginacion-controles"></div>
    </div>
</div>

<!-- Acciones -->
<div class="acciones-tabla">
    <button class="btn btn-danger btn-outline" onclick="eliminarTablaCompleta()">
        <i data-lucide="trash-2"></i> Eliminar Tabla
    </button>
    <div style="display: flex; gap: 12px;">
        <a href="<?= URL_BASE ?>views/jefa/resultados.php?id=<?= $idTabla ?>" class="btn btn-outline">
            <i data-lucide="file-text"></i> Ver Resultados
        </a>
        <?php if ($esEnviado): ?>
        <a href="<?= URL_BASE ?>api/exportar-txt.php?action=generar&id_tabla=<?= $idTabla ?>" class="btn btn-outline">
            <i data-lucide="download"></i> Descargar TXT
        </a>
        <?php endif; ?>
        <button class="btn btn-primary" id="btnGenerarTxt" onclick="generarTxt()">
            <i data-lucide="refresh-cw"></i> <?= $esEnviado ? 'Regenerar TXT' : 'Generar TXT' ?>
        </button>
    </div>
</div>

<!-- Modal Visualizar -->
<div class="modal-overlay" id="modalVisualizar">
    <div class="modal" style="max-width: 750px;">
        <div class="modal-header">
            <h3 class="modal-title"><i data-lucide="eye"></i> Detalle del Registro</h3>
            <button class="modal-close" onclick="cerrarModal('modalVisualizar')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
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
            <div class="seccion-modal" id="seccionNormalizacion" style="display: none;">
                <div class="seccion-titulo warning"><i data-lucide="alert-circle"></i> Normalización Aplicada</div>
                <div class="seccion-contenido" id="vizNormalizacion"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="cerrarModal('modalVisualizar')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal-overlay" id="modalEditar">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title"><i data-lucide="pencil"></i> Editar Alumno</h3>
            <button class="modal-close" onclick="cerrarModal('modalEditar')"><i data-lucide="x"></i></button>
        </div>
        <form id="formEditar" onsubmit="guardarEdicion(event)">
            <div class="modal-body">
                <input type="hidden" id="editarId">
                
                <div class="seccion-modal">
                    <div class="seccion-titulo"><i data-lucide="lock"></i> Datos Patronales (solo lectura)</div>
                    <div class="seccion-contenido">
                        <div class="campos-grid">
                            <div class="campo"><span class="campo-label">Registro Patronal</span><div class="campo-valor bloqueado"><?= $datosPatronales['registro_patronal'] ?></div></div>
                            <div class="campo"><span class="campo-label">DV Patronal</span><div class="campo-valor bloqueado"><?= $datosPatronales['digito_verificador_patronal'] ?></div></div>
                            <div class="campo"><span class="campo-label">UMF</span><div class="campo-valor bloqueado"><?= $datosPatronales['umf'] ?></div></div>
                            <div class="campo"><span class="campo-label">Código Op.</span><div class="campo-valor bloqueado"><?= $datosPatronales['codigo_operacion'] ?></div></div>
                            <div class="campo"><span class="campo-label">Fecha Mov.</span><div class="campo-valor bloqueado"><?= $datosPatronales['fecha_movimiento'] ?></div></div>
                            <div class="campo"><span class="campo-label">Número Cuenta</span><div class="campo-valor bloqueado cuenta" id="editarCuenta"></div></div>
                        </div>
                    </div>
                </div>
                
                <div class="seccion-modal">
                    <div class="seccion-titulo"><i data-lucide="edit-3"></i> Datos del Alumno (editables)</div>
                    <div class="seccion-contenido">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">NSS (10 dígitos) *</label>
                                <input type="text" id="editarNss" class="form-control" maxlength="10" pattern="\d{10}" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">DV Alumno (1 dígito) *</label>
                                <input type="text" id="editarDv" class="form-control" maxlength="1" pattern="\d" required>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Apellido Paterno *</label>
                                <input type="text" id="editarApPaterno" class="form-control input-upper" maxlength="27" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Apellido Materno</label>
                                <input type="text" id="editarApMaterno" class="form-control input-upper" maxlength="27">
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Nombre(s) *</label>
                                <input type="text" id="editarNombres" class="form-control input-upper" maxlength="27" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">CURP (18 caracteres)</label>
                                <input type="text" id="editarCurp" class="form-control input-upper" maxlength="18">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="alertaEditar" class="alerta-form" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalEditar')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Agregar CON ESCÁNER -->
<div class="modal-overlay" id="modalAgregar">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i data-lucide="plus"></i> Agregar Alumno</h3>
            <button class="modal-close" onclick="cerrarModal('modalAgregar')"><i data-lucide="x"></i></button>
        </div>
        
        <!-- ESCÁNER FUERA DEL FORM - Usando textarea para evitar submit -->
        <div id="seccionEscaneo" class="escaner-container" style="margin: 0 20px 0 20px; margin-top: 20px;">
            <div class="escaner-header">
                <i data-lucide="scan-line"></i>
                <span>Escanear Tarjeta NSS (Opcional)</span>
            </div>
            <textarea id="campoEscaneo" class="form-control escaner-input" 
                   placeholder="🔳 Haz clic aquí y escanea el código QR..."
                   autocomplete="off" rows="1" 
                   style="resize: none; overflow: hidden; min-height: 42px;"></textarea>
            <small class="escaner-hint">
                Escanea el QR y todos los campos se llenarán automáticamente
            </small>
        </div>
        <!-- FIN ESCÁNER -->
        
        <form id="formAgregar" onsubmit="guardarNuevo(event); return false;">
            <div class="modal-body" style="padding-top: 12px;">
                
            
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">NSS (10 dígitos) *</label>
                        <input type="text" id="agregarNss" class="form-control" maxlength="10" pattern="\d{10}" required placeholder="0000000000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">DV Alumno (1 dígito) *</label>
                        <input type="text" id="agregarDv" class="form-control" maxlength="1" pattern="\d" required placeholder="0">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Apellido Paterno *</label>
                        <input type="text" id="agregarApPaterno" class="form-control input-upper" maxlength="27" required placeholder="APELLIDO PATERNO">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Apellido Materno</label>
                        <input type="text" id="agregarApMaterno" class="form-control input-upper" maxlength="27" placeholder="APELLIDO MATERNO">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nombre(s) *</label>
                        <input type="text" id="agregarNombres" class="form-control input-upper" maxlength="27" required placeholder="NOMBRE(S)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">CURP (18 caracteres)</label>
                        <input type="text" id="agregarCurp" class="form-control input-upper" maxlength="18" placeholder="XXXX000000XXXXXX00">
                    </div>
                </div>
                
                <div id="alertaAgregar" class="alerta-form" style="display: none;"></div>
            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalAgregar')">Cancelar</button>
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-outline" onclick="guardarYOtro()">
                        <i data-lucide="plus"></i> Agregar y otro
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="check"></i> Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.info-label { font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.info-valor { font-weight: 600; font-size: 14px; }

.header-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.header-acciones { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

.buscador-container { position: relative; min-width: 220px; }
.buscador-icono { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted); }
.buscador-input { padding-left: 38px; }

.filtros-container { display: flex; gap: 4px; background: var(--bg-secondary); padding: 4px; border-radius: 8px; }
.filtro-btn { padding: 6px 12px; border: none; background: transparent; border-radius: 6px; font-size: 12px; cursor: pointer; color: var(--text-secondary); transition: all 0.2s; white-space: nowrap; }
.filtro-btn:hover { background: var(--bg-primary); color: var(--text-primary); }
.filtro-btn.active { background: var(--primary); color: white; }

.card-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; padding: 16px 20px; border-top: 1px solid var(--border-color); }
.footer-info { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: var(--text-secondary); }

.paginacion-controles { display: flex; gap: 4px; align-items: center; }
.paginacion-btn { min-width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); background: var(--bg-primary); border-radius: 6px; cursor: pointer; font-size: 13px; color: var(--text-primary); transition: all 0.2s; }
.paginacion-btn:hover { background: var(--bg-secondary); border-color: var(--primary); }
.paginacion-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
.paginacion-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.paginacion-ellipsis { padding: 0 8px; color: var(--text-muted); }

.acciones-tabla { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color); }

.seccion-modal { background: var(--bg-secondary); border-radius: 8px; margin-bottom: 16px; overflow: hidden; }
.seccion-titulo { background: #E2E8F0; padding: 12px 16px; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 8px; }
.seccion-titulo i { width: 16px; height: 16px; }
.seccion-titulo.warning { background: #FEF3C7; color: #92400E; }
.seccion-contenido { padding: 16px; }

.campos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
.campo-label { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
.campo-valor { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px 12px; font-family: 'Courier New', monospace; font-size: 13px; }
.campo-valor.bloqueado { background: #F1F5F9; color: #64748B; }
.campo-valor.cuenta { background: #DBEAFE; border-color: #93C5FD; color: #1E40AF; font-weight: 600; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.input-upper { text-transform: uppercase; }

.info-auto { display: flex; align-items: center; gap: 8px; padding: 12px 16px; background: #DBEAFE; border-radius: 8px; margin-bottom: 20px; color: #1E40AF; font-size: 13px; }
.info-auto i { width: 18px; height: 18px; }

.alerta-form { padding: 12px 16px; border-radius: 8px; margin-top: 16px; display: flex; align-items: center; gap: 8px; }
.alerta-form.error { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
.alerta-form.warning { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }

.fila-error { background: #FEF2F2 !important; }
.fila-normalizado { background: #FFFBEB !important; }

.alert { display: flex; align-items: center; gap: 12px; padding: 16px; border-radius: 8px; }
.alert-danger { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
.alert-success { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }
.alert-info { background: #DBEAFE; color: #1E40AF; border: 1px solid #93C5FD; }
.alert i { flex-shrink: 0; }

/* ESTILOS DEL ESCÁNER */
.escaner-container {
    margin-bottom: 20px;
    padding: 16px;
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
    border-radius: 12px;
    border: 2px dashed #3B82F6;
}
.escaner-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    color: #1D4ED8;
    font-weight: 600;
    font-size: 14px;
}
.escaner-header i {
    width: 20px;
    height: 20px;
}
.escaner-input {
    text-align: center;
    background: white;
    border: 2px solid #93C5FD !important;
    font-size: 14px;
}
.escaner-input:focus {
    border-color: #3B82F6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}
.escaner-hint {
    display: block;
    margin-top: 8px;
    text-align: center;
    color: #6B7280;
    font-size: 12px;
}
.campo-escaneado {
    animation: escaneadoExito 0.5s ease;
}
@keyframes escaneadoExito {
    0% { background-color: #D1FAE5; transform: scale(1.02); }
    100% { background-color: white; transform: scale(1); }
}

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.spin { animation: spin 1s linear infinite; }
</style>

<script>
const ID_TABLA = <?= $idTabla ?>;
const ES_BORRADOR = <?= $esBorrador ? 'true' : 'false' ?>;
const API_URL = '<?= URL_BASE ?>api/tablas.php';
const REGISTROS_POR_PAGINA = 100;

const CONFIG_PATRONAL = {
    registro_patronal: '<?= $datosPatronales['registro_patronal'] ?>',
    dv_patronal: '<?= $datosPatronales['digito_verificador_patronal'] ?>',
    umf: '<?= $datosPatronales['umf'] ?>',
    codigo_operacion: '<?= $datosPatronales['codigo_operacion'] ?>',
    fecha_movimiento: '<?= $datosPatronales['fecha_movimiento'] ?>'
};

let alumnosData = [];
let alumnosFiltrados = [];
let paginaActual = 1;
let filtroActivo = 'todos';

document.addEventListener('DOMContentLoaded', () => {
    cargarAlumnos();
    document.querySelectorAll('.input-upper').forEach(input => {
        input.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
    });
    inicializarEscanerNSS();
});

// Escaner QR


let bufferEscaneo = '';
let timeoutEscaneo = null;

function parsearEscaneoNSS(textoEscaneado) {
    const resultado = {
        nss: '',
        dvAlumno: '',
        apellidoPaterno: '',
        apellidoMaterno: '',
        nombres: '',
        curp: '',
        error: null
    };
    
    try {
        let texto = textoEscaneado.trim();
       
        
        // Extraer NSS - buscar "social:" seguido de 11 dígitos
        const regexNSS = /social[:\s]+(\d{11})/i;
        const matchNSS = texto.match(regexNSS);
        if (matchNSS) {
            const nssCompleto = matchNSS[1];
            resultado.nss = nssCompleto.substring(0, 10);
            resultado.dvAlumno = nssCompleto.substring(10, 11);
            console.log('NSS:', resultado.nss, 'DV:', resultado.dvAlumno);
        }
        
        // Extraer CURP - 18 caracteres alfanuméricos después de "CURP:"
        const regexCURP = /CURP[:\s]+([A-Z0-9]{18})/i;
        const matchCURP = texto.match(regexCURP);
        if (matchCURP) {
            resultado.curp = matchCURP[1].toUpperCase();
            console.log('CURP:', resultado.curp);
        }
        
        // Extraer Nombre - texto después de "Nombre:" hasta el siguiente |
        const regexNombre = /Nombre[:\s]+([^|]+)/i;
        const matchNombre = texto.match(regexNombre);
        if (matchNombre) {
            const nombreCompleto = matchNombre[1].trim().toUpperCase().replace(/\s+/g, ' ');
            const partes = nombreCompleto.split(' ');
            
            
            if (partes.length >= 3 && resultado.curp) {
                // CURP: posición 0 = 1ra letra Ap.Pat, posición 3 = 1ra letra Nombre
                const letraApPat = resultado.curp[0];
                const letraNom = resultado.curp[3];
                
                // Formato esperado: NOMBRE(S) AP_PATERNO AP_MATERNO
                const ultimaPalabra = partes[partes.length - 1];
                const penultimaPalabra = partes[partes.length - 2];
                const primerasPalabras = partes.slice(0, partes.length - 2).join(' ');
                
                if (penultimaPalabra[0] === letraApPat && primerasPalabras[0] === letraNom) {
                    resultado.nombres = primerasPalabras;
                    resultado.apellidoPaterno = penultimaPalabra;
                    resultado.apellidoMaterno = ultimaPalabra;
                } else if (partes[0][0] === letraApPat) {
                    // Formato: AP_PATERNO AP_MATERNO NOMBRE(S)
                    resultado.apellidoPaterno = partes[0];
                    resultado.apellidoMaterno = partes[1];
                    resultado.nombres = partes.slice(2).join(' ');
                } else {
                    // Default: NOMBRE(S) AP_PATERNO AP_MATERNO
                    resultado.nombres = primerasPalabras;
                    resultado.apellidoPaterno = penultimaPalabra;
                    resultado.apellidoMaterno = ultimaPalabra;
                }
            } else if (partes.length >= 3) {
                resultado.nombres = partes.slice(0, partes.length - 2).join(' ');
                resultado.apellidoPaterno = partes[partes.length - 2];
                resultado.apellidoMaterno = partes[partes.length - 1];
            } else if (partes.length === 2) {
                resultado.nombres = partes[0];
                resultado.apellidoPaterno = partes[1];
            }
            
        }
        
    } catch (e) {
        resultado.error = 'Error: ' + e.message;
        console.error(e);
    }
    
    return resultado;
}

function procesarEscaneoNSS(textoEscaneado) {
    const datos = parsearEscaneoNSS(textoEscaneado);
    
    if (datos.error) {
        mostrarNotificacion(datos.error, 'error');
        return;
    }
    
    if (!datos.nss) {
        mostrarNotificacion('🔴 No se pudo extraer el NSS. Intenta de nuevo.', 'error');
        return;
    }
    
    const mapaCampos = {
        'agregarNss': datos.nss,
        'agregarDv': datos.dvAlumno,
        'agregarApPaterno': datos.apellidoPaterno,
        'agregarApMaterno': datos.apellidoMaterno,
        'agregarNombres': datos.nombres,
        'agregarCurp': datos.curp
    };
    
    let camposLlenados = 0;
    
    for (const [id, valor] of Object.entries(mapaCampos)) {
        const elemento = document.getElementById(id);
        if (elemento && valor) {
            elemento.value = valor;
            camposLlenados++;
            
            // Efecto visual
            elemento.style.backgroundColor = '#D1FAE5';
            elemento.style.borderColor = '#22C55E';
            setTimeout(() => {
                elemento.style.backgroundColor = '';
                elemento.style.borderColor = '';
            }, 1500);
        }
    }
    
    if (camposLlenados > 0) {
        mostrarNotificacion(`🟢 ${camposLlenados} Campos llenados. Verifica los datos.`, 'success');
    } else {
        mostrarNotificacion('🔴 No se pudieron extraer datos.', 'error');
    }
}

function procesarBuffer() {
    if (bufferEscaneo.length > 20) {
        console.log('Procesando:', bufferEscaneo);
        procesarEscaneoNSS(bufferEscaneo);
        document.getElementById('campoEscaneo').value = '';
    }
    bufferEscaneo = '';
}

function inicializarEscanerNSS() {
    const campoEscaneo = document.getElementById('campoEscaneo');
    if (!campoEscaneo) return;
    

    
    let detectandoEscaneo = false;
    
    campoEscaneo.addEventListener('input', function(e) {
        if (timeoutEscaneo) clearTimeout(timeoutEscaneo);
        
        bufferEscaneo = e.target.value.replace(/[\r\n]+/g, '');
        
        // Si empieza a llegar texto largo, es el escáner
        if (bufferEscaneo.length > 10 && !detectandoEscaneo) {
            detectandoEscaneo = true;   
        }
        
        // Si termina en || es que ya llegó todo
        if (bufferEscaneo.endsWith('||') && bufferEscaneo.length > 30) {
            // QUITAR FOCO ANTES de procesar para que Enter no haga nada
            document.activeElement.blur();
            procesarBuffer();
            e.target.value = '';
            detectandoEscaneo = false;
            return;
        }
        
        // Timeout corto para procesar
        timeoutEscaneo = setTimeout(() => {
            bufferEscaneo = e.target.value.replace(/[\r\n]+/g, '');
            if (bufferEscaneo.length > 20) {  
                document.activeElement.blur();
                procesarBuffer();
                e.target.value = '';
                detectandoEscaneo = false;
            }
        }, 300);
    });
    
    // Bloquear Enter completamente
    campoEscaneo.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            // Si hay texto, procesarlo
            bufferEscaneo = e.target.value.replace(/[\r\n]+/g, '');
            if (bufferEscaneo.length > 15) {
                document.activeElement.blur();
                procesarBuffer();
                e.target.value = '';
            }
            return false;
        }
    }, true);
    
    campoEscaneo.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return false;
        }
    }, true);
    
    campoEscaneo.addEventListener('paste', function(e) {
        setTimeout(() => {
            bufferEscaneo = e.target.value.replace(/[\r\n]+/g, '');
            if (bufferEscaneo.length > 15) {
                procesarBuffer();
                e.target.value = '';
            }
        }, 100);
    });
}


// FUNCIONES DE LA TABLA


async function cargarAlumnos() {
    const resp = await llamarApi(API_URL + '?action=obtener&id_tabla=' + ID_TABLA);
    if (!resp || !resp.success) {
        document.getElementById('cuerpoTabla').innerHTML = `<tr><td colspan="${ES_BORRADOR ? 8 : 7}" style="text-align:center;padding:40px;color:var(--danger);">Error al cargar</td></tr>`;
        return;
    }
    alumnosData = resp.data.alumnos || [];
    document.getElementById('conteoTotal').textContent = alumnosData.length;
    aplicarFiltros();
    actualizarBotonGenerar();
}

function cambiarFiltro(filtro) {
    filtroActivo = filtro;
    document.querySelectorAll('.filtro-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.filtro === filtro));
    paginaActual = 1;
    aplicarFiltros();
}

function filtrarAlumnos() {
    paginaActual = 1;
    aplicarFiltros();
}

function aplicarFiltros() {
    const busqueda = document.getElementById('inputBuscar').value.toLowerCase().trim();
    alumnosFiltrados = alumnosData.filter(a => {
        if (busqueda) {
            const texto = [a.numero_afiliacion, a.apellido_paterno, a.apellido_materno, a.nombres, a.curp].join(' ').toLowerCase();
            if (!texto.includes(busqueda)) return false;
        }
        const tieneError = a.tiene_errores == 1;
        const normalizado = a.datos_originales != null && !tieneError;
        switch (filtroActivo) {
            case 'validos': return !tieneError && !normalizado;
            case 'normalizados': return normalizado;
            case 'errores': return tieneError;
            default: return true;
        }
    });
    renderizar();
}

function renderizar() {
    const tbody = document.getElementById('cuerpoTabla');
    const total = alumnosFiltrados.length;
    const totalPaginas = Math.ceil(total / REGISTROS_POR_PAGINA);
    const inicio = (paginaActual - 1) * REGISTROS_POR_PAGINA;
    const fin = Math.min(inicio + REGISTROS_POR_PAGINA, total);
    const pagina = alumnosFiltrados.slice(inicio, fin);

    document.getElementById('infoPaginacion').textContent = total > 0 ? `Mostrando ${inicio + 1}-${fin} de ${total}` : 'Sin resultados';
    
    const errores = alumnosData.filter(a => a.tiene_errores == 1).length;
    const normalizados = alumnosData.filter(a => a.datos_originales && a.tiene_errores != 1).length;
    document.getElementById('resumenEstado').innerHTML = `🟢 ${alumnosData.length - errores - normalizados} válidos • 🟡 ${normalizados} normalizados • 🔴 ${errores} errores`;

    const alertaErrores = document.getElementById('alertaErrores');
    if (errores > 0) {
        alertaErrores.style.display = 'flex';
        document.getElementById('textoErrores').textContent = `Hay ${errores} registro(s) con errores. Corrígelos antes de generar el TXT.`;
    } else {
        alertaErrores.style.display = 'none';
    }

    if (pagina.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">Sin resultados</td></tr>';
        document.getElementById('controlesPaginacion').innerHTML = '';
        return;
    }

    let html = '';
    pagina.forEach((a, i) => {
        const tieneError = a.tiene_errores == 1;
        const normalizado = a.datos_originales && !tieneError;
        const clase = tieneError ? 'fila-error' : (normalizado ? 'fila-normalizado' : '');
        const estado = tieneError ? '🔴' : (normalizado ? '🟡' : '🟢');
        
        html += `<tr class="${clase}">
            <td>${inicio + i + 1}</td>
            <td style="font-family:'Courier New',monospace;">${a.numero_afiliacion}-${a.digito_verificador}</td>
            <td>${escapeHtml(a.apellido_paterno) || '---'}</td>
            <td>${escapeHtml(a.apellido_materno) || '---'}</td>
            <td>${escapeHtml(a.nombres) || '---'}</td>
            <td style="font-family:'Courier New',monospace;font-size:12px;">${a.curp || '---'}</td>
            <td style="text-align:center;">${estado}</td>
            <td>
                <div style="display:flex;gap:4px;">
                    <button class="btn btn-ghost btn-sm btn-icon" onclick="visualizar(${a.id_registro})" title="Ver"><i data-lucide="eye"></i></button>
                    <button class="btn btn-ghost btn-sm btn-icon" onclick="editar(${a.id_registro})" title="Editar"><i data-lucide="pencil"></i></button>
                    <button class="btn btn-ghost btn-sm btn-icon text-danger" onclick="eliminar(${a.id_registro})" title="Eliminar"><i data-lucide="trash-2"></i></button>
                </div>
            </td>
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
    const totalPaginas = Math.ceil(alumnosFiltrados.length / REGISTROS_POR_PAGINA);
    if (p < 1 || p > totalPaginas) return;
    paginaActual = p;
    renderizar();
}

function actualizarBotonGenerar() {
    const btn = document.getElementById('btnGenerarTxt');
    if (!btn) return;
    const hayErrores = alumnosData.some(a => a.tiene_errores == 1);
    btn.disabled = hayErrores || alumnosData.length === 0;
}

function visualizar(id) {
    const a = alumnosData.find(x => x.id_registro == id);
    if (!a) return;
    
    document.getElementById('vizRegPatronal').textContent = CONFIG_PATRONAL.registro_patronal;
    document.getElementById('vizDvPatronal').textContent = CONFIG_PATRONAL.dv_patronal;
    document.getElementById('vizUmf').textContent = CONFIG_PATRONAL.umf;
    document.getElementById('vizCodOp').textContent = CONFIG_PATRONAL.codigo_operacion;
    document.getElementById('vizFechaMov').textContent = CONFIG_PATRONAL.fecha_movimiento;
    document.getElementById('vizCuenta').textContent = String(a.numero_cuenta).padStart(10, '0');
    
    document.getElementById('vizNss').textContent = a.numero_afiliacion || '---';
    document.getElementById('vizDvAlumno').textContent = a.digito_verificador || '---';
    document.getElementById('vizApPaterno').textContent = a.apellido_paterno || '---';
    document.getElementById('vizApMaterno').textContent = a.apellido_materno || '---';
    document.getElementById('vizNombres').textContent = a.nombres || '---';
    document.getElementById('vizCurp').textContent = a.curp || '---';
    
    const secNorm = document.getElementById('seccionNormalizacion');
    if (a.datos_originales) {
        let datosOriginales = typeof a.datos_originales === 'string' ? JSON.parse(a.datos_originales) : a.datos_originales;
        secNorm.style.display = 'block';
        let html = '';
        for (const [campo, original] of Object.entries(datosOriginales)) {
            html += `<div><strong>${campo}:</strong> <span style="color:#EF4444;">${escapeHtml(original)}</span> → <span style="color:#22C55E;">${escapeHtml(a[campo])}</span></div>`;
        }
        document.getElementById('vizNormalizacion').innerHTML = html;
    } else {
        secNorm.style.display = 'none';
    }
    
    document.getElementById('modalVisualizar').classList.add('active');
    lucide.createIcons();
}

function editar(id) {
    const a = alumnosData.find(x => x.id_registro == id);
    if (!a) return;
    
    document.getElementById('editarId').value = a.id_registro;
    document.getElementById('editarCuenta').textContent = String(a.numero_cuenta).padStart(10, '0');
    document.getElementById('editarNss').value = a.numero_afiliacion || '';
    document.getElementById('editarDv').value = a.digito_verificador || '';
    document.getElementById('editarApPaterno').value = a.apellido_paterno || '';
    document.getElementById('editarApMaterno').value = a.apellido_materno || '';
    document.getElementById('editarNombres').value = a.nombres || '';
    document.getElementById('editarCurp').value = a.curp || '';
    document.getElementById('alertaEditar').style.display = 'none';
    
    document.getElementById('modalEditar').classList.add('active');
    lucide.createIcons();
}

async function guardarEdicion(e) {
    e.preventDefault();
    const datos = {
        action: 'editar_alumno',
        id_registro: document.getElementById('editarId').value,
        numero_afiliacion: document.getElementById('editarNss').value,
        digito_verificador: document.getElementById('editarDv').value,
        apellido_paterno: document.getElementById('editarApPaterno').value.toUpperCase(),
        apellido_materno: document.getElementById('editarApMaterno').value.toUpperCase(),
        nombres: document.getElementById('editarNombres').value.toUpperCase(),
        curp: document.getElementById('editarCurp').value.toUpperCase()
    };
    
    const resp = await llamarApi(API_URL, { method: 'POST', body: JSON.stringify(datos) });
    if (resp && resp.success) {
        mostrarNotificacion(resp.message);
        cerrarModal('modalEditar');
        cargarAlumnos();
    } else {
        document.getElementById('alertaEditar').className = 'alerta-form error';
        document.getElementById('alertaEditar').innerHTML = `<i data-lucide="alert-circle"></i> ${resp?.message || 'Error al guardar'}`;
        document.getElementById('alertaEditar').style.display = 'flex';
        lucide.createIcons();
    }
}

function abrirModalAgregar() {
    document.getElementById('formAgregar').reset();
    document.getElementById('alertaAgregar').style.display = 'none';
    document.getElementById('campoEscaneo').value = '';
    document.getElementById('modalAgregar').classList.add('active');
    document.getElementById('campoEscaneo').focus();
    lucide.createIcons();
}

async function guardarNuevo(e) {
    e.preventDefault();
    e.stopPropagation();
    await enviarNuevoAlumno(false);
    return false;
}

async function guardarYOtro() {
    await enviarNuevoAlumno(true);
}

async function enviarNuevoAlumno(continuar) {
    const datos = {
        action: 'agregar_alumno',
        id_tabla: ID_TABLA,
        numero_afiliacion: document.getElementById('agregarNss').value,
        digito_verificador: document.getElementById('agregarDv').value,
        apellido_paterno: document.getElementById('agregarApPaterno').value.toUpperCase(),
        apellido_materno: document.getElementById('agregarApMaterno').value.toUpperCase(),
        nombres: document.getElementById('agregarNombres').value.toUpperCase(),
        curp: document.getElementById('agregarCurp').value.toUpperCase()
    };
    
    const resp = await llamarApi(API_URL, { method: 'POST', body: JSON.stringify(datos) });
    if (resp && resp.success) {
        mostrarNotificacion(resp.message);
        if (continuar) {
            document.getElementById('formAgregar').reset();
            document.getElementById('campoEscaneo').value = '';
            document.getElementById('campoEscaneo').focus();
        } else {
            cerrarModal('modalAgregar');
        }
        cargarAlumnos();
    } else {
        document.getElementById('alertaAgregar').className = 'alerta-form error';
        document.getElementById('alertaAgregar').innerHTML = `<i data-lucide="alert-circle"></i> ${resp?.message || 'Error al guardar'}`;
        document.getElementById('alertaAgregar').style.display = 'flex';
        lucide.createIcons();
    }
}

async function eliminar(id) {
    const result = await Swal.fire({
        title: '¿Eliminar alumno?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#EF4444'
    });
    if (!result.isConfirmed) return;
    
    const resp = await llamarApi(API_URL, { method: 'POST', body: JSON.stringify({ action: 'eliminar_alumno', id_registro: id }) });
    if (resp && resp.success) {
        mostrarNotificacion(resp.message);
        cargarAlumnos();
    } else {
        mostrarNotificacion(resp?.message || 'Error al eliminar', 'error');
    }
}

async function generarTxt() {
    const result = await Swal.fire({
        title: '¿Generar archivo TXT?',
        html: `<p>Se generará el archivo TXT con <strong>${alumnosData.length}</strong> alumno(s).</p><p style="color:var(--warning);margin-top:12px;">⚠️ Una vez generado, la tabla no podrá editarse.</p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, generar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#2563EB'
    });
    if (result.isConfirmed) {
        window.location.href = '<?= URL_BASE ?>api/exportar-txt.php?action=generar&id_tabla=' + ID_TABLA;
        setTimeout(() => window.location.reload(), 2000);
    }
}

async function eliminarTablaCompleta() {
    const result = await Swal.fire({
        title: '¿Eliminar tabla completa?',
        html: `<p>Se eliminará la tabla y todos sus alumnos.</p><p style="color:var(--danger);margin-top:12px;">Esta acción no se puede deshacer.</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar todo',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#EF4444'
    });
    if (result.isConfirmed) {
        const resp = await llamarApi(API_URL, { method: 'POST', body: JSON.stringify({ action: 'eliminar_tabla', id_tabla: ID_TABLA }) });
        if (resp && resp.success) {
            window.location.href = '<?= URL_BASE ?>views/jefa/carpetas.php';
        } else {
            mostrarNotificacion(resp?.message || 'Error al eliminar', 'error');
        }
    }
}

function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
function escapeHtml(text) { if (!text) return ''; const d = document.createElement('div'); d.textContent = text; return d.innerHTML; }

// BLOQUEAR TAB Y OTRAS TECLAS PELIGROSAS PARA EL ESCANEO CORRECTO
document.addEventListener('keydown', function(e) {
    const modalAgregar = document.getElementById('modalAgregar');
    
    if (modalAgregar && modalAgregar.classList.contains('active')) {
       
        if (e.key === 'Tab') {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return false;
        }
        
        
        if (e.key === 'Enter' || e.key === 'Backspace' || e.altKey || 
            (e.ctrlKey && e.key !== 'v' && e.key !== 'V' && e.key !== 'c' && e.key !== 'C')) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return false;
        }
    }
}, true);

// También en keypress para doble seguridad
document.addEventListener('keypress', function(e) {
    const modalAgregar = document.getElementById('modalAgregar');
    if (modalAgregar && modalAgregar.classList.contains('active')) {
        if (e.key === 'Tab' || e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }
}, true);

document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') ['modalVisualizar','modalEditar','modalAgregar'].forEach(id => cerrarModal(id)); 
});
['modalVisualizar','modalEditar','modalAgregar'].forEach(id => {
    document.getElementById(id).addEventListener('click', e => { if (e.target === e.currentTarget) cerrarModal(id); });
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>