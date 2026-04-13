<?php
/**
 * SIAE-IMSS - Configuración General
 */

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// URL base del sistema (ajustar según tu servidor)
define('BASE_URL', '/ProgWeb/siae-imss/');
define('ASSETS_URL', BASE_URL . 'assets/');

// Nombre del sistema
define('SYSTEM_NAME', 'SIAE-IMSS');
define('SYSTEM_VERSION', '1.0.0');

// Configuración de sesiones
define('SESSION_LIFETIME', 3600); // 1 hora

// Rutas de archivos
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('EXPORT_PATH', __DIR__ . '/../exports/');

// Configuración IMSS (valores por defecto)
define('REGISTRO_PATRONAL', 'E292977432');
define('DIGITO_VERIFICADOR', '1');
define('UMF_ALTA', '001');
define('UMF_BAJA', '000');
define('CODIGO_OPERACION_ALTA', '08');
define('CODIGO_OPERACION_BAJA', '02');
define('PREFIJO_ALTA', '000000      210');
define('PREFIJO_BAJA', '000000000000000');
define('CODIGO_INSTITUCION', '01402');

// Longitud línea TXT IMSS
define('TXT_LINE_LENGTH', 168);

// Roles del sistema
define('ROL_SUPERADMIN', 1);
define('ROL_JEFA_SERVICIOS', 2);
define('ROL_ADMIN_SERVICIOS', 3);
define('ROL_ADMIN_IMSS', 4);
define('ROL_ESTUDIANTE', 5);

// Nombres de roles
$ROLES = [
    ROL_SUPERADMIN => 'Superadmin',
    ROL_JEFA_SERVICIOS => 'Jefa de Servicios',
    ROL_ADMIN_SERVICIOS => 'Admin Servicios',
    ROL_ADMIN_IMSS => 'Admin IMSS',
    ROL_ESTUDIANTE => 'Estudiante'
];

// Tipos de movimiento
define('TIPO_ALTA', 'alta');
define('TIPO_BAJA', 'baja');
define('TIPO_EDICION', 'edicion');

// Estados de movimiento
define('ESTADO_PENDIENTE', 'pendiente');
define('ESTADO_VALIDADO', 'validado');
define('ESTADO_RECHAZADO', 'rechazado');
define('ESTADO_EXPORTADO', 'exportado');

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
