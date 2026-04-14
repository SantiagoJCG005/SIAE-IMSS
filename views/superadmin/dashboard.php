<?php
/**
 *  * SI FUNCIONA NO LE MUEVAS!!!!!
 * SIAE-IMSS - Dashboard Superadmin
 * Es la pagina principal del Superadmin. Muestra un resumen del sistema
 * con estadisticas, ultimas acciones y accesos rapidos a otras secciones.
 */

// Titulo que aparecera en la pestana del navegador
$tituloPagina = 'Panel de Control General';

// Carga el archivo de autenticacion
require_once __DIR__ . '/../../includes/auth.php';

// Carga el archivo de funciones auxiliares
require_once __DIR__ . '/../../includes/functions.php';

// Verifica que solo el Superadmin pueda acceder
requerirRol([ROL_SUPERADMIN]);

// Establece conexion con la base de datos
$conexion = obtenerConexion();

// Consulta el total de usuarios activos en el sistema
$consulta = $conexion->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
$totalUsuarios = $consulta->fetch()['total'];

// Consulta el total de alumnos registrados
$consulta = $conexion->query("SELECT COUNT(*) as total FROM alumnos WHERE activo = 1");
$totalAlumnos = $consulta->fetch()['total'];

// Consulta el total de roles configurados
$consulta = $conexion->query("SELECT COUNT(*) as total FROM roles");
$totalRoles = $consulta->fetch()['total'];

// Consulta cuantas acciones se han realizado hoy
// CURDATE() obtiene la fecha actual sin hora
$consulta = $conexion->query("SELECT COUNT(*) as total FROM bitacora WHERE DATE(fecha) = CURDATE()");
$accionesHoy = $consulta->fetch()['total'];

// Consulta las ultimas 10 acciones registradas en la bitacora
// JOIN une con usuarios para obtener el nombre de quien hizo la accion
// ORDER BY fecha DESC ordena de la mas reciente a la mas antigua
$consulta = $conexion->query("
    SELECT b.*, u.nombre_completo, u.username 
    FROM bitacora b 
    JOIN usuarios u ON b.id_usuario = u.id_usuario 
    ORDER BY b.fecha DESC 
    LIMIT 10
");
$ultimasAcciones = $consulta->fetchAll();

// Incluye el header de la pagina
include __DIR__ . '/../layouts/header.php';

// Incluye el menu lateral del superadmin
include __DIR__ . '/../layouts/sidebar-superadmin.php';
?>

<div class="page-header">
    <h1 class="page-title">Panel de Control General</h1>
    <p class="page-subtitle">Resumen ejecutivo del estado del sistema institucional.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i data-lucide="users"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Usuarios Activos</div>
            <?php // number_format agrega comas a los miles para mejor lectura ?>
            <div class="stat-value primary"><?= number_format($totalUsuarios) ?></div>
            <div class="stat-change up">↗ +12% vs mes anterior</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="graduation-cap"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Alumnos Registrados</div>
            <div class="stat-value success"><?= number_format($totalAlumnos) ?></div>
            <div class="stat-change">📊 Base de datos nacional</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon info">
            <i data-lucide="shield"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Roles Configurados</div>
            <div class="stat-value info"><?= $totalRoles ?></div>
            <div class="stat-change">⚙️ <?= $totalRoles ?> permisos pendientes</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i data-lucide="zap"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Acciones Hoy</div>
            <div class="stat-value warning"><?= number_format($accionesHoy) ?></div>
            <div class="stat-change">🕐 Pico de tráfico: 11:00 AM</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i data-lucide="scroll-text" style="width: 18px; height: 18px; margin-right: 8px;"></i>
                Últimas acciones en bitácora
            </h3>
            <a href="<?= URL_BASE ?>views/superadmin/bitacora.php" class="btn btn-outline btn-sm">
                Exportar CSV
            </a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Fecha</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php // Si no hay acciones, muestra mensaje ?>
                    <?php if (empty($ultimasAcciones)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted" style="padding: 40px;">
                            No hay acciones registradas
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php // Recorre cada accion y crea una fila ?>
                        <?php foreach ($ultimasAcciones as $accion): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-center gap-2">
                                    <?php // Avatar con color e iniciales generados ?>
                                    <div class="avatar avatar-sm" style="background: <?= obtenerColorAvatar($accion['nombre_completo']) ?>">
                                        <?= obtenerIniciales($accion['nombre_completo']) ?>
                                    </div>
                                    <span><?= htmlspecialchars($accion['nombre_completo']) ?></span>
                                </div>
                            </td>
                            <td>
                                <?php
                                // Determina el color de la etiqueta segun el tipo de accion
                                $badgeClass = 'badge-secondary';  // Gris por defecto
                                
                                // Verde para login
                                if (strpos($accion['accion'], 'LOGIN') !== false) $badgeClass = 'badge-success';
                                
                                // Azul para crear
                                elseif (strpos($accion['accion'], 'CREAR') !== false || strpos($accion['accion'], 'ALTA') !== false) $badgeClass = 'badge-info';
                                
                                // Amarillo para editar
                                elseif (strpos($accion['accion'], 'EDITAR') !== false || strpos($accion['accion'], 'EDICIÓN') !== false) $badgeClass = 'badge-warning';
                                
                                // Rojo para eliminar
                                elseif (strpos($accion['accion'], 'ELIMINAR') !== false || strpos($accion['accion'], 'BAJA') !== false) $badgeClass = 'badge-danger';
                                
                                // Azul para exportar
                                elseif (strpos($accion['accion'], 'EXPORTAR') !== false) $badgeClass = 'badge-info';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($accion['accion']) ?></span>
                            </td>
                            <td>
                                <?php 
                                // Convierte la fecha a timestamp para comparar
                                $fecha = strtotime($accion['fecha']);
                                $hoy = strtotime('today');      // Inicio del dia de hoy
                                $ayer = strtotime('yesterday'); // Inicio de ayer
                                
                                // Si la fecha es de hoy, muestra "Hoy, HH:MM"
                                if ($fecha >= $hoy) {
                                    echo 'Hoy, ' . date('H:i', $fecha);
                                }
                                // Si la fecha es de ayer, muestra "Ayer, HH:MM"
                                elseif ($fecha >= $ayer) {
                                    echo 'Ayer, ' . date('H:i', $fecha);
                                }
                                // Si es mas antigua, muestra fecha completa
                                else {
                                    echo date('d/m/Y H:i', $fecha);
                                }
                                ?>
                            </td>
                            <td>
                                <?php // Boton que abre un dialogo con el detalle de la accion ?>
                                <?php // addslashes escapa comillas para no romper el JavaScript ?>
                                <button class="btn btn-ghost btn-icon" title="Ver detalles" onclick="verDetalle('<?= htmlspecialchars(addslashes($accion['detalle'])) ?>')">
                                    <i data-lucide="eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div>
        <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <i data-lucide="zap" style="width: 18px; height: 18px;"></i>
            Accesos rápidos
        </h3>
        
        <div class="quick-actions">
            <?php // Enlaces rapidos a otras secciones del sistema ?>
            <a href="<?= URL_BASE ?>views/superadmin/usuarios.php?action=nuevo" class="quick-action-btn primary">
                <i data-lucide="user-plus"></i>
                <div class="quick-action-text">
                    Administrar usuarios
                    <small>Altas, bajas y modificaciones</small>
                </div>
            </a>
            
            <a href="<?= URL_BASE ?>views/superadmin/catalogos.php" class="quick-action-btn secondary">
                <i data-lucide="database"></i>
                <div class="quick-action-text">
                    Gestionar catálogos
                    <small>Parámetros del sistema</small>
                </div>
            </a>
            
            <a href="<?= URL_BASE ?>views/superadmin/bitacora.php" class="quick-action-btn secondary">
                <i data-lucide="file-text"></i>
                <div class="quick-action-text">
                    Ver bitácora
                    <small>Auditoría completa</small>
                </div>
            </a>
        </div>
    </div>
    
</div>

<script>
// Funcion que muestra un dialogo con el detalle de una accion
// Recibe el texto del detalle como parametro
function verDetalle(detalle) {
    // Usa SweetAlert2 para mostrar el dialogo
    Swal.fire({
        title: 'Detalle de la acción',
        text: detalle || 'Sin detalles adicionales',  // Si no hay detalle, muestra mensaje por defecto
        icon: 'info',
        confirmButtonColor: '#2563EB'  // Color azul para el boton
    });
}
</script>

<?php // Incluye el footer ?>
<?php include __DIR__ . '/../layouts/footer.php'; ?>