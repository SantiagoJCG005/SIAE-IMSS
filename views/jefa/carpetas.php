<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirRol([ROL_JEFA_SERVICIOS, ROL_ADMIN_SERVICIOS, ROL_SUPERADMIN]);
$rolVista = 'jefa';
include __DIR__ . '/../shared/carpetas.php';
