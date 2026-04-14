/**
 * SIAE-IMSS - JavaScript Principal
 */

// Inicializar iconos Lucide cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

/**
 * Formatear número con separadores de miles
 */
function formatearNumero(num) {
    return new Intl.NumberFormat('es-MX').format(num);
}

/**
 * Formatear fecha
 */
function formatearFecha(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-MX');
}

/**
 * Formatear fecha y hora
 */
function formatearFechaHora(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-MX') + ' ' + date.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
}

/**
 * Sanitizar caracteres para IMSS
 */
function limpiarParaIMSS(text) {
    if (!text) return '';
    
    const replacements = {
        'Á': 'A', 'É': 'E', 'Í': 'I', 'Ó': 'O', 'Ú': 'U', 'Ü': 'U', 'Ñ': 'N',
        'á': 'A', 'é': 'E', 'í': 'I', 'ó': 'O', 'ú': 'U', 'ü': 'U', 'ñ': 'N'
    };
    
    let result = text.toUpperCase();
    for (const [char, replacement] of Object.entries(replacements)) {
        result = result.split(char).join(replacement);
    }
    
    // Eliminar caracteres no ASCII
    result = result.replace(/[^A-Z0-9 ]/g, '');
    
    return result;
}

/**
 * Validar CURP
 */
function validarCURP(curp) {
    const pattern = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/;
    return pattern.test(curp.toUpperCase());
}

/**
 * Validar NSS
 */
function validarNSS(nss) {
    const cleaned = nss.replace(/[^0-9]/g, '');
    return cleaned.length === 11;
}

/**
 * Validar email
 */
function validarEmail(email) {
    const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return pattern.test(email);
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Copiar texto al portapapeles
 */
async function copiarAlPortapapeles(text) {
    try {
        await navigator.clipboard.writeText(text);
        mostrarNotificacion('Copiado al portapapeles');
        return true;
    } catch (err) {
        console.error('Error al copiar:', err);
        return false;
    }
}

/**
 * Descargar archivo
 */
function descargarArchivo(content, filename, mimeType = 'text/plain') {
    const blob = new Blob([content], { type: mimeType });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

/**
 * Loader global
 */
function mostrarCargando(message = 'Procesando...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function ocultarCargando() {
    Swal.close();
}

/**
 * Manejo de errores global
 */
window.addEventListener('unhandledrejection', function(event) {
    console.error('Error no manejado:', event.reason);
});

/**
 * Prevenir envío doble de formularios
 */
document.addEventListener('submit', function(e) {
    const form = e.target;
    if (form.classList.contains('submitting')) {
        e.preventDefault();
        return;
    }
    form.classList.add('submitting');
    
    // Remover clase después de 3 segundos por si hay error
    setTimeout(() => {
        form.classList.remove('submitting');
    }, 3000);
});

/**
 * Toggle de sidebar en móvil
 */
function alternarBarraLateral() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
}

// Cerrar sidebar al hacer clic fuera en móvil
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (sidebar && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && (!toggleBtn || !toggleBtn.contains(e.target))) {
            sidebar.classList.remove('open');
        }
    }
});

/**
 * Manejo de teclas globales
 */
document.addEventListener('keydown', function(e) {
    // Ctrl+S para guardar (prevenir comportamiento por defecto)
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const saveBtn = document.querySelector('button[type="submit"], .btn-primary');
        if (saveBtn) {
            saveBtn.click();
        }
    }
});

console.log('SIAE-IMSS v1.0.0 - Sistema cargado correctamente');
