<?php
/** * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Funciones de Autenticacion
 */

// Incluye configuracion general del sistema
require_once __DIR__ . '/../config/config.php';

// Incluye conexion a base de datos
require_once __DIR__ . '/../config/database.php';

/**
 * Verifica si el usuario ha iniciado sesion
 */
function estaLogueado() {

    // Regresa true si existe user_id en sesion y no esta vacio
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obtener datos del usuario actual
 */
function obtenerUsuarioActual() {

    // Si no esta logueado
    if (!estaLogueado()) {
        return null; // regresa null
    }

    // Regresa datos guardados en sesion
    return $_SESSION['user'] ?? null;
}

/**
 * Obtener ID del usuario actual
 */
function obtenerIdUsuarioActual() {

    // Regresa id del usuario o null si no existe
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtener rol del usuario actual
 */
function obtenerRolActual() {

    // Regresa id del rol o null
    return $_SESSION['user']['id_rol'] ?? null;
}

/**
 * Verificar si tiene un rol especifico
 */
function tieneRol($rolId) {

    // Compara el rol actual con el solicitado
    return obtenerRolActual() == $rolId;
}

/**
 * Verificar si tiene alguno de varios roles
 */
function tieneAlgunRol($listaRoles) {

    // Obtiene rol actual
    $currentRole = obtenerRolActual();

    // Verifica si esta dentro del arreglo
    return in_array($currentRole, $listaRoles);
}

/**
 * Verifica login y redirige si no hay sesion
 */
function requerirLogin() {

    // Si no esta logueado
    if (!estaLogueado()) {

        // Redirige al login
        header('Location: ' . URL_BASE . 'views/auth/login.php');

        exit; // detiene ejecucion
    }
}

/**
 * Verifica rol y redirige si no tiene permiso
 */
function requerirRol($listaRoles) {

    // Primero verifica que haya sesion
    requerirLogin();
    
    // Si no es arreglo, lo convierte en arreglo
    if (!is_array($listaRoles)) {
        $listaRoles = [$listaRoles];
    }
    
    // Si el usuario no tiene el rol permitido
    if (!tieneAlgunRol($listaRoles)) {

        // Lo manda a pagina sin acceso
        header('Location: ' . URL_BASE . 'views/auth/sin-acceso.php');

        exit;
    }
}

/**
 * Funcion para iniciar sesion
 */
function iniciarSesion($username, $password) {

    // Obtiene conexion a base de datos
    $conexion = obtenerConexion();
    
    // Consulta para obtener usuario y rol
    $consulta = $conexion->prepare("
        SELECT u.*, r.nombre as rol_nombre 
        FROM usuarios u 
        JOIN roles r ON u.id_rol = r.id_rol 
        WHERE u.username = ? AND u.activo = 1
    ");

    // Ejecuta consulta
    $consulta->execute([$username]);

    // Obtiene usuario
    $usuario = $consulta->fetch();
    
    // Verifica que exista y que la contraseña sea correcta
    if ($usuario && password_verify($password, $usuario['password_hash'])) {

        // Actualiza ultimo login
        $updateStmt = $conexion->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id_usuario = ?");
        $updateStmt->execute([$usuario['id_usuario']]);
        
        // Guarda datos en sesion
        $_SESSION['user_id'] = $usuario['id_usuario'];

        $_SESSION['user'] = [
            'id_usuario' => $usuario['id_usuario'],
            'username' => $usuario['username'],
            'nombre_completo' => $usuario['nombre_completo'],
            'email' => $usuario['email'],
            'id_rol' => $usuario['id_rol'],
            'rol_nombre' => $usuario['rol_nombre']
        ];
        
        // Guarda registro en bitacora
        registrarEnBitacora('LOGIN', 'Inicio de sesion exitoso');
        
        return true; // login correcto
    }
    
    return false; // login incorrecto
}

/**
 * Cerrar sesion
 */
function cerrarSesion() {

    // Guarda accion en bitacora
    registrarEnBitacora('LOGOUT', 'Cierre de sesion');
    
    // Limpia variables de sesion
    session_unset();

    // Destruye la sesion
    session_destroy();
    
    // Redirige al login
    header('Location: ' . URL_BASE . 'views/auth/login.php');

    exit;
}

/**
 * Guardar acciones en bitacora
 */
function registrarEnBitacora($accion, $detalle = '') {

    // Si no hay sesion, no hace nada
    if (!estaLogueado()) return;
    
    try {

        // Conexion a base de datos
        $conexion = obtenerConexion();

        // Inserta registro en bitacora
        $consulta = $conexion->prepare("
            INSERT INTO bitacora (id_usuario, accion, detalle, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");

        // Ejecuta con datos
        $consulta->execute([
            obtenerIdUsuarioActual(), // id usuario
            $accion, // accion realizada
            $detalle, // descripcion
            $_SERVER['REMOTE_ADDR'] ?? '', // ip del usuario
            $_SERVER['HTTP_USER_AGENT'] ?? '' // navegador
        ]);

    } catch (Exception $e) {

        // Ignora errores para no detener el sistema
    }
}

/**
 * Obtener pagina inicial segun rol
 */
function obtenerPaginaInicio() {

    // Obtiene rol del usuario
    $rol = obtenerRolActual();
    
    // Decide a donde enviarlo segun su rol
    switch ($rol) {

        case ROL_SUPERADMIN:
            return URL_BASE . 'views/superadmin/dashboard.php';

        case ROL_JEFA_SERVICIOS:
        case ROL_ADMIN_SERVICIOS:
            return URL_BASE . 'views/jefa/dashboard.php';

        case ROL_ADMIN_IMSS:
            return URL_BASE . 'views/jefa/alumnos.php';

        case ROL_ESTUDIANTE:
            return URL_BASE . 'views/estudiante/dashboard.php';

        default:
            return URL_BASE . 'views/auth/login.php';
    }
}