SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- =============================================
-- SIAE-IMSS - Base de Datos Completa
-- Sistema de Altas y Bajas IMSS
-- INSTRUCCIONES:
-- 1. Ejecutar este script completo en phpMyAdmin o MySQL
-- 2. Crear un usuario admin después de ejecutar
-- 3. La contraseña del admin por defecto es: password
--
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-06:00";
SET FOREIGN_KEY_CHECKS = 0;

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS `siae_imss` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `siae_imss`;

-- =============================================
-- PARTE 1: TABLAS DE CATÁLOGOS
-- =============================================

-- Roles del sistema
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id_rol` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(50) NOT NULL,
    `descripcion` TEXT,
    `permisos` JSON COMMENT 'Permisos específicos del rol en formato JSON',
    `activo` BOOLEAN DEFAULT TRUE,
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Roles de usuario del sistema';

-- Niveles educativos
DROP TABLE IF EXISTS `nivel`;
CREATE TABLE `nivel` (
    `id_nivel` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `activo` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Niveles educativos (Licenciatura, Maestría, etc.)';

-- Carreras
DROP TABLE IF EXISTS `carreras`;
CREATE TABLE `carreras` (
    `id_carrera` INT PRIMARY KEY AUTO_INCREMENT,
    `clave` VARCHAR(20),
    `nombre` VARCHAR(150) NOT NULL,
    `id_nivel` INT,
    `activo` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`id_nivel`) REFERENCES `nivel`(`id_nivel`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catálogo de carreras';

-- Modalidades
DROP TABLE IF EXISTS `modalidad`;
CREATE TABLE `modalidad` (
    `id_modalidad` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `activo` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Modalidades de estudio';

-- Relación carrera-modalidad
DROP TABLE IF EXISTS `carrera_modalidad`;
CREATE TABLE `carrera_modalidad` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `id_carrera` INT,
    `id_modalidad` INT,
    `activo` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`id_carrera`) REFERENCES `carreras`(`id_carrera`) ON DELETE CASCADE,
    FOREIGN KEY (`id_modalidad`) REFERENCES `modalidad`(`id_modalidad`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Semestres
DROP TABLE IF EXISTS `semestre`;
CREATE TABLE `semestre` (
    `id_semestre` INT PRIMARY KEY AUTO_INCREMENT,
    `numero` INT NOT NULL,
    `nombre` VARCHAR(50) NOT NULL,
    `activo` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catálogo de semestres';

-- Periodos escolares
DROP TABLE IF EXISTS `periodo_escolar`;
CREATE TABLE `periodo_escolar` (
    `id_periodo` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `activo` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Periodos escolares';

-- Motivos de baja
DROP TABLE IF EXISTS `motivos_bajas`;
CREATE TABLE `motivos_bajas` (
    `id_motivo` INT PRIMARY KEY AUTO_INCREMENT,
    `clave` VARCHAR(20),
    `descripcion` VARCHAR(255) NOT NULL,
    `activo` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catálogo de motivos de baja IMSS';

-- =============================================
-- PARTE 2: TABLAS DE USUARIOS
-- =============================================

-- Usuarios del sistema
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
    `id_usuario` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `nombre_completo` VARCHAR(150) NOT NULL,
    `id_rol` INT NOT NULL,
    `activo` BOOLEAN DEFAULT TRUE,
    `ultimo_login` DATETIME NULL,
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `numero_control` VARCHAR(20) NULL COMMENT 'Solo para rol estudiante',
    `curp` VARCHAR(18) NULL COMMENT 'CURP del usuario (opcional)',
    `id_nivel` INT NULL COMMENT 'Nivel educativo (solo estudiantes)',
    `id_carrera` INT NULL COMMENT 'Carrera (solo estudiantes)',
    `id_modalidad` INT NULL COMMENT 'Modalidad (solo estudiantes)',
    `id_semestre` INT NULL COMMENT 'Semestre actual (solo estudiantes)',
    `id_periodo` INT NULL COMMENT 'Periodo actual (solo estudiantes)',
    `debe_cambiar_password` BOOLEAN DEFAULT FALSE COMMENT 'Forzar cambio de contraseña',
    `id_sii` INT NULL COMMENT 'ID en sistema SII del Tecnológico',
    `sincronizado_sii` BOOLEAN DEFAULT FALSE COMMENT 'Si los datos vienen del SII',
    `fecha_sync_sii` DATETIME NULL COMMENT 'Última sincronización con SII',
    FOREIGN KEY (`id_rol`) REFERENCES `roles`(`id_rol`),
    INDEX `idx_usuarios_curp` (`curp`),
    INDEX `idx_usuarios_numero_control` (`numero_control`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Usuarios del sistema SIAE-IMSS';

-- Reset de contraseñas
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `email` VARCHAR(100) NOT NULL,
    `token` VARCHAR(100) NOT NULL,
    `fecha_expiracion` DATETIME NOT NULL,
    `usado` BOOLEAN DEFAULT FALSE,
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tokens para recuperación de contraseña';

-- =============================================
-- PARTE 3: TABLAS DE ALUMNOS
-- =============================================

-- Alumnos (catálogo maestro)
DROP TABLE IF EXISTS `alumnos`;
CREATE TABLE `alumnos` (
    `id_alumno` INT PRIMARY KEY AUTO_INCREMENT,
    `numero_control` VARCHAR(20) NOT NULL UNIQUE,
    `curp` VARCHAR(18) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `apellido_paterno` VARCHAR(100) NOT NULL,
    `apellido_materno` VARCHAR(100),
    `fecha_nacimiento` DATE,
    `sexo` ENUM('H', 'M') NOT NULL,
    `email` VARCHAR(100),
    `telefono` VARCHAR(20),
    `id_carrera` INT,
    `id_modalidad` INT,
    `id_semestre` INT,
    `id_periodo` INT,
    `activo` BOOLEAN DEFAULT TRUE,
    `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_carrera`) REFERENCES `carreras`(`id_carrera`) ON DELETE SET NULL,
    FOREIGN KEY (`id_modalidad`) REFERENCES `modalidad`(`id_modalidad`) ON DELETE SET NULL,
    FOREIGN KEY (`id_semestre`) REFERENCES `semestre`(`id_semestre`) ON DELETE SET NULL,
    FOREIGN KEY (`id_periodo`) REFERENCES `periodo_escolar`(`id_periodo`) ON DELETE SET NULL,
    INDEX `idx_curp` (`curp`),
    INDEX `idx_numero_control` (`numero_control`),
    INDEX `idx_carrera` (`id_carrera`),
    INDEX `idx_periodo` (`id_periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catálogo maestro de alumnos';

-- Datos IMSS del alumno
DROP TABLE IF EXISTS `datos_imss`;
CREATE TABLE `datos_imss` (
    `id_datos_imss` INT PRIMARY KEY AUTO_INCREMENT,
    `id_alumno` INT NOT NULL UNIQUE,
    `nss` VARCHAR(11) NOT NULL COMMENT 'Número de Seguro Social (11 dígitos)',
    `digito_verificador` CHAR(1),
    `fecha_alta_imss` DATE,
    `fecha_baja_imss` DATE,
    `estado_imss` ENUM('pendiente', 'activo', 'baja') DEFAULT 'pendiente',
    `vigencia_inicio` DATE,
    `vigencia_fin` DATE,
    `fecha_actualizacion` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_alumno`) REFERENCES `alumnos`(`id_alumno`) ON DELETE CASCADE,
    INDEX `idx_nss` (`nss`),
    INDEX `idx_estado` (`estado_imss`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Datos de IMSS por alumno';

-- =============================================
-- PARTE 4: TABLAS DE CARPETAS Y MOVIMIENTOS
-- =============================================

-- Carpetas IMSS (organización principal)
DROP TABLE IF EXISTS `carpetas_imss`;
CREATE TABLE `carpetas_imss` (
    `id_carpeta` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `activo` BOOLEAN DEFAULT TRUE,
    `id_usuario_creacion` INT NOT NULL,
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_usuario_creacion`) REFERENCES `usuarios`(`id_usuario`),
    INDEX `idx_nombre` (`nombre`),
    INDEX `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Carpetas principales para organizar movimientos IMSS';

-- Subcarpetas IMSS
DROP TABLE IF EXISTS `subcarpetas_imss`;
CREATE TABLE `subcarpetas_imss` (
    `id_subcarpeta` INT PRIMARY KEY AUTO_INCREMENT,
    `id_carpeta` INT NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `activo` BOOLEAN DEFAULT TRUE,
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_carpeta`) REFERENCES `carpetas_imss`(`id_carpeta`) ON DELETE CASCADE,
    INDEX `idx_carpeta` (`id_carpeta`),
    INDEX `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Subcarpetas dentro de carpetas IMSS';

-- Tablas de movimientos (Altas y Bajas)
DROP TABLE IF EXISTS `tablas_movimientos`;
CREATE TABLE `tablas_movimientos` (
    `id_tabla` INT PRIMARY KEY AUTO_INCREMENT,
    `id_subcarpeta` INT NOT NULL,
    `tipo` ENUM('alta', 'baja') NOT NULL COMMENT 'Tipo de movimiento',
    `nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre descriptivo de la tabla',
    `fecha_movimiento` DATE NOT NULL COMMENT 'Fecha del movimiento IMSS',
    `archivo_origen` VARCHAR(255) NULL COMMENT 'Nombre del archivo Excel importado',
    `total_registros` INT DEFAULT 0,
    `registros_con_errores` INT DEFAULT 0,
    `estado` ENUM('borrador', 'pendiente', 'validado', 'enviado') DEFAULT 'borrador' COMMENT 'Estado del proceso',
    `id_usuario_creacion` INT NOT NULL,
    `id_usuario_validacion` INT NULL,
    `fecha_validacion` DATETIME NULL,
    `fecha_exportacion` DATETIME NULL,
    `archivo_txt_generado` VARCHAR(255) NULL COMMENT 'Nombre del archivo TXT exportado',
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_subcarpeta`) REFERENCES `subcarpetas_imss`(`id_subcarpeta`) ON DELETE CASCADE,
    FOREIGN KEY (`id_usuario_creacion`) REFERENCES `usuarios`(`id_usuario`),
    FOREIGN KEY (`id_usuario_validacion`) REFERENCES `usuarios`(`id_usuario`),
    INDEX `idx_subcarpeta` (`id_subcarpeta`),
    INDEX `idx_tipo` (`tipo`),
    INDEX `idx_estado` (`estado`),
    INDEX `idx_fecha_movimiento` (`fecha_movimiento`),
    INDEX `idx_usuario_creacion` (`id_usuario_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tablas de movimientos IMSS (altas/bajas)';

-- Alumnos dentro de una tabla de movimientos
DROP TABLE IF EXISTS `tabla_alumnos`;
CREATE TABLE `tabla_alumnos` (
    `id_registro` INT PRIMARY KEY AUTO_INCREMENT,
    `id_tabla` INT NOT NULL,
    `numero_cuenta` INT NOT NULL COMMENT 'Número secuencial dentro de la tabla',
    `numero_afiliacion` VARCHAR(11) NOT NULL COMMENT 'NSS del alumno',
    `digito_verificador` CHAR(1) NULL,
    `apellido_paterno` VARCHAR(100) NOT NULL,
    `apellido_materno` VARCHAR(100) NULL,
    `nombres` VARCHAR(100) NOT NULL,
    `curp` VARCHAR(18) NOT NULL,
    `tiene_errores` BOOLEAN DEFAULT FALSE,
    `errores_detalle` TEXT NULL COMMENT 'Detalle de errores encontrados',
    `datos_originales` JSON NULL COMMENT 'Datos originales del Excel',
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_tabla`) REFERENCES `tablas_movimientos`(`id_tabla`) ON DELETE CASCADE,
    INDEX `idx_tabla` (`id_tabla`),
    INDEX `idx_nss` (`numero_afiliacion`),
    INDEX `idx_curp` (`curp`),
    INDEX `idx_errores` (`tiene_errores`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Alumnos dentro de cada tabla de movimientos';

-- =============================================
-- PARTE 5: TABLAS DE OPERACIONES LEGACY
-- =============================================

-- Importaciones de Excel (legacy)
DROP TABLE IF EXISTS `importaciones`;
CREATE TABLE `importaciones` (
    `id_importacion` INT PRIMARY KEY AUTO_INCREMENT,
    `tipo` ENUM('alta', 'baja') NOT NULL,
    `archivo_nombre` VARCHAR(255) NOT NULL,
    `archivo_ruta` VARCHAR(500),
    `total_registros` INT DEFAULT 0,
    `registros_validos` INT DEFAULT 0,
    `registros_error` INT DEFAULT 0,
    `estado` ENUM('procesando', 'completado', 'error') DEFAULT 'procesando',
    `id_usuario` INT NOT NULL,
    `fecha_importacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Detalle de importación (legacy)
DROP TABLE IF EXISTS `importacion_detalle`;
CREATE TABLE `importacion_detalle` (
    `id_detalle` INT PRIMARY KEY AUTO_INCREMENT,
    `id_importacion` INT NOT NULL,
    `fila_excel` INT,
    `datos_json` JSON,
    `valido` BOOLEAN DEFAULT FALSE,
    `errores` TEXT,
    `id_alumno_creado` INT,
    FOREIGN KEY (`id_importacion`) REFERENCES `importaciones`(`id_importacion`) ON DELETE CASCADE,
    FOREIGN KEY (`id_alumno_creado`) REFERENCES `alumnos`(`id_alumno`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Movimientos individuales (legacy)
DROP TABLE IF EXISTS `movimientos`;
CREATE TABLE `movimientos` (
    `id_movimiento` INT PRIMARY KEY AUTO_INCREMENT,
    `tipo` ENUM('alta', 'baja', 'edicion') NOT NULL,
    `id_alumno` INT NOT NULL,
    `estado` ENUM('pendiente', 'validado', 'rechazado', 'exportado') DEFAULT 'pendiente',
    `id_motivo_baja` INT NULL COMMENT 'Solo para bajas',
    `observaciones` TEXT,
    `fecha_movimiento` DATE NOT NULL,
    `id_usuario_registro` INT NOT NULL,
    `id_usuario_validacion` INT,
    `fecha_validacion` DATETIME,
    `id_exportacion` INT,
    `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_alumno`) REFERENCES `alumnos`(`id_alumno`),
    FOREIGN KEY (`id_motivo_baja`) REFERENCES `motivos_bajas`(`id_motivo`),
    FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuarios`(`id_usuario`),
    FOREIGN KEY (`id_usuario_validacion`) REFERENCES `usuarios`(`id_usuario`),
    INDEX `idx_estado` (`estado`),
    INDEX `idx_tipo` (`tipo`),
    INDEX `idx_fecha` (`fecha_movimiento`),
    INDEX `idx_alumno` (`id_alumno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exportaciones TXT (legacy)
DROP TABLE IF EXISTS `exportaciones`;
CREATE TABLE `exportaciones` (
    `id_exportacion` INT PRIMARY KEY AUTO_INCREMENT,
    `tipo` ENUM('alta', 'baja') NOT NULL,
    `archivo_nombre` VARCHAR(255) NOT NULL,
    `archivo_ruta` VARCHAR(500),
    `total_registros` INT DEFAULT 0,
    `id_usuario` INT NOT NULL,
    `fecha_exportacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- PARTE 6: TABLAS DE SISTEMA
-- =============================================

-- Notificaciones internas
DROP TABLE IF EXISTS `notificaciones`;
CREATE TABLE `notificaciones` (
    `id_notificacion` INT PRIMARY KEY AUTO_INCREMENT,
    `id_usuario_destino` INT NOT NULL COMMENT 'Usuario que recibe la notificación',
    `id_usuario_origen` INT NULL COMMENT 'Usuario que genera la notificación',
    `tipo` VARCHAR(50) NOT NULL DEFAULT 'info' COMMENT 'Tipos: exportacion_txt, alerta_problema, alerta_aprobado, info',
    `titulo` VARCHAR(255) NOT NULL,
    `mensaje` TEXT,
    `leida` BOOLEAN DEFAULT FALSE,
    `estado` ENUM('nueva', 'vista', 'revisada', 'problema') DEFAULT 'nueva',
    `referencia_tipo` VARCHAR(50) NULL COMMENT 'Tabla referenciada: tablas_movimientos, etc.',
    `referencia_id` INT NULL COMMENT 'ID del registro referenciado',
    `datos_extra` JSON NULL COMMENT 'Datos adicionales en formato JSON',
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `fecha_lectura` DATETIME NULL,
    FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuarios`(`id_usuario`) ON DELETE CASCADE,
    FOREIGN KEY (`id_usuario_origen`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL,
    INDEX `idx_destino_leida` (`id_usuario_destino`, `leida`),
    INDEX `idx_tipo` (`tipo`),
    INDEX `idx_fecha` (`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Notificaciones internas del sistema';

-- Incidencias/Reportes de estudiantes
DROP TABLE IF EXISTS `incidencias`;
CREATE TABLE `incidencias` (
    `id_incidencia` INT PRIMARY KEY AUTO_INCREMENT,
    `id_alumno` INT,
    `id_usuario_reporta` INT NOT NULL,
    `tipo` ENUM('datos_incorrectos', 'falta_documento', 'otro') NOT NULL,
    `descripcion` TEXT NOT NULL,
    `evidencia_ruta` VARCHAR(500),
    `estado` ENUM('pendiente', 'en_proceso', 'resuelto', 'rechazado') DEFAULT 'pendiente',
    `id_usuario_atiende` INT,
    `respuesta` TEXT,
    `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `fecha_atencion` DATETIME,
    FOREIGN KEY (`id_alumno`) REFERENCES `alumnos`(`id_alumno`) ON DELETE SET NULL,
    FOREIGN KEY (`id_usuario_reporta`) REFERENCES `usuarios`(`id_usuario`),
    FOREIGN KEY (`id_usuario_atiende`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Incidencias reportadas por estudiantes';

-- Bitácora de auditoría
DROP TABLE IF EXISTS `bitacora`;
CREATE TABLE `bitacora` (
    `id_bitacora` INT PRIMARY KEY AUTO_INCREMENT,
    `id_usuario` INT,
    `accion` VARCHAR(100) NOT NULL,
    `detalle` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(500),
    `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL,
    INDEX `idx_accion` (`accion`),
    INDEX `idx_fecha` (`fecha`),
    INDEX `idx_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Bitácora de auditoría del sistema';

-- Configuración patronal IMSS
DROP TABLE IF EXISTS `configuracion_patronal`;
CREATE TABLE `configuracion_patronal` (
    `id_config` INT PRIMARY KEY AUTO_INCREMENT,
    `registro_patronal` VARCHAR(15) NOT NULL,
    `digito_verificador` CHAR(1) NOT NULL,
    `umf_alta` VARCHAR(10) DEFAULT '001',
    `umf_baja` VARCHAR(10) DEFAULT '000',
    `codigo_operacion_alta` VARCHAR(5) DEFAULT '08',
    `codigo_operacion_baja` VARCHAR(5) DEFAULT '02',
    `prefijo_alta` VARCHAR(20) DEFAULT '000000      210',
    `prefijo_baja` VARCHAR(20) DEFAULT '000000000000000',
    `codigo_institucion` VARCHAR(10) DEFAULT '01402',
    `activo` BOOLEAN DEFAULT TRUE,
    `fecha_modificacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `id_usuario_modificacion` INT,
    FOREIGN KEY (`id_usuario_modificacion`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Configuración del registro patronal IMSS';

-- Configuración general del sistema
DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion` (
    `id_config` INT PRIMARY KEY AUTO_INCREMENT,
    `tipo` VARCHAR(50) NOT NULL COMMENT 'Categoría: smtp, sistema, notificaciones, etc.',
    `clave` VARCHAR(100) NOT NULL,
    `valor` TEXT,
    `fecha_modificacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `id_usuario_modificacion` INT,
    UNIQUE KEY `uk_tipo_clave` (`tipo`, `clave`),
    FOREIGN KEY (`id_usuario_modificacion`) REFERENCES `usuarios`(`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Configuración general del sistema';

-- =============================================
-- PARTE 7: DATOS INICIALES
-- =============================================

-- Roles del sistema
INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`, `permisos`) VALUES
(1, 'Superadmin', 'Acceso total al sistema. Gestiona usuarios, roles, catálogos y configuración.', '{"all": true}'),
(2, 'Jefa de Servicios Escolares', 'Valida movimientos, exporta archivos IMSS, gestiona personal de SE.', '{"validar": true, "exportar": true, "reportes": true}'),
(3, 'Admin Servicios Escolares', 'Registra altas/bajas, importa Excel, crea carpetas, exporta TXT.', '{"importar": true, "exportar": true, "carpetas": true}'),
(4, 'Admin IMSS', 'Consulta reportes y puede exportar archivos TXT (solo lectura).', '{"reportes": true, "exportar_lectura": true}'),
(5, 'Estudiante', 'Consulta sus datos y puede reportar errores en su información.', '{"ver_datos": true, "reportar": true}');

-- Niveles educativos
INSERT INTO `nivel` (`nombre`, `descripcion`) VALUES
('Licenciatura', 'Programas de nivel superior'),
('Maestría', 'Programas de posgrado'),
('Doctorado', 'Programas de doctorado'),
('Técnico Superior', 'Programas técnicos');

-- Modalidades
INSERT INTO `modalidad` (`nombre`, `descripcion`) VALUES
('Escolarizada', 'Modalidad presencial con horario fijo'),
('Semiescolarizada', 'Modalidad mixta presencial y a distancia'),
('A Distancia', 'Modalidad completamente en línea');

-- Semestres
INSERT INTO `semestre` (`numero`, `nombre`) VALUES
(1, 'Primer Semestre'),
(2, 'Segundo Semestre'),
(3, 'Tercer Semestre'),
(4, 'Cuarto Semestre'),
(5, 'Quinto Semestre'),
(6, 'Sexto Semestre'),
(7, 'Séptimo Semestre'),
(8, 'Octavo Semestre'),
(9, 'Noveno Semestre'),
(10, 'Décimo Semestre'),
(11, 'Onceavo Semestre'),
(12, 'Doceavo Semestre');

-- Motivos de baja
INSERT INTO `motivos_bajas` (`clave`, `descripcion`) VALUES
('BAJ001', 'Baja temporal por motivos personales'),
('BAJ002', 'Baja definitiva voluntaria'),
('BAJ003', 'Baja por término de estudios'),
('BAJ004', 'Baja por incumplimiento académico'),
('BAJ005', 'Baja por cambio de institución'),
('BAJ006', 'Baja por egreso/titulación');

-- Periodos escolares
INSERT INTO `periodo_escolar` (`nombre`, `fecha_inicio`, `fecha_fin`, `activo`) VALUES
('Enero-Junio 2025', '2025-01-13', '2025-06-20', 0),
('Agosto-Diciembre 2025', '2025-08-11', '2025-12-12', 0),
('Enero-Junio 2026', '2026-01-13', '2026-06-19', 1),
('Agosto-Diciembre 2026', '2026-08-10', '2026-12-11', 0);

-- Carreras de ejemplo (Instituto Tecnológico de Chetumal)
INSERT INTO `carreras` (`clave`, `nombre`, `id_nivel`) VALUES
('ISC', 'Ingeniería en Sistemas Computacionales', 1),
('IGE', 'Ingeniería en Gestión Empresarial', 1),
('IIA', 'Ingeniería en Industrias Alimentarias', 1),
('LAE', 'Licenciatura en Administración', 1),
('IC', 'Ingeniería Civil', 1),
('IQ', 'Ingeniería Química', 1),
('IE', 'Ingeniería Electromecánica', 1),
('IAMB', 'Ingeniería Ambiental', 1),
('LTUR', 'Licenciatura en Turismo', 1),
('LGAST', 'Licenciatura en Gastronomía', 1);

-- Configuración patronal inicial (Instituto Tecnológico de Chetumal)
INSERT INTO `configuracion_patronal` 
(`registro_patronal`, `digito_verificador`, `umf_alta`, `umf_baja`, `codigo_operacion_alta`, `codigo_operacion_baja`, `prefijo_alta`, `prefijo_baja`, `codigo_institucion`, `activo`) 
VALUES 
('E292977432', '1', '001', '000', '08', '02', '000000      210', '000000000000000', '01402', 1);

-- Configuración SMTP inicial
INSERT INTO `configuracion` (`tipo`, `clave`, `valor`) VALUES
('smtp', 'host', 'smtp.gmail.com'),
('smtp', 'port', '587'),
('smtp', 'username', ''),
('smtp', 'password', ''),
('smtp', 'from_email', ''),
('smtp', 'from_name', 'SIAE-IMSS'),
('smtp', 'encryption', 'tls'),
('sistema', 'nombre_institucion', 'Instituto Tecnológico de Chetumal'),
('sistema', 'version', '1.5'),
('notificaciones', 'email_activo', '1'),
('notificaciones', 'email_jefa_servicios', '');

-- Usuario administrador inicial (contraseña: password)
INSERT INTO `usuarios` (`username`, `email`, `password_hash`, `nombre_completo`, `id_rol`, `activo`) VALUES
('admin', 'admin@institucion.edu.mx', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador del Sistema', 1, 1);

-- =============================================
-- PARTE 8: VISTAS ÚTILES (OPCIONAL)
-- =============================================

-- Vista de tablas con información completa
CREATE OR REPLACE VIEW `v_tablas_completas` AS
SELECT 
    t.id_tabla,
    t.nombre as tabla_nombre,
    t.tipo,
    t.estado,
    t.fecha_movimiento,
    t.total_registros,
    t.registros_con_errores,
    s.id_subcarpeta,
    s.nombre as subcarpeta_nombre,
    c.id_carpeta,
    c.nombre as carpeta_nombre,
    u.nombre_completo as creado_por,
    t.fecha_creacion
FROM tablas_movimientos t
INNER JOIN subcarpetas_imss s ON t.id_subcarpeta = s.id_subcarpeta
INNER JOIN carpetas_imss c ON s.id_carpeta = c.id_carpeta
LEFT JOIN usuarios u ON t.id_usuario_creacion = u.id_usuario
WHERE s.activo = 1 AND c.activo = 1;

-- Vista de notificaciones con nombres
CREATE OR REPLACE VIEW `v_notificaciones` AS
SELECT 
    n.*,
    ud.nombre_completo as nombre_destino,
    uo.nombre_completo as nombre_origen
FROM notificaciones n
LEFT JOIN usuarios ud ON n.id_usuario_destino = ud.id_usuario
LEFT JOIN usuarios uo ON n.id_usuario_origen = uo.id_usuario;

-- =============================================
-- FIN DEL SCRIPT
-- =============================================

SET FOREIGN_KEY_CHECKS = 1;

SELECT ' Base de datos SIAE-IMSS creada correctamente' AS resultado;
SELECT 'Usuario admin creado con contraseña: password' AS nota;
