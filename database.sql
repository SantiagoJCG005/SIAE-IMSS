-- =============================================
-- SIAE-IMSS - Script de Base de Datos
-- Sistema de Altas y Bajas IMSS
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-06:00";

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS `siae_imss` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `siae_imss`;

-- =============================================
-- TABLAS DE CATÁLOGOS
-- =============================================

-- Roles del sistema
CREATE TABLE `roles` (
    `id_rol` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(50) NOT NULL,
    `descripcion` TEXT,
    `activo` BOOLEAN DEFAULT TRUE,
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Niveles educativos
CREATE TABLE `nivel` (
    `id_nivel` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `activo` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carreras
CREATE TABLE `carreras` (
    `id_carrera` INT PRIMARY KEY AUTO_INCREMENT,
    `clave` VARCHAR(20),
    `nombre` VARCHAR(150) NOT NULL,
    `id_nivel` INT,
    `activo` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`id_nivel`) REFERENCES `nivel`(`id_nivel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Modalidades
CREATE TABLE `modalidad` (
    `id_modalidad` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `activo` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relación carrera-modalidad
CREATE TABLE `carrera_modalidad` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `id_carrera` INT,
    `id_modalidad` INT,
    `activo` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`id_carrera`) REFERENCES `carreras`(`id_carrera`),
    FOREIGN KEY (`id_modalidad`) REFERENCES `modalidad`(`id_modalidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Semestres
CREATE TABLE `semestre` (
    `id_semestre` INT PRIMARY KEY AUTO_INCREMENT,
    `numero` INT NOT NULL,
    `nombre` VARCHAR(50) NOT NULL,
    `activo` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Periodos escolares
CREATE TABLE `periodo_escolar` (
    `id_periodo` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NOT NULL,
    `activo` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Motivos de baja
CREATE TABLE `motivos_bajas` (
    `id_motivo` INT PRIMARY KEY AUTO_INCREMENT,
    `clave` VARCHAR(20),
    `descripcion` VARCHAR(255) NOT NULL,
    `activo` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLAS DE USUARIOS
-- =============================================

-- Usuarios del sistema
CREATE TABLE `usuarios` (
    `id_usuario` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `nombre_completo` VARCHAR(150) NOT NULL,
    `id_rol` INT NOT NULL,
    `activo` BOOLEAN DEFAULT TRUE,
    `ultimo_login` DATETIME,
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `numero_control` VARCHAR(20) NULL COMMENT 'Solo para rol estudiante',
    FOREIGN KEY (`id_rol`) REFERENCES `roles`(`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reset de contraseñas
CREATE TABLE `password_resets` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `email` VARCHAR(100) NOT NULL,
    `token` VARCHAR(100) NOT NULL,
    `fecha_expiracion` DATETIME NOT NULL,
    `usado` BOOLEAN DEFAULT FALSE,
    `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (`email`),
    INDEX (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLAS DE ALUMNOS
-- =============================================

-- Alumnos
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
    FOREIGN KEY (`id_carrera`) REFERENCES `carreras`(`id_carrera`),
    FOREIGN KEY (`id_modalidad`) REFERENCES `modalidad`(`id_modalidad`),
    FOREIGN KEY (`id_semestre`) REFERENCES `semestre`(`id_semestre`),
    FOREIGN KEY (`id_periodo`) REFERENCES `periodo_escolar`(`id_periodo`),
    INDEX (`curp`),
    INDEX (`numero_control`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datos IMSS del alumno
CREATE TABLE `datos_imss` (
    `id_datos_imss` INT PRIMARY KEY AUTO_INCREMENT,
    `id_alumno` INT NOT NULL UNIQUE,
    `nss` VARCHAR(11) NOT NULL COMMENT 'Número de Seguro Social',
    `digito_verificador` CHAR(1),
    `fecha_alta_imss` DATE,
    `fecha_baja_imss` DATE,
    `estado_imss` ENUM('pendiente', 'activo', 'baja') DEFAULT 'pendiente',
    `vigencia_inicio` DATE,
    `vigencia_fin` DATE,
    `fecha_actualizacion` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_alumno`) REFERENCES `alumnos`(`id_alumno`) ON DELETE CASCADE,
    INDEX (`nss`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLAS DE OPERACIONES
-- =============================================

-- Importaciones de Excel
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

-- Detalle de importación
CREATE TABLE `importacion_detalle` (
    `id_detalle` INT PRIMARY KEY AUTO_INCREMENT,
    `id_importacion` INT NOT NULL,
    `fila_excel` INT,
    `datos_json` JSON,
    `valido` BOOLEAN DEFAULT FALSE,
    `errores` TEXT,
    `id_alumno_creado` INT,
    FOREIGN KEY (`id_importacion`) REFERENCES `importaciones`(`id_importacion`) ON DELETE CASCADE,
    FOREIGN KEY (`id_alumno_creado`) REFERENCES `alumnos`(`id_alumno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Movimientos (altas y bajas)
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
    INDEX (`estado`),
    INDEX (`tipo`),
    INDEX (`fecha_movimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exportaciones TXT
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

-- Incidencias/Reportes
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
    FOREIGN KEY (`id_alumno`) REFERENCES `alumnos`(`id_alumno`),
    FOREIGN KEY (`id_usuario_reporta`) REFERENCES `usuarios`(`id_usuario`),
    FOREIGN KEY (`id_usuario_atiende`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bitácora de auditoría
CREATE TABLE `bitacora` (
    `id_bitacora` INT PRIMARY KEY AUTO_INCREMENT,
    `id_usuario` INT,
    `accion` VARCHAR(100) NOT NULL,
    `detalle` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(500),
    `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`),
    INDEX (`accion`),
    INDEX (`fecha`),
    INDEX (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- TABLA DE CONFIGURACIÓN PATRONAL
-- =============================================

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
    `fecha_modificacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `id_usuario_modificacion` INT,
    FOREIGN KEY (`id_usuario_modificacion`) REFERENCES `usuarios`(`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- DATOS INICIALES
-- =============================================

-- Roles
INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`) VALUES
(1, 'Superadmin', 'Acceso total al sistema. Gestiona usuarios, roles, catálogos y configuración.'),
(2, 'Jefa de Servicios Escolares', 'Valida movimientos, exporta archivos IMSS, gestiona personal de SE.'),
(3, 'Admin Servicios Escolares', 'Registra altas/bajas, importa Excel, atiende incidencias.'),
(4, 'Admin IMSS', 'Consulta reportes y puede exportar archivos TXT (opcional).'),
(5, 'Estudiante', 'Consulta sus datos y puede reportar errores en su información.');

-- Niveles
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
(9, 'Noveno Semestre');

-- Motivos de baja
INSERT INTO `motivos_bajas` (`clave`, `descripcion`) VALUES
('BAJ001', 'Baja temporal por motivos personales'),
('BAJ002', 'Baja definitiva voluntaria'),
('BAJ003', 'Baja por término de estudios'),
('BAJ004', 'Baja por incumplimiento académico'),
('BAJ005', 'Baja por cambio de institución');

-- Periodo escolar de ejemplo
INSERT INTO `periodo_escolar` (`nombre`, `fecha_inicio`, `fecha_fin`, `activo`) VALUES
('Enero-Junio 2025', '2025-01-13', '2025-06-20', 1),
('Agosto-Diciembre 2025', '2025-08-11', '2025-12-12', 0);

-- Carreras de ejemplo
INSERT INTO `carreras` (`clave`, `nombre`, `id_nivel`) VALUES
('ISC', 'Ingeniería en Sistemas Computacionales', 1),
('IGE', 'Ingeniería en Gestión Empresarial', 1),
('IIA', 'Ingeniería en Industrias Alimentarias', 1),
('LAE', 'Licenciatura en Administración', 1);

-- Configuración patronal inicial
INSERT INTO `configuracion_patronal` 
(`registro_patronal`, `digito_verificador`, `umf_alta`, `umf_baja`, `codigo_operacion_alta`, `codigo_operacion_baja`, `prefijo_alta`, `prefijo_baja`, `codigo_institucion`, `activo`) 
VALUES 
('E292977432', '1', '001', '000', '08', '02', '000000      210', '000000000000000', '01402', 1);

-- Usuario administrador inicial (contraseña: Admin123!)
INSERT INTO `usuarios` (`username`, `email`, `password_hash`, `nombre_completo`, `id_rol`, `activo`) VALUES
('admin', 'admin@institucion.edu.mx', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador del Sistema', 1, 1);

-- =============================================
-- ÍNDICES ADICIONALES PARA RENDIMIENTO
-- =============================================

CREATE INDEX idx_alumnos_carrera ON alumnos(id_carrera);
CREATE INDEX idx_alumnos_periodo ON alumnos(id_periodo);
CREATE INDEX idx_movimientos_alumno ON movimientos(id_alumno);
CREATE INDEX idx_datos_imss_estado ON datos_imss(estado_imss);

-- =============================================
-- FIN DEL SCRIPT
-- =============================================
