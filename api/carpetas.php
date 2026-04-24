<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - API de Carpetas
 * CRUD para carpetas y subcarpetas del sistema de movimientos IMSS
 */

// Respuesta en formato JSON
header('Content-Type: application/json');

// Carga archivos necesarios
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Verifica autenticacion
if (!estaLogueado()) {
    respuestaError('No autorizado', 401);
}

// Solo Jefa de Servicios y Admin Servicios pueden usar esta API
if (!tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
    respuestaError('Sin permisos para esta accion', 403);
}

// Conexion a la base de datos
$conexion = obtenerConexion();

// Lee datos JSON enviados
$datosEntrada = json_decode(file_get_contents('php://input'), true);

// Obtiene la accion a realizar
$accion = $datosEntrada['action'] ?? obtenerGet('action', '');

// Ejecuta segun la accion
switch ($accion) {
    
    // ========================================
    // CARPETAS PRINCIPALES
    // ========================================
    
    // Listar todas las carpetas con sus subcarpetas y tablas
    case 'listar':
        
        try {
            $condicionAdicional = "";
            $parametros = [];
            
            // Si es Admin SE y NO es Jefa ni Superadmin, solo ve lo suyo
            if (tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
                $condicionAdicional = " AND c.id_usuario_creacion = ? ";
                $parametros[] = obtenerIdUsuarioActual();
            }

            // Obtiene carpetas activas
            $sqlCarpetas = "
                SELECT c.*, u.nombre_completo as creador, r.nombre as creador_rol,
                       (SELECT COUNT(*) FROM subcarpetas_imss s WHERE s.id_carpeta = c.id_carpeta AND s.activo = 1) as total_subcarpetas
                FROM carpetas_imss c
                LEFT JOIN usuarios u ON c.id_usuario_creacion = u.id_usuario
                LEFT JOIN roles r ON u.id_rol = r.id_rol
                WHERE c.activo = 1 $condicionAdicional
                ORDER BY c.fecha_creacion DESC
            ";
            $consulta = $conexion->prepare($sqlCarpetas);
            $consulta->execute($parametros);
            $carpetas = $consulta->fetchAll();
            
            // Para cada carpeta, obtiene sus subcarpetas
            foreach ($carpetas as &$carpeta) {
                $sqlSubcarpetas = "
                    SELECT s.*,
                           (SELECT COUNT(*) FROM tablas_movimientos t WHERE t.id_subcarpeta = s.id_subcarpeta) as total_tablas
                    FROM subcarpetas_imss s
                    WHERE s.id_carpeta = ? AND s.activo = 1
                    ORDER BY s.fecha_creacion DESC
                ";
                $consulta = $conexion->prepare($sqlSubcarpetas);
                $consulta->execute([$carpeta['id_carpeta']]);
                $carpeta['subcarpetas'] = $consulta->fetchAll();
                
                // Para cada subcarpeta, obtiene sus tablas
                foreach ($carpeta['subcarpetas'] as &$subcarpeta) {
                    $sqlTablas = "
                        SELECT t.*, 
                               (SELECT COUNT(*) FROM tabla_alumnos a WHERE a.id_tabla = t.id_tabla) as total_alumnos,
                               (SELECT COUNT(*) FROM tabla_alumnos a WHERE a.id_tabla = t.id_tabla AND a.tiene_errores = 1) as alumnos_con_errores
                        FROM tablas_movimientos t
                        WHERE t.id_subcarpeta = ?
                        ORDER BY t.fecha_creacion DESC
                    ";
                    $consulta = $conexion->prepare($sqlTablas);
                    $consulta->execute([$subcarpeta['id_subcarpeta']]);
                    $subcarpeta['tablas'] = $consulta->fetchAll();
                }
            }
            
            respuestaExitosa($carpetas);
            
        } catch (Exception $e) {
            respuestaError('Error al obtener carpetas: ' . $e->getMessage());
        }
        break;
    
    // Crear nueva carpeta
    case 'crear_carpeta':
        
        $nombre = trim($datosEntrada['nombre'] ?? '');
        $descripcion = trim($datosEntrada['descripcion'] ?? '');
        
        // Validaciones
        if (empty($nombre)) {
            respuestaError('El nombre de la carpeta es obligatorio');
        }
        
        if (strlen($nombre) > 100) {
            respuestaError('El nombre no puede exceder 100 caracteres');
        }
        
        // Verifica que no exista otra carpeta con el mismo nombre
        $consulta = $conexion->prepare("SELECT id_carpeta FROM carpetas_imss WHERE nombre = ? AND activo = 1");
        $consulta->execute([$nombre]);
        if ($consulta->fetch()) {
            respuestaError('Ya existe una carpeta con ese nombre');
        }
        
        try {
            $consulta = $conexion->prepare("
                INSERT INTO carpetas_imss (nombre, descripcion, id_usuario_creacion)
                VALUES (?, ?, ?)
            ");
            $consulta->execute([$nombre, $descripcion, obtenerIdUsuarioActual()]);
            
            $idCarpeta = $conexion->lastInsertId();
            
            registrarEnBitacora('CREAR_CARPETA', "Carpeta creada: $nombre (ID: $idCarpeta)");
            
            respuestaExitosa(['id_carpeta' => $idCarpeta], 'Carpeta creada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al crear carpeta: ' . $e->getMessage());
        }
        break;
    
    // Editar carpeta existente
    case 'editar_carpeta':
        
        $idCarpeta = intval($datosEntrada['id_carpeta'] ?? 0);
        $nombre = trim($datosEntrada['nombre'] ?? '');
        $descripcion = trim($datosEntrada['descripcion'] ?? '');
        
        if ($idCarpeta <= 0) {
            respuestaError('ID de carpeta no valido');
        }
        
        if (empty($nombre)) {
            respuestaError('El nombre de la carpeta es obligatorio');
        }
        
        // Verifica que no exista otra carpeta con el mismo nombre
        $consulta = $conexion->prepare("SELECT id_carpeta FROM carpetas_imss WHERE nombre = ? AND id_carpeta != ? AND activo = 1");
        $consulta->execute([$nombre, $idCarpeta]);
        if ($consulta->fetch()) {
            respuestaError('Ya existe otra carpeta con ese nombre');
        }
        
        try {
            $consulta = $conexion->prepare("UPDATE carpetas_imss SET nombre = ?, descripcion = ? WHERE id_carpeta = ?");
            $consulta->execute([$nombre, $descripcion, $idCarpeta]);
            
            registrarEnBitacora('EDITAR_CARPETA', "Carpeta editada ID: $idCarpeta");
            
            respuestaExitosa(null, 'Carpeta actualizada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al actualizar carpeta: ' . $e->getMessage());
        }
        break;
    
    // Eliminar carpeta (soft delete)
    case 'eliminar_carpeta':
        
        $idCarpeta = intval($datosEntrada['id_carpeta'] ?? 0);
        
        if ($idCarpeta <= 0) {
            respuestaError('ID de carpeta no valido');
        }
        
        // Verifica si tiene tablas con estado 'enviado'
        $consulta = $conexion->prepare("
            SELECT COUNT(*) as total 
            FROM tablas_movimientos t
            INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
            WHERE s.id_carpeta = ? AND t.estado = 'enviado'
        ");
        $consulta->execute([$idCarpeta]);
        $tablasEnviadas = $consulta->fetch()['total'];
        
       if ($tablasEnviadas > 0) {
            respuestaError("No se puede eliminar: hay $tablasEnviadas tabla(s) con TXT generado");
        }
        
        try {
            // Soft delete de la carpeta
            $consulta = $conexion->prepare("UPDATE carpetas_imss SET activo = 0 WHERE id_carpeta = ?");
            $consulta->execute([$idCarpeta]);
            
            // Tambien desactiva subcarpetas
            $consulta = $conexion->prepare("UPDATE subcarpetas_imss SET activo = 0 WHERE id_carpeta = ?");
            $consulta->execute([$idCarpeta]);
            
            registrarEnBitacora('ELIMINAR_CARPETA', "Carpeta eliminada ID: $idCarpeta");
            
            respuestaExitosa(null, 'Carpeta eliminada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al eliminar carpeta: ' . $e->getMessage());
        }
        break;
    
  
    // SUBCARPETAS
    
    // Crear nueva subcarpeta
    case 'crear_subcarpeta':
        
        $idCarpeta = intval($datosEntrada['id_carpeta'] ?? 0);
        $nombre = trim($datosEntrada['nombre'] ?? '');
        $descripcion = trim($datosEntrada['descripcion'] ?? '');
        
        if ($idCarpeta <= 0) {
            respuestaError('Debe seleccionar una carpeta');
        }
        
        if (empty($nombre)) {
            respuestaError('El nombre de la subcarpeta es obligatorio');
        }
        
        // Verifica que la carpeta exista
        $consulta = $conexion->prepare("SELECT id_carpeta FROM carpetas_imss WHERE id_carpeta = ? AND activo = 1");
        $consulta->execute([$idCarpeta]);
        if (!$consulta->fetch()) {
            respuestaError('La carpeta seleccionada no existe');
        }
        
        // Verifica que no exista otra subcarpeta con el mismo nombre en la misma carpeta
        $consulta = $conexion->prepare("SELECT id_subcarpeta FROM subcarpetas_imss WHERE nombre = ? AND id_carpeta = ? AND activo = 1");
        $consulta->execute([$nombre, $idCarpeta]);
        if ($consulta->fetch()) {
            respuestaError('Ya existe una subcarpeta con ese nombre en esta carpeta');
        }
        
        try {
            $consulta = $conexion->prepare("
                INSERT INTO subcarpetas_imss (id_carpeta, nombre, descripcion)
                VALUES (?, ?, ?)
            ");
            $consulta->execute([$idCarpeta, $nombre, $descripcion]);
            
            $idSubcarpeta = $conexion->lastInsertId();
            
            registrarEnBitacora('CREAR_SUBCARPETA', "Subcarpeta creada: $nombre (ID: $idSubcarpeta)");
            
            respuestaExitosa(['id_subcarpeta' => $idSubcarpeta], 'Subcarpeta creada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al crear subcarpeta: ' . $e->getMessage());
        }
        break;
    
    // Editar subcarpeta
    case 'editar_subcarpeta':
        
        $idSubcarpeta = intval($datosEntrada['id_subcarpeta'] ?? 0);
        $nombre = trim($datosEntrada['nombre'] ?? '');
        $descripcion = trim($datosEntrada['descripcion'] ?? '');
        
        if ($idSubcarpeta <= 0) {
            respuestaError('ID de subcarpeta no valido');
        }
        
        if (empty($nombre)) {
            respuestaError('El nombre de la subcarpeta es obligatorio');
        }
        
        // Obtiene la carpeta padre
        $consulta = $conexion->prepare("SELECT id_carpeta FROM subcarpetas_imss WHERE id_subcarpeta = ?");
        $consulta->execute([$idSubcarpeta]);
        $subcarpeta = $consulta->fetch();
        
        if (!$subcarpeta) {
            respuestaError('Subcarpeta no encontrada');
        }
        
        // Verifica nombre duplicado
        $consulta = $conexion->prepare("SELECT id_subcarpeta FROM subcarpetas_imss WHERE nombre = ? AND id_carpeta = ? AND id_subcarpeta != ? AND activo = 1");
        $consulta->execute([$nombre, $subcarpeta['id_carpeta'], $idSubcarpeta]);
        if ($consulta->fetch()) {
            respuestaError('Ya existe otra subcarpeta con ese nombre');
        }
        
        try {
            $consulta = $conexion->prepare("UPDATE subcarpetas_imss SET nombre = ?, descripcion = ? WHERE id_subcarpeta = ?");
            $consulta->execute([$nombre, $descripcion, $idSubcarpeta]);
            
            registrarEnBitacora('EDITAR_SUBCARPETA', "Subcarpeta editada ID: $idSubcarpeta");
            
            respuestaExitosa(null, 'Subcarpeta actualizada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al actualizar subcarpeta: ' . $e->getMessage());
        }
        break;
    
    // Eliminar subcarpeta
    case 'eliminar_subcarpeta':
        
        $idSubcarpeta = intval($datosEntrada['id_subcarpeta'] ?? 0);
        
        if ($idSubcarpeta <= 0) {
            respuestaError('ID de subcarpeta no valido');
        }
        
        // Verifica si tiene tablas enviadas
        $consulta = $conexion->prepare("SELECT COUNT(*) as total FROM tablas_movimientos WHERE id_subcarpeta = ? AND estado = 'enviado'");
        $consulta->execute([$idSubcarpeta]);
        $tablasEnviadas = $consulta->fetch()['total'];
        
        if ($tablasEnviadas > 0) {
            respuestaError("No se puede eliminar: hay $tablasEnviadas tabla(s) con TXT generado");
        }
        
        try {
            $consulta = $conexion->prepare("UPDATE subcarpetas_imss SET activo = 0 WHERE id_subcarpeta = ?");
            $consulta->execute([$idSubcarpeta]);
            
            registrarEnBitacora('ELIMINAR_SUBCARPETA', "Subcarpeta eliminada ID: $idSubcarpeta");
            
            respuestaExitosa(null, 'Subcarpeta eliminada correctamente');
            
        } catch (Exception $e) {
            respuestaError('Error al eliminar subcarpeta: ' . $e->getMessage());
        }
        break;
    
    // Obtener carpetas para select (lista simple)
    case 'listar_simple':
        
        try {
            $condicionAdicional = "";
            $parametros = [];
            
            if (tieneRol(ROL_ADMIN_SERVICIOS) && !tieneRol(ROL_JEFA_SERVICIOS) && !tieneRol(ROL_SUPERADMIN)) {
                $condicionAdicional = " AND id_usuario_creacion = ? ";
                $parametros[] = obtenerIdUsuarioActual();
            }

            $consulta = $conexion->prepare("
                SELECT id_carpeta, nombre FROM carpetas_imss WHERE activo = 1 $condicionAdicional ORDER BY nombre
            ");
            $consulta->execute($parametros);
            $carpetas = $consulta->fetchAll();
            
            respuestaExitosa($carpetas);
            
        } catch (Exception $e) {
            respuestaError('Error al obtener carpetas');
        }
        break;
    
    // Obtener subcarpetas de una carpeta
    case 'listar_subcarpetas':
        
        $idCarpeta = intval($datosEntrada['id_carpeta'] ?? obtenerGet('id_carpeta', 0));
        
        if ($idCarpeta <= 0) {
            respuestaError('ID de carpeta no valido');
        }
        
        try {
            $consulta = $conexion->prepare("
                SELECT id_subcarpeta, nombre FROM subcarpetas_imss WHERE id_carpeta = ? AND activo = 1 ORDER BY nombre
            ");
            $consulta->execute([$idCarpeta]);
            
            respuestaExitosa($consulta->fetchAll());
            
        } catch (Exception $e) {
            respuestaError('Error al obtener subcarpetas');
        }
        break;
    
    default:
        respuestaError('Accion no valida', 400);
}
