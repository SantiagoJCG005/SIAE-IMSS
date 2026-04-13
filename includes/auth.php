<?php
/**
 * SIAE-IMSS - Funciones de Autenticación
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Verificar si el usuario está logueado
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obtener usuario actual
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['user'] ?? null;
}

/**
 * Obtener ID del usuario actual
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtener rol del usuario actual
 */
function getCurrentRole() {
    return $_SESSION['user']['id_rol'] ?? null;
}

/**
 * Verificar si el usuario tiene un rol específico
 */
function hasRole($rolId) {
    return getCurrentRole() == $rolId;
}

/**
 * Verificar si el usuario tiene alguno de los roles especificados
 */
function hasAnyRole($roles) {
    $currentRole = getCurrentRole();
    return in_array($currentRole, $roles);
}

/**
 * Verificar acceso y redirigir si no está autorizado
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'views/auth/login.php');
        exit;
    }
}

/**
 * Verificar rol y redirigir si no está autorizado
 */
function requireRole($roles) {
    requireLogin();
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    if (!hasAnyRole($roles)) {
        header('Location: ' . BASE_URL . 'views/auth/sin-acceso.php');
        exit;
    }
}

/**
 * Login de usuario
 */
function login($username, $password) {
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("
        SELECT u.*, r.nombre as rol_nombre 
        FROM usuarios u 
        JOIN roles r ON u.id_rol = r.id_rol 
        WHERE u.username = ? AND u.activo = 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Actualizar último login
        $updateStmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id_usuario = ?");
        $updateStmt->execute([$user['id_usuario']]);
        
        // Guardar en sesión
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['user'] = [
            'id_usuario' => $user['id_usuario'],
            'username' => $user['username'],
            'nombre_completo' => $user['nombre_completo'],
            'email' => $user['email'],
            'id_rol' => $user['id_rol'],
            'rol_nombre' => $user['rol_nombre']
        ];
        
        // Registrar en bitácora
        registrarBitacora('LOGIN', 'Inicio de sesión exitoso');
        
        return true;
    }
    
    return false;
}

/**
 * Logout de usuario
 */
function logout() {
    registrarBitacora('LOGOUT', 'Cierre de sesión');
    
    session_unset();
    session_destroy();
    
    header('Location: ' . BASE_URL . 'views/auth/login.php');
    exit;
}

/**
 * Registrar acción en bitácora
 */
function registrarBitacora($accion, $detalle = '') {
    if (!isLoggedIn()) return;
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, accion, detalle, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            getCurrentUserId(),
            $accion,
            $detalle,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Silenciar errores de bitácora para no interrumpir el flujo
    }
}

/**
 * Obtener página de inicio según rol
 */
function getHomePage() {
    $rol = getCurrentRole();
    
    switch ($rol) {
        case ROL_SUPERADMIN:
            return BASE_URL . 'views/superadmin/dashboard.php';
        case ROL_JEFA_SERVICIOS:
        case ROL_ADMIN_SERVICIOS:
            return BASE_URL . 'views/jefa/dashboard.php';
        case ROL_ADMIN_IMSS:
            return BASE_URL . 'views/jefa/alumnos.php';
        case ROL_ESTUDIANTE:
            return BASE_URL . 'views/estudiante/dashboard.php';
        default:
            return BASE_URL . 'views/auth/login.php';
    }
}
