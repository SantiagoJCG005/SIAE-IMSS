<?php
/**
 *  * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Punto de entrada principal
 * Sistema de Altas y Bajas IMSS para Instituciones Educativas
 */

require_once __DIR__ . '/includes/auth.php';

// Si está logueado, redirigir a su dashboard
if (estaLogueado()) {
    header('Location: ' . obtenerPaginaInicio());
    exit;
}

// Si no está logueado, ir al login
header('Location: ' . URL_BASE . 'views/auth/login.php');
exit;
