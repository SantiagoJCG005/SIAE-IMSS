<?php
/**
 * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - API de Roles
 * Permite crear, editar y eliminar roles del sistema
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

// Solo el Superadmin puede gestionar roles
if (!tieneRol(ROL_SUPERADMIN)) {
    respuestaError('Sin permisos para esta accion', 403);
}

// Obtiene conexion a la base de datos
$conexion = obtenerConexion();

// Verifica si existe el campo permisos en la tabla roles
// Si no existe, lo crea
try {
    $columnas = $conexion->query("SHOW COLUMNS FROM roles LIKE 'permisos'")->fetch();
    if (!$columnas) {
        $conexion->exec("ALTER TABLE roles ADD COLUMN permisos JSON AFTER descripcion");
    }
} catch (Exception $e) {
    // Si falla, ignora el error
}

// Lee los datos enviados en formato JSON
$datosEntrada = json_decode(file_get_contents('php://input'), true);

// Obtiene la accion a realizar
$accion = $datosEntrada['action'] ?? obtenerGet('action', '');

// Ejecuta segun la accion solicitada
switch ($accion) {
    
    // Crear nuevo rol
    case 'crear':
        
        // Obtiene y limpia los datos
        $nombre = trim($datosEntrada['nombre'] ?? '');
        $descripcion = trim($datosEntrada['descripcion'] ?? '');
        $permisos = $datosEntrada['permisos'] ?? [];
        
        // Valida que el nombre no este vacio
        if (empty($nombre)) {
            respuestaError('El nombre del rol es obligatorio');
        }
        
        // Verifica que no exista otro rol con el mismo nombre
        $consulta = $conexion->prepare("SELECT id_rol FROM roles WHERE nombre = ?");
        $consulta->execute([$nombre]);
        
        if ($consulta->fetch()) {
            respuestaError('Ya existe un rol con ese nombre');
        }
        
        // Convierte los permisos a JSON para guardarlos
        $permisosJson = json_encode($permisos);
        
        try {
            // Inserta con campo permisos
            $consulta = $conexion->prepare("INSERT INTO roles (nombre, descripcion, permisos) VALUES (?, ?, ?)");
            $consulta->execute([$nombre, $descripcion, $permisosJson]);
            
            // Obtiene el ID del nuevo rol
            $nuevoId = $conexion->lastInsertId();
            
            // Registra en bitacora
            registrarEnBitacora('CREAR_ROL', "Rol creado: $nombre (ID: $nuevoId)");
            
            // Responde con exito
            respuestaExitosa(['id_rol' => $nuevoId], 'Rol creado correctamente');
        } catch (Exception $e) {
            respuestaError('Error al crear rol: ' . $e->getMessage());
        }
        break;
    
    // Editar rol existente
    case 'editar':
        
        // Obtiene el ID del rol
        $idRol = intval($datosEntrada['id_rol'] ?? 0);
        $nombre = trim($datosEntrada['nombre'] ?? '');
        $descripcion = trim($datosEntrada['descripcion'] ?? '');
        $permisos = $datosEntrada['permisos'] ?? [];
        
        // Valida que el ID sea valido
        if ($idRol <= 0) {
            respuestaError('ID de rol no valido');
        }
        
        // Convierte los permisos a JSON
        $permisosJson = json_encode($permisos);
        
        try {
            // Los roles del sistema (1-5) no se pueden modificar el nombre
            if ($idRol <= 5) {
                // Solo actualiza descripcion y permisos
                $consulta = $conexion->prepare("UPDATE roles SET descripcion = ?, permisos = ? WHERE id_rol = ?");
                $consulta->execute([$descripcion, $permisosJson, $idRol]);
            } else {
                // Valida nombre
                if (empty($nombre)) {
                    respuestaError('El nombre del rol es obligatorio');
                }
                
                // Verifica que no exista otro rol con el mismo nombre
                $consulta = $conexion->prepare("SELECT id_rol FROM roles WHERE nombre = ? AND id_rol != ?");
                $consulta->execute([$nombre, $idRol]);
                
                if ($consulta->fetch()) {
                    respuestaError('Ya existe otro rol con ese nombre');
                }
                
                // Actualiza todos los campos
                $consulta = $conexion->prepare("UPDATE roles SET nombre = ?, descripcion = ?, permisos = ? WHERE id_rol = ?");
                $consulta->execute([$nombre, $descripcion, $permisosJson, $idRol]);
            }
            
            // Registra en bitacora
            registrarEnBitacora('EDITAR_ROL', "Rol modificado ID: $idRol");
            
            respuestaExitosa(null, 'Rol actualizado correctamente');
        } catch (Exception $e) {
            respuestaError('Error al actualizar rol: ' . $e->getMessage());
        }
        break;
    
    // Eliminar rol
    case 'eliminar':
        
        $idRol = intval($datosEntrada['id_rol'] ?? 0);
        
        // Valida que el ID sea valido
        if ($idRol <= 0) {
            respuestaError('ID de rol no valido');
        }
        
        // Los roles del sistema (1-5) no se pueden eliminar
        if ($idRol <= 5) {
            respuestaError('No se pueden eliminar los roles del sistema');
        }
        
        // Verifica si hay usuarios asignados a este rol
        $consulta = $conexion->prepare("SELECT COUNT(*) as total FROM usuarios WHERE id_rol = ?");
        $consulta->execute([$idRol]);
        $resultado = $consulta->fetch();
        
        if ($resultado['total'] > 0) {
            respuestaError('No se puede eliminar: hay ' . $resultado['total'] . ' usuario(s) con este rol');
        }
        
        // Obtiene nombre del rol para la bitacora
        $consulta = $conexion->prepare("SELECT nombre FROM roles WHERE id_rol = ?");
        $consulta->execute([$idRol]);
        $rol = $consulta->fetch();
        
        // Elimina el rol
        $consulta = $conexion->prepare("DELETE FROM roles WHERE id_rol = ?");
        $consulta->execute([$idRol]);
        
        // Registra en bitacora
        registrarEnBitacora('ELIMINAR_ROL', "Rol eliminado: " . ($rol['nombre'] ?? 'ID ' . $idRol));
        
        respuestaExitosa(null, 'Rol eliminado correctamente');
        break;
    
    // Obtener un rol específico con sus permisos
    case 'obtener':
        
        $idRol = intval($datosEntrada['id_rol'] ?? obtenerGet('id_rol', 0));
        
        if ($idRol <= 0) {
            respuestaError('ID de rol no válido');
        }
        
        try {
            $consulta = $conexion->prepare("SELECT id_rol, nombre, descripcion, permisos FROM roles WHERE id_rol = ?");
            $consulta->execute([$idRol]);
            $rol = $consulta->fetch();
            
            if (!$rol) {
                respuestaError('Rol no encontrado');
            }
            
            // Decodifica los permisos de JSON a array
            $rol['permisos'] = json_decode($rol['permisos'] ?? '[]', true) ?: [];
            
            respuestaExitosa($rol);
            
        } catch (Exception $e) {
            respuestaError('Error al obtener rol: ' . $e->getMessage());
        }
        break;
    
    // Obtener lista de roles
    case 'listar':
        
        $consulta = $conexion->query("
            SELECT r.*, COUNT(u.id_usuario) as total_usuarios
            FROM roles r
            LEFT JOIN usuarios u ON r.id_rol = u.id_rol AND u.activo = 1
            GROUP BY r.id_rol
            ORDER BY r.id_rol
        ");
        
        $roles = $consulta->fetchAll();
        
        respuestaExitosa($roles);
        break;
    
    default:
        respuestaError('Accion no valida', 400);
}
