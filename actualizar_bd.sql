-- =============================================
-- SIAE-IMSS - Script de Actualización de BD
-- Ejecutar DESPUÉS de tener la base de datos creada
-- =============================================

-- Agregar campo permisos a tabla roles (si no existe)
-- Este campo almacena los permisos como JSON
SET @columnExists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'siae_imss' 
    AND TABLE_NAME = 'roles' 
    AND COLUMN_NAME = 'permisos'
);

SET @sql = IF(@columnExists = 0, 
    'ALTER TABLE `roles` ADD COLUMN `permisos` JSON AFTER `descripcion`', 
    'SELECT "Campo permisos ya existe"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Crear tabla de configuración del sistema (si no existe)
CREATE TABLE IF NOT EXISTS `configuracion` (
    `id_config` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo` VARCHAR(50) NOT NULL,
    `clave` VARCHAR(100) NOT NULL,
    `valor` TEXT,
    `fecha_modificacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `id_usuario_modificacion` INT,
    UNIQUE KEY `tipo_clave` (`tipo`, `clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verificar que se creó correctamente
SELECT 'Actualización completada' AS resultado;
