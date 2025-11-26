-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-10-2025 a las 00:23:35
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `coregedoc`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_acuerdo`
--

CREATE TABLE `t_acuerdo` (
  `idAcuerdo` int(11) NOT NULL,
  `descAcuerdo` varchar(45) DEFAULT NULL,
  `t_tipoReunion_idTipoReunion` int(11) NOT NULL,
  `t_tema_idTema` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_adjunto`
--

CREATE TABLE `t_adjunto` (
  `idAdjunto` int(11) NOT NULL,
  `pathAdjunto` varchar(45) NOT NULL,
  `t_minuta_idMinuta` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_asistencia`
--

CREATE TABLE `t_asistencia` (
  `idAsistencia` int(11) NOT NULL,
  `t_minuta_idMinuta` int(11) NOT NULL,
  `t_tipoReunion_idTipoReunion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_comision`
--

CREATE TABLE `t_comision` (
  `idComision` int(11) NOT NULL,
  `nombreComision` varchar(245) NOT NULL,
  `vigencia` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `t_comision`
--

INSERT INTO `t_comision` (`idComision`, `nombreComision`, `vigencia`) VALUES
(1, 'Régimen Interior', 0),
(2, 'Familia y Adulto Mayor', 0),
(3, 'Turismo y Fomento Productivo', 0),
(4, 'Educación, Arte, Cultura, Deportes, Recreación y Patrimonio Cultural', 0),
(5, 'Ciencia, Tecnología e Innovación', 0),
(6, 'Seguridad Ciudadana y Protección Civil', 0),
(7, 'Salud', 0),
(8, 'Inversiones, Presupuesto y Patrimonio Regional', 0),
(9, 'Recursos Hídricos, Agricultura y Ganadería', 0),
(10, 'Ordenamiento Territorial, Intereses Marítimos y Pesca', 0),
(11, 'Medio Ambiente, Patrimonio Natural y Cambio Climático', 0),
(12, 'Pueblos Originarios', 0),
(13, 'Vivienda, Campamentos y Territorios', 0),
(14, 'Descentralización y Regionalización', 0),
(15, 'Relaciones Internacionales', 0),
(16, 'Desarrollo Social, Participación Ciudadana y Grupos Prioritarios', 0),
(17, 'Control de Gestión y Fiscalización', 0),
(18, 'Minería', 0),
(19, 'Transporte', 0),
(20, 'Desarrollo Regional y Relaciones Interregionales', 1),
(22, 'prueba de comision', 0),
(23, 'prueba de comision 2', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_comuna`
--

CREATE TABLE `t_comuna` (
  `idComuna` int(11) NOT NULL,
  `nombreComuna` varchar(45) NOT NULL,
  `t_provincia_idProvincia` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `t_comuna`
--

INSERT INTO `t_comuna` (`idComuna`, `nombreComuna`, `t_provincia_idProvincia`) VALUES
(6101, 'Valparaíso', 61),
(6102, 'Viña del Mar', 61),
(6103, 'Concón', 61),
(6104, 'Quintero', 61),
(6105, 'Puchuncaví', 61),
(6106, 'Casablanca', 61),
(6107, 'Juan Fernández', 61),
(6201, 'Isla de Pascua', 62),
(6301, 'Los Andes', 63),
(6302, 'Calle Larga', 63),
(6303, 'Rinconada', 63),
(6304, 'San Esteban', 63),
(6401, 'La Ligua', 64),
(6402, 'Cabildo', 64),
(6403, 'Papudo', 64),
(6404, 'Zapallar', 64),
(6405, 'Petorca', 64),
(6501, 'Quillota', 65),
(6502, 'La Calera', 65),
(6503, 'Hijuelas', 65),
(6504, 'La Cruz', 65),
(6505, 'Nogales', 65),
(6601, 'San Antonio', 66),
(6602, 'Cartagena', 66),
(6603, 'El Tabo', 66),
(6604, 'El Quisco', 66),
(6605, 'Algarrobo', 66),
(6606, 'Santo Domingo', 66),
(6701, 'San Felipe', 67),
(6702, 'Llaillay', 67),
(6703, 'Catemu', 67),
(6704, 'Panquehue', 67),
(6705, 'Putaendo', 67),
(6706, 'Santa María', 67),
(6801, 'Quilpué', 68),
(6802, 'Villa Alemana', 68),
(6803, 'Limache', 68),
(6804, 'Olmué', 68);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_minuta`
--

CREATE TABLE `t_minuta` (
  `idMinuta` int(11) NOT NULL,
  `pathArchivo` varchar(45) NOT NULL,
  `nombreComision` varchar(50) NOT NULL,
  `nombrePresidente` int(11) NOT NULL,
  `horaMinuta` time NOT NULL,
  `fechaMinuta` date NOT NULL,
  `t_acuerdo_idAcuerdo` int(11) NOT NULL,
  `t_propuesta_idPropuesta` int(11) NOT NULL,
  `t_voto_idVoto` int(11) NOT NULL,
  `t_voto_t_usuario_idUsuario` int(11) NOT NULL,
  `t_voto_t_propuesta_idPropuesta` int(11) NOT NULL,
  `t_voto_t_propuesta_t_acuerdo_idAcuerdo` int(11) NOT NULL,
  `t_voto_t_propuesta_t_acuerdo_t_tipoReunion_idTipoReunion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_mocion`
--

CREATE TABLE `t_mocion` (
  `idMocion` int(11) NOT NULL,
  `numeroMocion` int(10) NOT NULL,
  `nombreMocion` varchar(245) NOT NULL,
  `t_reunion_idReunion` int(11) NOT NULL,
  `t_sesion_idSesion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_partido`
--

CREATE TABLE `t_partido` (
  `idPartido` int(11) NOT NULL,
  `nombrePartido` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `t_partido`
--

INSERT INTO `t_partido` (`idPartido`, `nombrePartido`) VALUES
(0, 'Independiente'),
(1, 'Partido Comunista de Chile'),
(2, 'Partido Socialista de Chile'),
(3, 'Partido Por la Democracia'),
(4, 'Democracia Cristiana'),
(5, 'Unión Demócrata Independiente'),
(6, 'Renovación Nacional'),
(7, 'Partido Republicano'),
(8, 'Evolución Política'),
(9, 'Frente Amplio'),
(19, 'Federación Regionalista Verde Social'),
(99, 'No Aplica');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_perfil`
--

CREATE TABLE `t_perfil` (
  `idPerfil` int(11) NOT NULL,
  `descPerfil` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `t_perfil`
--

INSERT INTO `t_perfil` (`idPerfil`, `descPerfil`) VALUES
(1, 'Administrador'),
(2, 'Editor'),
(3, 'Visualizador');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_pleno`
--

CREATE TABLE `t_pleno` (
  `idPleno` int(11) NOT NULL,
  `numeroPleno` int(10) NOT NULL,
  `fechaInicioPleno` datetime NOT NULL,
  `fechaTerminoPleno` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_propuesta`
--

CREATE TABLE `t_propuesta` (
  `idPropuesta` int(11) NOT NULL,
  `descPropuesta` varchar(45) DEFAULT NULL,
  `t_acuerdo_idAcuerdo` int(11) NOT NULL,
  `t_acuerdo_t_tipoReunion_idTipoReunion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_provincia`
--

CREATE TABLE `t_provincia` (
  `idProvincia` int(11) NOT NULL,
  `nombreProvincia` varchar(45) DEFAULT NULL,
  `t_region_idRegion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `t_provincia`
--

INSERT INTO `t_provincia` (`idProvincia`, `nombreProvincia`, `t_region_idRegion`) VALUES
(0, 'Provincia de Valparaíso', 1),
(61, 'Provincia de Valparaíso', 6),
(62, 'Provincia de Isla de Pascua', 6),
(63, 'Provincia de Los Andes', 6),
(64, 'Provincia de Petorca', 6),
(65, 'Provincia de Quillota', 6),
(66, 'Provincia de San Antonio', 6),
(67, 'Provincia de San Felipe de Aconcagua', 6),
(68, 'Provincia de Marga Marga', 6);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_region`
--

CREATE TABLE `t_region` (
  `idRegion` int(11) NOT NULL,
  `nombreRegion` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `t_region`
--

INSERT INTO `t_region` (`idRegion`, `nombreRegion`) VALUES
(0, 'Región de Valparaíso'),
(1, 'Región de Arica y Parinacota'),
(2, 'Región de Tarapacá'),
(3, 'Región de Antofagasta'),
(4, 'Región de Atacama'),
(5, 'Región de Coquimbo'),
(6, 'Región de Valparaíso'),
(7, 'Región Metropolitana de Santiago'),
(8, 'Región del Libertador General Bernardo O’Higg'),
(9, 'Región del Maule'),
(10, 'Región de Ñuble'),
(11, 'Región del Biobío'),
(12, 'Región de La Araucanía'),
(13, 'Región de Los Ríos'),
(14, 'Región de Los Lagos'),
(15, 'Región de Aysén del General Carlos Ibáñez del'),
(16, 'Región de Magallanes y de la Antártica Chilen');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_reunion`
--

CREATE TABLE `t_reunion` (
  `idReunion` int(11) NOT NULL,
  `numeroReunion` varchar(45) NOT NULL,
  `nombreReunion` varchar(45) NOT NULL,
  `fechaInicioReunion` datetime NOT NULL,
  `fechaTerminoReunion` datetime NOT NULL,
  `vigente` int(1) NOT NULL,
  `t_acuerdo_idAcuerdo` int(11) NOT NULL,
  `t_comision_idComision` int(11) NOT NULL,
  `t_minuta_idMinuta` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_sesion`
--

CREATE TABLE `t_sesion` (
  `idSesion` int(11) NOT NULL,
  `nombreSesion` varchar(45) NOT NULL,
  `tipoSesion` int(1) NOT NULL,
  `t_pleno_idPleno` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_tema`
--

CREATE TABLE `t_tema` (
  `idTema` int(11) NOT NULL,
  `nombreTema` varchar(255) NOT NULL,
  `objetivo` varchar(500) NOT NULL,
  `compromiso` varchar(500) NOT NULL,
  `observacion` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `t_tema`
--

INSERT INTO `t_tema` (`idTema`, `nombreTema`, `objetivo`, `compromiso`, `observacion`) VALUES
(1, 'prueba 1', 'prueba 1', 'prueba 1', 'prueba 1'),
(2, 'prueba 1', 'prueba 1', 'prueba 1', 'prueba 1'),
(3, 'prueba 2', 'prueba 2', 'prueba 2', 'prueba 2'),
(4, 'prueba 4', 'prueba 4', 'prueba 4', 'prueba 4'),
(5, 'la importancia de invertir recursos en TI', 'destinar mayor prespuesto a informatica', 'jorge, presentar el tema a su equipo para previa aprobación', ''),
(6, 'la importancia de invertir recursos en TI', 'destinar mayor prespuesto a informatica', 'jorge, presentar el tema a su equipo para previa aprobación', ''),
(7, 'la importancia de invertir recursos en TI', 'destinar mayor prespuesto a informatica', 'jorge, presentar el tema a su equipo para previa aprobación', ''),
(8, 'prueba gene', 'prueba gene', 'prueba gene', ''),
(9, 'Recursos para TI', 'Destinar mayores recursos a TI', 'jorge sepulveda', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_tiporeunion`
--

CREATE TABLE `t_tiporeunion` (
  `idTipoReunion` int(11) NOT NULL,
  `descTipoReu` varchar(45) NOT NULL,
  `t_usuario_idUsuario` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_tipousuario`
--

CREATE TABLE `t_tipousuario` (
  `idTipoUsuario` int(11) NOT NULL,
  `descTipoUsuario` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `t_tipousuario`
--

INSERT INTO `t_tipousuario` (`idTipoUsuario`, `descTipoUsuario`) VALUES
(1, 'Consejero Regional'),
(2, 'Secretario Técnico'),
(3, 'Presidente Comisión'),
(4, 'Invitado'),
(5, 'Resumen'),
(6, 'Administrador');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_usuario`
--

CREATE TABLE `t_usuario` (
  `idUsuario` int(11) NOT NULL,
  `pNombre` varchar(45) NOT NULL,
  `sNombre` varchar(45) DEFAULT NULL,
  `aPaterno` varchar(45) NOT NULL,
  `aMaterno` varchar(45) NOT NULL,
  `correo` varchar(45) DEFAULT NULL,
  `contrasena` varchar(255) NOT NULL,
  `perfil_id` int(11) DEFAULT NULL,
  `tipoUsuario_id` int(11) DEFAULT NULL,
  `partido_id` int(11) DEFAULT NULL,
  `comuna_id` int(11) DEFAULT NULL,
  `t_partido_nombrePartido` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `t_usuario`
--

INSERT INTO `t_usuario` (`idUsuario`, `pNombre`, `sNombre`, `aPaterno`, `aMaterno`, `correo`, `contrasena`, `perfil_id`, `tipoUsuario_id`, `partido_id`, `comuna_id`, `t_partido_nombrePartido`) VALUES
(1, 'Catalina', 'María', 'Thauby', 'Krebs', 'catalina.thauby@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 7, 6102, ''),
(2, 'Javier', 'Ignacio', 'Venegas', 'Muñoz', 'javier.venegas@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 7, 6102, ''),
(3, 'Osvaldo', NULL, 'Urrutia', 'Soto', 'osvaldo.urrutia@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 5, 6101, ''),
(4, 'Marcela', 'Alicia', 'Varas', 'Fuentes', 'marcela.varas@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 3, 6101, ''),
(5, 'Tania', 'Estefanía', 'Valenzuela', 'Rossi', 'tania.valenzuela@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 9, 6101, ''),
(6, 'José', 'Luis', 'Miranda', 'Muñoz', 'jose.miranda@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 7, 6101, ''),
(7, 'Paulina', NULL, 'Yáñez', 'Gula', 'paulina.yanez@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 7, 6101, ''),
(8, 'Omar', 'Gabriel', 'Valdivia', 'Álvarez', 'omar.valdivia@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 3, 6101, ''),
(9, 'Paula', NULL, 'Rosso', 'Montenegro', 'paula.rosso@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 9, 6101, ''),
(10, 'Cristián', 'Humberto', 'Fuentes', 'Duque', 'cristian.fuentes@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 7, 6801, ''),
(11, 'Elsa', 'Graciela', 'Bueno', 'Cortés', 'elsa.bueno@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 7, 6801, ''),
(12, 'Emmanuel', 'José', 'Olfos', 'Vargas', 'emmanuel.olfos@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 6, 6801, ''),
(13, 'María', 'Elena', 'Rubilar', 'Muñoz', 'maria.rubilar@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 9, 6801, ''),
(14, 'Felipe', 'Ignacio Sebastián', 'Córdoba', 'Araya', 'felipe.cordoba@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 7, 6501, ''),
(15, 'Lautaro', NULL, 'Correa', 'Castillo', 'lautaro.correa@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 2, 6501, ''),
(16, 'Giselle', 'Makarena', 'Ahumada', 'Espina', 'giselle.ahumada@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 1, 6501, ''),
(17, 'Mauricio', 'Alejandro', 'López', 'Castillo', 'mauricio.lopez@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 7, 6601, ''),
(18, 'Paola', 'Emilia', 'Zamorano', 'Arratia', 'paola.zamorano@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 6, 6601, ''),
(19, 'Romy', NULL, 'Farias', 'Caballero', 'romy.farias@gobiernovalparaiso.cl', '$2y$10$tKhnlk2wYRmrPdnt5cld9udAsegZalP5Y2eCL0jW.O0bZ2daAOAmy', 3, 1, 4, 6601, ''),
(20, 'Fernando', 'Marcelo', 'Astorga', 'Terraza', 'fernando.astorga@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 7, 6701, ''),
(21, 'Rodolfo', 'Ignacio', 'Silva', 'González', 'rodolfo.silva@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 6, 6701, ''),
(22, 'Maricel', 'Alejandra', 'Martínez', 'Vicencio', 'maricel.martinez@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 3, 6701, ''),
(23, 'Edith', 'Clarisa de las Nieves', 'Quiroz', 'Ortíz', 'edith.quiroz@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 6, 6301, ''),
(24, 'María', 'Victoria', 'Rodríguez', 'Herrera', 'maria.rodriguez@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 19, 6301, ''),
(25, 'Cristián', 'René', 'Pinilla', 'Ibacache', 'cristian.pinilla@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 7, 6401, ''),
(26, 'Juan', 'Bautista', 'Ibacache', 'Ibacache', 'juan.ibacache@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 6, 6401, ''),
(27, 'Sabrina', NULL, 'Tuki', 'Pont', 'sabrina.tuki@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 8, 6201, ''),
(28, 'Francisco', NULL, 'Haoa', 'Hotus', 'francisco.haoa@gobiernovalparaiso.cl', '$2y$10$abcdefghijklmnopqrstuv', 3, 1, 2, 6201, ''),
(29, 'Pedro', 'Pablo', 'vergara', 'gonzalez', 'pedro1994_02@gmail.com', '$2y$10$kTMI78LFny4sl5CbEEoPne7PcNWdYBttxIiPKcauO6eywE6i2wrSG', 1, 2, 99, 6401, ''),
(31, 'Enrique', NULL, 'Astudillo', '', 'enrique.astudillo@gobiernovalparaiso.cl', 'e03c634b4b2b6b0a3cf9824cd23bf86842e1add5b36609cc95caa07324962b18', 1, 6, 99, 6101, 'No Aplica'),
(32, 'Pamela', 'Danitza', 'Bernal', '', 'pamela.bernal@gobiernovalparaiso.cl', '$2y$10$aqNBPRNO2ABOAkeK2H7kHO8mHIX4t9kraD3yT6cTcL1kB9GhlAx8a', 2, 2, 99, 6101, 'No Aplica'),
(33, 'Jose', 'Luis', 'Carvajal', '', 'joseluis.carvajal@gobiernovalparaiso.cl', 'e03c634b4b2b6b0a3cf9824cd23bf86842e1add5b36609cc95caa07324962b18', 2, 2, 99, 6101, 'No Aplica'),
(34, 'Susana', 'Noelia', 'Cordova', '', 'noelia.cordova@gobiernovalparaiso.cl', 'e03c634b4b2b6b0a3cf9824cd23bf86842e1add5b36609cc95caa07324962b18', 2, 2, 99, 6101, 'No Aplica'),
(35, 'Maria', 'Alejandra', 'Gonzalez', '', 'alejandra.gonzalez@gobiernovalparaiso.cl', 'e03c634b4b2b6b0a3cf9824cd23bf86842e1add5b36609cc95caa07324962b18', 2, 2, 99, 6101, 'No Aplica'),
(36, 'Mario', 'Jorge', 'Lagomarsino', '', 'mario.lagomarsino@gobiernovalparaiso.cl', 'e03c634b4b2b6b0a3cf9824cd23bf86842e1add5b36609cc95caa07324962b18', 2, 2, 99, 6101, 'No Aplica');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `t_voto`
--

CREATE TABLE `t_voto` (
  `idVoto` int(11) NOT NULL,
  `opcionVoto` int(11) NOT NULL,
  `descVoto` varchar(45) DEFAULT NULL,
  `t_usuario_idUsuario` int(11) NOT NULL,
  `t_propuesta_idPropuesta` int(11) NOT NULL,
  `t_propuesta_t_acuerdo_idAcuerdo` int(11) NOT NULL,
  `t_propuesta_t_acuerdo_t_tipoReunion_idTipoReunion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `t_acuerdo`
--
ALTER TABLE `t_acuerdo`
  ADD PRIMARY KEY (`idAcuerdo`,`t_tipoReunion_idTipoReunion`,`t_tema_idTema`),
  ADD KEY `fk_t_acuerdo_t_tipoReunion1_idx` (`t_tipoReunion_idTipoReunion`),
  ADD KEY `fk_t_acuerdo_t_tema1_idx` (`t_tema_idTema`);

--
-- Indices de la tabla `t_adjunto`
--
ALTER TABLE `t_adjunto`
  ADD PRIMARY KEY (`idAdjunto`),
  ADD KEY `fk_t_adjunto_t_minuta1_idx` (`t_minuta_idMinuta`);

--
-- Indices de la tabla `t_asistencia`
--
ALTER TABLE `t_asistencia`
  ADD PRIMARY KEY (`idAsistencia`,`t_tipoReunion_idTipoReunion`),
  ADD KEY `fk_t_asistencia_t_minuta1_idx` (`t_minuta_idMinuta`),
  ADD KEY `fk_t_asistencia_t_tipoReunion1_idx` (`t_tipoReunion_idTipoReunion`);

--
-- Indices de la tabla `t_comision`
--
ALTER TABLE `t_comision`
  ADD PRIMARY KEY (`idComision`);

--
-- Indices de la tabla `t_comuna`
--
ALTER TABLE `t_comuna`
  ADD PRIMARY KEY (`idComuna`),
  ADD KEY `fk_t_comuna_t_provincia1_idx` (`t_provincia_idProvincia`);

--
-- Indices de la tabla `t_minuta`
--
ALTER TABLE `t_minuta`
  ADD PRIMARY KEY (`idMinuta`,`t_acuerdo_idAcuerdo`,`t_propuesta_idPropuesta`,`t_voto_idVoto`,`t_voto_t_usuario_idUsuario`,`t_voto_t_propuesta_idPropuesta`,`t_voto_t_propuesta_t_acuerdo_idAcuerdo`,`t_voto_t_propuesta_t_acuerdo_t_tipoReunion_idTipoReunion`),
  ADD KEY `fk_t_minuta_t_acuerdo1_idx` (`t_acuerdo_idAcuerdo`),
  ADD KEY `fk_t_minuta_t_propuesta1_idx` (`t_propuesta_idPropuesta`),
  ADD KEY `fk_t_minuta_t_voto1_idx` (`t_voto_idVoto`,`t_voto_t_usuario_idUsuario`,`t_voto_t_propuesta_idPropuesta`,`t_voto_t_propuesta_t_acuerdo_idAcuerdo`,`t_voto_t_propuesta_t_acuerdo_t_tipoReunion_idTipoReunion`);

--
-- Indices de la tabla `t_mocion`
--
ALTER TABLE `t_mocion`
  ADD PRIMARY KEY (`idMocion`),
  ADD KEY `fk_t_acuerdo_t_reunion1_idx` (`t_reunion_idReunion`),
  ADD KEY `fk_t_mocion_t_sesion1_idx` (`t_sesion_idSesion`);

--
-- Indices de la tabla `t_partido`
--
ALTER TABLE `t_partido`
  ADD PRIMARY KEY (`idPartido`,`nombrePartido`);

--
-- Indices de la tabla `t_perfil`
--
ALTER TABLE `t_perfil`
  ADD PRIMARY KEY (`idPerfil`);

--
-- Indices de la tabla `t_pleno`
--
ALTER TABLE `t_pleno`
  ADD PRIMARY KEY (`idPleno`);

--
-- Indices de la tabla `t_propuesta`
--
ALTER TABLE `t_propuesta`
  ADD PRIMARY KEY (`idPropuesta`,`t_acuerdo_idAcuerdo`,`t_acuerdo_t_tipoReunion_idTipoReunion`),
  ADD KEY `fk_t_propuesta_t_acuerdo1_idx` (`t_acuerdo_idAcuerdo`,`t_acuerdo_t_tipoReunion_idTipoReunion`);

--
-- Indices de la tabla `t_provincia`
--
ALTER TABLE `t_provincia`
  ADD PRIMARY KEY (`idProvincia`),
  ADD KEY `fk_t_provincia_t_region1_idx` (`t_region_idRegion`);

--
-- Indices de la tabla `t_region`
--
ALTER TABLE `t_region`
  ADD PRIMARY KEY (`idRegion`);

--
-- Indices de la tabla `t_reunion`
--
ALTER TABLE `t_reunion`
  ADD PRIMARY KEY (`idReunion`,`t_minuta_idMinuta`),
  ADD KEY `fk_t_reunion_t_comision1_idx` (`t_comision_idComision`),
  ADD KEY `fk_t_reunion_t_minuta1_idx` (`t_minuta_idMinuta`);

--
-- Indices de la tabla `t_sesion`
--
ALTER TABLE `t_sesion`
  ADD PRIMARY KEY (`idSesion`,`t_pleno_idPleno`),
  ADD KEY `fk_t_sesion_t_pleno1_idx` (`t_pleno_idPleno`);

--
-- Indices de la tabla `t_tema`
--
ALTER TABLE `t_tema`
  ADD PRIMARY KEY (`idTema`);

--
-- Indices de la tabla `t_tiporeunion`
--
ALTER TABLE `t_tiporeunion`
  ADD PRIMARY KEY (`idTipoReunion`),
  ADD KEY `fk_t_tipoReunion_t_usuario1_idx` (`t_usuario_idUsuario`);

--
-- Indices de la tabla `t_tipousuario`
--
ALTER TABLE `t_tipousuario`
  ADD PRIMARY KEY (`idTipoUsuario`);

--
-- Indices de la tabla `t_usuario`
--
ALTER TABLE `t_usuario`
  ADD PRIMARY KEY (`idUsuario`),
  ADD KEY `fk_t_usuario_t_partido1_idx` (`t_partido_nombrePartido`),
  ADD KEY `fk_usuario_perfil` (`perfil_id`),
  ADD KEY `fk_usuario_tipousuario` (`tipoUsuario_id`),
  ADD KEY `fk_usuario_partido` (`partido_id`),
  ADD KEY `fk_usuario_comuna` (`comuna_id`);

--
-- Indices de la tabla `t_voto`
--
ALTER TABLE `t_voto`
  ADD PRIMARY KEY (`idVoto`,`t_usuario_idUsuario`,`t_propuesta_idPropuesta`,`t_propuesta_t_acuerdo_idAcuerdo`,`t_propuesta_t_acuerdo_t_tipoReunion_idTipoReunion`),
  ADD KEY `fk_t_voto_t_usuario1_idx` (`t_usuario_idUsuario`),
  ADD KEY `fk_t_voto_t_propuesta1_idx` (`t_propuesta_idPropuesta`,`t_propuesta_t_acuerdo_idAcuerdo`,`t_propuesta_t_acuerdo_t_tipoReunion_idTipoReunion`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `t_acuerdo`
--
ALTER TABLE `t_acuerdo`
  MODIFY `idAcuerdo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `t_asistencia`
--
ALTER TABLE `t_asistencia`
  MODIFY `idAsistencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `t_comision`
--
ALTER TABLE `t_comision`
  MODIFY `idComision` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `t_mocion`
--
ALTER TABLE `t_mocion`
  MODIFY `idMocion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `t_sesion`
--
ALTER TABLE `t_sesion`
  MODIFY `idSesion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `t_tema`
--
ALTER TABLE `t_tema`
  MODIFY `idTema` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `t_usuario`
--
ALTER TABLE `t_usuario`
  MODIFY `idUsuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `t_acuerdo`
--
ALTER TABLE `t_acuerdo`
  ADD CONSTRAINT `fk_t_acuerdo_t_tema1` FOREIGN KEY (`t_tema_idTema`) REFERENCES `t_tema` (`idTema`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_t_acuerdo_t_tipoReunion1` FOREIGN KEY (`t_tipoReunion_idTipoReunion`) REFERENCES `t_tiporeunion` (`idTipoReunion`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_adjunto`
--
ALTER TABLE `t_adjunto`
  ADD CONSTRAINT `fk_t_adjunto_t_minuta1` FOREIGN KEY (`t_minuta_idMinuta`) REFERENCES `t_minuta` (`idMinuta`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_asistencia`
--
ALTER TABLE `t_asistencia`
  ADD CONSTRAINT `fk_t_asistencia_t_minuta1` FOREIGN KEY (`t_minuta_idMinuta`) REFERENCES `t_minuta` (`idMinuta`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_t_asistencia_t_tipoReunion1` FOREIGN KEY (`t_tipoReunion_idTipoReunion`) REFERENCES `t_tiporeunion` (`idTipoReunion`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_comuna`
--
ALTER TABLE `t_comuna`
  ADD CONSTRAINT `fk_t_comuna_t_provincia1` FOREIGN KEY (`t_provincia_idProvincia`) REFERENCES `t_provincia` (`idProvincia`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_minuta`
--
ALTER TABLE `t_minuta`
  ADD CONSTRAINT `fk_t_minuta_t_acuerdo1` FOREIGN KEY (`t_acuerdo_idAcuerdo`) REFERENCES `t_acuerdo` (`idAcuerdo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_t_minuta_t_propuesta1` FOREIGN KEY (`t_propuesta_idPropuesta`) REFERENCES `t_propuesta` (`idPropuesta`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_t_minuta_t_voto1` FOREIGN KEY (`t_voto_idVoto`,`t_voto_t_usuario_idUsuario`,`t_voto_t_propuesta_idPropuesta`,`t_voto_t_propuesta_t_acuerdo_idAcuerdo`,`t_voto_t_propuesta_t_acuerdo_t_tipoReunion_idTipoReunion`) REFERENCES `t_voto` (`idVoto`, `t_usuario_idUsuario`, `t_propuesta_idPropuesta`, `t_propuesta_t_acuerdo_idAcuerdo`, `t_propuesta_t_acuerdo_t_tipoReunion_idTipoReunion`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_mocion`
--
ALTER TABLE `t_mocion`
  ADD CONSTRAINT `fk_t_acuerdo_t_reunion1` FOREIGN KEY (`t_reunion_idReunion`) REFERENCES `t_reunion` (`idReunion`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_t_mocion_t_sesion1` FOREIGN KEY (`t_sesion_idSesion`) REFERENCES `t_sesion` (`idSesion`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_propuesta`
--
ALTER TABLE `t_propuesta`
  ADD CONSTRAINT `fk_t_propuesta_t_acuerdo1` FOREIGN KEY (`t_acuerdo_idAcuerdo`,`t_acuerdo_t_tipoReunion_idTipoReunion`) REFERENCES `t_acuerdo` (`idAcuerdo`, `t_tipoReunion_idTipoReunion`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_provincia`
--
ALTER TABLE `t_provincia`
  ADD CONSTRAINT `fk_t_provincia_t_region1` FOREIGN KEY (`t_region_idRegion`) REFERENCES `t_region` (`idRegion`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_reunion`
--
ALTER TABLE `t_reunion`
  ADD CONSTRAINT `fk_t_reunion_t_comision1` FOREIGN KEY (`t_comision_idComision`) REFERENCES `t_comision` (`idComision`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_t_reunion_t_minuta1` FOREIGN KEY (`t_minuta_idMinuta`) REFERENCES `t_minuta` (`idMinuta`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_sesion`
--
ALTER TABLE `t_sesion`
  ADD CONSTRAINT `fk_t_sesion_t_pleno1` FOREIGN KEY (`t_pleno_idPleno`) REFERENCES `t_pleno` (`idPleno`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_tiporeunion`
--
ALTER TABLE `t_tiporeunion`
  ADD CONSTRAINT `fk_t_tipoReunion_t_usuario1` FOREIGN KEY (`t_usuario_idUsuario`) REFERENCES `t_usuario` (`idUsuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `t_usuario`
--
ALTER TABLE `t_usuario`
  ADD CONSTRAINT `fk_usuario_comuna` FOREIGN KEY (`comuna_id`) REFERENCES `t_comuna` (`idComuna`),
  ADD CONSTRAINT `fk_usuario_partido` FOREIGN KEY (`partido_id`) REFERENCES `t_partido` (`idPartido`),
  ADD CONSTRAINT `fk_usuario_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `t_perfil` (`idPerfil`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuario_tipousuario` FOREIGN KEY (`tipoUsuario_id`) REFERENCES `t_tipousuario` (`idTipoUsuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `t_voto`
--
ALTER TABLE `t_voto`
  ADD CONSTRAINT `fk_t_voto_t_propuesta1` FOREIGN KEY (`t_propuesta_idPropuesta`,`t_propuesta_t_acuerdo_idAcuerdo`,`t_propuesta_t_acuerdo_t_tipoReunion_idTipoReunion`) REFERENCES `t_propuesta` (`idPropuesta`, `t_acuerdo_idAcuerdo`, `t_acuerdo_t_tipoReunion_idTipoReunion`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_t_voto_t_usuario1` FOREIGN KEY (`t_usuario_idUsuario`) REFERENCES `t_usuario` (`idUsuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
