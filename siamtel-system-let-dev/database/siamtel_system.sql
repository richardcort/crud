-- =============================================================================
-- SIAMTEL - Sistema de Información y Gestión (Módulo de Morbilidad)
-- Gobernación del Estado Lara
-- Base de Datos MariaDB 10.11 / MySQL (UTF-8 / utf8mb4)
-- =============================================================================
-- Si importas fuera de Docker y la BD aún no existe, descomenta:
-- CREATE DATABASE IF NOT EXISTS `siamtel_system`
--   CHARACTER SET utf8mb4
--   COLLATE utf8mb4_unicode_ci;

USE `siamtel_system`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1. TABLA: persona
-- Datos personales base compartidos entre personal (Usuario) y pacientes (cliente)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `persona` (
    `cedula`           VARCHAR(20)  NOT NULL,
    `nombre`           VARCHAR(100) NOT NULL,
    `apellido`         VARCHAR(100) NOT NULL,
    `fecha_nacimiento` DATE         NULL DEFAULT NULL,
    `telefono`         VARCHAR(30)  NULL DEFAULT NULL,
    `correo`           VARCHAR(120) NULL DEFAULT NULL,
    `direccion`        VARCHAR(255) NULL DEFAULT NULL,
    `sexo`             CHAR(1)      NOT NULL DEFAULT 'M',
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`cedula`),
    INDEX `idx_persona_nombre` (`nombre`, `apellido`),
    INDEX `idx_persona_correo` (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. TABLA: rol
-- Catálogo de roles del sistema
-- =============================================================================
CREATE TABLE IF NOT EXISTS `rol` (
    `id_rol`      VARCHAR(30)  NOT NULL,
    `nombre_rol`  VARCHAR(80)  NOT NULL,
    `descripcion` VARCHAR(255) NULL DEFAULT NULL,
    `estatus`     TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. TABLA: Usuario (Respetar 'U' mayúscula: consultada así en Auth y Usuario)
-- Cuentas de acceso y autenticación del sistema
-- =============================================================================
CREATE TABLE IF NOT EXISTS `Usuario` (
    `cedula`        VARCHAR(20)  NOT NULL,
    `user`          VARCHAR(60)  NOT NULL,
    `password`      VARCHAR(255) NOT NULL,
    `rol`           VARCHAR(30)  NOT NULL DEFAULT 'CLIENTE',
    `estatus`       TINYINT(1)   NOT NULL DEFAULT 1,
    `ultimo_acceso` DATETIME     NULL DEFAULT NULL,
    `tema`          VARCHAR(20)  NOT NULL DEFAULT 'light',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`cedula`),
    UNIQUE KEY `uq_usuario_user` (`user`),
    INDEX `idx_usuario_rol` (`rol`),
    CONSTRAINT `fk_usuario_rol`
        FOREIGN KEY (`rol`) REFERENCES `rol` (`id_rol`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. TABLA: submodulo
-- Catálogo de módulos y submódulos de navegación/permisos
-- =============================================================================
CREATE TABLE IF NOT EXISTS `submodulo` (
    `id`          VARCHAR(50)  NOT NULL,
    `modulo`      VARCHAR(60)  NOT NULL,
    `nombre`      VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL DEFAULT NULL,
    `icono`       VARCHAR(60)  NOT NULL DEFAULT 'bi-grid',
    `ruta`        VARCHAR(120) NOT NULL,
    `orden`       INT          NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    INDEX `idx_submodulo_modulo_orden` (`modulo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. TABLA: rol_submodulo
-- Matriz de permisos predeterminados por rol
-- =============================================================================
CREATE TABLE IF NOT EXISTS `rol_submodulo` (
    `id_rol`         VARCHAR(30) NOT NULL,
    `id_submodulo`   VARCHAR(50) NOT NULL,
    `puede_ver`      TINYINT(1)  NOT NULL DEFAULT 1,
    `puede_crear`    TINYINT(1)  NOT NULL DEFAULT 0,
    `puede_editar`   TINYINT(1)  NOT NULL DEFAULT 0,
    `puede_eliminar` TINYINT(1)  NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_rol`, `id_submodulo`),
    CONSTRAINT `fk_rol_submodulo_rol`
        FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `fk_rol_submodulo_submodulo`
        FOREIGN KEY (`id_submodulo`) REFERENCES `submodulo` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6. TABLA: usuario_submodulo
-- Excepciones / permisos personalizados por usuario
-- =============================================================================
CREATE TABLE IF NOT EXISTS `usuario_submodulo` (
    `cedula`         VARCHAR(20) NOT NULL,
    `id_submodulo`   VARCHAR(50) NOT NULL,
    `puede_ver`      TINYINT(1)  NOT NULL DEFAULT 1,
    `puede_crear`    TINYINT(1)  NOT NULL DEFAULT 0,
    `puede_editar`   TINYINT(1)  NOT NULL DEFAULT 0,
    `puede_eliminar` TINYINT(1)  NOT NULL DEFAULT 0,
    PRIMARY KEY (`cedula`, `id_submodulo`),
    CONSTRAINT `fk_usuario_submodulo_usuario`
        FOREIGN KEY (`cedula`) REFERENCES `Usuario` (`cedula`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `fk_usuario_submodulo_submodulo`
        FOREIGN KEY (`id_submodulo`) REFERENCES `submodulo` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. TABLA: cliente
-- Información clínica de pacientes/clientes
-- =============================================================================
CREATE TABLE IF NOT EXISTS `cliente` (
    `cedula`          VARCHAR(20) NOT NULL,
    `grupo_sanguineo` VARCHAR(10) NULL DEFAULT NULL,
    `alergias`        TEXT        NULL,
    `antecedentes`    TEXT        NULL,
    `estatus`         TINYINT(1)  NOT NULL DEFAULT 1,
    `fecha_registro`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`cedula`),
    INDEX `idx_cliente_estatus` (`estatus`),
    CONSTRAINT `fk_cliente_persona`
        FOREIGN KEY (`cedula`) REFERENCES `persona` (`cedula`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 8. TABLA: morbilidad
-- Registro de consultas médicas, diagnósticos CIE-10 y tratamientos
-- =============================================================================
CREATE TABLE IF NOT EXISTS `morbilidad` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cedula_cliente`  VARCHAR(20)  NOT NULL,
    `motivo_consulta` VARCHAR(255) NOT NULL,
    `diagnostico`     VARCHAR(255) NOT NULL,
    `codigo_cie10`    VARCHAR(20)  NULL DEFAULT NULL,
    `tipo_morbilidad` VARCHAR(40)  NOT NULL DEFAULT 'Aguda',
    `especialidad`    VARCHAR(80)  NOT NULL DEFAULT 'Medicina General',
    `medico_tratante` VARCHAR(120) NOT NULL DEFAULT 'Médico de Guardia',
    `tratamiento`     TEXT         NULL,
    `observaciones`   TEXT         NULL,
    `fecha_atencion`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_morbilidad_cedula` (`cedula_cliente`),
    INDEX `idx_morbilidad_tipo` (`tipo_morbilidad`),
    INDEX `idx_morbilidad_cie10` (`codigo_cie10`),
    INDEX `idx_morbilidad_fecha` (`fecha_atencion`),
    CONSTRAINT `fk_morbilidad_persona`
        FOREIGN KEY (`cedula_cliente`) REFERENCES `persona` (`cedula`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 9. VISTA: vw_clientes
-- Vista consolidada de pacientes activos utilizada por Cliente::consultarClientes()
-- =============================================================================
CREATE OR REPLACE VIEW `vw_clientes` AS
SELECT
    p.cedula,
    p.nombre,
    p.apellido,
    CONCAT(p.nombre, ' ', p.apellido) AS nombre_completo,
    p.fecha_nacimiento,
    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
    p.sexo,
    p.telefono,
    p.correo,
    p.direccion,
    c.grupo_sanguineo,
    c.alergias,
    c.antecedentes,
    c.estatus,
    c.fecha_registro
FROM `cliente` c
INNER JOIN `persona` p ON c.cedula = p.cedula
WHERE c.estatus = 1;

-- =============================================================================
-- DATOS INICIALES (SEED)
-- =============================================================================

-- Roles del sistema
INSERT IGNORE INTO `rol` (`id_rol`, `nombre_rol`, `descripcion`, `estatus`) VALUES
('ADMIN',    'Administrador',       'Control total del sistema, seguridad y configuración', 1),
('MEDICO',   'Médico',              'Atención médica, historial clínico y casos de morbilidad', 1),
('OPERADOR', 'Operador',            'Admisión y registro de pacientes y consultas', 1),
('CLIENTE',  'Cliente / Paciente',  'Acceso básico de paciente', 1);

-- Submódulos del sistema
INSERT IGNORE INTO `submodulo` (`id`, `modulo`, `nombre`, `descripcion`, `icono`, `ruta`, `orden`) VALUES
('dashboard',      'Principal',           'Panel Visual',         'Resumen ejecutivo, KPIs clínicos y distribución epidemiológica', 'bi-speedometer2',    '/dashboard',             1),
('pacientes',      'Gestión Clínica',     'Pacientes / Clientes', 'Registro, búsqueda e historias clínicas de pacientes del Estado Lara', 'bi-person-heart',    '/pacientes',             1),
('morbilidad',     'Gestión Clínica',     'Casos de Morbilidad',  'Registro de consultas médicas, diagnósticos CIE-10 y tratamientos', 'bi-journal-medical', '/morbilidad',            2),
('usuarios',       'Seguridad & Sistema', 'Gestión de Usuarios',  'Administración de cuentas del personal, estados y contraseñas', 'bi-person-gear',     '/usuarios',              1),
('roles_permisos', 'Seguridad & Sistema', 'Roles y Permisos',     'Configuración de roles y matriz de acceso a submódulos', 'bi-shield-lock',     '/usuarios#rolesSection', 2);

-- Permisos por rol
INSERT IGNORE INTO `rol_submodulo` (`id_rol`, `id_submodulo`, `puede_ver`, `puede_crear`, `puede_editar`, `puede_eliminar`) VALUES
-- ADMIN: acceso total
('ADMIN', 'dashboard',      1, 1, 1, 1),
('ADMIN', 'pacientes',      1, 1, 1, 1),
('ADMIN', 'morbilidad',     1, 1, 1, 1),
('ADMIN', 'usuarios',       1, 1, 1, 1),
('ADMIN', 'roles_permisos', 1, 1, 1, 1),
-- MEDICO: clínico completo, sin seguridad
('MEDICO', 'dashboard',      1, 0, 0, 0),
('MEDICO', 'pacientes',      1, 1, 1, 0),
('MEDICO', 'morbilidad',     1, 1, 1, 0),
('MEDICO', 'usuarios',       0, 0, 0, 0),
('MEDICO', 'roles_permisos', 0, 0, 0, 0),
-- OPERADOR: admisión y consulta
('OPERADOR', 'dashboard',      1, 0, 0, 0),
('OPERADOR', 'pacientes',      1, 1, 1, 0),
('OPERADOR', 'morbilidad',     1, 1, 0, 0),
('OPERADOR', 'usuarios',       0, 0, 0, 0),
('OPERADOR', 'roles_permisos', 0, 0, 0, 0),
-- CLIENTE: sin acceso administrativo
('CLIENTE', 'dashboard',      0, 0, 0, 0),
('CLIENTE', 'pacientes',      0, 0, 0, 0),
('CLIENTE', 'morbilidad',     0, 0, 0, 0),
('CLIENTE', 'usuarios',       0, 0, 0, 0),
('CLIENTE', 'roles_permisos', 0, 0, 0, 0);

-- Personal del sistema (Personas + Usuarios)
-- Nota: Auth::iniciarSesion() migra automáticamente las claves de texto plano del seed a hash bcrypt en el primer inicio de sesión.
INSERT IGNORE INTO `persona` (`cedula`, `nombre`, `apellido`, `fecha_nacimiento`, `telefono`, `correo`, `direccion`, `sexo`) VALUES
('V-00000000', 'Jesús',  'Mendoza',   '1988-06-15', '0414-5000001', 'admin@siamtel.gob.ve',        'Barquisimeto, Carrera 19 con Calle 25, Gob. Estado Lara', 'M'),
('V-14523890', 'Carlos', 'Rodríguez', '1980-03-21', '0412-5551234', 'dr.rodriguez@siamtel.gob.ve', 'Barquisimeto, Av. Vargas, Hospital Central',              'M'),
('V-19876543', 'María',  'Pérez',     '1992-10-08', '0424-5559876', 'op.perez@siamtel.gob.ve',     'Cabudare, Municipio Palavecino, Estado Lara',             'F');

INSERT IGNORE INTO `Usuario` (`cedula`, `user`, `password`, `rol`, `estatus`, `tema`) VALUES
('V-00000000', 'admin',        'admin123',    'ADMIN',    1, 'light'),
('V-14523890', 'dr.rodriguez', 'medico123',   'MEDICO',   1, 'light'),
('V-19876543', 'op.perez',     'operador123', 'OPERADOR', 1, 'light');

-- Pacientes de ejemplo (Estado Lara)
INSERT IGNORE INTO `persona` (`cedula`, `nombre`, `apellido`, `fecha_nacimiento`, `telefono`, `correo`, `direccion`, `sexo`) VALUES
('V-15234871', 'Ana Lucía',    'Torrealba',  '1982-04-15', '0414-5123456', 'ana.torrealba@email.com',   'Barquisimeto, Parroquia Catedral, Carrera 18',       'F'),
('V-18765432', 'Luis Enrique', 'Alvarado',   '1989-11-03', '0412-5234567', 'luis.alvarado@email.com',   'Cabudare, Parroquia José Gregorio Bastidas',         'M'),
('V-21345678', 'Carmen Elena', 'Giménez',    '1994-07-22', '0424-5345678', 'carmen.gimenez@email.com',  'Carora, Municipio Torres, Sector La Guzmana',        'F'),
('V-11987654', 'José Gregorio','Colmenárez', '1971-02-10', '0416-5456789', 'jose.colmenarez@email.com', 'Quíbor, Municipio Jiménez, Av. Pedro León Torres',   'M'),
('V-25678901', 'Valeria Sofía','Castillo',   '2001-09-18', '0414-5567890', 'valeria.castillo@email.com','El Tocuyo, Municipio Morán, Calle Principal',        'F'),
('V-28901234', 'Diego Andrés', 'Linárez',    '2012-05-30', '0424-5678901', 'familia.linarez@email.com', 'Barquisimeto, Parroquia Concepción, Barrio Unión',   'M');

INSERT IGNORE INTO `cliente` (`cedula`, `grupo_sanguineo`, `alergias`, `antecedentes`, `estatus`) VALUES
('V-15234871', 'O+',  'Penicilina', 'Hipertensión arterial',      1),
('V-18765432', 'A+',  'Ninguna',    'Asma bronquial',             1),
('V-21345678', 'B+',  'AINEs',      'Diabetes Tipo 2',            1),
('V-11987654', 'O-',  'Ninguna',    'Cardiopatía hipertensiva',   1),
('V-25678901', 'AB+', 'Sulfas',     'Ninguno',                    1),
('V-28901234', 'A-',  'Ninguna',    'Rinitis alérgica',           1);

-- Casos de morbilidad / consultas de ejemplo
INSERT IGNORE INTO `morbilidad` (
    `id`, `cedula_cliente`, `motivo_consulta`, `diagnostico`, `codigo_cie10`,
    `tipo_morbilidad`, `especialidad`, `medico_tratante`, `tratamiento`, `observaciones`, `fecha_atencion`
) VALUES
(1, 'V-15234871', 'Cefalea occipital y mareos desde hace 2 días', 'Hipertensión Arterial Esencial', 'I10',
    'Crónica', 'Medicina Interna', 'Dr. Carlos Rodríguez',
    'Losartán 50mg VO c/12h, dieta hiposódica', 'Control de tensión arterial diario durante 7 días',
    NOW() - INTERVAL 6 DAY),
(2, 'V-18765432', 'Tos seca persistente, congestión nasal y malestar general', 'Infección Respiratoria Aguda', 'J06.9',
    'Respiratoria', 'Neumonología', 'Dr. Carlos Rodríguez',
    'Nebuloterapia con salbutamol, hidratación oral y reposo', 'Reevaluación en 5 días si persiste disnea',
    NOW() - INTERVAL 5 DAY),
(3, 'V-21345678', 'Polidipsia, fatiga y glicemia capilar elevada (245 mg/dL)', 'Diabetes Mellitus Tipo 2', 'E11.9',
    'Crónica', 'Medicina Interna', 'Dr. Carlos Rodríguez',
    'Ajuste de Metformina 850mg VO c/12h, plan nutricional', 'Solicitar HbA1c y perfil lipídico',
    NOW() - INTERVAL 4 DAY),
(4, 'V-25678901', 'Fiebre de 38.5°C, mialgias y dolor retroocular', 'Síndrome Febril Agudo / Sospecha Dengue', 'A90',
    'Infecciosa', 'Epidemiología', 'Dr. Carlos Rodríguez',
    'Acetaminofén 500mg VO c/6h PRN fiebre, sales de rehidratación oral', 'Hemograma con plaquetas en 48 horas; evitar AINEs',
    NOW() - INTERVAL 3 DAY),
(5, 'V-28901234', 'Rinorrea hialina, tos nocturna y febrícula', 'Infección Respiratoria Aguda', 'J06.9',
    'Respiratoria', 'Pediatría', 'Dra. Laura Silva',
    'Lavados nasales con solución fisiológica, antihistamínico pediátrico', 'Control pediátrico en 72 horas',
    NOW() - INTERVAL 2 DAY),
(6, 'V-11987654', 'Control mensual de presión arterial y palpitaciones leves', 'Hipertensión Arterial Esencial', 'I10',
    'Cardiovascular', 'Medicina Interna', 'Dr. Carlos Rodríguez',
    'Continuar Amlodipino 5mg VO OD y seguimiento cardiológico', 'Realizar electrocardiograma de control',
    NOW() - INTERVAL 1 DAY),
(7, 'V-18765432', 'Dolor abdominal tipo cólico y evacuaciones líquidas', 'Gastroenteritis Aguda', 'A09',
    'Aguda', 'Medicina General', 'Dr. Carlos Rodríguez',
    'Suero oral a libre demanda, probióticos y dieta astringente', 'Signos de alarma explicados al paciente',
    NOW() - INTERVAL 12 HOUR),
(8, 'V-15234871', 'Fiebre alta de inicio súbito y artralgias', 'Síndrome Febril Agudo / Sospecha Dengue', 'A90',
    'Infecciosa', 'Medicina General', 'Dra. Laura Silva',
    'Acetaminofén 500mg VO c/6h, abundante líquido y reposo absoluto', 'Control hematológico en 48 horas',
    NOW() - INTERVAL 3 HOUR);

SET FOREIGN_KEY_CHECKS = 1;

