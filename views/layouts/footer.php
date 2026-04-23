</div><!-- /.page-content -->
    
    <!-- Footer -->
    <footer class="main-footer">
        <div>
            <?php // date('Y') obtiene el ano actual y NOMBRE_SISTEMA es el nombre del sistema definido en config ?>
            &copy; <?= date('Y') ?> <?= NOMBRE_SISTEMA ?>. Instituto Mexicano del Seguro Social. Todos los derechos reservados.
        </div>
        <div class="footer-links">
            <a href="#">Seguridad</a>
            <a href="#">Privacidad</a>
            <a href="#">Soporte Técnico</a>
        </div>
    </footer>
</main>
</div>

<!-- Scripts -->
<script>
     //* SI FUNCIONA NO LE MUEVAS!!!!!
    // Inicializa todos los iconos de Lucide en la pagina
    // Convierte las etiquetas con data-lucide en iconos SVG visibles
    lucide.createIcons();
    
    // Guarda la URL base del sistema en una variable de JavaScript
    // Esto permite usarla en otras funciones para hacer peticiones al servidor
    const URL_BASE = '<?= URL_BASE ?>';
    
    // Funcion que muestra notificaciones temporales en la esquina superior derecha
    // Recibe el mensaje a mostrar y el tipo (success, error, warning, info)
    function mostrarNotificacion(message, type = 'success') {

        // Crea una configuracion personalizada de SweetAlert2 para notificaciones tipo toast
        const Toast = Swal.mixin({
            toast: true,              // Activa el modo toast (notificacion pequena)
            position: 'top-end',      // Posicion: arriba a la derecha
            showConfirmButton: false, // No muestra boton de confirmar
            timer: 3000,              // Desaparece automaticamente en 3 segundos
            timerProgressBar: true    // Muestra barra de progreso del tiempo restante
        });
        
        // Muestra la notificacion con el icono segun el tipo y el mensaje
        Toast.fire({
            icon: type,
            title: message
        });
    }
    
    // Funcion que muestra un dialogo de confirmacion antes de realizar acciones importantes
    // Recibe el mensaje a mostrar y una funcion callback que se ejecuta si el usuario confirma
    function confirmarAccion(message, callback) {

        // Muestra el dialogo de SweetAlert2 con opciones de confirmar o cancelar
        Swal.fire({
            title: '¿Estás seguro?',           // Titulo del dialogo
            text: message,                      // Mensaje descriptivo
            icon: 'warning',                    // Icono de advertencia (triangulo amarillo)
            showCancelButton: true,             // Muestra boton de cancelar
            confirmButtonColor: '#F97316',      // Color naranja para el boton de confirmar
            cancelButtonColor: '#64748B',       // Color gris para el boton de cancelar
            confirmButtonText: 'Sí, continuar', // Texto del boton confirmar
            cancelButtonText: 'Cancelar'        // Texto del boton cancelar

        }).then((result) => {

            // Si el usuario hizo clic en confirmar y existe una funcion callback
            if (result.isConfirmed && typeof callback === 'function') {

                // Ejecuta la funcion que se paso como parametro
                callback();
            }
        });
    }
    
    // Funcion auxiliar para hacer peticiones HTTP al servidor (API)
    // Simplifica el uso de fetch agregando configuracion por defecto
    // Recibe la URL a consultar y opciones adicionales (method, body, headers)
    async function llamarApi(url, options = {}) {

        try {

            // Hace la peticion HTTP con fetch
            const response = await fetch(url, {
                ...options,  // Incluye las opciones que se pasaron (method, body, etc.)
                headers: {
                    'Content-Type': 'application/json',      // Indica que enviamos datos en formato JSON
                    'X-Requested-With': 'XMLHttpRequest',    // Indica que es una peticion AJAX
                    ...options.headers                        // Incluye headers adicionales si los hay
                }
            });

            // Convierte la respuesta a formato JSON y la retorna
            return await response.json();

        } catch (error) {

            // Si hay un error de conexion, lo muestra en la consola
            console.error('Error:', error);

            // Muestra una notificacion de error al usuario
            mostrarNotificacion('Error de conexión', 'error');

            // Retorna null para indicar que la peticion fallo
            return null;
        }
    }
</script>

<!-- Carga el archivo JavaScript principal del sistema -->
<?php // URL_RECURSOS contiene la ruta a la carpeta de recursos (css, js, imagenes) ?>
<script src="<?= URL_RECURSOS ?>js/main.js"></script>

<?php // Si existe la variable $extraScripts, la imprime (permite agregar scripts adicionales por pagina) ?>
<?php if (isset($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>

<?php
// Obtiene el mensaje flash de la sesion si existe
// Los mensajes flash son notificaciones que se muestran una sola vez (despues de redirigir)
$flash = obtenerMensajeFlash();

// Si hay un mensaje flash pendiente, lo muestra
if ($flash): ?>
<script>
    // Muestra el mensaje usando la funcion mostrarNotificacion
    // addslashes escapa caracteres especiales para evitar errores en JavaScript
    mostrarNotificacion('<?= addslashes($flash['message']) ?>', '<?= $flash['type'] ?>');
</script>
<?php endif; ?>

</body>
</html>