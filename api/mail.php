<?php
/**
 * SIAE-IMSS - API de Correo
 * Probar configuración SMTP
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Verificar rol (solo Superadmin puede probar SMTP)
if (!tieneAlgunRol([ROL_SUPERADMIN])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

// Leer JSON del body (importante para fetch con JSON)
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// También revisar POST tradicional por si acaso
$emailPrueba = '';
if (!empty($input['email_prueba'])) {
    $emailPrueba = trim($input['email_prueba']);
} elseif (!empty($_POST['email_prueba'])) {
    $emailPrueba = trim($_POST['email_prueba']);
} elseif (!empty($_GET['email'])) {
    $emailPrueba = trim($_GET['email']);
}

$action = $_GET['action'] ?? $input['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'probar_smtp':
        probarSMTP($emailPrueba);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Prueba la configuración SMTP enviando un correo de prueba
 */
function probarSMTP($emailPrueba) {
    
    if (empty($emailPrueba)) {
        echo json_encode(['success' => false, 'message' => 'Debes proporcionar un email de prueba']);
        return;
    }
    
    if (!filter_var($emailPrueba, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'El email proporcionado no es válido']);
        return;
    }
    
    // Enviar correo de prueba
    $resultado = enviarCorreo(
        $emailPrueba,
        '✅ Prueba SMTP - SIAE-IMSS',
        plantillaCorreoPrueba()
    );
    
    if ($resultado['success']) {
        // Registrar en bitácora
        registrarEnBitacora('Prueba SMTP', 'Correo de prueba enviado a: ' . $emailPrueba);
        echo json_encode(['success' => true, 'message' => 'Correo enviado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => $resultado['message']]);
    }
}
