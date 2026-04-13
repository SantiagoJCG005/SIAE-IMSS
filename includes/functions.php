<?php
/**
 * SIAE-IMSS - Funciones Helper
 */

/**
 * Sanitizar entrada de texto
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitizar caracteres especiales para IMSS (ñ, acentos)
 */
function sanitizeIMSS($text) {
    $text = mb_strtoupper(trim($text), 'UTF-8');
    
    // Reemplazar caracteres especiales
    $search = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
    $replace = ['A', 'E', 'I', 'O', 'U', 'U', 'N', 'A', 'E', 'I', 'O', 'U', 'U', 'N'];
    
    $text = str_replace($search, $replace, $text);
    
    // Eliminar cualquier otro carácter no ASCII
    $text = preg_replace('/[^A-Z0-9 ]/', '', $text);
    
    return $text;
}

/**
 * Formatear texto con padding para TXT IMSS
 */
function padIMSS($text, $length, $padChar = ' ', $padType = STR_PAD_RIGHT) {
    $text = sanitizeIMSS($text);
    return mb_substr(str_pad($text, $length, $padChar, $padType), 0, $length);
}

/**
 * Validar CURP
 */
function validarCURP($curp) {
    $curp = strtoupper(trim($curp));
    $pattern = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/';
    return preg_match($pattern, $curp);
}

/**
 * Validar NSS (11 dígitos)
 */
function validarNSS($nss) {
    $nss = preg_replace('/[^0-9]/', '', $nss);
    return strlen($nss) === 11;
}

/**
 * Formatear fecha para mostrar
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Formatear fecha y hora para mostrar
 */
function formatDateTime($date, $format = 'd/m/Y H:i') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Generar respuesta JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Respuesta de éxito JSON
 */
function jsonSuccess($data = null, $message = 'Operación exitosa') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Respuesta de error JSON
 */
function jsonError($message = 'Error en la operación', $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], $statusCode);
}

/**
 * Obtener iniciales de un nombre
 */
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= mb_substr($word, 0, 1, 'UTF-8');
            if (strlen($initials) >= 2) break;
        }
    }
    return strtoupper($initials);
}

/**
 * Generar color de avatar basado en nombre
 */
function getAvatarColor($name) {
    $colors = ['#3B8BD4', '#1D9E75', '#D85A30', '#7F77DD', '#D4537E', '#BA7517'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

/**
 * Paginar resultados
 */
function paginate($total, $perPage = 10, $currentPage = 1) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Mostrar alerta flash
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Obtener y limpiar mensaje flash
 */
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Verificar si es petición AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Obtener parámetro GET sanitizado
 */
function get($key, $default = '') {
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
}

/**
 * Obtener parámetro POST sanitizado
 */
function post($key, $default = '') {
    return isset($_POST[$key]) ? sanitize($_POST[$key]) : $default;
}
