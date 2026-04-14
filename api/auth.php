<?php
/** * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - API de Autenticacion
 */

// Incluye el archivo auth.php que contiene funciones como login o logout
require_once __DIR__ . '/../includes/auth.php';

// Obtiene el valor de "action" que viene en la URL (por ejemplo ?action=logout)
// Si no existe, se asigna un texto vacio
$accion = $_GET['action'] ?? '';

// Inicia una estructura switch para revisar que accion se solicito
switch ($accion) {

    // Si la accion es "logout"
    case 'logout':
        cerrarSesion(); // Llama a la funcion logout que cierra la sesion y redirige automaticamente
        break;
    // Si no coincide con ninguna accion conocida
    default:
        // Redirige al usuario a la pagina de login
        header('Location: ' . URL_BASE . 'views/auth/login.php');
        exit; // Detiene la ejecucion del codigo
}