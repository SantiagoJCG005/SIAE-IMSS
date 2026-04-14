<?php
/** * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Configuracion General
 */

// Define la zona horaria del sistema (hora de mexico)
date_default_timezone_set('America/Mexico_City');

// Define la ruta base del sistema (donde esta instalado)
define('URL_BASE', '/ProgWeb/siae-imss/');

// Define la ruta de archivos estaticos (css, js, imagenes)
define('URL_RECURSOS', URL_BASE . 'assets/');

// Nombre del sistema
define('NOMBRE_SISTEMA', 'SIAE-IMSS');

// Version del sistema
define('SYSTEM_VERSION', '1.0.0');

// Tiempo de duracion de la sesion (en segundos) 3600 = 1 hora
define('DURACION_SESION', 3600);

// Ruta donde se guardan archivos subidos por el usuario
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Ruta donde se guardan archivos exportados
define('EXPORT_PATH', __DIR__ . '/../exports/');

// Datos por defecto para generacion de archivos del IMSS
define('REGISTRO_PATRONAL', 'E292977432'); // registro patronal
define('DIGITO_VERIFICADOR', '1'); // digito verificador
define('UMF_ALTA', '001'); // unidad medica para altas
define('UMF_BAJA', '000'); // unidad medica para bajas
define('CODIGO_OPERACION_ALTA', '08'); // codigo para altas
define('CODIGO_OPERACION_BAJA', '02'); // codigo para bajas
define('PREFIJO_ALTA', '000000      210'); // prefijo para archivo de alta
define('PREFIJO_BAJA', '000000000000000'); // prefijo para archivo de baja
define('CODIGO_INSTITUCION', '01402'); // codigo de la institucion

// Longitud fija que debe tener cada linea en archivos TXT del IMSS
define('LONGITUD_LINEA_TXT', 168);

// Roles del sistema (numeros que identifican cada tipo de usuario)
define('ROL_SUPERADMIN', 1);
define('ROL_JEFA_SERVICIOS', 2);
define('ROL_ADMIN_SERVICIOS', 3);
define('ROL_ADMIN_IMSS', 4);
define('ROL_ESTUDIANTE', 5);

// Lista de nombres de roles (para mostrar en pantalla)
$ROLES = [
    ROL_SUPERADMIN => 'Superadmin',
    ROL_JEFA_SERVICIOS => 'Jefa de Servicios',
    ROL_ADMIN_SERVICIOS => 'Admin Servicios',
    ROL_ADMIN_IMSS => 'Admin IMSS',
    ROL_ESTUDIANTE => 'Estudiante'
];

// Tipos de movimientos en el sistema
define('TIPO_ALTA', 'alta'); // registro nuevo
define('TIPO_BAJA', 'baja'); // baja o eliminacion
define('TIPO_EDICION', 'edicion'); // modificacion

// Estados de los movimientos
define('ESTADO_PENDIENTE', 'pendiente'); // aun no procesado
define('ESTADO_VALIDADO', 'validado'); // ya revisado
define('ESTADO_RECHAZADO', 'rechazado'); // no aprobado
define('ESTADO_EXPORTADO', 'exportado'); // ya enviado a archivo

// Verifica si la sesion ya esta iniciada
if (session_status() === PHP_SESSION_NONE) {

    // Si no esta iniciada, la inicia
    session_start();
}