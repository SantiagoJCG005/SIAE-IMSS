<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - API de Configuracion
 * Permite guardar la configuracion del sistema (general, academico, seguridad, smtp)
 */

// Establece el tipo de respuesta como JSON
header('Content-Type: application/json');

// Carga archivos necesarios
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Verifica que el usuario este logueado
if (!estaLogueado()) {
    respuestaError('No autorizado', 401);
}

// Solo el Superadmin puede modificar la configuracion
if (!tieneRol(ROL_SUPERADMIN)) {
    respuestaError('Sin permisos para esta accion', 403);
}

// Obtiene conexion a la base de datos
$conexion = obtenerConexion();

// Lee los datos enviados en formato JSON
$datosEntrada = json_decode(file_get_contents('php://input'), true);

// Obtiene la accion y el tipo de configuracion
$accion = $datosEntrada['action'] ?? obtenerGet('action', '');
$tipo = $datosEntrada['tipo'] ?? '';

// Ejecuta segun la accion solicitada
switch ($accion) {
    
    // Guardar configuracion
    case 'guardar':
        
        // Valida que el tipo sea valido
        $tiposValidos = ['general', 'academico', 'seguridad', 'smtp'];
        if (!in_array($tipo, $tiposValidos)) {
            respuestaError('Tipo de configuracion no valido: ' . $tipo);
        }
        
        // Obtiene los datos de configuracion
        $datos = $datosEntrada['datos'] ?? [];
        
        if (empty($datos)) {
            respuestaError('No hay datos para guardar');
        }
        
        try {
            // Crea la tabla si no existe
            $conexion->exec("
                CREATE TABLE IF NOT EXISTS configuracion (
                    id_config INT AUTO_INCREMENT PRIMARY KEY,
                    tipo VARCHAR(50) NOT NULL,
                    clave VARCHAR(100) NOT NULL,
                    valor TEXT,
                    fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    id_usuario_modificacion INT,
                    UNIQUE KEY tipo_clave (tipo, clave)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Guarda cada parametro de configuracion
            $guardados = 0;
            foreach ($datos as $clave => $valor) {
                
                // Usa INSERT ... ON DUPLICATE KEY UPDATE para insertar o actualizar
                $sql = "INSERT INTO configuracion (tipo, clave, valor, id_usuario_modificacion, fecha_modificacion) 
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        valor = VALUES(valor), 
                        id_usuario_modificacion = VALUES(id_usuario_modificacion),
                        fecha_modificacion = NOW()";
                
                $consulta = $conexion->prepare($sql);
                $resultado = $consulta->execute([$tipo, $clave, $valor, obtenerIdUsuarioActual()]);
                
                if ($resultado) {
                    $guardados++;
                }
            }
            
            // Registra en bitacora
            registrarEnBitacora('CONFIGURACION_' . strtoupper($tipo), "Configuracion $tipo actualizada ($guardados campos)");
            
            respuestaExitosa(['guardados' => $guardados], 'Configuración guardada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al guardar: ' . $e->getMessage());
        }
        break;
    
    // Obtener configuracion
    case 'obtener':
        
        $tipo = $datosEntrada['tipo'] ?? obtenerGet('tipo', '');
        
        if (empty($tipo)) {
            respuestaError('Tipo de configuracion requerido');
        }
        
        try {
            $consulta = $conexion->prepare("SELECT clave, valor FROM configuracion WHERE tipo = ?");
            $consulta->execute([$tipo]);
            $resultados = $consulta->fetchAll();
            
            // Convierte a objeto clave => valor
            $configuracion = [];
            foreach ($resultados as $fila) {
                $configuracion[$fila['clave']] = $fila['valor'];
            }
            
            respuestaExitosa($configuracion);
            
        } catch (Exception $e) {
            // Si la tabla no existe, devuelve configuracion vacia
            respuestaExitosa([]);
        }
        break;
    
    default:
        respuestaError('Accion no valida', 400);
}
