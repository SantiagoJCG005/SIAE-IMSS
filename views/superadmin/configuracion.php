<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * 
 * SIAE-IMSS - Configuracion del Sistema
 * Permite configurar los parametros del sistema como datos del IMSS,
 * seguridad, correo SMTP y opciones academicas.
 * Solo el Superadmin puede acceder a esta pagina.
 */

// Titulo que aparecera en la pestana del navegador
$tituloPagina = 'Configuración';

// Carga el archivo de autenticacion
require_once __DIR__ . '/../../includes/auth.php';

// Carga el archivo de funciones auxiliares
require_once __DIR__ . '/../../includes/functions.php';

// Verifica que solo el Superadmin pueda acceder
requerirRol([ROL_SUPERADMIN]);

// Establece conexion con la base de datos
$conexion = obtenerConexion();

// Lee cual pestana quiere ver el usuario, por defecto 'general'
$pestana = obtenerGet('tab', 'general');

// Arreglo donde guardaremos la configuracion patronal del IMSS
$configuracionPatronal = [];

// Intenta obtener la configuracion patronal de la base de datos
try {
    // Consulta la configuracion activa (solo debe haber una)
    $consulta = $conexion->query("SELECT * FROM configuracion_patronal WHERE activo = 1 LIMIT 1");
    
    // Si hay resultado lo guarda, si no queda como arreglo vacio
    $configuracionPatronal = $consulta->fetch() ?: [];
} catch (Exception $e) {
    // Si la tabla no existe aun, ignora el error
}

// Variables para mostrar mensajes al usuario
$mensaje = '';
$tipoMensaje = '';

// Verifica si se envio un formulario (metodo POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obtiene de que pestana viene el formulario
    $postTab = obtenerPost('tab');
    
    // Si el formulario es de la pestana IMSS
    if ($postTab === 'imss') {
        
        // Recolecta todos los datos del formulario en un arreglo
        $datos = [
            'registro_patronal' => post('registro_patronal'),
            'digito_verificador' => post('digito_verificador'),
            'umf_alta' => post('umf_alta'),
            'umf_baja' => post('umf_baja'),
            'codigo_operacion_alta' => post('codigo_operacion_alta'),
            'codigo_operacion_baja' => post('codigo_operacion_baja'),
            'prefijo_alta' => post('prefijo_alta'),
            'prefijo_baja' => post('prefijo_baja'),
            'codigo_institucion' => post('codigo_institucion')
        ];
        
        try {
            // Si ya existe una configuracion, la actualiza
            if (!empty($configuracionPatronal['id_config'])) {
                
                // Consulta UPDATE para modificar el registro existente
                $sql = "UPDATE configuracion_patronal SET 
                        registro_patronal = ?, digito_verificador = ?, umf_alta = ?, umf_baja = ?,
                        codigo_operacion_alta = ?, codigo_operacion_baja = ?, prefijo_alta = ?,
                        prefijo_baja = ?, codigo_institucion = ?, fecha_modificacion = NOW(),
                        id_usuario_modificacion = ?
                        WHERE id_config = ?";
                
                // Prepara y ejecuta la consulta con los valores
                $consulta = $conexion->prepare($sql);
                $consulta->execute([
                    $datos['registro_patronal'], $datos['digito_verificador'], $datos['umf_alta'],
                    $datos['umf_baja'], $datos['codigo_operacion_alta'], $datos['codigo_operacion_baja'],
                    $datos['prefijo_alta'], $datos['prefijo_baja'], $datos['codigo_institucion'],
                    obtenerIdUsuarioActual(), $configuracionPatronal['id_config']
                ]);
            } else {
                // Si no existe configuracion, crea una nueva
                $sql = "INSERT INTO configuracion_patronal 
                        (registro_patronal, digito_verificador, umf_alta, umf_baja, codigo_operacion_alta,
                         codigo_operacion_baja, prefijo_alta, prefijo_baja, codigo_institucion, activo,
                         fecha_modificacion, id_usuario_modificacion)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)";
                
                $consulta = $conexion->prepare($sql);
                $consulta->execute([
                    $datos['registro_patronal'], $datos['digito_verificador'], $datos['umf_alta'],
                    $datos['umf_baja'], $datos['codigo_operacion_alta'], $datos['codigo_operacion_baja'],
                    $datos['prefijo_alta'], $datos['prefijo_baja'], $datos['codigo_institucion'],
                    obtenerIdUsuarioActual()
                ]);
            }
            
            // Registra la accion en la bitacora
            registrarEnBitacora('CONFIGURACION_IMSS', 'Parámetros IMSS actualizados');
            
            // Prepara el mensaje de exito
            $mensaje = 'Configuración guardada correctamente';
            $tipoMensaje = 'success';
            
            // Recarga los datos para mostrar los valores actualizados
            $consulta = $conexion->query("SELECT * FROM configuracion_patronal WHERE activo = 1 LIMIT 1");
            $configuracionPatronal = $consulta->fetch() ?: [];
            
        } catch (Exception $e) {
            // Si hay error, prepara el mensaje de error
            $mensaje = 'Error al guardar: ' . $e->getMessage();
            $tipoMensaje = 'error';
        }
    }
}

// Incluye el header de la pagina
include __DIR__ . '/../layouts/header.php';

// Incluye el menu lateral del superadmin
include __DIR__ . '/../layouts/sidebar-superadmin.php';
?>

<div class="page-header">
    <h1 class="page-title">Configuración del Sistema</h1>
    <p class="page-subtitle">Parámetros generales, académicos y de exportación IMSS.</p>
</div>

<?php // Si hay un mensaje (exito o error), lo muestra ?>
<?php if ($mensaje): ?>
<div class="alert alert-<?= $tipoMensaje ?>">
    <?php // Muestra icono de check si es exito, o alerta si es error ?>
    <i data-lucide="<?= $tipoMensaje === 'success' ? 'check-circle' : 'alert-circle' ?>" class="alert-icon"></i>
    <div class="alert-content">
        <div class="alert-message"><?= htmlspecialchars($mensaje) ?></div>
    </div>
</div>
<?php endif; ?>

<div class="tabs">
    <?php // Cada pestana verifica si es la actual para agregar la clase 'active' ?>
    <a href="?tab=general" class="tab <?= $pestana === 'general' ? 'active' : '' ?>">
        <i data-lucide="settings" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        General
    </a>
    <a href="?tab=academico" class="tab <?= $pestana === 'academico' ? 'active' : '' ?>">
        <i data-lucide="graduation-cap" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Parámetros Académicos
    </a>
    <a href="?tab=imss" class="tab <?= $pestana === 'imss' ? 'active' : '' ?>">
        <i data-lucide="file-text" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Exportación IMSS
    </a>
    <a href="?tab=seguridad" class="tab <?= $pestana === 'seguridad' ? 'active' : '' ?>">
        <i data-lucide="shield" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Seguridad
    </a>
    <a href="?tab=smtp" class="tab <?= $pestana === 'smtp' ? 'active' : '' ?>">
        <i data-lucide="mail" style="width: 16px; height: 16px; margin-right: 6px;"></i>
        Correo SMTP
    </a>
</div>

<?php // Si la pestana activa es 'general' ?>
<?php if ($pestana === 'general'): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Configuración General</h3>
    </div>
    <div class="card-body">
        <form>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="form-group">
                    <label class="form-label">Nombre del Sistema</label>
                    <input type="text" class="form-control" value="SIAE-IMSS" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Versión</label>
                    <?php // SYSTEM_VERSION es una constante definida en config.php ?>
                    <input type="text" class="form-control" value="<?= SYSTEM_VERSION ?>" disabled>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre de la Institución</label>
                <input type="text" class="form-control" value="Instituto Tecnológico" placeholder="Nombre de tu institución">
            </div>
            <div class="form-group">
                <label class="form-label">Zona Horaria</label>
                <select class="form-control form-select">
                    <option value="America/Mexico_City" selected>Ciudad de México (UTC-6)</option>
                    <option value="America/Tijuana">Tijuana (UTC-8)</option>
                    <option value="America/Cancun">Cancún (UTC-5)</option>
                </select>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary">
            <i data-lucide="save"></i>
            Guardar cambios
        </button>
    </div>
</div>

<?php // Si la pestana activa es 'academico' ?>
<?php elseif ($pestana === 'academico'): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Parámetros Académicos</h3>
    </div>
    <div class="card-body">
        <form>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="form-group">
                    <label class="form-label">Periodo Escolar Activo</label>
                    <select class="form-control form-select">
                        <option>Enero-Junio 2025</option>
                        <option>Agosto-Diciembre 2025</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Formato Número de Control</label>
                    <input type="text" class="form-control" value="AAXXNNNN" placeholder="Ej: 25010001">
                    <small class="form-text">AA=Año, XX=Carrera, NNNN=Consecutivo</small>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Días de gracia para validación</label>
                <input type="number" class="form-control" value="5" min="1" max="30" style="max-width: 150px;">
                <small class="form-text">Días que tiene la Jefa de SE para validar movimientos</small>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary">
            <i data-lucide="save"></i>
            Guardar cambios
        </button>
    </div>
</div>

<?php // Si la pestana activa es 'imss' (la mas importante) ?>
<?php elseif ($pestana === 'imss'): ?>
<form method="POST">
    <?php // Campo oculto para saber de que pestana viene el formulario ?>
    <input type="hidden" name="tab" value="imss">
    
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="building" style="width: 18px; height: 18px; margin-right: 8px;"></i>
                Datos Patronales IMSS
            </h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info" style="margin-bottom: 24px;">
                <i data-lucide="info" class="alert-icon"></i>
                <div class="alert-content">
                    <div class="alert-title">Importante</div>
                    <div class="alert-message">Estos valores se utilizan para generar el archivo TXT de 168 caracteres que se envía al IMSS. Verifique que sean correctos.</div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="form-group">
                    <label class="form-label">Registro Patronal *</label>
                    <?php // Usa el valor de la BD o la constante por defecto ?>
                    <input type="text" name="registro_patronal" class="form-control" 
                           value="<?= htmlspecialchars($configuracionPatronal['registro_patronal'] ?? REGISTRO_PATRONAL) ?>" 
                           maxlength="15" required>
                    <small class="form-text">Ejemplo: E292977432</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Dígito Verificador *</label>
                    <input type="text" name="digito_verificador" class="form-control" 
                           value="<?= htmlspecialchars($configuracionPatronal['digito_verificador'] ?? DIGITO_VERIFICADOR) ?>" 
                           maxlength="1" required style="max-width: 80px;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 24px;">
                <div class="form-group">
                    <label class="form-label">UMF Alta</label>
                    <input type="text" name="umf_alta" class="form-control" 
                           value="<?= htmlspecialchars($configuracionPatronal['umf_alta'] ?? UMF_ALTA) ?>" 
                           maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">UMF Baja</label>
                    <input type="text" name="umf_baja" class="form-control" 
                           value="<?= htmlspecialchars($configuracionPatronal['umf_baja'] ?? UMF_BAJA) ?>" 
                           maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">Código Op. Alta</label>
                    <input type="text" name="codigo_operacion_alta" class="form-control" 
                           value="<?= htmlspecialchars($configuracionPatronal['codigo_operacion_alta'] ?? CODIGO_OPERACION_ALTA) ?>" 
                           maxlength="5">
                </div>
                <div class="form-group">
                    <label class="form-label">Código Op. Baja</label>
                    <input type="text" name="codigo_operacion_baja" class="form-control" 
                           value="<?= htmlspecialchars($configuracionPatronal['codigo_operacion_baja'] ?? CODIGO_OPERACION_BAJA) ?>" 
                           maxlength="5">
                </div>
            </div>
        </div>
    </div>
    
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="file-code" style="width: 18px; height: 18px; margin-right: 8px;"></i>
                Formato de Línea TXT (168 caracteres)
            </h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="form-group">
                    <label class="form-label">Prefijo Alta (zona de control)</label>
                    <input type="text" name="prefijo_alta" class="form-control" 
                           value="<?= htmlspecialchars($configuracionPatronal['prefijo_alta'] ?? PREFIJO_ALTA) ?>" 
                           maxlength="20">
                    <small class="form-text">Caracteres fijos después de fecha en altas</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Prefijo Baja (zona de control)</label>
                    <input type="text" name="prefijo_baja" class="form-control" 
                           value="<?= htmlspecialchars($configuracionPatronal['prefijo_baja'] ?? PREFIJO_BAJA) ?>" 
                           maxlength="20">
                    <small class="form-text">Caracteres fijos después de fecha en bajas</small>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Código de Institución</label>
                <input type="text" name="codigo_institucion" class="form-control" 
                       value="<?= htmlspecialchars($configuracionPatronal['codigo_institucion'] ?? CODIGO_INSTITUCION) ?>" 
                       maxlength="10" style="max-width: 150px;">
            </div>
            
            <div style="margin-top: 24px; padding: 16px; background: #1E293B; border-radius: 8px;">
                <label style="display: block; color: #94A3B8; font-size: 12px; margin-bottom: 8px;">Vista previa de línea TXT (ejemplo):</label>
                <?php // Genera una linea de ejemplo usando las constantes del sistema ?>
                <code style="color: #22C55E; font-family: monospace; font-size: 12px; word-break: break-all; display: block;">
                    <?= REGISTRO_PATRONAL . DIGITO_VERIFICADOR ?>12345678901GARCIA LOPEZ                 MARTINEZ HERNANDEZ           JUAN CARLOS                  08<?= date('dmY') ?>000000      210<?= CODIGO_INSTITUCION ?>001...
                </code>
                <small style="color: #64748B; display: block; margin-top: 8px;">Total: 168 caracteres por línea</small>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="type" style="width: 18px; height: 18px; margin-right: 8px;"></i>
                Conversión de Caracteres
            </h3>
        </div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                El IMSS no acepta caracteres especiales. El sistema convierte automáticamente:
            </p>
            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 12px;">
                <div style="text-align: center; padding: 12px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 20px; margin-bottom: 4px;">Ñ</div>
                    <div style="font-size: 12px; color: var(--text-muted);">→ N</div>
                </div>
                <div style="text-align: center; padding: 12px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 20px; margin-bottom: 4px;">Á</div>
                    <div style="font-size: 12px; color: var(--text-muted);">→ A</div>
                </div>
                <div style="text-align: center; padding: 12px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 20px; margin-bottom: 4px;">É</div>
                    <div style="font-size: 12px; color: var(--text-muted);">→ E</div>
                </div>
                <div style="text-align: center; padding: 12px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 20px; margin-bottom: 4px;">Í</div>
                    <div style="font-size: 12px; color: var(--text-muted);">→ I</div>
                </div>
                <div style="text-align: center; padding: 12px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 20px; margin-bottom: 4px;">Ó</div>
                    <div style="font-size: 12px; color: var(--text-muted);">→ O</div>
                </div>
                <div style="text-align: center; padding: 12px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 20px; margin-bottom: 4px;">Ú</div>
                    <div style="font-size: 12px; color: var(--text-muted);">→ U</div>
                </div>
                <div style="text-align: center; padding: 12px; background: var(--bg-secondary); border-radius: 8px;">
                    <div style="font-size: 20px; margin-bottom: 4px;">Ü</div>
                    <div style="font-size: 12px; color: var(--text-muted);">→ U</div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save"></i>
                Guardar configuración IMSS
            </button>
        </div>
    </div>
</form>

<?php // Si la pestana activa es 'seguridad' ?>
<?php elseif ($pestana === 'seguridad'): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Configuración de Seguridad</h3>
    </div>
    <div class="card-body">
        <form>
            <div class="form-group">
                <label class="form-label">Duración de sesión (minutos)</label>
                <input type="number" class="form-control" value="60" min="15" max="480" style="max-width: 150px;">
            </div>
            <div class="form-group">
                <label class="form-label">Intentos de login fallidos antes de bloqueo</label>
                <input type="number" class="form-control" value="5" min="3" max="10" style="max-width: 150px;">
            </div>
            <div class="form-group">
                <label class="form-label">Tiempo de bloqueo (minutos)</label>
                <input type="number" class="form-control" value="30" min="5" max="120" style="max-width: 150px;">
            </div>
            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" checked style="margin-right: 8px;">
                    Forzar cambio de contraseña cada 90 días
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" checked style="margin-right: 8px;">
                    Requerir contraseña fuerte (mayúsculas, números, símbolos)
                </label>
            </div>
        </form>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary">
            <i data-lucide="save"></i>
            Guardar cambios
        </button>
    </div>
</div>

<?php // Si la pestana activa es 'smtp' (correo electronico) ?>
<?php elseif ($pestana === 'smtp'): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Configuración de Correo SMTP</h3>
    </div>
    <div class="card-body">
        <form>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                <div class="form-group">
                    <label class="form-label">Servidor SMTP</label>
                    <input type="text" class="form-control" placeholder="smtp.gmail.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Puerto</label>
                    <input type="number" class="form-control" value="587">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <input type="text" class="form-control" placeholder="correo@institucion.edu.mx">
                </div>
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <input type="password" class="form-control" placeholder="••••••••">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Encriptación</label>
                <select class="form-control form-select" style="max-width: 200px;">
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="none">Ninguna</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Email remitente</label>
                <input type="email" class="form-control" placeholder="noreply@institucion.edu.mx">
            </div>
            <div class="form-group">
                <label class="form-label">Nombre remitente</label>
                <input type="text" class="form-control" placeholder="SIAE-IMSS Sistema">
            </div>
        </form>
    </div>
    <div class="card-footer" style="display: flex; gap: 12px;">
        <button class="btn btn-outline" onclick="probarCorreo()">
            <i data-lucide="send"></i>
            Enviar correo de prueba
        </button>
        <button class="btn btn-primary">
            <i data-lucide="save"></i>
            Guardar cambios
        </button>
    </div>
</div>
<?php endif; ?>

<script>
// Funcion que muestra un dialogo para enviar un correo de prueba
// Usa SweetAlert2 para mostrar un campo de entrada de email
function probarCorreo() {
    Swal.fire({
        title: 'Enviar correo de prueba',
        input: 'email',                           // Tipo de campo: email
        inputLabel: 'Ingresa el email de destino',
        inputPlaceholder: 'correo@ejemplo.com',
        showCancelButton: true,                   // Muestra boton cancelar
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        // Si el usuario confirmo y escribio un email
        if (result.isConfirmed) {
            // Muestra notificacion de exito
            mostrarNotificacion('Correo de prueba enviado a: ' + result.value);
        }
    });
}
</script>

<?php // Incluye el footer ?>
<?php include __DIR__ . '/../layouts/footer.php'; ?>