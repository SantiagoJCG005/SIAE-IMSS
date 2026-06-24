-- ============================================================
-- SIAE-IMSS — Dump limpio (estructura + catálogos)
-- Generado: 2026-06-24 17:18:20
-- Sin datos personales, usuarios ni datos operativos
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `siae_imss` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `siae_imss`;

-- ============================================================
-- TABLAS
-- ============================================================

-- ------------------------------------------------------------
-- Tabla: acuse_detalle [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `acuse_detalle`;
CREATE TABLE `acuse_detalle` (
  `id_detalle` int NOT NULL AUTO_INCREMENT,
  `id_acuse` int NOT NULL,
  `nss` varchar(11) NOT NULL,
  `nombre_pdf` varchar(200) DEFAULT NULL,
  `apellido_paterno_pdf` varchar(150) DEFAULT NULL,
  `apellido_materno_pdf` varchar(60) DEFAULT NULL,
  `curp_pdf` char(18) DEFAULT NULL,
  `nombre_en_pdf` varchar(200) DEFAULT NULL,
  `id_alumno` int DEFAULT NULL COMMENT 'NULL si no se encontró en sistema',
  `resultado` enum('procesado','no_encontrado','omitido_por_fecha','duplicado_nss') NOT NULL,
  `fecha_pdf` date DEFAULT NULL COMMENT 'Fecha del movimiento según el PDF',
  `fecha_existente` date DEFAULT NULL COMMENT 'Fecha del estatus anterior',
  PRIMARY KEY (`id_detalle`),
  KEY `id_acuse` (`id_acuse`),
  KEY `id_alumno` (`id_alumno`),
  KEY `nss` (`nss`),
  KEY `resultado` (`resultado`),
  CONSTRAINT `acuse_detalle_ibfk_1` FOREIGN KEY (`id_acuse`) REFERENCES `acuses_imss` (`id_acuse`) ON DELETE CASCADE,
  CONSTRAINT `acuse_detalle_ibfk_2` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`)
) ENGINE=InnoDB AUTO_INCREMENT=23926 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: acuses_imss [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `acuses_imss`;
CREATE TABLE `acuses_imss` (
  `id_acuse` int NOT NULL AUTO_INCREMENT,
  `numero_lote` varchar(50) NOT NULL COMMENT 'Número de lote del archivo IMSS',
  `tipo` enum('alta','baja') NOT NULL,
  `archivo_nombre` varchar(255) NOT NULL,
  `archivo_ruta` varchar(500) DEFAULT NULL,
  `fecha_recepcion_imss` date NOT NULL COMMENT 'Fecha interna del acuse',
  `total_registros` int DEFAULT '0',
  `procesados` int DEFAULT '0',
  `no_encontrados` int DEFAULT '0',
  `omitidos_por_fecha` int DEFAULT '0',
  `id_usuario` int NOT NULL,
  `fecha_subida` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_acuse`),
  UNIQUE KEY `numero_lote` (`numero_lote`),
  KEY `id_usuario` (`id_usuario`),
  KEY `numero_lote_2` (`numero_lote`),
  KEY `tipo` (`tipo`),
  KEY `fecha_recepcion_imss` (`fecha_recepcion_imss`),
  CONSTRAINT `acuses_imss_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: alumnos [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `alumnos`;
CREATE TABLE `alumnos` (
  `id_alumno` int NOT NULL AUTO_INCREMENT,
  `numero_control` varchar(20) NOT NULL,
  `curp` varchar(18) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido_paterno` varchar(100) NOT NULL,
  `apellido_materno` varchar(100) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `sexo` enum('H','M') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `id_carrera` int DEFAULT NULL,
  `carrera_nombre_excel` varchar(200) DEFAULT NULL,
  `id_modalidad` int DEFAULT NULL,
  `id_semestre` int DEFAULT NULL,
  `id_periodo` int DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_alumno`),
  UNIQUE KEY `numero_control` (`numero_control`),
  KEY `id_modalidad` (`id_modalidad`),
  KEY `id_semestre` (`id_semestre`),
  KEY `idx_curp` (`curp`),
  KEY `idx_numero_control` (`numero_control`),
  KEY `idx_carrera` (`id_carrera`),
  KEY `idx_periodo` (`id_periodo`),
  CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`id_carrera`) REFERENCES `carreras` (`id_carrera`) ON DELETE SET NULL,
  CONSTRAINT `alumnos_ibfk_2` FOREIGN KEY (`id_modalidad`) REFERENCES `modalidad` (`id_modalidad`) ON DELETE SET NULL,
  CONSTRAINT `alumnos_ibfk_3` FOREIGN KEY (`id_semestre`) REFERENCES `semestre` (`id_semestre`) ON DELETE SET NULL,
  CONSTRAINT `alumnos_ibfk_4` FOREIGN KEY (`id_periodo`) REFERENCES `periodo_escolar` (`id_periodo`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo maestro de alumnos';

-- ------------------------------------------------------------
-- Tabla: bitacora [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `bitacora`;
CREATE TABLE `bitacora` (
  `id_bitacora` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `detalle` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_bitacora`),
  KEY `idx_accion` (`accion`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_usuario` (`id_usuario`),
  CONSTRAINT `bitacora_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=571 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bitácora de auditoría del sistema';

-- ------------------------------------------------------------
-- Tabla: carpetas_imss [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `carpetas_imss`;
CREATE TABLE `carpetas_imss` (
  `id_carpeta` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `activo` tinyint(1) DEFAULT '1',
  `id_usuario_creacion` int NOT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_carpeta`),
  KEY `id_usuario_creacion` (`id_usuario_creacion`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `carpetas_imss_ibfk_1` FOREIGN KEY (`id_usuario_creacion`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Carpetas principales para organizar movimientos IMSS';

-- ------------------------------------------------------------
-- Tabla: carrera_modalidad [CATÁLOGO]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `carrera_modalidad`;
CREATE TABLE `carrera_modalidad` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_carrera` int DEFAULT NULL,
  `id_modalidad` int DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `id_carrera` (`id_carrera`),
  KEY `id_modalidad` (`id_modalidad`),
  CONSTRAINT `carrera_modalidad_ibfk_1` FOREIGN KEY (`id_carrera`) REFERENCES `carreras` (`id_carrera`) ON DELETE CASCADE,
  CONSTRAINT `carrera_modalidad_ibfk_2` FOREIGN KEY (`id_modalidad`) REFERENCES `modalidad` (`id_modalidad`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: carreras [CATÁLOGO]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `carreras`;
CREATE TABLE `carreras` (
  `id_carrera` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(20) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `id_nivel` int DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_carrera`),
  KEY `id_nivel` (`id_nivel`),
  CONSTRAINT `carreras_ibfk_1` FOREIGN KEY (`id_nivel`) REFERENCES `nivel` (`id_nivel`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de carreras';

INSERT INTO `carreras` (`id_carrera`, `clave`, `nombre`, `id_nivel`, `activo`) VALUES
  ('1', 'ISC', 'Ingeniería en Sistemas Computacionales', '1', '1'),
  ('2', 'IGE', 'Ingeniería en Gestión Empresarial', '1', '1'),
  ('3', 'IIA', 'Ingeniería en Industrias Alimentarias', '1', '1'),
  ('4', 'LAE', 'Licenciatura en Administración', '1', '1'),
  ('5', 'IC', 'Ingeniería Civil', '1', '1'),
  ('6', 'IQ', 'Ingeniería Química', '1', '1'),
  ('7', 'IE', 'Ingeniería Electromecánica', '1', '1'),
  ('8', 'IAMB', 'Ingeniería Ambiental', '1', '1'),
  ('10', 'LGAST', 'Licenciatura en Gastronomía', '1', '1'),
  ('13', 'IND', 'Ingeniería Industrial', '1', '1'),
  ('14', 'INDe', 'Ingeniería Industrial', '3', '1');

-- ------------------------------------------------------------
-- Tabla: configuracion [CATÁLOGO]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion` (
  `id_config` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) NOT NULL COMMENT 'Categoría: smtp, sistema, notificaciones, etc.',
  `clave` varchar(100) NOT NULL,
  `valor` text,
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_usuario_modificacion` int DEFAULT NULL,
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `uk_tipo_clave` (`tipo`,`clave`),
  KEY `id_usuario_modificacion` (`id_usuario_modificacion`),
  CONSTRAINT `configuracion_ibfk_1` FOREIGN KEY (`id_usuario_modificacion`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuración general del sistema';

INSERT INTO `configuracion` (`id_config`, `tipo`, `clave`, `valor`, `fecha_modificacion`, `id_usuario_modificacion`) VALUES
  ('1', 'smtp', 'host', 'smtp.gmail.com', '2026-04-27 20:44:22', NULL),
  ('2', 'smtp', 'port', '587', '2026-04-27 20:44:22', NULL),
  ('3', 'smtp', 'username', '', '2026-04-27 20:44:22', NULL),
  ('4', 'smtp', 'password', '', '2026-04-27 20:44:22', NULL),
  ('5', 'smtp', 'from_email', '', '2026-04-27 20:44:22', NULL),
  ('6', 'smtp', 'from_name', 'SIAE-IMSS', '2026-04-27 20:44:22', NULL),
  ('7', 'smtp', 'encryption', 'tls', '2026-04-27 20:44:22', NULL),
  ('8', 'sistema', 'nombre_institucion', 'Instituto Tecnológico de Chetumal', '2026-04-27 20:44:22', NULL),
  ('9', 'sistema', 'version', '1.5', '2026-04-27 20:44:22', NULL),
  ('10', 'notificaciones', 'email_activo', '1', '2026-04-27 20:44:22', NULL),
  ('11', 'notificaciones', 'email_jefa_servicios', '', '2026-04-27 20:44:22', NULL),
  ('12', 'general', 'nombre_institucion', 'Instituto Tecnológico de Chetumal', '2026-04-27 21:45:02', '1'),
  ('13', 'general', 'zona_horaria', 'America/Mexico_City', '2026-04-27 21:45:02', '1'),
  ('14', 'academico', 'periodo_activo', '2', '2026-04-27 21:45:11', '1'),
  ('15', 'academico', 'formato_control', 'AAXXNNNN', '2026-04-27 21:45:11', '1'),
  ('16', 'academico', 'dias_gracia', '5', '2026-04-27 21:45:11', '1'),
  ('17', 'smtp', 'smtp_servidor', 'smtp.gmail.com', '2026-04-27 21:47:43', '1'),
  ('18', 'smtp', 'smtp_puerto', '587', '2026-04-27 21:47:43', '1'),
  ('19', 'smtp', 'smtp_usuario', 'santiagoelijar@gmail.com', '2026-04-27 21:47:43', '1'),
  ('20', 'smtp', 'smtp_password', 'joft kpco uhnm aqmz', '2026-04-27 21:47:43', '1'),
  ('21', 'smtp', 'smtp_encriptacion', 'tls', '2026-04-27 21:47:43', '1'),
  ('22', 'smtp', 'smtp_email_remitente', 'javierelijar@gmail.com', '2026-04-27 21:47:43', '1'),
  ('23', 'smtp', 'smtp_nombre_remitente', 'SIAE-IMSS', '2026-04-27 21:47:43', '1');

-- ------------------------------------------------------------
-- Tabla: configuracion_patronal [CATÁLOGO]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `configuracion_patronal`;
CREATE TABLE `configuracion_patronal` (
  `id_config` int NOT NULL AUTO_INCREMENT,
  `registro_patronal` varchar(15) NOT NULL,
  `digito_verificador` char(1) NOT NULL,
  `umf_alta` varchar(10) DEFAULT '001',
  `umf_baja` varchar(10) DEFAULT '000',
  `codigo_operacion_alta` varchar(5) DEFAULT '08',
  `codigo_operacion_baja` varchar(5) DEFAULT '02',
  `prefijo_alta` varchar(20) DEFAULT '000000      210',
  `prefijo_baja` varchar(20) DEFAULT '000000000000000',
  `codigo_institucion` varchar(10) DEFAULT '01402',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_usuario_modificacion` int DEFAULT NULL,
  PRIMARY KEY (`id_config`),
  KEY `id_usuario_modificacion` (`id_usuario_modificacion`),
  CONSTRAINT `configuracion_patronal_ibfk_1` FOREIGN KEY (`id_usuario_modificacion`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuración del registro patronal IMSS';

INSERT INTO `configuracion_patronal` (`id_config`, `registro_patronal`, `digito_verificador`, `umf_alta`, `umf_baja`, `codigo_operacion_alta`, `codigo_operacion_baja`, `prefijo_alta`, `prefijo_baja`, `codigo_institucion`, `activo`, `fecha_modificacion`, `id_usuario_modificacion`) VALUES
  ('1', 'E292977432', '1', '001', '000', '08', '02', '000000      210', '000000000000000', '01402', '1', '2026-04-27 20:44:22', NULL);

-- ------------------------------------------------------------
-- Tabla: datos_imss [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `datos_imss`;
CREATE TABLE `datos_imss` (
  `id_datos_imss` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `nss` varchar(11) NOT NULL COMMENT 'Número de Seguro Social (11 dígitos)',
  `digito_verificador` char(1) DEFAULT NULL,
  `fecha_alta_imss` date DEFAULT NULL,
  `fecha_baja_imss` date DEFAULT NULL,
  `estado_imss` enum('pendiente','activo','baja') DEFAULT 'pendiente',
  `vigencia_inicio` date DEFAULT NULL,
  `vigencia_fin` date DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_datos_imss`),
  UNIQUE KEY `id_alumno` (`id_alumno`),
  KEY `idx_nss` (`nss`),
  KEY `idx_estado` (`estado_imss`),
  CONSTRAINT `datos_imss_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Datos de IMSS por alumno';

-- ------------------------------------------------------------
-- Tabla: estatus_imss_alumnos [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `estatus_imss_alumnos`;
CREATE TABLE `estatus_imss_alumnos` (
  `id_estatus` int NOT NULL AUTO_INCREMENT,
  `nss` varchar(11) NOT NULL,
  `id_alumno` int DEFAULT NULL,
  `estatus` enum('alta','baja') NOT NULL,
  `fecha_movimiento` date NOT NULL COMMENT 'Fecha del movimiento más reciente confirmado por IMSS',
  `id_acuse_origen` int DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_estatus`),
  UNIQUE KEY `nss` (`nss`),
  KEY `id_alumno` (`id_alumno`),
  KEY `id_acuse_origen` (`id_acuse_origen`),
  KEY `nss_2` (`nss`),
  KEY `estatus` (`estatus`),
  CONSTRAINT `estatus_imss_alumnos_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`),
  CONSTRAINT `estatus_imss_alumnos_ibfk_2` FOREIGN KEY (`id_acuse_origen`) REFERENCES `acuses_imss` (`id_acuse`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: exportaciones [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `exportaciones`;
CREATE TABLE `exportaciones` (
  `id_exportacion` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('alta','baja') NOT NULL,
  `archivo_nombre` varchar(255) NOT NULL,
  `archivo_ruta` varchar(500) DEFAULT NULL,
  `total_registros` int DEFAULT '0',
  `id_usuario` int NOT NULL,
  `fecha_exportacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_exportacion`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `exportaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: importacion_detalle [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `importacion_detalle`;
CREATE TABLE `importacion_detalle` (
  `id_detalle` int NOT NULL AUTO_INCREMENT,
  `id_importacion` int NOT NULL,
  `fila_excel` int DEFAULT NULL,
  `datos_json` json DEFAULT NULL,
  `valido` tinyint(1) DEFAULT '0',
  `errores` text,
  `id_alumno_creado` int DEFAULT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `id_importacion` (`id_importacion`),
  KEY `id_alumno_creado` (`id_alumno_creado`),
  CONSTRAINT `importacion_detalle_ibfk_1` FOREIGN KEY (`id_importacion`) REFERENCES `importaciones` (`id_importacion`) ON DELETE CASCADE,
  CONSTRAINT `importacion_detalle_ibfk_2` FOREIGN KEY (`id_alumno_creado`) REFERENCES `alumnos` (`id_alumno`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: importaciones [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `importaciones`;
CREATE TABLE `importaciones` (
  `id_importacion` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('alta','baja') NOT NULL,
  `archivo_nombre` varchar(255) NOT NULL,
  `archivo_ruta` varchar(500) DEFAULT NULL,
  `total_registros` int DEFAULT '0',
  `registros_validos` int DEFAULT '0',
  `registros_error` int DEFAULT '0',
  `estado` enum('procesando','completado','error') DEFAULT 'procesando',
  `id_usuario` int NOT NULL,
  `fecha_importacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_importacion`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `importaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: incidencias [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `incidencias`;
CREATE TABLE `incidencias` (
  `id_incidencia` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int DEFAULT NULL,
  `id_usuario_reporta` int NOT NULL,
  `tipo` enum('datos_incorrectos','falta_documento','otro') NOT NULL,
  `descripcion` text NOT NULL,
  `evidencia_ruta` varchar(500) DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','resuelto','rechazado') DEFAULT 'pendiente',
  `id_usuario_atiende` int DEFAULT NULL,
  `respuesta` text,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_atencion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_incidencia`),
  KEY `id_alumno` (`id_alumno`),
  KEY `id_usuario_reporta` (`id_usuario_reporta`),
  KEY `id_usuario_atiende` (`id_usuario_atiende`),
  CONSTRAINT `incidencias_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_2` FOREIGN KEY (`id_usuario_reporta`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `incidencias_ibfk_3` FOREIGN KEY (`id_usuario_atiende`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Incidencias reportadas por estudiantes';

-- ------------------------------------------------------------
-- Tabla: modalidad [CATÁLOGO]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `modalidad`;
CREATE TABLE `modalidad` (
  `id_modalidad` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_modalidad`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Modalidades de estudio';

INSERT INTO `modalidad` (`id_modalidad`, `nombre`, `descripcion`, `activo`) VALUES
  ('1', 'Escolarizada', 'Modalidad presencial con horario fijo', '1'),
  ('2', 'Semiescolarizada', 'Modalidad mixta presencial y a distancia', '1'),
  ('3', 'A Distancia', 'Modalidad completamente en línea', '1');

-- ------------------------------------------------------------
-- Tabla: motivos_bajas [CATÁLOGO]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `motivos_bajas`;
CREATE TABLE `motivos_bajas` (
  `id_motivo` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(20) DEFAULT NULL,
  `descripcion` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_motivo`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de motivos de baja IMSS';

INSERT INTO `motivos_bajas` (`id_motivo`, `clave`, `descripcion`, `activo`) VALUES
  ('1', 'BAJ001', 'Baja temporal por motivos personales', '1'),
  ('2', 'BAJ002', 'Baja definitiva voluntaria', '1'),
  ('3', 'BAJ003', 'Baja por término de estudios', '1'),
  ('4', 'BAJ004', 'Baja por incumplimiento académico', '1'),
  ('5', 'BAJ005', 'Baja por cambio de institución', '1'),
  ('6', 'BAJ006', 'Baja por egreso/titulación', '1');

-- ------------------------------------------------------------
-- Tabla: movimientos [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `movimientos`;
CREATE TABLE `movimientos` (
  `id_movimiento` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('alta','baja','edicion') NOT NULL,
  `id_alumno` int NOT NULL,
  `estado` enum('pendiente','validado','rechazado','exportado') DEFAULT 'pendiente',
  `id_motivo_baja` int DEFAULT NULL COMMENT 'Solo para bajas',
  `observaciones` text,
  `fecha_movimiento` date NOT NULL,
  `id_usuario_registro` int NOT NULL,
  `id_usuario_validacion` int DEFAULT NULL,
  `fecha_validacion` datetime DEFAULT NULL,
  `id_exportacion` int DEFAULT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_movimiento`),
  KEY `id_motivo_baja` (`id_motivo_baja`),
  KEY `id_usuario_registro` (`id_usuario_registro`),
  KEY `id_usuario_validacion` (`id_usuario_validacion`),
  KEY `idx_estado` (`estado`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha` (`fecha_movimiento`),
  KEY `idx_alumno` (`id_alumno`),
  CONSTRAINT `movimientos_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`),
  CONSTRAINT `movimientos_ibfk_2` FOREIGN KEY (`id_motivo_baja`) REFERENCES `motivos_bajas` (`id_motivo`),
  CONSTRAINT `movimientos_ibfk_3` FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `movimientos_ibfk_4` FOREIGN KEY (`id_usuario_validacion`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: nivel [CATÁLOGO]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `nivel`;
CREATE TABLE `nivel` (
  `id_nivel` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_nivel`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Niveles educativos (Licenciatura, Maestría, etc.)';

INSERT INTO `nivel` (`id_nivel`, `nombre`, `descripcion`, `activo`) VALUES
  ('1', 'Licenciatura', 'Programas de nivel superior', '1'),
  ('2', 'Maestría', 'Programas de posgrado', '1'),
  ('3', 'Doctorado', 'Programas de doctorado', '1'),
  ('4', 'Técnico Superior', 'Programas técnicos', '1');

-- ------------------------------------------------------------
-- Tabla: notificaciones [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `notificaciones`;
CREATE TABLE `notificaciones` (
  `id_notificacion` int NOT NULL AUTO_INCREMENT,
  `id_usuario_destino` int NOT NULL COMMENT 'Usuario que recibe la notificación',
  `id_usuario_origen` int DEFAULT NULL COMMENT 'Usuario que genera la notificación',
  `tipo` varchar(50) NOT NULL DEFAULT 'info' COMMENT 'Tipos: exportacion_txt, alerta_problema, alerta_aprobado, info',
  `titulo` varchar(255) NOT NULL,
  `mensaje` text,
  `leida` tinyint(1) DEFAULT '0',
  `estado` enum('nueva','vista','revisada','problema') DEFAULT 'nueva',
  `referencia_tipo` varchar(50) DEFAULT NULL COMMENT 'Tabla referenciada: tablas_movimientos, etc.',
  `referencia_id` int DEFAULT NULL COMMENT 'ID del registro referenciado',
  `datos_extra` json DEFAULT NULL COMMENT 'Datos adicionales en formato JSON',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_lectura` datetime DEFAULT NULL,
  PRIMARY KEY (`id_notificacion`),
  KEY `id_usuario_origen` (`id_usuario_origen`),
  KEY `idx_destino_leida` (`id_usuario_destino`,`leida`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha` (`fecha_creacion`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `notificaciones_ibfk_2` FOREIGN KEY (`id_usuario_origen`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notificaciones internas del sistema';

-- ------------------------------------------------------------
-- Tabla: password_resets [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `fecha_expiracion` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT '0',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tokens para recuperación de contraseña';

-- ------------------------------------------------------------
-- Tabla: periodo_escolar [CATÁLOGO]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `periodo_escolar`;
CREATE TABLE `periodo_escolar` (
  `id_periodo` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_periodo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Periodos escolares';

INSERT INTO `periodo_escolar` (`id_periodo`, `nombre`, `fecha_inicio`, `fecha_fin`, `activo`) VALUES
  ('1', 'Enero-Junio 2025', '2025-01-13', '2025-06-20', '0'),
  ('2', 'Agosto-Diciembre 2025', '2025-08-11', '2025-12-12', '0'),
  ('3', 'Enero-Junio 2026', '2026-01-13', '2026-06-19', '1'),
  ('4', 'Agosto-Diciembre 2026', '2026-08-10', '2026-12-11', '0');

-- ------------------------------------------------------------
-- Tabla: roles [CATÁLOGO]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id_rol` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text,
  `permisos` json DEFAULT NULL COMMENT 'Permisos específicos del rol en formato JSON',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roles de usuario del sistema';

INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`, `permisos`, `activo`, `fecha_creacion`) VALUES
  ('1', 'Superadmin', 'Acceso total al sistema. Gestiona usuarios, roles, catálogos y configuración.', '{\"all\": true}', '1', '2026-04-27 20:44:22'),
  ('2', 'Jefa de Servicios Escolares', 'Valida movimientos, exporta archivos IMSS, gestiona personal de SE.', '{\"validar\": true, \"exportar\": true, \"reportes\": true}', '1', '2026-04-27 20:44:22'),
  ('3', 'Admin Servicios Escolares', 'Registra altas/bajas, importa Excel, crea carpetas, exporta TXT.', '{\"carpetas\": true, \"exportar\": true, \"importar\": true}', '1', '2026-04-27 20:44:22'),
  ('4', 'Admin IMSS', 'Consulta reportes y puede exportar archivos TXT (solo lectura).', '{\"reportes\": true, \"exportar_lectura\": true}', '1', '2026-04-27 20:44:22'),
  ('5', 'Estudiante', 'Consulta sus datos y puede reportar errores en su información.', '{\"reportar\": true, \"ver_datos\": true}', '1', '2026-04-27 20:44:22'),
  ('7', 'Local', 'Rol de visitante', '[\"ver_datos\", \"reportar_falla\"]', '1', '2026-06-03 12:46:11');

-- ------------------------------------------------------------
-- Tabla: semestre [CATÁLOGO]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `semestre`;
CREATE TABLE `semestre` (
  `id_semestre` int NOT NULL AUTO_INCREMENT,
  `numero` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_semestre`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de semestres';

INSERT INTO `semestre` (`id_semestre`, `numero`, `nombre`, `activo`) VALUES
  ('1', '1', 'Primer Semestre', '1'),
  ('2', '2', 'Segundo Semestre', '1'),
  ('3', '3', 'Tercer Semestre', '1'),
  ('4', '4', 'Cuarto Semestre', '1'),
  ('5', '5', 'Quinto Semestre', '1'),
  ('6', '6', 'Sexto Semestre', '1'),
  ('7', '7', 'Séptimo Semestre', '1'),
  ('8', '8', 'Octavo Semestre', '1'),
  ('9', '9', 'Noveno Semestre', '1'),
  ('10', '10', 'Décimo Semestre', '1'),
  ('11', '11', 'Onceavo Semestre', '1'),
  ('12', '12', 'Doceavo Semestre', '1');

-- ------------------------------------------------------------
-- Tabla: subcarpetas_imss [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `subcarpetas_imss`;
CREATE TABLE `subcarpetas_imss` (
  `id_subcarpeta` int NOT NULL AUTO_INCREMENT,
  `id_carpeta` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_subcarpeta`),
  KEY `idx_carpeta` (`id_carpeta`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `subcarpetas_imss_ibfk_1` FOREIGN KEY (`id_carpeta`) REFERENCES `carpetas_imss` (`id_carpeta`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Subcarpetas dentro de carpetas IMSS';

-- ------------------------------------------------------------
-- Tabla: tabla_alumnos [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `tabla_alumnos`;
CREATE TABLE `tabla_alumnos` (
  `id_registro` int NOT NULL AUTO_INCREMENT,
  `id_tabla` int NOT NULL,
  `numero_cuenta` int NOT NULL COMMENT 'Número secuencial dentro de la tabla',
  `numero_afiliacion` varchar(11) NOT NULL COMMENT 'NSS del alumno',
  `digito_verificador` char(1) DEFAULT NULL,
  `apellido_paterno` varchar(100) NOT NULL,
  `apellido_materno` varchar(100) DEFAULT NULL,
  `nombres` varchar(100) NOT NULL,
  `curp` varchar(18) NOT NULL,
  `tiene_errores` tinyint(1) DEFAULT '0',
  `errores_detalle` text COMMENT 'Detalle de errores encontrados',
  `datos_originales` json DEFAULT NULL COMMENT 'Datos originales del Excel',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_registro`),
  KEY `idx_tabla` (`id_tabla`),
  KEY `idx_nss` (`numero_afiliacion`),
  KEY `idx_curp` (`curp`),
  KEY `idx_errores` (`tiene_errores`),
  CONSTRAINT `tabla_alumnos_ibfk_1` FOREIGN KEY (`id_tabla`) REFERENCES `tablas_movimientos` (`id_tabla`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1420 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alumnos dentro de cada tabla de movimientos';

-- ------------------------------------------------------------
-- Tabla: tablas_movimientos [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `tablas_movimientos`;
CREATE TABLE `tablas_movimientos` (
  `id_tabla` int NOT NULL AUTO_INCREMENT,
  `id_subcarpeta` int NOT NULL,
  `tipo` enum('alta','baja') NOT NULL COMMENT 'Tipo de movimiento',
  `nombre` varchar(150) NOT NULL COMMENT 'Nombre descriptivo de la tabla',
  `fecha_movimiento` date NOT NULL COMMENT 'Fecha del movimiento IMSS',
  `archivo_origen` varchar(255) DEFAULT NULL COMMENT 'Nombre del archivo Excel importado',
  `total_registros` int DEFAULT '0',
  `registros_con_errores` int DEFAULT '0',
  `estado` enum('borrador','pendiente','validado','enviado') DEFAULT 'borrador' COMMENT 'Estado del proceso',
  `id_usuario_creacion` int NOT NULL,
  `id_usuario_validacion` int DEFAULT NULL,
  `fecha_validacion` datetime DEFAULT NULL,
  `fecha_exportacion` datetime DEFAULT NULL,
  `archivo_txt_generado` varchar(255) DEFAULT NULL COMMENT 'Nombre del archivo TXT exportado',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_tabla`),
  KEY `id_usuario_validacion` (`id_usuario_validacion`),
  KEY `idx_subcarpeta` (`id_subcarpeta`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha_movimiento` (`fecha_movimiento`),
  KEY `idx_usuario_creacion` (`id_usuario_creacion`),
  CONSTRAINT `tablas_movimientos_ibfk_1` FOREIGN KEY (`id_subcarpeta`) REFERENCES `subcarpetas_imss` (`id_subcarpeta`) ON DELETE CASCADE,
  CONSTRAINT `tablas_movimientos_ibfk_2` FOREIGN KEY (`id_usuario_creacion`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `tablas_movimientos_ibfk_3` FOREIGN KEY (`id_usuario_validacion`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tablas de movimientos IMSS (altas/bajas)';

-- ------------------------------------------------------------
-- Tabla: usuarios [solo estructura]
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `id_rol` int NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `ultimo_login` datetime DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `numero_control` varchar(20) DEFAULT NULL COMMENT 'Solo para rol estudiante',
  `curp` varchar(18) DEFAULT NULL COMMENT 'CURP del usuario (opcional)',
  `id_nivel` int DEFAULT NULL COMMENT 'Nivel educativo (solo estudiantes)',
  `id_carrera` int DEFAULT NULL COMMENT 'Carrera (solo estudiantes)',
  `id_modalidad` int DEFAULT NULL COMMENT 'Modalidad (solo estudiantes)',
  `id_semestre` int DEFAULT NULL COMMENT 'Semestre actual (solo estudiantes)',
  `id_periodo` int DEFAULT NULL COMMENT 'Periodo actual (solo estudiantes)',
  `debe_cambiar_password` tinyint(1) DEFAULT '0' COMMENT 'Forzar cambio de contraseña',
  `id_sii` int DEFAULT NULL COMMENT 'ID en sistema SII del Tecnológico',
  `sincronizado_sii` tinyint(1) DEFAULT '0' COMMENT 'Si los datos vienen del SII',
  `fecha_sync_sii` datetime DEFAULT NULL COMMENT 'Última sincronización con SII',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `id_rol` (`id_rol`),
  KEY `idx_usuarios_curp` (`curp`),
  KEY `idx_usuarios_numero_control` (`numero_control`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuarios del sistema SIAE-IMSS';

-- ------------------------------------------------------------
-- Usuario superadmin por defecto
-- username: admin | password: Admin2025!
-- CAMBIA LA CONTRASEÑA después del primer login
-- ------------------------------------------------------------
INSERT INTO `usuarios` (`id_usuario`, `username`, `email`, `password_hash`, `nombre_completo`, `id_rol`, `activo`, `debe_cambiar_password`)
VALUES (1, 'admin', 'admin@siae.local', '$2y$10$ngQKZYYGB0jZnkO9iaYCfeqdQ.WnvzQwhMWoFa5yS5eZ4JMaIcUDq', 'Administrador', 1, 1, 1);

-- ============================================================
-- VISTAS
-- ============================================================

-- Vista: v_notificaciones
DROP VIEW IF EXISTS `v_notificaciones`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_notificaciones` AS select `n`.`id_notificacion` AS `id_notificacion`,`n`.`id_usuario_destino` AS `id_usuario_destino`,`n`.`id_usuario_origen` AS `id_usuario_origen`,`n`.`tipo` AS `tipo`,`n`.`titulo` AS `titulo`,`n`.`mensaje` AS `mensaje`,`n`.`leida` AS `leida`,`n`.`estado` AS `estado`,`n`.`referencia_tipo` AS `referencia_tipo`,`n`.`referencia_id` AS `referencia_id`,`n`.`datos_extra` AS `datos_extra`,`n`.`fecha_creacion` AS `fecha_creacion`,`n`.`fecha_lectura` AS `fecha_lectura`,`ud`.`nombre_completo` AS `nombre_destino`,`uo`.`nombre_completo` AS `nombre_origen` from ((`notificaciones` `n` left join `usuarios` `ud` on((`n`.`id_usuario_destino` = `ud`.`id_usuario`))) left join `usuarios` `uo` on((`n`.`id_usuario_origen` = `uo`.`id_usuario`)));

-- Vista: v_tablas_completas
DROP VIEW IF EXISTS `v_tablas_completas`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_tablas_completas` AS select `t`.`id_tabla` AS `id_tabla`,`t`.`nombre` AS `tabla_nombre`,`t`.`tipo` AS `tipo`,`t`.`estado` AS `estado`,`t`.`fecha_movimiento` AS `fecha_movimiento`,`t`.`total_registros` AS `total_registros`,`t`.`registros_con_errores` AS `registros_con_errores`,`s`.`id_subcarpeta` AS `id_subcarpeta`,`s`.`nombre` AS `subcarpeta_nombre`,`c`.`id_carpeta` AS `id_carpeta`,`c`.`nombre` AS `carpeta_nombre`,`u`.`nombre_completo` AS `creado_por`,`t`.`fecha_creacion` AS `fecha_creacion` from (((`tablas_movimientos` `t` join `subcarpetas_imss` `s` on((`t`.`id_subcarpeta` = `s`.`id_subcarpeta`))) join `carpetas_imss` `c` on((`s`.`id_carpeta` = `c`.`id_carpeta`))) left join `usuarios` `u` on((`t`.`id_usuario_creacion` = `u`.`id_usuario`))) where ((`s`.`activo` = 1) and (`c`.`activo` = 1));

SET FOREIGN_KEY_CHECKS = 1;
-- Fin del dump
