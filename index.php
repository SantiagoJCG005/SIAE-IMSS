<?php
/**
 * SIAE-IMSS - Punto de entrada principal
 * Sistema de Altas y Bajas IMSS para Instituciones Educativas
 */

require_once __DIR__ . '/includes/auth.php';

// Si está logueado, redirigir a su dashboard
if (isLoggedIn()) {
    header('Location: ' . getHomePage());
    exit;
}

// Si no está logueado, ir al login
header('Location: ' . BASE_URL . 'views/auth/login.php');
exit;
