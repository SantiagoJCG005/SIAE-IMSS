    </div><!-- /.page-content -->
    
    <!-- Footer -->
    <footer class="main-footer">
        <div>
            &copy; <?= date('Y') ?> <?= SYSTEM_NAME ?>. Instituto Mexicano del Seguro Social. Todos los derechos reservados.
        </div>
        <div class="footer-links">
            <a href="#">Seguridad</a>
            <a href="#">Privacidad</a>
            <a href="#">Soporte Técnico</a>
        </div>
    </footer>
</main><!-- /.main-content -->
</div><!-- /.app-container -->

<!-- Scripts -->
<script>
    // Inicializar iconos Lucide
    lucide.createIcons();
    
    // Configuración global
    const BASE_URL = '<?= BASE_URL ?>';
    
    // Toast de notificación
    function showToast(message, type = 'success') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: type,
            title: message
        });
    }
    
    // Confirmar acción
    function confirmAction(message, callback) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#F97316',
            cancelButtonColor: '#64748B',
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed && typeof callback === 'function') {
                callback();
            }
        });
    }
    
    // Fetch helper
    async function fetchAPI(url, options = {}) {
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...options.headers
                }
            });
            return await response.json();
        } catch (error) {
            console.error('Error:', error);
            showToast('Error de conexión', 'error');
            return null;
        }
    }
</script>

<!-- JS principal -->
<script src="<?= ASSETS_URL ?>js/main.js"></script>

<?php if (isset($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>

<?php
// Mostrar mensaje flash si existe
$flash = getFlashMessage();
if ($flash): ?>
<script>
    showToast('<?= addslashes($flash['message']) ?>', '<?= $flash['type'] ?>');
</script>
<?php endif; ?>

</body>
</html>
