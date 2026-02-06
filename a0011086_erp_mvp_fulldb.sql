-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 05-02-2026 a las 12:11:06
-- Versión del servidor: 8.0.44-35.1
-- Versión de PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `a0011086_erp_mvp`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `attendance`
--

CREATE TABLE `attendance` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `date` date NOT NULL,
  `ingreso_manana` time DEFAULT NULL,
  `ingreso_tarde` time DEFAULT NULL,
  `status` enum('presente','ausente','tarde','justificado') COLLATE utf8mb4_general_ci DEFAULT 'presente',
  `observations` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint NOT NULL,
  `user_id` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `accion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_id` int DEFAULT NULL,
  `detalle` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `fecha`, `accion`, `entidad`, `entidad_id`, `detalle`) VALUES
(1, 2, '2026-01-25 16:06:47', 'ENTREGA_OP', 'production_orders', 9, '{\"op_id\":9,\"order_id\":3,\"product_pt_id\":20,\"cantidad\":1,\"usuario_id\":2,\"usuario_nombre\":\"sysadmin\",\"obs\":\"Entrega a cliente desde OP #9. Usuario: sysadmin (id: 2)\"}');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_cuenta` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banco` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `nombre`, `numero_cuenta`, `banco`, `activo`, `created_at`) VALUES
(1, 'Cuenta Gonzalo', '12399999', 'Banco Galicia', 1, '2026-01-28 14:20:06'),
(3, 'Cuenta Tercero', '999', 'Banco\r\n', 1, '2026-01-28 14:20:24'),
(4, 'Cuenta Tomi', NULL, NULL, 1, '2026-01-29 12:46:25'),
(7, 'Cuenta Pao', NULL, NULL, 1, '2026-01-29 12:46:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cash_expenses`
--

CREATE TABLE `cash_expenses` (
  `id` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `categoria` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `medio` enum('EFECTIVO','DEBITO','TRANSFER','CREDITO','NC','OTRO') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'EFECTIVO',
  `importe` decimal(12,2) NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cash_expenses`
--

INSERT INTO `cash_expenses` (`id`, `fecha`, `categoria`, `descripcion`, `medio`, `importe`, `created_by`, `created_at`) VALUES
(19, '2026-01-02 14:34:00', 'INSUMOS', 'TUERCAS MADERA X 1000', 'EFECTIVO', 111078.00, 2, '2026-01-30 17:35:25'),
(22, '2026-01-02 14:35:00', 'OTROS', 'ENRIQUE', 'EFECTIVO', 307000.00, 2, '2026-01-30 17:36:15'),
(25, '2026-01-02 14:36:00', 'OTROS', 'HIERRONORT', 'EFECTIVO', 65960.00, 2, '2026-01-30 17:36:39'),
(28, '2026-01-02 14:36:00', 'INSUMOS', 'TINTA', 'EFECTIVO', 56600.00, 2, '2026-01-30 17:36:54'),
(31, '2026-01-02 14:36:00', 'INSUMOS', 'ACERLOT', 'EFECTIVO', 3750000.00, 2, '2026-01-30 17:39:14'),
(34, '2026-01-03 14:39:00', 'SUELDOS', 'EMPLEADOS', 'EFECTIVO', 5522075.00, 2, '2026-01-30 17:39:44'),
(37, '2026-01-03 14:39:00', 'OTROS', 'SILLAS X2', 'EFECTIVO', 125750.00, 2, '2026-01-30 17:40:02'),
(40, '2026-01-05 14:40:00', 'OTROS', 'ENCOMIENDA', 'EFECTIVO', 83000.00, 2, '2026-01-30 17:40:24'),
(43, '2026-01-05 14:40:00', 'OTROS', 'ENCOMIENDA', 'EFECTIVO', 46000.00, 2, '2026-01-30 17:40:40'),
(46, '2026-01-05 14:40:00', 'OTROS', 'LOGICAL SUPLY', 'EFECTIVO', 128260.00, 2, '2026-01-30 17:41:00'),
(49, '2026-01-06 14:41:00', 'INSUMOS', 'MADERA', 'EFECTIVO', 310000.00, 2, '2026-01-30 17:41:13'),
(52, '2026-01-07 14:41:00', 'SUELDOS', 'CLARI', 'EFECTIVO', 1507280.00, 2, '2026-01-30 17:42:31'),
(55, '2026-01-07 14:42:00', 'OTROS', 'ALEM', 'EFECTIVO', 160000.00, 2, '2026-01-30 17:43:30'),
(58, '2026-01-08 14:43:00', 'INSUMOS', 'EMBALAJES', 'TRANSFER', 1108216.00, 2, '2026-01-30 17:47:13'),
(61, '2026-01-08 14:47:00', 'SERVICIOS', 'LUZ', 'EFECTIVO', 868255.00, 2, '2026-01-30 17:47:35'),
(64, '2026-01-08 14:47:00', 'OTROS', 'ENCOMIENDA POWERFULL', 'EFECTIVO', 57000.00, 2, '2026-01-30 17:47:54'),
(67, '2026-01-09 14:47:00', 'OTROS', 'ENCOMIENDA SAN ISIDRO', 'EFECTIVO', 60000.00, 2, '2026-01-30 17:48:16'),
(70, '2026-01-09 14:48:00', 'OTROS', 'EMBALAJES', 'EFECTIVO', 249000.00, 2, '2026-01-30 17:48:52'),
(73, '2026-01-09 14:48:00', 'ALQUILER', 'GONZALO ALQUILER', 'TRANSFER', 760000.00, 2, '2026-01-30 17:49:19'),
(76, '2026-01-09 14:49:00', 'OTROS', 'EL OVALO', 'TRANSFER', 580000.00, 2, '2026-01-30 17:49:43'),
(79, '2026-01-09 14:49:00', 'OTROS', 'ARIEL MULTIGRAF', 'TRANSFER', 485000.00, 2, '2026-01-30 17:50:07'),
(82, '2026-01-10 14:50:00', 'SUELDOS', 'EMPLEADOS\r\n', 'EFECTIVO', 7798088.00, 2, '2026-01-30 17:50:21'),
(85, '2026-01-12 14:50:00', 'OTROS', 'MOLINM', 'EFECTIVO', 1034200.00, 2, '2026-01-30 17:51:44'),
(88, '2026-01-12 14:51:00', 'INSUMOS', 'CALCOS', 'TRANSFER', 163350.00, 2, '2026-01-30 17:52:08'),
(91, '2026-01-12 14:52:00', 'OTROS', 'DEVOLVI MAQUINA', 'TRANSFER', 1700000.00, 2, '2026-01-30 17:52:25'),
(94, '2026-01-12 14:52:00', 'SERVICIOS', 'VIA CARGO', 'EFECTIVO', 428000.00, 2, '2026-01-30 17:52:52'),
(97, '2026-01-12 14:52:00', 'OTROS', 'WURT', 'TRANSFER', 555136.00, 2, '2026-01-30 17:53:10'),
(100, '2026-01-12 14:53:00', 'INSUMOS', 'GRAMPAS', 'EFECTIVO', 555390.00, 2, '2026-01-30 17:53:26'),
(103, '2026-01-12 14:53:00', 'INSUMOS', 'ACERLOT', 'EFECTIVO', 948000.00, 2, '2026-01-30 17:53:41'),
(106, '2026-01-14 14:53:00', 'INSUMOS', 'IMPRESORA', 'EFECTIVO', 799793.00, 2, '2026-01-30 17:54:27'),
(109, '2026-01-14 14:54:00', 'INSUMOS', 'GOMA CAR', 'TRANSFER', 750000.00, 2, '2026-01-30 17:55:27'),
(112, '2026-01-14 14:55:00', 'INSUMOS', 'FILM + CARTON', 'EFECTIVO', 126392.00, 2, '2026-01-30 17:55:45'),
(115, '2026-01-14 14:55:00', 'SERVICIOS', 'OVALO ENCOMIENDA', 'EFECTIVO', 41760.00, 2, '2026-01-30 17:56:04'),
(118, '2026-01-14 14:56:00', 'SERVICIOS', 'ENCOMIENDAS GRILLOM', 'EFECTIVO', 30000.00, 2, '2026-01-30 17:56:18'),
(121, '2026-01-14 14:56:00', 'OTROS', 'DOMES', 'TRANSFER', 44800.00, 2, '2026-01-30 17:56:36'),
(124, '2026-01-14 14:56:00', 'INSUMOS', 'NICO BULONERA', 'EFECTIVO', 317500.00, 2, '2026-01-30 17:56:51'),
(127, '2026-01-14 14:56:00', 'INSUMOS', 'FILM + CARTON', 'EFECTIVO', 284706.00, 2, '2026-01-30 17:57:19'),
(130, '2026-01-16 14:57:00', 'IMPUESTOS', 'LLAVE ALEM X4', 'EFECTIVO', 40000.00, 2, '2026-01-30 17:57:41'),
(133, '2026-01-17 14:57:00', 'SUELDOS', 'EMPLEADOS', 'EFECTIVO', 6974526.00, 2, '2026-01-30 17:58:28'),
(136, '2026-01-19 14:58:00', 'INSUMOS', 'ENCOMIENDA CARTONES', 'EFECTIVO', 402000.00, 2, '2026-01-30 17:58:45'),
(139, '2026-01-21 14:58:00', 'SERVICIOS', 'INTERNET', 'EFECTIVO', 35897.00, 2, '2026-01-30 17:59:13'),
(142, '2026-01-22 14:59:00', 'INSUMOS', 'BULONES', 'EFECTIVO', 500000.00, 2, '2026-01-30 18:02:18'),
(145, '2026-01-23 15:02:00', 'INSUMOS', 'RULEMANES SALTA', 'TRANSFER', 427000.00, 2, '2026-01-30 18:07:44'),
(148, '2026-01-23 15:07:00', 'INSUMOS', 'ENCOMIENDA SOLDADORA', 'EFECTIVO', 258000.00, 2, '2026-01-30 18:08:04'),
(151, '2026-01-23 15:08:00', 'SERVICIOS', 'ENCOMIENDA OVALO', 'EFECTIVO', 27100.00, 2, '2026-01-30 18:08:17'),
(154, '2026-01-23 15:08:00', 'SERVICIOS', 'BOTIQUIN', 'EFECTIVO', 100000.00, 2, '2026-01-30 18:08:28'),
(157, '2026-01-23 15:08:00', 'SERVICIOS', 'CADETE', 'EFECTIVO', 14000.00, 2, '2026-01-30 18:08:42'),
(160, '2026-01-24 15:08:00', 'SERVICIOS', 'ENCOMIENDA MEI', 'EFECTIVO', 24000.00, 2, '2026-01-30 18:08:52'),
(163, '2026-01-24 15:08:00', 'SUELDOS', 'EMPLEADOS', 'EFECTIVO', 7542600.00, 2, '2026-01-30 18:09:16'),
(166, '2026-01-31 09:30:39', 'SUELDOS', 'Adelanto de sueldo - Antonio Lance (ADELANTO SEMANAL)', 'EFECTIVO', 10000.00, 2, '2026-01-31 12:30:39'),
(169, '2026-01-31 09:31:12', 'SUELDOS', 'Adelanto de sueldo - Nicolas Diaz (ADELANTO SEMANAL)', 'EFECTIVO', 20000.00, 2, '2026-01-31 12:31:12'),
(172, '2026-01-31 09:31:39', 'SUELDOS', 'Adelanto de sueldo - Tobias Chavez (ADELANTO SEMANAL)', 'EFECTIVO', 30000.00, 2, '2026-01-31 12:31:39'),
(175, '2026-01-26 09:52:00', 'OTROS', 'enrique', 'EFECTIVO', 117000.00, 2, '2026-01-31 12:53:00'),
(178, '2026-01-26 09:53:00', 'SERVICIOS', 'PINTURA', 'EFECTIVO', 500000.00, 2, '2026-01-31 12:53:16'),
(181, '2026-01-26 09:53:00', 'INSUMOS', 'ACERLOT', 'EFECTIVO', 500000.00, 2, '2026-01-31 12:53:29'),
(184, '2026-01-26 09:53:00', 'INSUMOS', 'AEROSOL X2', 'EFECTIVO', 16000.00, 2, '2026-01-31 12:53:44'),
(187, '2026-01-27 09:53:00', 'INSUMOS', 'rodaluz', 'EFECTIVO', 1313165.00, 2, '2026-01-31 12:54:31'),
(190, '2026-01-29 09:54:00', 'OTROS', 'auriculares tomi', 'EFECTIVO', 75000.00, 2, '2026-01-31 12:54:46'),
(193, '2026-01-29 09:54:00', 'SERVICIOS', 'agua', 'EFECTIVO', 72000.00, 2, '2026-01-31 12:54:57'),
(196, '2026-01-30 09:54:00', 'INSUMOS', 'rodaluz', 'EFECTIVO', 370122.00, 2, '2026-01-31 12:55:11'),
(199, '2026-01-30 09:55:00', 'INSUMOS', 'bulonera velez', 'EFECTIVO', 23000.00, 2, '2026-01-31 12:56:05'),
(202, '2026-01-30 09:56:00', 'SERVICIOS', 'pintura', 'EFECTIVO', 1750000.00, 2, '2026-01-31 12:56:19'),
(205, '2026-01-30 09:56:00', 'INSUMOS', 'acerlot', 'EFECTIVO', 2474500.00, 2, '2026-01-31 12:56:45'),
(208, '2026-01-31 09:56:00', 'SERVICIOS', 'rodaluz flete', 'EFECTIVO', 210000.00, 2, '2026-01-31 12:57:23'),
(211, '2026-01-31 09:57:00', 'SERVICIOS', 'encomienda ignia', 'EFECTIVO', 148000.00, 2, '2026-01-31 12:57:43'),
(214, '2026-01-31 10:24:01', 'SUELDOS', 'Adelanto de sueldo - Jose Duran Inga (TRANSFERENCIA)', 'EFECTIVO', 40000.00, 2, '2026-01-31 13:24:01'),
(217, '2026-01-31 10:36:11', 'SUELDOS', 'Adelanto de sueldo - Luis Ibarra (ADELANTO SEMANA ANTERIOR)', 'EFECTIVO', 40000.00, 2, '2026-01-31 13:36:11'),
(220, '2026-01-31 10:50:50', 'SUELDOS', 'Pago nómina - Celeste Castillo', 'EFECTIVO', 125000.00, 2, '2026-01-31 13:50:50'),
(223, '2026-01-31 11:13:28', 'SUELDOS', 'Pago nómina - Joana Cardozo', 'EFECTIVO', 118728.00, 2, '2026-01-31 14:13:28'),
(226, '2026-01-31 11:21:27', 'SUELDOS', 'Pago nómina - Leonel Delgado', 'EFECTIVO', 225000.00, 2, '2026-01-31 14:21:27'),
(229, '2026-01-31 11:23:31', 'SUELDOS', 'Pago nómina - Agustin Frias Lobos', 'EFECTIVO', 232100.00, 2, '2026-01-31 14:23:31'),
(232, '2026-01-31 11:23:35', 'SUELDOS', 'Pago nómina - Antonio Lance', 'EFECTIVO', 163341.33, 2, '2026-01-31 14:23:35'),
(235, '2026-01-31 11:23:51', 'SUELDOS', 'Pago nómina - Antonio Lance', 'EFECTIVO', 10000.00, 2, '2026-01-31 14:23:51'),
(238, '2026-01-31 11:24:13', 'SUELDOS', 'Pago nómina - Brisa Dening', 'EFECTIVO', 199980.00, 2, '2026-01-31 14:24:13'),
(241, '2026-01-31 11:24:22', 'SUELDOS', 'Pago nómina - Clara Ayosa', 'EFECTIVO', 298800.00, 2, '2026-01-31 14:24:22'),
(244, '2026-01-31 11:24:52', 'SUELDOS', 'Pago nómina - Cristian Zacarias', 'EFECTIVO', 276102.00, 2, '2026-01-31 14:24:52'),
(247, '2026-01-31 11:25:04', 'SUELDOS', 'Pago nómina - Erik Yapura', 'EFECTIVO', 276102.00, 2, '2026-01-31 14:25:04'),
(250, '2026-01-31 11:25:16', 'SUELDOS', 'Pago nómina - Fabricio Lopez Saavedra', 'EFECTIVO', 191492.00, 2, '2026-01-31 14:25:16'),
(253, '2026-01-31 11:25:28', 'SUELDOS', 'Pago nómina - Facundo Sanchez', 'EFECTIVO', 148500.00, 2, '2026-01-31 14:25:28'),
(256, '2026-01-31 11:25:45', 'SUELDOS', 'Pago nómina - Francisco Cardozo', 'EFECTIVO', 237512.00, 2, '2026-01-31 14:25:45'),
(259, '2026-01-31 11:25:54', 'SUELDOS', 'Pago nómina - Francisco Toconas', 'EFECTIVO', 244326.00, 2, '2026-01-31 14:25:54'),
(262, '2026-01-31 11:26:06', 'SUELDOS', 'Pago nómina - Franco Gomez', 'EFECTIVO', 237512.00, 2, '2026-01-31 14:26:06'),
(265, '2026-01-31 11:26:13', 'SUELDOS', 'Pago nómina - Joaquin Fabian', 'EFECTIVO', 235125.00, 2, '2026-01-31 14:26:13'),
(268, '2026-01-31 11:26:34', 'SUELDOS', 'Pago nómina - Jorge Geronimo', 'EFECTIVO', 153126.91, 2, '2026-01-31 14:26:34'),
(271, '2026-01-31 11:27:01', 'SUELDOS', 'Pago nómina - Jose Duran Inga', 'EFECTIVO', 208308.00, 2, '2026-01-31 14:27:01'),
(274, '2026-01-31 11:27:20', 'SUELDOS', 'Pago nómina - Jose Duran Inga', 'EFECTIVO', 40000.00, 2, '2026-01-31 14:27:20'),
(277, '2026-01-31 11:27:29', 'SUELDOS', 'Pago nómina - Juan Pablo Lobos', 'EFECTIVO', 280644.00, 2, '2026-01-31 14:27:29'),
(280, '2026-01-31 11:27:55', 'SUELDOS', 'Pago nómina - JUAN PABLO LOBOS - MENSUAL', 'EFECTIVO', 200000.00, 2, '2026-01-31 14:27:55'),
(283, '2026-01-31 11:29:46', 'SUELDOS', 'Pago nómina - Julian Sanchez', 'EFECTIVO', 240920.00, 2, '2026-01-31 14:29:46'),
(286, '2026-01-31 11:30:01', 'SUELDOS', 'Pago nómina - Julio Uzzy', 'EFECTIVO', 115920.00, 2, '2026-01-31 14:30:01'),
(289, '2026-01-31 11:30:19', 'SUELDOS', 'Pago nómina - Lautaro Lopez', 'EFECTIVO', 181500.00, 2, '2026-01-31 14:30:19'),
(292, '2026-01-31 11:30:47', 'SUELDOS', 'Pago nómina - Luis Ibarra', 'EFECTIVO', 174500.00, 2, '2026-01-31 14:30:47'),
(295, '2026-01-31 11:31:08', 'SUELDOS', 'Pago nómina - Luis Ibarra', 'EFECTIVO', 40000.00, 2, '2026-01-31 14:31:08'),
(298, '2026-01-31 11:31:19', 'SUELDOS', 'Pago nómina - Marcelo Lopez', 'EFECTIVO', 261326.00, 2, '2026-01-31 14:31:19'),
(301, '2026-01-31 11:31:38', 'SUELDOS', 'Pago nómina - Maximiliano Zacarias Asman', 'EFECTIVO', 198000.00, 2, '2026-01-31 14:31:38'),
(304, '2026-01-31 11:31:52', 'SUELDOS', 'Pago nómina - Nelson Guerra', 'EFECTIVO', 278418.00, 2, '2026-01-31 14:31:52'),
(307, '2026-01-31 11:32:03', 'SUELDOS', 'Pago nómina - Nicolas Diaz', 'EFECTIVO', 166164.50, 2, '2026-01-31 14:32:03'),
(310, '2026-01-31 11:32:26', 'SUELDOS', 'Pago nómina - Nicolas Diaz', 'EFECTIVO', 20000.00, 2, '2026-01-31 14:32:26'),
(313, '2026-01-31 11:32:39', 'SUELDOS', 'Pago nómina - Rodrigo Villagran', 'EFECTIVO', 181500.00, 2, '2026-01-31 14:32:39'),
(316, '2026-01-31 11:32:59', 'SUELDOS', 'Pago nómina - Tobias Chavez', 'EFECTIVO', 194972.00, 2, '2026-01-31 14:32:59'),
(319, '2026-01-31 11:33:09', 'SUELDOS', 'Pago nómina - Tobias Chavez', 'EFECTIVO', 30000.00, 2, '2026-01-31 14:33:09'),
(322, '2026-01-31 11:33:33', 'SUELDOS', 'Pago nómina - Tomás Canavidez', 'EFECTIVO', 300000.00, 2, '2026-01-31 14:33:33'),
(325, '2026-01-31 11:33:43', 'SUELDOS', 'Pago nómina - Tomás Duran Chavez', 'EFECTIVO', 250008.00, 2, '2026-01-31 14:33:43'),
(328, '2026-01-31 11:33:49', 'SUELDOS', 'Pago nómina - Tomás Duran Chavez', 'EFECTIVO', 250008.00, 2, '2026-01-31 14:33:49'),
(331, '2026-01-31 11:33:55', 'SUELDOS', 'Pago nómina - Tomás Duran Chavez', 'EFECTIVO', 250008.00, 2, '2026-01-31 14:33:55'),
(332, '2026-02-04 11:40:00', 'INSUMOS', 'MOLINS E HIJOS SRL', 'EFECTIVO', 650000.00, 2, '2026-02-04 14:42:58'),
(333, '2026-02-02 09:32:00', 'INSUMOS', 'polimeros grillon', 'TRANSFER', 68504.00, 2, '2026-02-05 12:33:27'),
(336, '2026-02-03 09:33:00', 'SERVICIOS', 'encomienda porosint', 'EFECTIVO', 350000.00, 2, '2026-02-05 12:33:48'),
(339, '2026-02-03 09:33:00', 'SUELDOS', 'enrique', 'EFECTIVO', 35000.00, 2, '2026-02-05 12:33:59'),
(342, '2026-02-03 09:33:00', 'INSUMOS', 'madera', 'EFECTIVO', 170000.00, 2, '2026-02-05 12:34:12'),
(345, '2026-02-04 09:34:00', 'SERVICIOS', 'encomienda san isidro', 'EFECTIVO', 25000.00, 2, '2026-02-05 12:34:25'),
(348, '2026-02-04 09:34:00', 'SERVICIOS', 'camion', 'TRANSFER', 550000.00, 2, '2026-02-05 12:34:46'),
(351, '2026-02-04 09:34:00', 'INSUMOS', 'aceros figueroa', 'EFECTIVO', 1700000.00, 2, '2026-02-05 12:35:39'),
(354, '2026-02-04 09:35:00', 'INSUMOS', 'gasol', 'TRANSFER', 1449335.00, 2, '2026-02-05 12:36:06'),
(357, '2026-02-04 09:36:00', 'INSUMOS', 'gym top barras x 9', 'TRANSFER', 900000.00, 2, '2026-02-05 12:36:38'),
(360, '2026-02-05 09:36:00', 'ALQUILER', 'alquiler', 'EFECTIVO', 1100000.00, 2, '2026-02-05 12:36:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cc_movimientos`
--

CREATE TABLE `cc_movimientos` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo` enum('VENTA','PAGO','AJUSTE','NOTA','ENTREGA') COLLATE utf8mb4_general_ci DEFAULT 'VENTA',
  `detalle` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `debe` decimal(12,2) NOT NULL DEFAULT '0.00',
  `haber` decimal(12,2) NOT NULL DEFAULT '0.00',
  `saldo` decimal(12,2) GENERATED ALWAYS AS ((`debe` - `haber`)) STORED,
  `usuario` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gym` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuit_dni` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condicion_iva` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `limite_credito` decimal(12,2) DEFAULT '0.00',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `customers`
--

INSERT INTO `customers` (`id`, `nombre`, `gym`, `cuit_dni`, `telefono`, `email`, `direccion`, `condicion_iva`, `limite_credito`, `notas`, `activo`, `created_at`) VALUES
(1, 'SANTIAGO JULIAN RAMIRO', 'Zona Fit Gym', '20-31907435-0', '2392567250', NULL, 'Hipólito Yrigoyen 969, Tres Lomas', NULL, 0.00, 'Fecha compra: 2024-06-06; Color tapizado: Olímpicos; Color estructura: Toro gris / Maquina gris; Máquinas: 1 POLEA DOBLE V REG; 1 SILLÓN CUÁDRICEPS; 1 CAMILLA FEMORAL; Problema: cable', 0, '2026-01-28 12:31:12'),
(3, 'JAVIER GUSTAVO RUFFET', '—', '20-21454112-3', '1136160006', NULL, 'Angel Marcelo T de Alvear 2272, Don Torcuato, Partido Tigre', NULL, 0.00, 'Fecha compra: 2024-06-07; Observación: 18/09/25; Color tapizado: Estándar; Color estructura: Negro brillante / Móvil rojo; Máquinas: 1 REMO T; 1 ABDUCTOR DE PIE; 1 BELT SQUAT; 1 ABDUCTOR ARTICULADO PRO; Problema: no', 1, '2026-01-28 12:31:12'),
(5, 'Alfredo Omar Fernandez', 'Yago Gym', '23-14275741-9', '2213646566', NULL, '134 esquina 66 nro 1949, La Plata (Los Hornos)', NULL, 0.00, 'Fecha compra: 2024-06-18; Observación: 23/09/25; Color tapizado: Olímpicos; Color estructura: Negro brillante / negro; Máquinas: 1 POLEA ALTA; 1 HIP THRUST; Problema: no', 1, '2026-01-28 12:31:12'),
(7, 'Susana Carneli', 'Aereo Gym', '27-18.107.650-5', '26146990633', NULL, 'Manuel Estrada 243, Maipú, Mendoza', NULL, 0.00, 'Fecha compra: 2024-06-24; Color tapizado: Gris humo; Color estructura: Verde telefónica; Máquinas: 1 banco remo; Problema: Demora en plazo de entrega', 0, '2026-01-28 12:31:12'),
(9, 'Daniela Goglia', NULL, '27-34650458-2', NULL, NULL, 'Josefina B de Marquez 3893, Morón', NULL, 0.00, 'Fecha compra: 2024-06-24; Color tapizado: Negro; Color estructura: azul; Máquinas: (no listado)', 1, '2026-01-28 12:31:12'),
(11, 'Marcantoni Hernán', NULL, NULL, '2317449576', NULL, 'Compayre 656, Nueve de Julio, Buenos Aires', NULL, 0.00, 'Fecha compra: 2024-06-27; Color tapizado: Negro mate; Color estructura: amarillo; Máquinas: 1 banco remo; 1 set de accesorios; Problema: no', 1, '2026-01-28 12:31:12'),
(13, 'Martin Alberto Villalba', NULL, '20-31611712-1', '2914198865', NULL, 'Pilmaiquen 1421, Bahía Blanca', NULL, 0.00, 'Fecha compra: 2024-07-17; Color tapizado: Gris humo; Color estructura: Rojo tapizado; Máquinas: 1 torre de polea; Problema: no', 0, '2026-01-28 12:31:12'),
(15, 'Eduardo Condori', 'Acuarius gym', '20-27726755-2', '3884074873', NULL, 'Dorrego 134, El Carmen, Jujuy', NULL, 0.00, 'Fecha compra: 2024-07-17; Color tapizado: olímpico; Color estructura: Negro mate / rojo; Máquinas: 1 femoral parado; Problema: Demora de entrega', 0, '2026-01-28 12:31:12'),
(17, 'Alejandro Ferrini', 'Endorfinas gym', NULL, '2474666693', NULL, 'Pueblos originarios 254, Rojas, Provincia de Buenos Aires', NULL, 0.00, 'Fecha compra: 2024-07-17; Color tapizado: Estándar; Color estructura: Gris / Negro toro; Máquinas: 1 HIPEREXTENSION INV.; Problema: no', 0, '2026-01-28 12:31:12'),
(19, 'Ricardo Melgarejo', NULL, '20-33399492-6', '27319304', NULL, 'Aeronautica Argentina 1303, Moreno (Bs As)', NULL, 0.00, 'Fecha compra: 2024-07-25; Color tapizado: Gris humo; Color estructura: Gris plata toro; Máquinas: 1 PANTORRILLA DE PIE', 0, '2026-01-28 12:31:12'),
(21, 'Pablo Carlos Silvestri', 'The Crab Gym', '20-28801951-8', NULL, NULL, '14 de Julio 1240, Mar del Plata', NULL, 0.00, 'Fecha compra: 2024-11-12; Color tapizado: olimpico / Negro mate; Color estructura: Gris; Máquinas: 1 ELEVACIÓN DE PELVIS; 1 HACK INVERTIDA; Problema: no', 1, '2026-01-28 12:31:12'),
(23, 'Retamero Dario', 'Bunker Gym', NULL, NULL, NULL, 'Calle Buenos Aires / J.A.Roca y Pueyrredón, Ituzaingó, Corrientes', NULL, 0.00, 'Fecha compra: 2024-11-14; Máquinas: 1 ADUCTOR/ABDUCTOR; 1 APERTURA POSTERIOR; 1 POLEA ENFRENTADA REG.; 1 SYSSY; Problema: Pintura / sissy', 0, '2026-01-28 12:31:12'),
(25, 'Marcos Damián Cossi', 'Mark Gym', '23-33286272-3', NULL, NULL, 'San Fernando, Buenos Aires', NULL, 0.00, 'Fecha compra: 2024-10-11; Máquinas: 1 BELT SQUAT; 1 POLEA ENFRENTADA REGULABLE; 1 VUELO LATERAL DE PIE; Problemas: Pintura / Golpeadas / Cableado / demora', 0, '2026-01-28 12:31:12'),
(27, 'Monica Fernandez', NULL, NULL, NULL, NULL, 'Cliente Pao', NULL, 0.00, 'Notas: Cliente Pao', 1, '2026-01-28 12:31:12'),
(29, 'Mario Gonzalo Pachao', NULL, NULL, NULL, NULL, 'Cliente Pao', NULL, 0.00, 'Notas: Cliente Pao', 1, '2026-01-28 12:31:12'),
(31, 'Yauck Ulises', 'Arena Gym', '20267869821', NULL, NULL, 'Moreno 3700, Buenos Aires', NULL, 0.00, 'Fecha compra: 2024-08-06; Color tapizado: Turquesa; Color estructura: Gris humo; Máquinas: 2 POLEA ALTA; 2 POLEA ENFRENTADA SIMPLE; 1 POLEA ENFRENTADA; 1 SILLÓN DE CUADRICEPS; 1 CAMILLA FEMORAL; 1 VUELO LATERAL DE PIE; 1 HIP THRUST DE PIE; 1 ABDUCTOR ARTICULADO; 1 REMO T; 1 FEMORAL DE PIE; 1 PANTORRILLA DE PIE; 1 BANCO LUMBAR; 1 MANCUERNERO X 2 PISOS; 1 BANCO REMO; Problemas: Tiempo de demora / pintura', 1, '2026-01-28 12:31:12'),
(33, 'Carlos Calderon', '', '', '', '', 'Namuncura 187 / Bolivar 6550', '', 0.00, 'Fecha compra: (08 de enero); Máquinas: 1 BANCO ARTICULADO; 1 POLEA ENFRENTADA REG; Problema: no', 0, '2026-01-28 12:31:12'),
(35, 'Fabián Osvaldo Alcántara', 'Evolución Fitness', '20-29378261-0', '3755648820', NULL, 'Juan B. Alberdi 866, Obera Misiones 3360', NULL, 0.00, 'Fecha compra: 2025-01-16; Color tapizado: Negro mate; Color estructura: gris; Máquinas: 1 ABDUCTOR ARTICULADO; 1 HIP THRUST DE PIE; 1 HACK CIRCULAR; Problema: no', 1, '2026-01-28 12:31:12'),
(37, 'Ezequiel Llan de Rosos', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-29 11:58:42'),
(40, 'Franco Elguero', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-29 12:18:28'),
(43, 'Gladys Sosa', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-29 12:20:09'),
(46, 'Karina Rodas', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-29 12:30:33'),
(49, 'Javier Encina', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-29 12:37:39'),
(52, 'Sergio Palacios', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-29 12:46:52'),
(55, 'Ariel Callen', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-29 12:50:32'),
(58, 'Alejandro Faisal', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-29 12:51:42'),
(61, 'Paulo Gonza', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-29 12:54:30'),
(64, 'Agustin Piermattei', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-29 12:58:56'),
(65, 'Javier Rufet', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-30 20:56:02'),
(67, 'Diego Camenforte', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-31 12:46:15'),
(70, 'Javier Bello', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-31 12:49:47'),
(73, 'Ivan Tuma', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-01-31 14:15:56'),
(75, 'Matias Guillot', '', '', '', '', '', 'Consumidor Final', 0.00, '', 1, '2026-02-05 13:15:11'),
(78, 'Victor Jose Florio', 'Forza club', '29257800', '2235292484', '', 'Edison 1978, Mar del Plata, Buenos aires', 'Consumidor Final', 0.00, 'estructura negro mate, detalles en gris, tapizado negro, olímpico', 1, '2026-02-05 13:40:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `customer_ledger`
--

CREATE TABLE `customer_ledger` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo` enum('CARGO','ABONO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `origen` enum('VENTA','PAGO','NC') COLLATE utf8mb4_unicode_ci NOT NULL,
  `referencia_id` int DEFAULT NULL,
  `detalle` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `saldo_resultante` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `customer_ledger`
--

INSERT INTO `customer_ledger` (`id`, `customer_id`, `fecha`, `tipo`, `origen`, `referencia_id`, `detalle`, `monto`, `saldo_resultante`) VALUES
(1, 31, '2026-01-28 11:20:42', 'ABONO', 'PAGO', 1, 'Pago registrado en caja', 1.00, -1.00),
(3, 21, '2026-01-28 11:23:07', 'ABONO', 'PAGO', 3, 'Pago registrado en caja', 1.00, -1.00),
(5, 37, '2026-01-29 09:17:15', 'CARGO', 'VENTA', 1, 'Venta pedido #1', 8692000.00, 8692000.00),
(7, 37, '2026-01-29 09:17:15', 'ABONO', 'PAGO', 1, 'Seña pedido #1', 3155000.00, 5537000.00),
(10, 40, '2026-01-29 09:19:10', 'CARGO', 'VENTA', 4, 'Venta pedido #4', 3576000.00, 3576000.00),
(13, 40, '2026-01-29 09:19:10', 'ABONO', 'PAGO', 4, 'Seña pedido #4', 1788000.00, 1788000.00),
(16, 40, '2026-01-29 09:19:26', 'ABONO', 'PAGO', 10, 'Pago registrado en caja', 600000.00, 1188000.00),
(19, 40, '2026-01-29 09:19:46', 'ABONO', 'PAGO', 13, 'Pago registrado en caja', 1188000.00, 0.00),
(22, 43, '2026-01-29 09:28:06', 'CARGO', 'VENTA', 7, 'Venta pedido #7', 35280000.00, 35280000.00),
(25, 43, '2026-01-29 09:28:06', 'ABONO', 'PAGO', 7, 'Seña pedido #7', 7634000.00, 27646000.00),
(28, 43, '2026-01-29 09:28:41', 'ABONO', 'PAGO', 19, 'Pago registrado en caja', 2950000.00, 24696000.00),
(31, 46, '2026-01-29 09:36:30', 'CARGO', 'VENTA', 10, 'Venta pedido #10', 23809000.00, 23809000.00),
(34, 46, '2026-01-29 09:36:30', 'ABONO', 'PAGO', 10, 'Seña pedido #10', 7404500.00, 16404500.00),
(37, 46, '2026-01-29 09:36:50', 'ABONO', 'PAGO', 25, 'Pago registrado en caja', 5000000.00, 11404500.00),
(40, 49, '2026-01-29 09:43:57', 'CARGO', 'VENTA', 13, 'Venta pedido #13', 29112000.00, 29112000.00),
(43, 49, '2026-01-29 09:43:57', 'ABONO', 'PAGO', 13, 'Seña pedido #13', 2118478.00, 26993522.00),
(46, 49, '2026-01-29 09:44:31', 'ABONO', 'PAGO', 31, 'Pago registrado en caja', 2846200.00, 24147322.00),
(49, 49, '2026-01-29 09:45:00', 'ABONO', 'PAGO', 34, 'Pago registrado en caja', 7621799.00, 16525523.00),
(52, 49, '2026-01-29 09:45:24', 'ABONO', 'PAGO', 37, 'Pago registrado en caja', 500000.00, 16025523.00),
(55, 49, '2026-01-29 09:46:02', 'ABONO', 'PAGO', 40, 'Pago registrado en caja', 1513783.00, 14511740.00),
(58, 52, '2026-01-29 09:50:23', 'CARGO', 'VENTA', 16, 'Venta pedido #16', 3000000.00, 3000000.00),
(61, 52, '2026-01-29 09:50:23', 'ABONO', 'PAGO', 16, 'Seña pedido #16', 1000000.00, 2000000.00),
(64, 55, '2026-01-29 09:51:35', 'CARGO', 'VENTA', 19, 'Venta pedido #19', 2741000.00, 2741000.00),
(67, 55, '2026-01-29 09:51:35', 'ABONO', 'PAGO', 19, 'Seña pedido #19', 1370500.00, 1370500.00),
(70, 58, '2026-01-29 09:53:09', 'CARGO', 'VENTA', 22, 'Venta pedido #22', 5951000.00, 5951000.00),
(73, 58, '2026-01-29 09:53:09', 'ABONO', 'PAGO', 22, 'Seña pedido #22', 2000000.00, 3951000.00),
(76, 58, '2026-01-29 09:53:36', 'ABONO', 'PAGO', 52, 'Pago registrado en caja', 1630777.00, 2320223.00),
(79, 58, '2026-01-29 09:54:06', 'ABONO', 'PAGO', 55, 'Pago registrado en caja', 1704557.00, 615666.00),
(82, 61, '2026-01-29 09:58:21', 'CARGO', 'VENTA', 25, 'Venta pedido #25', 18375000.00, 18375000.00),
(85, 61, '2026-01-29 09:58:21', 'ABONO', 'PAGO', 25, 'Seña pedido #25', 4172000.00, 14203000.00),
(88, 61, '2026-01-29 09:58:39', 'ABONO', 'PAGO', 61, 'Pago registrado en caja', 298000.00, 13905000.00),
(91, 64, '2026-01-29 10:00:07', 'CARGO', 'VENTA', 28, 'Venta pedido #28', 1328000.00, 1328000.00),
(94, 64, '2026-01-29 10:00:07', 'ABONO', 'PAGO', 28, 'Seña pedido #28', 600000.00, 728000.00),
(95, 3, '2026-01-30 17:58:17', 'CARGO', 'VENTA', 29, 'Venta pedido #29', 3040000.00, 3040000.00),
(96, 3, '2026-01-30 17:58:17', 'ABONO', 'PAGO', 29, 'Seña pedido #29', 500000.00, 2540000.00),
(97, 67, '2026-01-31 09:48:19', 'CARGO', 'VENTA', 31, 'Venta pedido #31', 4377000.00, 4377000.00),
(100, 67, '2026-01-31 09:48:19', 'ABONO', 'PAGO', 31, 'Seña pedido #31', 2377000.00, 2000000.00),
(103, 70, '2026-01-31 09:50:48', 'CARGO', 'VENTA', 34, 'Venta pedido #34', 6600000.00, 6600000.00),
(106, 70, '2026-01-31 09:50:48', 'ABONO', 'PAGO', 34, 'Seña pedido #34', 1625000.00, 4975000.00),
(109, 70, '2026-01-31 09:51:08', 'ABONO', 'PAGO', 73, 'Pago registrado en caja', 500000.00, 4475000.00),
(112, 70, '2026-01-31 09:51:37', 'ABONO', 'PAGO', 76, 'Pago registrado en caja', 600000.00, 3875000.00),
(115, 73, '2026-01-31 11:16:18', 'ABONO', 'PAGO', 79, 'Pago registrado en caja', 2500000.00, -2500000.00),
(117, 75, '2026-02-05 10:26:31', 'CARGO', 'VENTA', 36, 'Venta pedido #36', 13108000.00, 13108000.00),
(120, 75, '2026-02-05 10:27:17', 'CARGO', 'VENTA', 39, 'Venta pedido #39', 52048000.00, 65156000.00),
(123, 78, '2026-02-05 10:45:03', 'CARGO', 'VENTA', 42, 'Venta pedido #42', 3576000.00, 3576000.00),
(126, 78, '2026-02-05 10:45:03', 'ABONO', 'PAGO', 42, 'Seña pedido #42', 200000.00, 3376000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `employees`
--

CREATE TABLE `employees` (
  `id` int NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dni` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `domicilio` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provincia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_postal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_contratacion` date NOT NULL,
  `sueldo_base_semanal` decimal(12,2) NOT NULL,
  `puesto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVO',
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `saldo_pendiente` decimal(12,2) DEFAULT '0.00',
  `sueldo_semanal` decimal(10,2) DEFAULT '0.00',
  `pago_por_hora` decimal(10,2) DEFAULT '0.00',
  `pago_semanal` decimal(10,2) DEFAULT '0.00',
  `pago_mensual` decimal(10,2) DEFAULT '0.00',
  `suspendido` tinyint(1) DEFAULT '0',
  `fecha_inicio_suspension` date DEFAULT NULL,
  `fecha_fin_suspension` date DEFAULT NULL,
  `motivo_suspension` text COLLATE utf8mb4_unicode_ci,
  `en_licencia_medica` tinyint(1) DEFAULT '0',
  `fecha_inicio_licencia` date DEFAULT NULL,
  `fecha_fin_licencia` date DEFAULT NULL,
  `motivo_licencia` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `employees`
--

INSERT INTO `employees` (`id`, `nombre`, `apellido`, `email`, `telefono`, `dni`, `fecha_nacimiento`, `domicilio`, `ciudad`, `provincia`, `codigo_postal`, `fecha_contratacion`, `sueldo_base_semanal`, `puesto`, `estado`, `activo`, `created_at`, `updated_at`, `saldo_pendiente`, `sueldo_semanal`, `pago_por_hora`, `pago_semanal`, `pago_mensual`, `suspendido`, `fecha_inicio_suspension`, `fecha_fin_suspension`, `motivo_suspension`, `en_licencia_medica`, `fecha_inicio_licencia`, `fecha_fin_licencia`, `motivo_licencia`) VALUES
(12, 'Leonel', 'Delgado', NULL, '3875829855', '39489257', NULL, 'B° Union mza 335b casa 8', NULL, NULL, NULL, '2021-02-01', 275000.00, NULL, 'ACTIVO', 1, '2026-01-28 13:16:06', '2026-01-28 15:52:43', 0.00, 0.00, 6250.00, 275000.00, 1100000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(13, 'Agustin', 'Frias Lobos', NULL, '3875902064', '43168444', NULL, 'Mza 7d casa 17, grupo 222 B° Castañares', NULL, NULL, NULL, '2021-08-01', 250000.00, NULL, 'ACTIVO', 1, '2026-01-28 13:23:50', '2026-01-31 13:18:16', 0.00, 0.00, 5275.00, 232100.00, 928400.00, 0, NULL, NULL, '', 0, '0000-00-00', NULL, NULL),
(15, 'Celeste', 'Castillo', NULL, '3875379579', '31035759', NULL, 'Mza r lote 11 B° 15 de septiembre', NULL, NULL, NULL, '2020-10-01', 125000.00, NULL, 'ACTIVO', 1, '2026-01-28 13:29:45', '2026-01-28 15:52:43', 0.00, 0.00, 5209.00, 125000.00, 500000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(17, 'Juan Pablo', 'Lobos', NULL, '3876851549', '42502933', NULL, 'Salvador Mazza 334 B° Universitario', NULL, NULL, NULL, '2022-03-01', 237468.00, NULL, 'ACTIVO', 1, '2026-01-28 13:31:20', '2026-01-28 15:52:43', 0.00, 0.00, 5397.00, 237468.00, 949872.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(19, 'Cristian', 'Zacarias', NULL, '3875101672', '42502989', NULL, 'Paz Lezcano lote 27 ,grupo 244 B° Castañares', NULL, NULL, NULL, '2022-07-01', 224972.00, NULL, 'ACTIVO', 1, '2026-01-28 13:32:04', '2026-01-28 15:52:43', 0.00, 0.00, 5113.00, 224972.00, 899888.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(21, 'Fabricio', 'Lopez Saavedra', NULL, '3876231753', '39364432', NULL, 'Los tilos 190, B° tres cerritos', NULL, NULL, NULL, '2023-01-01', 237512.00, NULL, 'ACTIVO', 1, '2026-01-28 13:34:47', '2026-01-31 14:35:44', 100000.00, 0.00, 5398.00, 237512.00, 950000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(23, 'Joana', 'Cardozo', NULL, '3874419911', '39893146', NULL, 'Pueyrredón 2096, B° Vicente Sola', NULL, NULL, NULL, '2023-01-01', 118728.00, NULL, 'ACTIVO', 1, '2026-01-28 13:36:05', '2026-01-28 15:52:43', 0.00, 0.00, 4947.00, 118728.00, 474912.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(25, 'Marcelo', 'Lopez', NULL, '3874139376', '24138964', NULL, 'Los tilos 190, B° tres cerritos', NULL, NULL, NULL, '2023-03-01', 249964.00, NULL, 'ACTIVO', 1, '2026-01-28 13:37:01', '2026-01-28 15:52:43', 0.00, 0.00, 5681.00, 249964.00, 999856.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(27, 'Clara', 'Ayosa', NULL, '3875891520', '42753310', NULL, 'Francisco Corvalan 54 block 40, dpto 9 B° Huaico', NULL, NULL, NULL, '2023-05-01', 292160.00, NULL, 'ACTIVO', 1, '2026-01-28 13:38:05', '2026-01-28 15:52:43', 0.00, 0.00, 6640.00, 292160.00, 1168640.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(29, 'Francisco', 'Toconas', NULL, '3874486744', '31035759', NULL, 'Casa 19, grupo 222 B° Castañares', NULL, NULL, NULL, '2023-09-01', 250008.00, NULL, 'ACTIVO', 1, '2026-01-28 13:38:54', '2026-01-28 15:52:43', 0.00, 0.00, 5682.00, 250008.00, 1000032.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(31, 'Franco', 'Gomez', NULL, '3874849106', '43951724', NULL, 'Juramento 3134 B° Lamadrid', NULL, NULL, NULL, '2023-07-07', 237512.00, NULL, 'INACTIVO', 1, '2026-01-28 13:39:50', '2026-02-02 12:26:41', 0.00, 0.00, 5398.00, 237512.00, 950048.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(33, 'Antonio', 'Lance', NULL, '3875615859', '43837791', NULL, 'Dean Funes 2495 B° Vicente Sola', NULL, NULL, NULL, '2023-08-01', 250008.00, NULL, 'ACTIVO', 1, '2026-01-28 13:40:42', '2026-01-31 14:35:44', 76666.67, 0.00, 5682.00, 250008.00, 1000032.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(35, 'Jose', 'Duran Inga', NULL, '3875045043', '45770616', NULL, 'Tejada 305 B° Vicente Sola', NULL, NULL, NULL, '2023-11-01', 237512.00, NULL, 'ACTIVO', 1, '2026-01-28 13:41:57', '2026-01-28 15:52:43', 0.00, 0.00, 5398.00, 237512.00, 950048.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(37, 'Jose', 'Moreno', NULL, '2901308811', '40147890', NULL, 'Pueyredon 2096 B° Vicente sola', NULL, NULL, NULL, '2024-09-01', 224512.00, NULL, 'ACTIVO', 1, '2026-01-28 13:42:52', '2026-01-31 14:35:44', 224512.00, 0.00, 5113.00, 224512.00, 899888.00, 1, '2026-01-26', '2026-02-26', '', 0, NULL, NULL, ''),
(39, 'Francisco', 'Cardozo', NULL, '3875638378', '45597037', NULL, 'Pueyredon 2096 B° Vicente sola', NULL, NULL, NULL, '2024-09-01', 237512.00, NULL, 'ACTIVO', 1, '2026-01-28 13:45:24', '2026-01-28 15:52:43', 0.00, 0.00, 5398.00, 237512.00, 950048.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(41, 'Nelson', 'Guerra', NULL, '3874158299', '31194989', NULL, 'Pueyrredon 3288 B° Vicente sola', NULL, NULL, NULL, '2025-02-01', 250008.00, NULL, 'ACTIVO', 1, '2026-01-28 13:46:02', '2026-01-28 15:52:43', 0.00, 0.00, 5682.00, 250008.00, 1000032.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(43, 'Julio', 'Uzzy', NULL, '3875537563', '43685882', NULL, 'Casa 8, mza 27, grupo 648 B°', NULL, NULL, NULL, '2025-02-01', 237512.00, NULL, 'ACTIVO', 1, '2026-01-28 13:46:55', '2026-01-31 14:35:44', 100000.00, 0.00, 5398.00, 237512.00, 950000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(45, 'Jorge', 'Geronimo', NULL, '3874426560', '39404646', NULL, 'Cristian Ibañez 56 B°  Ara san juan', NULL, NULL, NULL, '2022-02-01', 300036.00, NULL, 'ACTIVO', 1, '2026-01-28 13:47:45', '2026-01-31 14:35:44', 146909.09, 0.00, 6819.00, 300036.00, 1200144.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(47, 'Brisa', 'Dening', NULL, '3875248472', '46801674', NULL, 'Mza V casa 19 B° san Benjamin', NULL, NULL, NULL, '2024-08-01', 199980.00, NULL, 'ACTIVO', 1, '2026-01-28 13:48:35', '2026-01-28 15:52:43', 0.00, 0.00, 4545.00, 199980.00, 799920.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(49, 'Tobias', 'Chavez', NULL, '3874810723', '46172894', NULL, 'Mza r lote 11 B° 15 de septiembre', NULL, NULL, NULL, '2025-06-01', 224972.00, NULL, 'ACTIVO', 1, '2026-01-28 13:49:17', '2026-01-28 15:52:43', 0.00, 0.00, 5113.00, 224972.00, 899888.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(51, 'Julian', 'Sanchez', NULL, '3874026801', '40657843', NULL, 'Casa 26 mza 16, grupo 648 B° Castañares', NULL, NULL, NULL, '2025-07-01', 250008.00, NULL, 'ACTIVO', 1, '2026-01-28 13:50:06', '2026-01-31 12:34:50', 0.00, 0.00, 5682.00, 250008.00, 1000032.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(53, 'Erik', 'Yapura', NULL, '3875111884', '45435605', NULL, 'Av Costanera 35 Vaqueros', NULL, NULL, NULL, '2025-07-01', 224972.00, NULL, 'ACTIVO', 1, '2026-01-28 13:50:42', '2026-01-28 15:52:43', 0.00, 0.00, 5113.00, 224972.00, 899888.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(55, 'Nicolas', 'Diaz', NULL, '3875094798', '45767855', NULL, 'Av Saavedra mza 1 casa 1 villa palacios', NULL, NULL, NULL, '2025-08-01', 224512.00, NULL, 'ACTIVO', 1, '2026-01-28 13:51:20', '2026-02-02 12:27:04', 0.00, 0.00, 5113.00, 224512.00, 899888.00, 1, NULL, NULL, '', 0, NULL, NULL, ''),
(57, 'Lautaro', 'Lopez', NULL, '3875832789', '46170564', NULL, 'Los tilos 190, B° tres cerritos', NULL, NULL, NULL, '2025-10-01', 181500.00, NULL, 'ACTIVO', 1, '2026-01-28 13:52:09', '2026-01-28 15:52:43', 0.00, 0.00, 4125.00, 181500.00, 726000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(59, 'Maximiliano', 'Zacarias Asman', NULL, '3875793999', '45770517', NULL, 'Paz Lezcano lote 27, grupo 244 B° Castañares', NULL, NULL, NULL, '2025-11-01', 181500.00, NULL, 'ACTIVO', 1, '2026-01-28 13:52:52', '2026-01-28 15:52:43', 0.00, 0.00, 4125.00, 181500.00, 726000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(61, 'Rodrigo', 'Villagran', NULL, '3876851097', '43218712', NULL, 'Matienzo 640 B° Miguel Ortiz', NULL, NULL, NULL, '2025-12-01', 181500.00, NULL, 'ACTIVO', 1, '2026-01-28 13:53:29', '2026-01-28 15:52:43', 0.00, 0.00, 4125.00, 181500.00, 726000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(63, 'Facundo', 'Sanchez', NULL, '3875689717', '45598057', NULL, '4 bis B° Nogalar', NULL, NULL, NULL, '2025-12-01', 181500.00, NULL, 'ACTIVO', 1, '2026-01-28 13:54:22', '2026-01-28 15:52:43', 0.00, 0.00, 4125.00, 181500.00, 726000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(65, 'Joaquin', 'Fabian', NULL, '3876364727', '48209280', NULL, 'Mza 5 lote 14 B°  Crespones Cerrillos', NULL, NULL, NULL, '2025-12-01', 181500.00, NULL, 'ACTIVO', 1, '2026-01-28 13:54:55', '2026-01-28 15:52:43', 0.00, 0.00, 4125.00, 181500.00, 726000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(67, 'Luis', 'Ibarra', NULL, '3875637366', '43438755', NULL, 'Mza 913 c lote 4 B° 17 de mayo', NULL, NULL, NULL, '2025-12-01', 181500.00, NULL, 'ACTIVO', 1, '2026-01-28 13:55:28', '2026-01-28 15:52:43', 0.00, 0.00, 4125.00, 181500.00, 726000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(72, 'Tomás', 'Canavidez', NULL, '3873076160', '43429142', NULL, 'B° Huaico Mza 463D Casa 21', NULL, NULL, NULL, '2026-01-26', 300000.00, NULL, 'ACTIVO', 1, '2026-01-28 15:58:49', '2026-01-28 15:58:49', 0.00, 0.00, 12500.00, 300000.00, 1200000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(73, 'JUAN PABLO LOBOS', '- MENSUAL', NULL, '', '', NULL, '', NULL, NULL, NULL, '2020-01-01', 200000.00, NULL, 'ACTIVO', 1, '2026-01-30 18:39:10', '2026-01-30 18:43:08', 0.00, 200000.00, 0.00, 200000.00, 200000.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(76, 'Tomás', 'Duran Chavez', NULL, '', '45547970', NULL, '', NULL, NULL, NULL, '2022-01-01', 250008.00, NULL, 'ACTIVO', 1, '2026-01-31 13:44:31', '2026-01-31 14:35:44', -500016.00, 0.00, 5682.00, 250008.00, 1000032.00, 0, NULL, NULL, '', 0, NULL, NULL, ''),
(77, 'Operario 2', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00', 0.00, 'Operario de Soldadura', 'ACTIVO', 1, '2026-02-04 15:22:41', '2026-02-04 15:22:41', 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(78, 'Operario 3', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00', 0.00, 'Operario de Pintura', 'ACTIVO', 1, '2026-02-04 15:22:41', '2026-02-04 15:22:41', 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(79, 'Supervisor QC', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00', 0.00, 'Control de Calidad', 'ACTIVO', 1, '2026-02-04 15:22:41', '2026-02-04 15:22:41', 0.00, 0.00, 0.00, 0.00, 0.00, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `employee_advances`
--

CREATE TABLE `employee_advances` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `fecha_solicitud` date NOT NULL,
  `fecha_aprobacion` date DEFAULT NULL,
  `estado` enum('SOLICITADO','APROBADO','RECHAZADO','DESCONTADO') COLLATE utf8mb4_unicode_ci DEFAULT 'SOLICITADO',
  `razon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `employee_advances`
--

INSERT INTO `employee_advances` (`id`, `employee_id`, `monto`, `fecha_solicitud`, `fecha_aprobacion`, `estado`, `razon`, `created_at`) VALUES
(4, 33, 10000.00, '2026-01-31', '2026-01-31', 'DESCONTADO', 'ADELANTO SEMANAL', '2026-01-31 12:30:39'),
(7, 55, 20000.00, '2026-01-31', '2026-01-31', 'DESCONTADO', 'ADELANTO SEMANAL', '2026-01-31 12:31:12'),
(10, 49, 30000.00, '2026-01-31', '2026-01-31', 'DESCONTADO', 'ADELANTO SEMANAL', '2026-01-31 12:31:39'),
(13, 35, 40000.00, '2026-01-31', '2026-01-31', 'DESCONTADO', 'TRANSFERENCIA', '2026-01-31 13:24:01'),
(16, 67, 40000.00, '2026-01-31', '2026-01-31', 'DESCONTADO', 'ADELANTO SEMANA ANTERIOR', '2026-01-31 13:36:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `employee_attendance`
--

CREATE TABLE `employee_attendance` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `fecha` date NOT NULL,
  `hora_entrada` time DEFAULT NULL,
  `hora_salida` time DEFAULT NULL,
  `presente` tinyint(1) DEFAULT '1',
  `justificado` tinyint(1) DEFAULT '0',
  `notas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ingreso_manana` time DEFAULT NULL,
  `ingreso_tarde` time DEFAULT NULL,
  `horas_extras` decimal(5,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `employee_attendance`
--

INSERT INTO `employee_attendance` (`id`, `employee_id`, `fecha`, `hora_entrada`, `hora_salida`, `presente`, `justificado`, `notas`, `created_at`, `ingreso_manana`, `ingreso_tarde`, `horas_extras`) VALUES
(19, 19, '2026-01-30', NULL, NULL, 1, 0, '', '2026-01-30 18:17:25', NULL, NULL, 2.00),
(22, 21, '2026-01-30', NULL, NULL, 1, 0, '', '2026-01-30 18:17:41', NULL, NULL, 2.00),
(25, 25, '2026-01-30', NULL, NULL, 1, 0, '', '2026-01-30 18:18:10', NULL, NULL, 2.00),
(28, 27, '2026-01-30', NULL, NULL, 1, 0, '', '2026-01-30 18:18:23', NULL, NULL, 1.00),
(31, 35, '2026-01-30', NULL, NULL, 1, 0, '', '2026-01-30 18:20:08', NULL, NULL, 2.00),
(34, 41, '2026-01-30', NULL, NULL, 1, 0, '', '2026-01-30 18:20:40', NULL, NULL, 5.00),
(37, 53, '2026-01-30', NULL, NULL, 1, 0, '', '2026-01-30 18:21:17', NULL, NULL, 2.00),
(40, 65, '2026-01-30', NULL, NULL, 1, 0, '', '2026-01-30 18:21:38', NULL, NULL, 5.00),
(43, 67, '2026-01-31', NULL, NULL, 1, 0, '', '2026-01-31 12:27:56', NULL, NULL, 8.00),
(46, 17, '2026-01-31', NULL, NULL, 1, 0, '', '2026-01-31 12:28:19', NULL, NULL, 8.00),
(49, 53, '2026-01-31', NULL, NULL, 1, 0, '', '2026-01-31 12:28:31', NULL, NULL, 8.00),
(52, 19, '2026-01-31', NULL, NULL, 1, 0, '', '2026-01-31 12:28:41', NULL, NULL, 8.00),
(55, 65, '2026-01-31', NULL, NULL, 1, 0, '', '2026-01-31 12:29:53', NULL, NULL, 8.00),
(58, 21, '2026-01-31', NULL, NULL, 1, 0, '', '2026-01-31 12:30:03', NULL, NULL, 8.00),
(61, 51, '2026-01-31', NULL, NULL, 1, 0, '', '2026-01-31 13:31:10', NULL, NULL, 2.00),
(64, 55, '2026-01-31', NULL, NULL, 1, 0, '', '2026-01-31 13:31:27', NULL, NULL, 0.50),
(67, 59, '2026-01-31', NULL, NULL, 1, 0, '', '2026-01-31 13:33:36', NULL, NULL, 4.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `employee_discounts`
--

CREATE TABLE `employee_discounts` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `tipo` enum('FALTA','LLEGADA_TARDE','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `minutos_descuento` int DEFAULT '0',
  `monto_descuento` decimal(12,2) DEFAULT '0.00',
  `razon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `employee_discounts`
--

INSERT INTO `employee_discounts` (`id`, `employee_id`, `tipo`, `fecha`, `minutos_descuento`, `monto_descuento`, `razon`, `created_at`) VALUES
(1, 12, 'FALTA', '2026-01-30', 0, 50000.00, '- 1 DIA', '2026-01-30 18:23:05'),
(4, 51, 'FALTA', '2026-01-30', 0, 20452.00, '-4 HORAS', '2026-01-30 18:24:14'),
(10, 63, 'FALTA', '2026-01-30', 0, 33000.00, '-1 DIA', '2026-01-30 18:25:42'),
(22, 29, 'FALTA', '2026-01-31', 0, 5682.00, 'salida una hora antes', '2026-01-31 13:20:35'),
(25, 43, 'FALTA', '2026-01-31', 0, 21592.00, 'MEDIO DIA', '2026-01-31 13:23:35'),
(31, 55, 'FALTA', '2026-01-31', 0, 40904.00, '-1 DIA', '2026-01-31 13:47:14'),
(33, 31, 'FALTA', '2026-02-02', 0, 21592.00, 'Falta a la mañana', '2026-02-02 12:23:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `employee_incidents`
--

CREATE TABLE `employee_incidents` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `fecha` date NOT NULL,
  `tipo` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'OTRO',
  `gravedad` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'LEVE',
  `descripcion` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `employee_incidents`
--

INSERT INTO `employee_incidents` (`id`, `employee_id`, `fecha`, `tipo`, `gravedad`, `descripcion`, `created_at`, `created_by`) VALUES
(1, 47, '2026-01-31', 'OTRO', 'LEVE', 'FALTA JUSTIFICADA - 26/01/2026', '2026-01-31 13:25:42', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `employee_loans`
--

CREATE TABLE `employee_loans` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `monto_solicitado` decimal(12,2) NOT NULL,
  `monto_aprobado` decimal(12,2) DEFAULT NULL,
  `fecha_solicitud` date NOT NULL,
  `fecha_aprobacion` date DEFAULT NULL,
  `estado` enum('SOLICITADO','APROBADO','RECHAZADO','PAGADO') COLLATE utf8mb4_unicode_ci DEFAULT 'SOLICITADO',
  `razon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuotas_cantidad` int DEFAULT '1',
  `cuotas_pagadas` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `employee_loans`
--

INSERT INTO `employee_loans` (`id`, `employee_id`, `monto_solicitado`, `monto_aprobado`, `fecha_solicitud`, `fecha_aprobacion`, `estado`, `razon`, `cuotas_cantidad`, `cuotas_pagadas`, `created_at`) VALUES
(1, 33, 460000.00, 460000.00, '2026-01-30', '2026-01-30', 'APROBADO', '', 6, 0, '2026-01-30 18:32:00'),
(4, 43, 1000000.00, 1000000.00, '2026-01-30', '2026-01-30', 'APROBADO', '', 10, 0, '2026-01-30 18:32:51'),
(10, 45, 1616000.00, 1616000.00, '2026-01-30', '2026-01-30', 'APROBADO', '', 11, 0, '2026-01-30 18:38:28'),
(13, 21, 2000000.00, 2000000.00, '2026-01-31', '2026-01-31', 'APROBADO', 'PRESTAMO', 20, 0, '2026-01-31 12:33:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `employee_payroll`
--

CREATE TABLE `employee_payroll` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `fecha_pago` date NOT NULL,
  `semana_inicio` date DEFAULT NULL,
  `semana_fin` date DEFAULT NULL,
  `sueldo_base` decimal(12,2) NOT NULL,
  `descuentos_total` decimal(12,2) DEFAULT '0.00',
  `adelantos_total` decimal(12,2) DEFAULT '0.00',
  `prestamos_cuota` decimal(12,2) DEFAULT '0.00',
  `sueldo_neto` decimal(12,2) NOT NULL,
  `medio_pago` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('PENDIENTE','PAGADO','ANULADO') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDIENTE',
  `notas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `period_id` int DEFAULT NULL,
  `saldo_periodo_anterior` decimal(12,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `employee_payroll`
--

INSERT INTO `employee_payroll` (`id`, `employee_id`, `fecha_pago`, `semana_inicio`, `semana_fin`, `sueldo_base`, `descuentos_total`, `adelantos_total`, `prestamos_cuota`, `sueldo_neto`, `medio_pago`, `estado`, `notas`, `created_at`, `period_id`, `saldo_periodo_anterior`) VALUES
(19, 15, '2026-01-31', NULL, NULL, 125000.00, 0.00, 0.00, 0.00, 125000.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 13:50:50', 12, 0.00),
(22, 23, '2026-01-31', NULL, NULL, 118728.00, 0.00, 0.00, 0.00, 118728.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:13:28', 12, 0.00),
(25, 12, '2026-01-31', NULL, NULL, 275000.00, 50000.00, 0.00, 0.00, 225000.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:21:27', 12, 0.00),
(28, 13, '2026-01-31', NULL, NULL, 232100.00, 0.00, 0.00, 0.00, 232100.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:23:31', 12, 0.00),
(31, 33, '2026-01-31', NULL, NULL, 250008.00, 0.00, 10000.00, 76666.67, 163341.33, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:23:35', 12, 0.00),
(34, 33, '2026-01-31', NULL, NULL, 250008.00, 0.00, 0.00, 76666.67, 10000.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:23:51', 12, 0.00),
(37, 47, '2026-01-31', NULL, NULL, 199980.00, 0.00, 0.00, 0.00, 199980.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:24:13', 12, 0.00),
(40, 27, '2026-01-31', NULL, NULL, 298800.00, 0.00, 0.00, 0.00, 298800.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:24:22', 12, 0.00),
(43, 19, '2026-01-31', NULL, NULL, 276102.00, 0.00, 0.00, 0.00, 276102.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:24:52', 12, 0.00),
(46, 53, '2026-01-31', NULL, NULL, 276102.00, 0.00, 0.00, 0.00, 276102.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:25:04', 12, 0.00),
(49, 21, '2026-01-31', NULL, NULL, 291492.00, 0.00, 0.00, 100000.00, 191492.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:25:16', 12, 0.00),
(52, 63, '2026-01-31', NULL, NULL, 181500.00, 33000.00, 0.00, 0.00, 148500.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:25:28', 12, 0.00),
(55, 39, '2026-01-31', NULL, NULL, 237512.00, 0.00, 0.00, 0.00, 237512.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:25:45', 12, 0.00),
(58, 29, '2026-01-31', NULL, NULL, 250008.00, 5682.00, 0.00, 0.00, 244326.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:25:54', 12, 0.00),
(61, 31, '2026-01-31', NULL, NULL, 237512.00, 0.00, 0.00, 0.00, 237512.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:26:06', 12, 0.00),
(64, 65, '2026-01-31', NULL, NULL, 235125.00, 0.00, 0.00, 0.00, 235125.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:26:12', 12, 0.00),
(67, 45, '2026-01-31', NULL, NULL, 300036.00, 0.00, 0.00, 146909.09, 153126.91, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:26:34', 12, 0.00),
(70, 35, '2026-01-31', NULL, NULL, 248308.00, 0.00, 40000.00, 0.00, 208308.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:27:01', 12, 0.00),
(73, 35, '2026-01-31', NULL, NULL, 248308.00, 0.00, 0.00, 0.00, 40000.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:27:20', 12, 0.00),
(76, 17, '2026-01-31', NULL, NULL, 280644.00, 0.00, 0.00, 0.00, 280644.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:27:29', 12, 0.00),
(79, 73, '2026-01-31', NULL, NULL, 200000.00, 0.00, 0.00, 0.00, 200000.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:27:55', 12, 0.00),
(82, 51, '2026-01-31', NULL, NULL, 261372.00, 20452.00, 0.00, 0.00, 240920.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:29:46', 12, 0.00),
(85, 43, '2026-01-31', NULL, NULL, 237512.00, 21592.00, 0.00, 100000.00, 115920.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:30:01', 12, 0.00),
(88, 57, '2026-01-31', NULL, NULL, 181500.00, 0.00, 0.00, 0.00, 181500.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:30:19', 12, 0.00),
(91, 67, '2026-01-31', NULL, NULL, 214500.00, 0.00, 40000.00, 0.00, 174500.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:30:47', 12, 0.00),
(94, 67, '2026-01-31', NULL, NULL, 214500.00, 0.00, 0.00, 0.00, 40000.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:31:08', 12, 0.00),
(97, 25, '2026-01-31', NULL, NULL, 261326.00, 0.00, 0.00, 0.00, 261326.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:31:19', 12, 0.00),
(100, 59, '2026-01-31', NULL, NULL, 198000.00, 0.00, 0.00, 0.00, 198000.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:31:38', 12, 0.00),
(103, 41, '2026-01-31', NULL, NULL, 278418.00, 0.00, 0.00, 0.00, 278418.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:31:52', 12, 0.00),
(106, 55, '2026-01-31', NULL, NULL, 227068.50, 40904.00, 20000.00, 0.00, 166164.50, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:32:03', 12, 0.00),
(109, 55, '2026-01-31', NULL, NULL, 227068.50, 40904.00, 0.00, 0.00, 20000.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:32:26', 12, 0.00),
(112, 61, '2026-01-31', NULL, NULL, 181500.00, 0.00, 0.00, 0.00, 181500.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:32:39', 12, 0.00),
(115, 49, '2026-01-31', NULL, NULL, 224972.00, 0.00, 30000.00, 0.00, 194972.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:32:59', 12, 0.00),
(118, 49, '2026-01-31', NULL, NULL, 224972.00, 0.00, 0.00, 0.00, 30000.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:33:09', 12, 0.00),
(121, 72, '2026-01-31', NULL, NULL, 300000.00, 0.00, 0.00, 0.00, 300000.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:33:33', 12, 0.00),
(124, 76, '2026-01-31', NULL, NULL, 250008.00, 0.00, 0.00, 0.00, 250008.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:33:43', 12, 0.00),
(127, 76, '2026-01-31', NULL, NULL, 250008.00, 0.00, 0.00, 0.00, 250008.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:33:49', 12, 0.00),
(130, 76, '2026-01-31', NULL, NULL, 250008.00, 0.00, 0.00, 0.00, 250008.00, 'EFECTIVO', 'PAGADO', '', '2026-01-31 14:33:55', 12, 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expenses`
--

CREATE TABLE `expenses` (
  `id` int NOT NULL,
  `fecha` datetime NOT NULL,
  `categoria` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `medio` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `importe` decimal(12,2) NOT NULL,
  `detalle` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_entrega` date DEFAULT NULL,
  `estado` enum('BORRADOR','CONFIRMADO','EN_PRODUCCION','LISTO_ENTREGA','ENTREGADO','CERRADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BORRADOR',
  `total_bruto` decimal(12,2) DEFAULT '0.00',
  `descuento` decimal(12,2) DEFAULT '0.00',
  `total_neto` decimal(12,2) DEFAULT '0.00',
  `senia` decimal(12,2) DEFAULT '0.00',
  `saldo` decimal(12,2) DEFAULT '0.00',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `transporte_bonificado` tinyint(1) DEFAULT '0',
  `empresa_transporte` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `incluye_iva` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `fecha`, `fecha_entrega`, `estado`, `total_bruto`, `descuento`, `total_neto`, `senia`, `saldo`, `observaciones`, `transporte_bonificado`, `empresa_transporte`, `incluye_iva`) VALUES
(1, 37, '2026-01-29 09:17:15', '2026-02-28', 'EN_PRODUCCION', 8692000.00, 0.00, 8692000.00, 3155000.00, 5537000.00, '', 0, NULL, 1),
(4, 40, '2026-01-29 09:19:10', '2026-02-28', 'EN_PRODUCCION', 3576000.00, 0.00, 3576000.00, 1788000.00, 0.00, '', 0, NULL, 1),
(7, 43, '2026-01-29 09:28:06', '2026-03-30', 'EN_PRODUCCION', 35280000.00, 0.00, 35280000.00, 7634000.00, 24696000.00, 'Transf distribuidor MEI 16/01', 0, NULL, 1),
(10, 46, '2026-01-29 09:36:30', '2026-03-30', 'EN_PRODUCCION', 23809000.00, 0.00, 23809000.00, 7404500.00, 11404500.00, 'PAGO EN DOLARES + DTO 10% EN MAQUINAS A CONFIRMAR', 0, NULL, 1),
(13, 49, '2026-01-29 09:43:57', '2026-04-21', 'EN_PRODUCCION', 29112000.00, 0.00, 29112000.00, 2118478.00, 14511740.00, 'TRANSF. ACEROS FIGUEROA 21/01\r\nOlímpico negro mate, móvil rojo, tapizado negro', 0, NULL, 1),
(16, 52, '2026-01-29 09:50:22', '2026-02-28', 'EN_PRODUCCION', 3000000.00, 0.00, 3000000.00, 1000000.00, 2000000.00, 'Transf. a pintura\r\nNegro mate, detalles en gris, olimpico', 0, NULL, 1),
(19, 55, '2026-01-29 09:51:35', '2026-02-28', 'EN_PRODUCCION', 2741000.00, 0.00, 2741000.00, 1370500.00, 1370500.00, 'Transf. Pintura\r\nGris humo, móvil amarillo, olimpico', 0, NULL, 1),
(22, 58, '2026-01-29 09:53:09', '2026-03-15', 'EN_PRODUCCION', 5951000.00, 0.00, 5951000.00, 2000000.00, 615666.00, 'Transf. Aparejo 22/01', 0, NULL, 1),
(25, 61, '2026-01-29 09:58:21', '2026-03-30', 'EN_PRODUCCION', 18375000.00, 0.00, 18375000.00, 4172000.00, 13905000.00, 'Pago en Dolares 23/1', 0, NULL, 1),
(28, 64, '2026-01-29 10:00:07', '2026-02-28', 'EN_PRODUCCION', 1328000.00, 0.00, 1328000.00, 600000.00, 728000.00, 'Negro brillante, detalles amarillos', 0, NULL, 1),
(29, 3, '2026-01-30 17:58:17', '2026-03-31', 'EN_PRODUCCION', 3040000.00, 0.00, 3040000.00, 500000.00, 2540000.00, 'Negro brillante, móvil rojo, standar', 0, NULL, 1),
(31, 67, '2026-01-31 09:48:19', '2026-04-01', 'EN_PRODUCCION', 4377000.00, 0.00, 4377000.00, 2377000.00, 2000000.00, 'PAGO ACERLOT', 0, NULL, 1),
(34, 70, '2026-01-31 09:50:48', '2026-04-01', 'EN_PRODUCCION', 6600000.00, 0.00, 6600000.00, 1625000.00, 3875000.00, '', 0, NULL, 1),
(42, 78, '2026-02-05 10:45:03', '2026-04-06', 'EN_PRODUCCION', 3576000.00, 0.00, 3576000.00, 200000.00, 3376000.00, 'negro mate, detalles en gris, tapizado negro, olímpico', 0, NULL, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `cant` decimal(12,3) NOT NULL,
  `precio_unit` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `cant`, `precio_unit`, `subtotal`) VALUES
(1, 1, 133, 1.000, 2375000.00, 2375000.00),
(3, 1, 135, 1.000, 2741000.00, 2741000.00),
(5, 1, 177, 1.000, 3576000.00, 3576000.00),
(7, 4, 177, 1.000, 3576000.00, 3576000.00),
(10, 7, 139, 1.000, 3000000.00, 3000000.00),
(13, 7, 177, 1.000, 3576000.00, 3576000.00),
(16, 7, 125, 1.000, 3200000.00, 3200000.00),
(19, 7, 129, 1.000, 2776000.00, 2776000.00),
(22, 7, 127, 1.000, 2776000.00, 2776000.00),
(25, 7, 155, 1.000, 3000000.00, 3000000.00),
(28, 7, 151, 1.000, 3252000.00, 3252000.00),
(31, 7, 181, 1.000, 9500000.00, 9500000.00),
(34, 7, 187, 1.000, 4200000.00, 4200000.00),
(37, 10, 127, 1.000, 2776000.00, 2776000.00),
(40, 10, 139, 1.000, 3000000.00, 3000000.00),
(43, 10, 133, 1.000, 2375000.00, 2375000.00),
(46, 10, 193, 1.000, 3910000.00, 3910000.00),
(49, 10, 177, 1.000, 3576000.00, 3576000.00),
(52, 10, 151, 1.000, 3252000.00, 3252000.00),
(55, 10, 203, 1.000, 720000.00, 720000.00),
(58, 10, 187, 1.000, 4200000.00, 4200000.00),
(61, 13, 197, 1.000, 3200000.00, 3200000.00),
(64, 13, 153, 1.000, 2100000.00, 2100000.00),
(67, 13, 143, 1.000, 3240000.00, 3240000.00),
(70, 13, 137, 2.000, 3910000.00, 7820000.00),
(73, 13, 129, 1.000, 2776000.00, 2776000.00),
(76, 13, 177, 1.000, 3576000.00, 3576000.00),
(79, 13, 175, 1.000, 3200000.00, 3200000.00),
(82, 13, 125, 1.000, 3200000.00, 3200000.00),
(85, 16, 179, 1.000, 3000000.00, 3000000.00),
(88, 19, 135, 1.000, 2741000.00, 2741000.00),
(91, 22, 133, 1.000, 2375000.00, 2375000.00),
(94, 22, 177, 1.000, 3576000.00, 3576000.00),
(97, 25, 189, 1.000, 3000000.00, 3000000.00),
(100, 25, 163, 1.000, 2000000.00, 2000000.00),
(103, 25, 205, 1.000, 1560000.00, 1560000.00),
(106, 25, 217, 1.000, 1560000.00, 1560000.00),
(109, 25, 145, 1.000, 2599000.00, 2599000.00),
(112, 25, 173, 1.000, 2100000.00, 2100000.00),
(115, 25, 159, 1.000, 2690000.00, 2690000.00),
(118, 25, 199, 1.000, 1560000.00, 1560000.00),
(121, 25, 147, 1.000, 1306000.00, 1306000.00),
(124, 28, 231, 1.000, 1328000.00, 1328000.00),
(125, 29, 191, 1.000, 3040000.00, 3040000.00),
(127, 31, 165, 1.000, 4377000.00, 4377000.00),
(130, 34, 167, 1.000, 3300000.00, 3300000.00),
(133, 34, 191, 1.000, 3300000.00, 3300000.00),
(135, 36, 199, 1.000, 1560000.00, 1560000.00),
(138, 36, 179, 1.000, 3000000.00, 3000000.00),
(141, 36, 125, 1.000, 3200000.00, 3200000.00),
(144, 36, 175, 1.000, 3200000.00, 3200000.00),
(147, 36, 141, 1.000, 2148000.00, 2148000.00),
(150, 39, 127, 1.000, 2776000.00, 2776000.00),
(153, 39, 171, 1.000, 2541000.00, 2541000.00),
(156, 39, 139, 1.000, 3000000.00, 3000000.00),
(159, 39, 161, 1.000, 2880000.00, 2880000.00),
(162, 39, 165, 1.000, 4377000.00, 4377000.00),
(165, 39, 151, 1.000, 4252000.00, 4252000.00),
(168, 39, 155, 1.000, 3000000.00, 3000000.00),
(171, 39, 133, 1.000, 2375000.00, 2375000.00),
(174, 39, 135, 1.000, 2741000.00, 2741000.00),
(177, 39, 137, 1.000, 3910000.00, 3910000.00),
(180, 39, 193, 1.000, 3910000.00, 3910000.00),
(183, 39, 197, 1.000, 3200000.00, 3200000.00),
(186, 39, 231, 1.000, 1328000.00, 1328000.00),
(189, 39, 223, 1.000, 1360000.00, 1360000.00),
(192, 39, 191, 1.000, 3200000.00, 3200000.00),
(195, 39, 229, 3.000, 1680000.00, 5040000.00),
(198, 39, 221, 1.000, 830000.00, 830000.00),
(201, 39, 241, 2.000, 664000.00, 1328000.00),
(204, 42, 177, 1.000, 3576000.00, 3576000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `order_items_backup`
--

CREATE TABLE `order_items_backup` (
  `id` int NOT NULL DEFAULT '0',
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `cant` decimal(12,3) NOT NULL,
  `precio_unit` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `payments`
--

CREATE TABLE `payments` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `medio` enum('EFECTIVO','DEBITO','TRANSFER','CREDITO','NC') COLLATE utf8mb4_unicode_ci NOT NULL,
  `importe` decimal(12,2) NOT NULL,
  `referencia` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_id` int DEFAULT NULL,
  `third_party_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voucher_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `payments`
--

INSERT INTO `payments` (`id`, `customer_id`, `order_id`, `fecha`, `medio`, `importe`, `referencia`, `bank_account_id`, `third_party_name`, `voucher_path`) VALUES
(1, 31, NULL, '2026-01-28 11:20:42', 'TRANSFER', 1.00, '', 3, NULL, '../storage/vouchers/voucher_1769610042_b64db826.pdf'),
(3, 21, NULL, '2026-01-28 11:23:07', 'TRANSFER', 1.00, '', 1, NULL, '../storage/vouchers/voucher_1769610187_af721b2c.pdf'),
(5, 37, 1, '2026-01-29 09:17:15', 'TRANSFER', 3155000.00, 'Seña', 1, NULL, NULL),
(7, 40, 4, '2026-01-29 09:19:10', 'TRANSFER', 1788000.00, 'Seña', 1, NULL, NULL),
(10, 40, 4, '2026-01-29 09:19:26', 'TRANSFER', 600000.00, '', 1, NULL, NULL),
(13, 40, 4, '2026-01-29 09:19:46', 'TRANSFER', 1188000.00, '', 1, NULL, NULL),
(16, 43, 7, '2026-01-29 09:28:06', 'TRANSFER', 7634000.00, 'Seña', 3, NULL, NULL),
(19, 43, 7, '2026-01-29 09:28:41', 'TRANSFER', 2950000.00, 'Transf cuenta TOMI', 1, NULL, NULL),
(22, 46, 10, '2026-01-29 09:36:30', 'EFECTIVO', 7404500.00, 'Seña', NULL, NULL, NULL),
(25, 46, 10, '2026-01-29 09:36:50', 'TRANSFER', 5000000.00, '', 1, NULL, NULL),
(28, 49, 13, '2026-01-29 09:43:57', 'TRANSFER', 2118478.00, 'Seña', 3, NULL, NULL),
(31, 49, 13, '2026-01-29 09:44:31', 'TRANSFER', 2846200.00, 'TRANSF. POROSINT 21/01', 3, NULL, NULL),
(34, 49, 13, '2026-01-29 09:45:00', 'TRANSFER', 7621799.00, 'TRANSF. ACERLOT 22/01', 3, NULL, NULL),
(37, 49, 13, '2026-01-29 09:45:24', 'TRANSFER', 500000.00, 'TRANSF. BULONES 22/01', 3, NULL, NULL),
(40, 49, 13, '2026-01-29 09:46:02', 'TRANSFER', 1513783.00, 'TRANSF. TOMI', 1, NULL, NULL),
(43, 52, 16, '2026-01-29 09:50:23', 'TRANSFER', 1000000.00, 'Seña', 3, NULL, NULL),
(46, 55, 19, '2026-01-29 09:51:35', 'TRANSFER', 1370500.00, 'Seña', 3, NULL, NULL),
(49, 58, 22, '2026-01-29 09:53:09', 'TRANSFER', 2000000.00, 'Seña', 3, NULL, NULL),
(52, 58, 22, '2026-01-29 09:53:36', 'TRANSFER', 1630777.00, 'Transf. Aparejo 23/01', 3, NULL, NULL),
(55, 58, 22, '2026-01-29 09:54:06', 'TRANSFER', 1704557.00, 'Transf. IGNIA', 3, NULL, NULL),
(58, 61, 25, '2026-01-29 09:58:21', 'EFECTIVO', 4172000.00, 'Seña', NULL, NULL, NULL),
(61, 61, 25, '2026-01-29 09:58:39', 'TRANSFER', 298000.00, '', 1, NULL, NULL),
(64, 64, 28, '2026-01-29 10:00:07', 'TRANSFER', 600000.00, 'Seña', 1, NULL, NULL),
(65, 3, 29, '2026-01-30 17:58:17', 'TRANSFER', 500000.00, 'Seña', 1, NULL, NULL),
(67, 67, 31, '2026-01-31 09:48:19', 'TRANSFER', 2377000.00, 'Seña', 3, NULL, NULL),
(70, 70, 34, '2026-01-31 09:50:48', 'TRANSFER', 1625000.00, 'Seña', 1, NULL, NULL),
(73, 70, 34, '2026-01-31 09:51:08', 'TRANSFER', 500000.00, '', 1, NULL, NULL),
(76, 70, 34, '2026-01-31 09:51:37', 'TRANSFER', 600000.00, '', 1, NULL, NULL),
(79, 73, NULL, '2026-01-31 11:16:18', 'EFECTIVO', 2500000.00, '', NULL, NULL, NULL),
(81, 78, 42, '2026-02-05 10:45:03', 'TRANSFER', 200000.00, 'Seña', 1, NULL, 'voucher_1770299097_b94aec20.jpeg');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `payment_vouchers`
--

CREATE TABLE `payment_vouchers` (
  `id` int NOT NULL,
  `payment_id` int NOT NULL,
  `archivo_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_ruta` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_archivo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamano` int DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `payroll_periods`
--

CREATE TABLE `payroll_periods` (
  `id` int NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado` varchar(20) DEFAULT 'ACTIVO',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` timestamp NULL DEFAULT NULL,
  `closed_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `payroll_periods`
--

INSERT INTO `payroll_periods` (`id`, `fecha_inicio`, `fecha_fin`, `estado`, `created_at`, `closed_at`, `closed_by`) VALUES
(12, '2026-01-27', '2026-02-02', 'CERRADO', '2026-01-28 15:52:43', '2026-01-31 14:35:44', 2),
(13, '2026-02-03', '2026-02-09', 'ACTIVO', '2026-01-31 14:35:45', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `production_component_stages`
--

CREATE TABLE `production_component_stages` (
  `id` int NOT NULL,
  `component_type` varchar(50) NOT NULL COMMENT 'PERFIL, CHAPA, TUBO, PINTURA, QUIMICO, RODAMIENTO, etc',
  `etapa_stock` varchar(30) NOT NULL COMMENT 'CORTE, PINTURA, ENSAMBLE',
  `nombre_display` varchar(100) NOT NULL,
  `orden` int DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Configuración de qué componentes se descargan en cada etapa';

--
-- Volcado de datos para la tabla `production_component_stages`
--

INSERT INTO `production_component_stages` (`id`, `component_type`, `etapa_stock`, `nombre_display`, `orden`, `activo`) VALUES
(1, 'PERFIL', 'CORTE', 'Perfiles metálicos', 1, 1),
(2, 'CHAPA', 'CORTE', 'Chapas y láminas', 2, 1),
(3, 'TUBO', 'CORTE', 'Tubos y caños', 3, 1),
(4, 'PINTURA', 'PINTURA', 'Pinturas y esmaltes', 1, 1),
(5, 'QUIMICO', 'PINTURA', 'Químicos y diluyentes', 2, 1),
(6, 'RODAMIENTO', 'ENSAMBLE', 'Rodamientos', 1, 1),
(7, 'POLEA', 'ENSAMBLE', 'Poleas', 2, 1),
(8, 'TORNILLERIA', 'ENSAMBLE', 'Tornillería y fijaciones', 3, 1),
(9, 'TAPIZADO', 'ENSAMBLE', 'Tapizados y textiles', 4, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `production_orders`
--

CREATE TABLE `production_orders` (
  `id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `product_pt_id` int NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `estado` enum('PENDIENTE','EN_CURSO','FINALIZADA','OBSERVADA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `fecha_ini` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `estado_actual` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estado actual del sistema avanzado',
  `color_personalizado` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Color especificado por el cliente',
  `tapizado_personalizado` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tapizado especificado por el cliente',
  `bloqueada_razon` text COLLATE utf8mb4_unicode_ci COMMENT 'Razón por la que está bloqueada (ej: falta stock)',
  `ticket_impreso` tinyint(1) DEFAULT '0' COMMENT '1 si ya se imprimió algún ticket',
  `qr_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token único para QR de escaneo rápido'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `production_orders`
--

INSERT INTO `production_orders` (`id`, `order_id`, `product_pt_id`, `cantidad`, `estado`, `fecha_ini`, `fecha_fin`, `observaciones`, `estado_actual`, `color_personalizado`, `tapizado_personalizado`, `bloqueada_razon`, `ticket_impreso`, `qr_code`) VALUES
(1, 1, 133, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(3, 1, 135, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(5, 1, 177, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(7, 4, 177, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(10, 7, 139, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(13, 7, 177, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(16, 7, 125, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(19, 7, 129, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(22, 7, 127, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(25, 7, 155, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(28, 7, 151, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(31, 7, 181, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(34, 7, 187, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(37, 10, 127, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '9054250204a80fc5fb3b43150306cba7_37'),
(40, 10, 139, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(43, 10, 133, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(46, 10, 193, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(49, 10, 177, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(52, 10, 151, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(55, 10, 203, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(58, 10, 187, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(61, 13, 197, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(64, 13, 153, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(67, 13, 143, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(70, 13, 137, 2.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(73, 13, 129, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(76, 13, 177, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(79, 13, 175, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(82, 13, 125, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(85, 16, 179, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(88, 19, 135, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(91, 22, 133, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(94, 22, 177, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(97, 25, 189, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(100, 25, 163, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(103, 25, 205, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(106, 25, 217, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(109, 25, 145, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(112, 25, 173, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(115, 25, 159, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(118, 25, 199, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(121, 25, 147, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(124, 28, 231, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(125, 29, 191, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(127, 31, 165, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(130, 34, 167, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(133, 34, 191, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'ff56e947ea07861009c086cbe5133767_133'),
(135, 36, 199, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(138, 36, 179, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(141, 36, 125, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(144, 36, 175, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(147, 36, 141, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(150, 39, 127, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(153, 39, 171, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(156, 39, 139, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(159, 39, 161, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(162, 39, 165, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(165, 39, 151, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(168, 39, 155, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(171, 39, 133, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(174, 39, 135, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(177, 39, 137, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(180, 39, 193, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(183, 39, 197, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(186, 39, 231, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(189, 39, 223, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(192, 39, 191, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(195, 39, 229, 3.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(198, 39, 221, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '76410de620864e14f4f495234acb4025_198'),
(201, 39, 241, 2.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(204, 42, 177, 1.000, 'PENDIENTE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `production_states`
--

CREATE TABLE `production_states` (
  `id` int NOT NULL,
  `production_order_id` int NOT NULL,
  `estado` varchar(30) NOT NULL COMMENT 'SELECCION, CORTE, ARMADO, SOLDADURA, LIMPIEZA, PINTURA, ENSAMBLE, QC_FINAL, DESPACHO',
  `operario_id` int DEFAULT NULL COMMENT 'ID del empleado que realiza el cambio',
  `timestamp_inicio` datetime NOT NULL,
  `timestamp_fin` datetime DEFAULT NULL,
  `notas` text,
  `aprobado_qc` tinyint(1) DEFAULT '0' COMMENT '1 si fue aprobado por QC',
  `qc_aprobado_por` int DEFAULT NULL COMMENT 'User ID que aprobó el QC'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial de estados de producción';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `production_stock_movements`
--

CREATE TABLE `production_stock_movements` (
  `id` int NOT NULL,
  `production_state_id` int NOT NULL,
  `production_order_id` int NOT NULL,
  `product_id` int NOT NULL COMMENT 'Componente descontado',
  `cantidad` decimal(10,2) NOT NULL,
  `etapa` varchar(30) NOT NULL COMMENT 'CORTE, PINTURA, ENSAMBLE',
  `timestamp_descuento` datetime NOT NULL,
  `observaciones` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Movimientos de stock por etapa de producción';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `production_tickets`
--

CREATE TABLE `production_tickets` (
  `id` int NOT NULL,
  `production_order_id` int NOT NULL,
  `qr_token` varchar(100) DEFAULT NULL COMMENT 'Token único del QR',
  `url_qr` text COMMENT 'URL completa del QR',
  `estado_ticket` varchar(30) DEFAULT NULL COMMENT 'Estado para el que se imprimió el ticket',
  `impreso_por` int DEFAULT NULL COMMENT 'User ID',
  `fecha_impresion` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historial de tickets impresos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('MP','PT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidad` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'u',
  `costo_std` decimal(12,2) DEFAULT '0.00',
  `precio_std` decimal(12,2) DEFAULT '0.00',
  `stock_actual` decimal(12,3) DEFAULT '0.000',
  `stock_reservado` decimal(12,3) DEFAULT '0.000',
  `stock_minimo` decimal(12,3) NOT NULL DEFAULT '0.000',
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `margen_pct` decimal(6,2) NOT NULL DEFAULT '30.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `products`
--

INSERT INTO `products` (`id`, `codigo`, `nombre`, `tipo`, `unidad`, `costo_std`, `precio_std`, `stock_actual`, `stock_reservado`, `stock_minimo`, `activo`, `created_at`, `margen_pct`) VALUES
(125, 'PT001', 'APERTURA POSTERIOR 70KG', 'PT', 'UN', 0.00, 3200000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(127, 'PT002', 'CAMILLA FEMORAL 50 KG', 'PT', 'UN', 0.00, 2776000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(129, 'PT003', 'MÁQUINA DE BÍCEPS 50 KG', 'PT', 'UN', 0.00, 2776000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(131, 'PT004', 'PATADA DE GLÚTEOS 50 KG', 'PT', 'UN', 0.00, 3042000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(133, 'PT005', 'POLEA ALTA 90 KG', 'PT', 'UN', 0.00, 2375000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(135, 'PT006', 'POLEA BAJA 90 KG', 'PT', 'UN', 0.00, 2741000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(137, 'PT007', 'POLEA DOBLE V REGULABLE 100 KG', 'PT', 'UN', 0.00, 3910000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(139, 'PT008', 'SILLÓN CUÁDRICEPS 70 KG', 'PT', 'UN', 0.00, 3000000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(141, 'PT009', 'ABDUCTOR DE PIE', 'PT', 'UN', 0.00, 2148000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(143, 'PT010', 'BELT SQUAT', 'PT', 'UN', 0.00, 3240000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(145, 'PT011', 'FEMORAL DE PIE', 'PT', 'UN', 0.00, 2599000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(147, 'PT012', 'FONDO CHICO', 'PT', 'UN', 0.00, 1306000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(149, 'PT013', 'HACK INVERTIDA', 'PT', 'UN', 0.00, 3376000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(151, 'PT014', 'HACK 50°', 'PT', 'UN', 0.00, 4252000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(153, 'PT015', 'HIPEREXTENSION INVERTIDA', 'PT', 'UN', 0.00, 2100000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(155, 'PT016', 'HIP THRUST', 'PT', 'UN', 0.00, 3000000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(157, 'PT017', 'HIP THRUST DE PIE', 'PT', 'UN', 0.00, 2040000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(159, 'PT018', 'MÁQUINA ABDOMINAL', 'PT', 'UN', 0.00, 2690000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(161, 'PT019', 'PATADA DE GLUTEOS', 'PT', 'UN', 0.00, 2880000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(163, 'PT020', 'PANTORRILLA SENTADO', 'PT', 'UN', 0.00, 2000000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(165, 'PT021', 'PRENSA 45', 'PT', 'UN', 0.00, 4377000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(167, 'PT022', 'PULLOVER', 'PT', 'UN', 0.00, 3600000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(169, 'PT023', 'REMO T', 'PT', 'UN', 0.00, 1900000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(171, 'PT024', 'SILLÓN DE FEMORALES', 'PT', 'UN', 0.00, 2541000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(173, 'PT025', 'TRICEPS DIP', 'PT', 'UN', 0.00, 2100000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(175, 'PT026', 'ABDUCTOR ARTICULADO', 'PT', 'UN', 0.00, 3200000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(177, 'PT027', 'ABDUCTOR / ABEDUCTOR A LING.', 'PT', 'UN', 0.00, 3576000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(179, 'PT028', 'APERTURA DE PECHO', 'PT', 'UN', 0.00, 3000000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(181, 'PT029', 'CUATRO ESTACIONES', 'PT', 'UN', 0.00, 9500000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(183, 'PT030', 'JALON AL PECHO', 'PT', 'UN', 0.00, 3200000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(185, 'PT031', 'HACK CIRCULAR', 'PT', 'UN', 0.00, 3500000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(187, 'PT032', 'MULTIFUERZA GRAVEDAD CERO', 'PT', 'UN', 0.00, 4200000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(189, 'PT033', 'PANTORRILLA DE PIE', 'PT', 'UN', 0.00, 3000000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(191, 'PT034', 'PECHO PLANO Y DECLINADO CONVERGENTE', 'PT', 'UN', 0.00, 3200000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(193, 'PT035', 'POLEA ENFRENTADA REGULABLE A LING.', 'PT', 'UN', 0.00, 3910000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(195, 'PT036', 'POLEA SIMPLE REGULABLE A LING.', 'PT', 'UN', 0.00, 3000000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(197, 'PT037', 'REMO CONVERGENTE', 'PT', 'UN', 0.00, 3200000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(199, 'PT038', 'APERTURA', 'PT', 'UN', 0.00, 1560000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(201, 'PT039', 'BANCO ABDOMINAL', 'PT', 'UN', 0.00, 600000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(203, 'PT040', 'BANCO MULTIANGULAR', 'PT', 'UN', 0.00, 720000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(205, 'PT041', 'CAMILLA FEMORAL (CROSS)', 'PT', 'UN', 0.00, 1560000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(207, 'PT042', 'DORSALERA CONVERGENTE', 'PT', 'UN', 0.00, 1640000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(209, 'PT043', 'HOMBRO CONVERGENTE', 'PT', 'UN', 0.00, 1500000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(211, 'PT044', 'PATADA DE GLUTEOS (CROSS)', 'PT', 'UN', 0.00, 1400000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(213, 'PT045', 'POWER SQUAT', 'PT', 'UN', 0.00, 1560000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(215, 'PT046', 'REMO BAJO', 'PT', 'UN', 0.00, 1200000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(217, 'PT047', 'SILLON DE CUADRICEPS (CROSS)', 'PT', 'UN', 0.00, 1560000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(219, 'PT048', 'BANCO ABDOMINAL (SOPORTES)', 'PT', 'UN', 0.00, 1395000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(221, 'PT049', 'BANCO FIJO 80°', 'PT', 'UN', 0.00, 830000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(223, 'PT050', 'BANCO INCLINADO', 'PT', 'UN', 0.00, 1360000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(225, 'PT051', 'BANCO LUMBAR', 'PT', 'UN', 0.00, 1395000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(227, 'PT052', 'BANCO REMO (SOPORTE)', 'PT', 'UN', 0.00, 600000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(229, 'PT053', 'BANCO MULTIANGULAR (SOPORTE)', 'PT', 'UN', 0.00, 1680000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(231, 'PT054', 'BANCO PLANO', 'PT', 'UN', 0.00, 1328000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(233, 'PT055', 'BANCO SCOTT', 'PT', 'UN', 0.00, 1321000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(235, 'PT056', 'RACK DE SENTADILLAS', 'PT', 'UN', 0.00, 1660000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(237, 'PT057', 'MANCUERNERO X2.5 MTS', 'PT', 'UN', 0.00, 1111000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(239, 'PT058', 'MANCUERNERO X2.5 MTS X 2 PISOS', 'PT', 'UN', 0.00, 2450000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(241, 'PT059', 'POSADISCO', 'PT', 'UN', 0.00, 664000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(243, 'PT060', 'SYSSY', 'PT', 'UN', 0.00, 830000.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:33:03', 30.00),
(245, 'MP001', 'Caño Rectangular 2mm 50x100', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(247, 'MP002', 'Caño Rectangular 2mm 40x80', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(249, 'MP003', 'Caño Rectangular 2mm 50x70', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(251, 'MP004', 'Caño Rectangular 2mm 40x60', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(253, 'MP005', 'Caño Rectangular 2mm 50x30', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(255, 'MP006', 'Caño Cuadrado 2mm 100x100', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(257, 'MP007', 'Caño Cuadrado 2mm 80x80', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(259, 'MP008', 'Caño Cuadrado 2mm 60x60', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(261, 'MP009', 'Caño Cuadrado 2mm 50x50', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(263, 'MP010', 'Caño Cuadrado 2mm 40x40', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(265, 'MP011', 'Caño Rectangular 1.6mm 100x20', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(267, 'MP012', 'Caño Rectangular 1.6mm 40x20', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(269, 'MP013', 'Caño Cuadrado 1.6mm 50x50', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(271, 'MP014', 'Caño Cuadrado 1.6mm 30x30', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(273, 'MP015', 'Caño Redondo 2mm 1\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(275, 'MP016', 'Caño Redondo 2mm 1 1/4\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(277, 'MP017', 'Caño Redondo 2mm 1 1/2\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(279, 'MP018', 'Caño Redondo 2mm 2\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(281, 'MP019', 'Caño Redondo 2mm 3\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(283, 'MP020', 'Caño Redondo 2mm 4\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(285, 'MP021', 'Caño Mecánico Redondo 3/4\" x 2.3', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(287, 'MP022', 'Caño Mecánico Redondo 1 1/2\" x 2.9', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(289, 'MP023', 'Caño Mecánico Redondo 1 1/2\" x 3.2', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(291, 'MP024', 'Caño Redondo 1.6mm 1 1/4\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(293, 'MP025', 'Caño Redondo 1.6mm 7/8\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(295, 'MP026', 'Caño Redondo 1.2mm 1 1/8\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(297, 'MP027', 'Planchuela 1/8 1\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(299, 'MP028', 'Planchuela 1/8 1 1/2\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(301, 'MP029', 'Planchuela 3/8 3\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(303, 'MP030', 'Planchuela 3/8 2\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(305, 'MP031', 'Acero 1\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(307, 'MP032', 'Acero 20 diametro', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(309, 'MP033', 'Acero 25 diametro', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(311, 'MP034', 'Acero 30 diametro', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(313, 'MP035', 'Acero 12 trafilado', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(315, 'MP036', 'Acero 16 trafilado', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(317, 'MP037', 'Acero 20 trafilado', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(319, 'MP038', 'Hierro 10 diametro', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(321, 'MP039', 'Hierro 12 diametro', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(323, 'MP040', 'Hierro 16 diametro', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(325, 'MP041', 'Angulo 1/4 2\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(327, 'MP042', 'Planchuela 3/4 4\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:26', 30.00),
(329, 'b5c06025', 'BULON 5G HEXAGONAL 1/4 x 1\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(331, 'b5c08025', 'BULON 5G HEXAGONAL 5/16 x 1\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(333, 'b5c08025-2', 'BULON 5G HEXAGONAL 5/16 x 2\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(335, 'b5c08127', 'BULON 5G HEXAGONAL 5/16 x 5\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(337, 'b5c10019', 'BULON 5G HEXAGONAL 3/8 x 3/4', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(339, 'b5c10025', 'BULON 5G HEXAGONAL 3/8 x 1\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(341, 'b5c10032', 'BULON 5G HEXAGONAL 3/8 x 1\" 1/4', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(343, 'b5c10038', 'BULON 5G HEXAGONAL 3/8 x 1\" 1/2', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(345, 'b5c10044', 'BULON 5G HEXAGONAL 3/8 x 1\" 3/4', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(347, 'b5c10056', 'BULON 5G HEXAGONAL 3/8 x 2\" 1/4', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(349, 'b5c10063', 'BULON 5G HEXAGONAL 3/8 x 2\" 1/2', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(351, 'b5c10070', 'BULON 5G HEXAGONAL 3/8 x 2\" 3/4', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(353, 'b5c10076', 'BULON 5G HEXAGONAL 3/8 x 3\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(355, 'b5c10082', 'BULON 5G HEXAGONAL 3/8 x 3\" 1/4', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(357, 'b5c10090', 'BULON 5G HEXAGONAL 3/8 x 3\" 1/2', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(359, 'b5c10100', 'BULON 5G HEXAGONAL 3/8 x 4\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(361, 'b5c10114', 'BULON 5G HEXAGONAL 3/8 x 4\" 1/2', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(363, 'b5c10127', 'BULON 5G HEXAGONAL 3/8 x 5\"', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(365, 'bma12050', 'BULON 8.8 HEXAGONAL M12 x 50 x1.75', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(367, 'bma12060', 'BULON 8.8 HEXAGONAL M12 x 60 x1.75', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(369, 'bma12070', 'BULON 8.8 HEXAGONAL M12 x 70 x1.75', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(371, 'bma12090', 'BULON 8.8 HEXAGONAL M12 x 90 x1.75', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(373, 'bma12100', 'BULON 8.8 HEXAGONAL M12 x100 x1.75', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(375, 'bma12110', 'BULON 8.8 HEXAGONAL M12 x110 x1.75', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(377, 'bma12130', 'BULON 8.8 HEXAGONAL M12 x130 x1.75', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(379, 'bma12140', 'BULON 8.8 HEXAGONAL M12 x140 x1.75', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(381, 'MP043', 'BULON 8.8 HEXAGONAL 16 x 40', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(383, 'MP044', 'BULON 8.8 HEXAGONAL 16 x 50', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(385, 'bma10020', 'BULON 8.8 HEXAGONAL M10 x 20 x1.50', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(387, 'bma10025', 'BULON 8.8 HEXAGONAL M10 x 25 x1.50', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(389, 'Bma16050', 'BULON 8.8 HEXAGONAL M16 x50 x2.00', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(391, 'Bma16060', 'BULON 8.8 HEXAGONAL M16 x60 x2.00', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(393, 'MP045', 'TUERCA AUTOFRENANTE 3/8 x 16', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(395, 'tanc10016', 'TUERCA AUTOFRENANTE M10 x 1.75', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(397, 'tanc08018', 'TUERCA AUTOFRENANTE 5/16 x 18', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(399, 'tdnc08018', 'TUERCA ACERO UNC 5/16 x 18', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(401, 'tdnc10016', 'TUERCA ACERO UNC 3/8 x 16', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(403, 'MP046', 'TUERCA ACERO M10 x 1.50', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(405, 'MP047', 'CINTA METRICA AUTOFRENANTE 19mm', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(407, 'MP048', 'GRAMPA PARA ENGRAMPADORA AUTOMATICA 0.95x0.65mm', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(409, 'MP049', 'ARANDELA 3/8', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(411, 'MP050', 'ARANDELA 5/16', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(413, 'MP051', 'ARANDELA 1/4', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(415, 'MP052', 'ARANDELA 12 VUELO CHICO', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(417, 'MP053', 'ARANDELA 10 VUELO CHICO', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(419, 'MP054', 'ARANDELA POSADISCO 28', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(421, 'MP055', 'ARANDELA POSADISCO 48', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(423, 'MP056', 'ARANDELA GROWER M16', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(425, 'MP057', 'MACHO 1/4 x 20', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(427, 'MP058', 'MACHO 3/8 x 16', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(429, 'MP059', 'MACHO 5/16 x 18', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(431, 'MP060', 'MACHO 12 x 1.75', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(433, 'MP061', 'MACHO 10 x 1.50', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(435, 'MP062', 'MACHO 1/2 x 12', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(437, 'MP063', 'BULON ESPECIAL 12.8mm (varios)', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-01-28 12:34:39', 30.00),
(440, 'ROTPOS16', 'ROTULA IMP POS 16', 'MP', 'UN', 10000.00, 0.00, 230.000, 0.000, 0.000, 1, '2026-01-29 14:21:00', 30.00),
(449, 'CNM34', 'CAÑO NEG MEC 3/4\" 26,9 X 2 X 1 MT', 'MP', 'MT', 2744.00, 0.00, 211.200, 0.000, 0.000, 1, '2026-01-29 14:31:07', 30.00),
(452, 'TNUT14', 'TUERCA T 1/4 P MADERA', 'MP', 'UN', 91.80, 0.00, 6000.000, 0.000, 0.000, 1, '2026-01-29 14:37:12', 30.00),
(455, 'CUNAUTCARBON', 'CUERINA NAUTICA CARBON FIBER - BLACK - 1,37 MTS DE ANCHO', 'MP', 'MT', 29074.38, 0.00, 30.000, 0.000, 0.000, 1, '2026-01-29 14:49:20', 30.00),
(458, 'ROD-UPC205', 'RULEMAN UCP-205 PAROD', 'MP', 'UN', 4400.00, 0.00, 100.000, 0.000, 0.000, 1, '2026-01-29 15:06:46', 30.00),
(461, 'ROD-UCFL205', 'RULEMAN UCFL-205 PAROD', 'MP', 'UN', 7500.00, 0.00, 380.000, 0.000, 0.000, 1, '2026-01-29 15:06:46', 30.00),
(464, 'ROD-UCF205', 'RULEMAN UCF-205 PAROD', 'MP', 'UN', 4250.00, 0.00, 100.000, 0.000, 0.000, 1, '2026-01-29 15:06:46', 30.00),
(467, 'ROD-UCFL204', 'RULEMAN UCFL-204 PAROD', 'MP', 'UN', 4135.00, 0.00, 100.000, 0.000, 0.000, 1, '2026-01-29 15:06:46', 30.00),
(470, 'ROD-6301', 'RULEMAN 6301-2RS DPI', 'MP', 'UN', 671.00, 0.00, 400.000, 0.000, 0.000, 1, '2026-01-29 15:06:46', 30.00),
(473, 'ROD-6205', 'RULEMAN 6205-2RS IMPOR', 'MP', 'UN', 1450.00, 0.00, 50.000, 0.000, 0.000, 1, '2026-01-29 15:06:46', 30.00),
(476, 'ROT-POS12', 'ROTULA MCP POS-12', 'MP', 'UN', 4412.00, 0.00, 50.000, 0.000, 0.000, 1, '2026-01-29 15:06:46', 30.00),
(479, 'CHAP-DD20', 'CHAPAS DD N°20 1.22 X 2.44 (0.9MM)', 'MP', 'UN', 43862.00, 0.00, 20.000, 0.000, 0.000, 1, '2026-01-29 15:11:49', 30.00),
(482, 'CHAP-18LC', 'CHAPAS LC - 1/8\" (3.18) X MT2', 'MP', 'MT2', 44673.00, 0.00, 9.000, 0.000, 0.000, 1, '2026-01-29 15:11:49', 30.00),
(485, 'CHAP-316LC', 'CHAPAS LC - 3/16\"(4.76) X MT2', 'MP', 'MT2', 64708.00, 0.00, 18.000, 0.000, 0.000, 1, '2026-01-29 15:11:49', 30.00),
(488, 'GUA-VAQUETA', 'GUANTE VAQUETA DPS T.10', 'MP', 'PAR', 3000.00, 0.00, 20.000, 0.000, 0.000, 1, '2026-01-29 15:15:28', 30.00),
(491, 'GUA-BILVEX', 'GUANTE PU BILVEX MULTIFLEX', 'MP', 'PAR', 950.00, 0.00, 20.000, 0.000, 0.000, 1, '2026-01-29 15:15:28', 30.00),
(494, 'GUA-SOLDKEV', 'GUANTE SOLDADOR SG C/KEVLAR', 'MP', 'PAR', 4500.00, 0.00, 6.000, 0.000, 0.000, 1, '2026-01-29 15:15:28', 30.00),
(497, 'CNM112', 'CAÑO NEG MEC 1 1/2\" (48.3 X 2.5) X 1 MT', 'MP', 'MT', 6343.00, 0.00, 128.000, 0.000, 0.000, 1, '2026-01-29 15:22:38', 30.00),
(500, 'HL25', 'H. LISO DE 25MM X 1 MT', 'MP', 'MT', 12367.50, 0.00, 128.000, 0.000, 0.000, 1, '2026-01-29 15:22:38', 30.00),
(503, 'CORTEPERF', 'CORTE PERFILERIA', 'MP', 'UN', 22991.50, 0.00, 0.500, 0.000, 0.000, 1, '2026-01-29 15:22:38', 30.00),
(506, 'CHNF16122244', 'CH NEGRA L.F. 16 1,22 X 2,44', 'MP', 'UN', 88975.00, 0.00, 10.000, 0.000, 0.000, 1, '2026-01-29 15:24:55', 30.00),
(509, 'CHAP-DD14X22', 'CHAPAS DD N°14 1.22 X 2.44 (2MM)', 'MP', 'UN', 94161.00, 0.00, 6.000, 0.000, 0.000, 1, '2026-01-29 15:31:16', 30.00),
(512, '14046', 'BARRA DE ALUMINIO RED. 31,75MM', 'MP', 'MT O CM', 14366.40, 0.00, 24.400, 0.000, 0.000, 1, '2026-01-29 15:33:35', 30.00),
(515, '16714', 'CAÑO DE ALUMINIO RED. 31,75 X 3 MM', 'MP', 'MT O CM', 11855.20, 0.00, 4.200, 0.000, 0.000, 1, '2026-01-29 15:33:35', 30.00),
(518, 'KITXM3253-M-N', 'SOLDADORA AXO WELDING X-MIG 3253 CON ACCS', 'MP', 'UN', 3339366.52, 0.00, 2.000, 0.000, 0.000, 1, '2026-01-29 15:38:37', 30.00),
(521, '2.230.01.240', 'MACHO MAQ HSSE H40 M 10 X', 'MP', 'UN', 36280.99, 0.00, 2.000, 0.000, 0.000, 1, '2026-01-29 15:38:37', 30.00),
(524, '2.230.03.017', 'MACHO MAQ HSSE H40 BSW 1/4', 'MP', 'UN', 26859.50, 0.00, 2.000, 0.000, 0.000, 1, '2026-01-29 15:38:37', 30.00),
(527, '2.230.03.019', 'MACHO MAQ HSSE H40 BSW 3/8', 'MP', 'UN', 41818.18, 0.00, 2.000, 0.000, 0.000, 1, '2026-01-29 15:38:37', 30.00),
(530, 'CA-CUAD40X40X20', 'CAÑO CUADRADOS 40 X 40 X 20', 'MP', 'MT', 4553.00, 0.00, 6.000, 0.000, 0.000, 1, '2026-01-29 15:45:53', 30.00),
(533, 'CA-REC50X100X2', 'CAÑO RECTANGULARES 50X100X2', 'MP', 'MT', 8655.00, 0.00, 330.000, 0.000, 0.000, 1, '2026-01-29 15:45:53', 30.00),
(536, 'ELECT-CONARCO2.5', 'ELECTRODOS CONARCO 6013 PA 2.5MM', 'MP', 'KG', 8641.00, 0.00, 1.200, 0.000, 0.000, 1, '2026-01-29 15:45:53', 30.00),
(537, 'CA-CUA100X100X2', 'CAÑO CUADRADO 100X100X2', 'MP', 'MT', 11440.00, 0.00, 18.000, 0.000, 0.000, 1, '2026-01-29 16:01:21', 30.00),
(538, 'CA-CUA30X30X16', 'CAÑO CUADRADO 30X30X1.6', 'MP', 'MT', 2949.00, 0.00, 60.000, 0.000, 0.000, 1, '2026-01-29 16:01:21', 30.00),
(539, 'CA-CUA40X40X2', 'CAÑO CUADRADO 40X40X2', 'MP', 'MT', 4544.00, 0.00, 60.000, 0.000, 0.000, 1, '2026-01-29 16:01:21', 30.00),
(540, 'CA-CUA50X50X2', 'CAÑO CUADRADO 50X50X2', 'MP', 'MT', 5763.00, 0.00, 30.000, 0.000, 0.000, 1, '2026-01-29 16:01:21', 30.00),
(541, 'CA-REC20X40X16', 'CAÑO RECTANCULAR 20X40X1.6', 'MP', 'MT', 2943.00, 0.00, 18.000, 0.000, 0.000, 1, '2026-01-29 16:01:21', 30.00),
(542, 'CA-REC40X80X2', 'CAÑO RECTANGULAR 40X80X2', 'MP', 'MT', 6255.00, 0.00, 60.000, 0.000, 0.000, 1, '2026-01-29 16:01:21', 30.00),
(543, 'CHAP-DD16X16', 'CHAPA DD N°16 1.22 x 2.44 (1.6mm)', 'MP', 'UN', 73462.00, 0.00, 10.000, 0.000, 0.000, 1, '2026-01-29 16:01:21', 30.00),
(544, 'CHAP-14LC', 'CHAPAS LC - 1/4\" (6.35) X MT2', 'MP', 'MT2', 83281.00, 0.00, 6.000, 0.000, 0.000, 1, '2026-01-29 16:01:21', 30.00),
(545, 'PLAN-78X18', 'PLANCHUELA 7/8 X 1/8 /22.23 X 3.18', 'MP', 'MT', 1210.00, 0.00, 120.000, 0.000, 0.000, 1, '2026-01-29 16:01:21', 30.00),
(547, 'RURB62002RS', '6200 2RS FARMER', 'MP', 'UN', 700.00, 0.00, 50.000, 0.000, 0.000, 1, '2026-01-30 17:09:12', 30.00),
(550, '84261100', 'APAREJO 1T A CADENA CON CARRO ELECTRICO MODELO HHBB 01.02 380V SINGLE SPEED', 'MP', 'UN', 1875.00, 0.00, 1.000, 0.000, 0.000, 1, '2026-01-30 17:11:11', 30.00),
(553, '300213005', 'BARRA SOPORTADA TEMPLADA P/SBR25X100MM', 'MP', 'UN', 8760.00, 0.00, 126.000, 0.000, 0.000, 1, '2026-01-30 17:18:13', 30.00),
(556, '100203004', 'SBR25UU', 'MP', 'UN', 17417.00, 0.00, 16.000, 0.000, 0.000, 1, '2026-01-30 17:18:13', 30.00),
(559, '900101410', 'CORTE DE PRECISION 20-40 TOL +- 1MM', 'MP', 'UN', 2920.00, 0.00, 9.000, 0.000, 0.000, 1, '2026-01-30 17:18:13', 30.00),
(562, 'CUECLOE-ROJO140', 'CUERINA CLOE - ROJO - 1,40 MTS DE ANCHO', 'MP', 'MT', 10107.00, 0.00, 2.000, 0.000, 0.000, 1, '2026-01-30 19:24:06', 30.00),
(564, 'MP101', 'BULON 12 X 90', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-02-03 15:16:34', 30.00),
(567, 'MP102', 'BULON 12 X 130', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-02-03 15:16:43', 30.00),
(570, 'MP102', 'BULON 12 X 130', 'MP', 'UN', 0.00, 0.00, 0.000, 0.000, 0.000, 1, '2026-02-03 15:16:46', 30.00),
(571, 'CUEPESECO1', 'CUERINA PESADA  GO ECO', 'MP', 'MT', 17840.00, 0.00, 50.000, 0.000, 0.000, 1, '2026-02-04 14:54:25', 30.00),
(572, 'VISIONNEG', 'VISION NEG P+', 'MP', 'MT', 11760.00, 0.00, 30.000, 0.000, 0.000, 1, '2026-02-04 14:54:25', 30.00),
(573, 'AISL-BCO', 'AISLANTE ALUM-BCO', 'MP', 'ROLLO', 35200.00, 0.00, 2.000, 0.000, 0.000, 1, '2026-02-04 14:54:25', 30.00),
(574, 'AISL-SL', 'AISLANTE SL BCO', 'MP', 'ROLLO', 27500.00, 0.00, 1.000, 0.000, 0.000, 1, '2026-02-04 14:54:25', 30.00),
(575, 'N202000', 'N20 2000', 'MP', 'UN', 10800.00, 0.00, 1.000, 0.000, 0.000, 1, '2026-02-04 14:54:25', 30.00),
(576, '40X1000', 'COC. 40X1000 ARMAOZ', 'MP', 'UN', 7400.00, 0.00, 2.000, 0.000, 0.000, 1, '2026-02-04 14:54:25', 30.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `products_backup`
--

CREATE TABLE `products_backup` (
  `id` int NOT NULL DEFAULT '0',
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('MP','PT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidad` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'u',
  `costo_std` decimal(12,2) DEFAULT '0.00',
  `precio_std` decimal(12,2) DEFAULT '0.00',
  `stock_actual` decimal(12,3) DEFAULT '0.000',
  `stock_reservado` decimal(12,3) DEFAULT '0.000',
  `stock_minimo` decimal(12,3) NOT NULL DEFAULT '0.000',
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `margen_pct` decimal(6,2) NOT NULL DEFAULT '30.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `product_bom`
--

CREATE TABLE `product_bom` (
  `id` int NOT NULL,
  `product_pt_id` int NOT NULL,
  `component_id` int NOT NULL,
  `cant_por_unidad` decimal(12,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `product_bom`
--

INSERT INTO `product_bom` (`id`, `product_pt_id`, `component_id`, `cant_por_unidad`) VALUES
(3, 175, 331, 6.000),
(6, 175, 333, 1.000),
(9, 175, 355, 16.000),
(12, 175, 353, 14.000),
(15, 175, 351, 8.000),
(18, 175, 564, 1.000),
(21, 175, 570, 2.000),
(24, 175, 393, 38.000),
(27, 175, 397, 7.000),
(30, 175, 409, 68.000),
(33, 175, 415, 6.000),
(36, 175, 411, 12.000),
(39, 175, 464, 4.000),
(42, 175, 461, 4.000),
(45, 175, 440, 10.000),
(48, 175, 476, 2.000),
(51, 175, 470, 6.000),
(54, 175, 473, 2.000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `product_bom_backup`
--

CREATE TABLE `product_bom_backup` (
  `id` int NOT NULL DEFAULT '0',
  `product_pt_id` int NOT NULL,
  `component_id` int NOT NULL,
  `cant_por_unidad` decimal(12,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `purchases`
--

CREATE TABLE `purchases` (
  `id` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `proveedor` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comp_tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comp_serie` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comp_numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `moneda` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ARS',
  `archivo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `incluye_iva` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `purchases`
--

INSERT INTO `purchases` (`id`, `fecha`, `proveedor`, `comp_tipo`, `comp_serie`, `comp_numero`, `total`, `moneda`, `archivo_path`, `notas`, `created_by`, `created_at`, `incluye_iva`) VALUES
(2, '2026-01-02 11:19:00', 'RULEMANES SALTA SRL', 'OTRO', 'N', '3664', 400000.00, 'ARS', NULL, '', 2, '2026-01-29 14:21:00', 1),
(11, '2026-01-02 11:26:00', 'HIERRONORT SRL', 'FACTURA', 'A', '0036-00036488', 52684.80, 'ARS', NULL, '', 2, '2026-01-29 14:31:07', 1),
(14, '2026-01-02 11:31:00', 'Eloy Dellepiane S.A.', 'FACTURA', 'a', '0008-00012060', 91800.00, 'ARS', NULL, '', 2, '2026-01-29 14:37:12', 1),
(17, '2026-01-12 11:37:00', 'Eloy Dellepiane S.A.', 'FACTURA', 'A', '0008-00012091', 459000.00, 'ARS', NULL, '', 2, '2026-01-29 14:37:59', 1),
(20, '2026-01-06 11:39:00', 'SAN ISIDRO LONAS', 'FACTURA', 'A', '0009-00029649', 872231.40, 'ARS', NULL, '', 2, '2026-01-29 14:49:20', 1),
(23, '2026-01-07 11:54:00', 'RODAMIENTOS DALENA', 'FACTURA', 'A', '0004-00005552', 2740000.00, 'ARS', NULL, '', 2, '2026-01-29 15:06:46', 1),
(26, '2026-01-07 12:06:00', 'RULEMANES SALTA SRL', 'OTRO', 'N', '3671', 225000.00, 'ARS', NULL, '', 2, '2026-01-29 15:07:44', 1),
(29, '2026-01-13 12:07:00', 'HIMECO STEEL SRL', 'FACTURA', 'A', '002-00014854', 1660640.50, 'ARS', NULL, '', 2, '2026-01-29 15:11:49', 1),
(32, '2026-01-13 12:12:00', 'SOUTH COAST SRL', 'FACTURA', 'A', '003-0020', 106000.00, 'ARS', NULL, '', 2, '2026-01-29 15:15:28', 1),
(35, '2026-01-14 12:15:00', 'HIERRONORT SRL', 'FACTURA', 'A', '0036-00036614', 2933287.75, 'ARS', NULL, '', 2, '2026-01-29 15:22:38', 1),
(38, '2026-01-17 12:23:00', 'HIERRONORT SRL', 'FACTURA', 'B', '0036-00171754', 889750.00, 'ARS', NULL, '', 2, '2026-01-29 15:24:55', 1),
(41, '2026-01-19 12:28:00', 'ACERLOT', 'FACTURA', 'A', '0010-00034269', 564966.00, 'ARS', NULL, '', 2, '2026-01-29 15:31:16', 1),
(44, '2026-01-20 12:31:00', 'ALUMINA ARGENTINA SRL', 'FACTURA', 'A', '002-009676', 400332.00, 'ARS', NULL, '', 2, '2026-01-29 15:33:35', 1),
(47, '2026-01-19 12:34:00', 'DISTRIBUIDORA MEI SRL', 'FACTURA', 'A', '002-008489', 6888650.38, 'ARS', NULL, '', 2, '2026-01-29 15:38:37', 1),
(50, '2026-01-22 12:39:00', 'ACERLOT', 'FACTURA', 'A', '0010-00034331', 297337.20, 'ARS', NULL, '', 2, '2026-01-29 15:45:53', 1),
(51, '2026-01-22 12:46:00', 'ACERLOT', 'FACTURA', 'A', '0010-0034329', 6016070.50, 'ARS', NULL, '', 2, '2026-01-29 16:01:21', 1),
(52, '2026-01-22 14:05:00', 'RULEMANES SALTA SRL', 'FACTURA', 'B', '0017-0082172', 810000.00, 'ARS', NULL, '', 2, '2026-01-30 17:09:12', 1),
(55, '2026-01-22 14:10:00', 'CRANE SRL ING CABRERA', 'FACTURA', 'A', '0016-00000169', 1875.00, 'ARS', NULL, '', 2, '2026-01-30 17:11:11', 1),
(58, '2026-01-22 14:11:00', 'RODALUZ', 'FACTURA', 'A', '006-004987', 2500000.00, 'ARS', NULL, '', 2, '2026-01-30 17:12:46', 1),
(61, '2026-01-23 14:14:00', 'IGNIA Automatizacion', 'FACTURA', 'a', '003-007534', 1408712.00, 'ARS', NULL, '', 2, '2026-01-30 17:18:13', 1),
(64, '2026-01-30 16:22:00', 'SAN ISIDRO LONAS', 'FACTURA', 'A', '009-0030049', 20214.00, 'ARS', NULL, '', 2, '2026-01-30 19:24:06', 1),
(65, '2026-02-04 11:43:00', 'MOLINS E HIJOS SRL', 'REMITO', 'X', '883854', 1368300.00, 'ARS', NULL, '', 2, '2026-02-04 14:54:25', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int NOT NULL,
  `purchase_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UN',
  `cantidad` decimal(12,3) NOT NULL,
  `costo_unit` decimal(12,4) NOT NULL,
  `subtotal` decimal(14,2) NOT NULL,
  `notas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `codigo`, `nombre`, `unidad`, `cantidad`, `costo_unit`, `subtotal`, `notas`) VALUES
(2, 2, 440, 'ROTPOS16', 'ROTULA IMP POS 16', 'UN', 40.000, 10000.0000, 400000.00, ''),
(5, 11, 449, 'CNM34', 'CAÑO NEG MEC 3/4\" 26,9 X 2 X 1 MT', 'MT', 19.200, 2744.0000, 52684.80, ''),
(8, 14, 452, 'TNUT14', 'TUERCA T 1/4 P MADERA', 'UN', 1000.000, 91.8000, 91800.00, ''),
(11, 17, 452, 'TNUT14', 'TUERCA T 1/4 P MADERA', 'UN', 5000.000, 91.8000, 459000.00, ''),
(14, 20, 455, 'CUNAUTCARBON', 'CUERINA NAUTICA CARBON FIBER - BLACK - 1,37 MTS DE ANCHO', 'MT', 30.000, 29074.3800, 872231.40, ''),
(17, 23, 458, 'ROD-UPC205', 'RULEMAN UCP-205 PAROD', 'UN', 100.000, 4400.0000, 440000.00, ''),
(20, 23, 461, 'ROD-UCFL205', 'RULEMAN UCFL-205 PAROD', 'UN', 100.000, 4000.0000, 400000.00, ''),
(23, 23, 464, 'ROD-UCF205', 'RULEMAN UCF-205 PAROD', 'UN', 100.000, 4250.0000, 425000.00, ''),
(26, 23, 467, 'ROD-UCFL204', 'RULEMAN UCFL-204 PAROD', 'UN', 100.000, 4135.0000, 413500.00, ''),
(29, 23, 470, 'ROD-6301', 'RULEMAN 6301-2RS DPI', 'UN', 400.000, 671.0000, 268400.00, ''),
(32, 23, 473, 'ROD-6205', 'RULEMAN 6205-2RS IMPOR', 'UN', 50.000, 1450.0000, 72500.00, ''),
(35, 23, 440, 'ROTPOS16', 'ROTULA IMP/HBF POS 16', 'UN', 50.000, 10000.0000, 500000.00, ''),
(38, 23, 476, 'ROT-POS12', 'ROTULA MCP POS-12', 'UN', 50.000, 4412.0000, 220600.00, ''),
(41, 26, 461, 'ROD-UCFL205', 'RULEMAN UCFL-205 PAROD', 'UN', 30.000, 7500.0000, 225000.00, ''),
(44, 29, 479, 'CHAP-DD20', 'CHAPAS DD N°20 1.22 X 2.44 (0.9MM)', 'UN', 20.000, 43862.0000, 877240.00, ''),
(47, 29, 482, 'CHAP-18LC', 'CHAPAS LC - 1/8\" (3.18) X MT2', 'MT2', 4.500, 44673.0000, 201028.50, ''),
(50, 29, 485, 'CHAP-316LC', 'CHAPAS LC - 3/16\"(4.76) X MT2', 'MT2', 9.000, 64708.0000, 582372.00, ''),
(53, 32, 488, 'GUA-VAQUETA', 'GUANTE VAQUETA DPS T.10', 'PAR', 20.000, 3000.0000, 60000.00, ''),
(56, 32, 491, 'GUA-BILVEX', 'GUANTE PU BILVEX MULTIFLEX', 'PAR', 20.000, 950.0000, 19000.00, ''),
(59, 32, 494, 'GUA-SOLDKEV', 'GUANTE SOLDADOR SG C/KEVLAR', 'PAR', 6.000, 4500.0000, 27000.00, ''),
(62, 35, 449, 'CNM34', 'CAÑO NEG MEC 3/4\" 26,9 X 2 X 1 MT', 'MT', 192.000, 2744.0000, 526848.00, ''),
(65, 35, 497, 'CNM112', 'CAÑO NEG MEC 1 1/2\" (48.3 X 2.5) X 1 MT', 'MT', 128.000, 6343.0000, 811904.00, ''),
(68, 35, 500, 'HL25', 'H. LISO DE 25MM X 1 MT', 'MT', 128.000, 12367.5000, 1583040.00, ''),
(71, 35, 503, 'CORTEPERF', 'CORTE PERFILERIA', 'UN', 0.500, 22991.5000, 11495.75, ''),
(74, 38, 506, 'CHNF16122244', 'CH NEGRA L.F. 16 1,22 X 2,44', 'UN', 10.000, 88975.0000, 889750.00, ''),
(77, 41, 509, 'CHAP-DD14X22', 'CHAPAS DD N°14 1.22 X 2.44 (2MM)', 'UN', 6.000, 94161.0000, 564966.00, ''),
(80, 44, 512, '14046', 'BARRA DE ALUMINIO RED. 31,75MM', 'MT O CM', 24.400, 14366.4000, 350540.16, ''),
(83, 44, 515, '16714', 'CAÑO DE ALUMINIO RED. 31,75 X 3 MM', 'MT O CM', 4.200, 11855.2000, 49791.84, ''),
(86, 47, 518, 'KITXM3253-M-N', 'SOLDADORA AXO WELDING X-MIG 3253 CON ACCS', 'UN', 2.000, 3339366.5200, 6678733.04, ''),
(89, 47, 521, '2.230.01.240', 'MACHO MAQ HSSE H40 M 10 X', 'UN', 2.000, 36280.9900, 72561.98, ''),
(92, 47, 524, '2.230.03.017', 'MACHO MAQ HSSE H40 BSW 1/4', 'UN', 2.000, 26859.5000, 53719.00, ''),
(95, 47, 527, '2.230.03.019', 'MACHO MAQ HSSE H40 BSW 3/8', 'UN', 2.000, 41818.1800, 83636.36, ''),
(98, 50, 530, 'CA-CUAD40X40X20', 'CAÑO CUADRADOS 40 X 40 X 20', 'MT', 6.000, 4553.0000, 27318.00, ''),
(101, 50, 533, 'CA-REC50X100X2', 'CAÑO RECTANGULARES 50X100X2', 'MT', 30.000, 8655.0000, 259650.00, ''),
(104, 50, 536, 'ELECT-CONARCO2.5', 'ELECTRODOS CONARCO 6013 PA 2.5MM', 'KG', 1.200, 8641.0000, 10369.20, ''),
(105, 51, 537, 'CA-CUA100X100X2', 'CAÑO CUADRADO 100X100X2', 'MT', 18.000, 11440.0000, 205920.00, ''),
(106, 51, 538, 'CA-CUA30X30X16', 'CAÑO CUADRADO 30X30X1.6', 'MT', 60.000, 2949.0000, 176940.00, ''),
(107, 51, 539, 'CA-CUA40X40X2', 'CAÑO CUADRADO 40X40X2', 'MT', 60.000, 4544.0000, 272640.00, ''),
(108, 51, 540, 'CA-CUA50X50X2', 'CAÑO CUADRADO 50X50X2', 'MT', 30.000, 5763.0000, 172890.00, ''),
(109, 51, 541, 'CA-REC20X40X16', 'CAÑO RECTANCULAR 20X40X1.6', 'MT', 18.000, 2943.0000, 52974.00, ''),
(110, 51, 542, 'CA-REC40X80X2', 'CAÑO RECTANGULAR 40X80X2', 'MT', 60.000, 6255.0000, 375300.00, ''),
(111, 51, 533, 'CA-REC50X100X2', 'CAÑO RECTANGULAR 50X100X2', 'MT', 300.000, 8655.0000, 2596500.00, ''),
(112, 51, 543, 'CHAP-DD16X16', 'CHAPA DD N°16 1.22 x 2.44 (1.6mm)', 'UN', 10.000, 73462.0000, 734620.00, ''),
(113, 51, 482, 'CHAP-18LC', 'CHAPAS LC - 1/8\" (3.18) X MT2', 'MT2', 4.500, 44673.0000, 201028.50, ''),
(114, 51, 485, 'CHAP-316LC', 'CHAPAS LC - 3/16\"(4.76) X MT2', 'MT2', 9.000, 64708.0000, 582372.00, ''),
(115, 51, 544, 'CHAP-14LC', 'CHAPAS LC - 1/4\" (6.35) X MT2', 'MT2', 6.000, 83281.0000, 499686.00, ''),
(116, 51, 545, 'PLAN-78X18', 'PLANCHUELA 7/8 X 1/8 /22.23 X 3.18', 'MT', 120.000, 1210.0000, 145200.00, ''),
(118, 52, 440, 'ROTPOS16', 'ROTULA IMP POS 16', 'UN', 40.000, 10000.0000, 400000.00, ''),
(121, 52, 547, 'RURB62002RS', '6200 2RS FARMER', 'UN', 50.000, 700.0000, 35000.00, ''),
(124, 52, 461, 'ROD-UCFL205', 'RULEMAN UCFL-205 PAROD/TOPROL', 'UN', 50.000, 7500.0000, 375000.00, ''),
(127, 55, 550, '84261100', 'APAREJO 1T A CADENA CON CARRO ELECTRICO MODELO HHBB 01.02 380V SINGLE SPEED', 'UN', 1.000, 1875.0000, 1875.00, 'USD $'),
(130, 58, 461, 'ROD-UCFL205', 'RULEMAN UCFL-205 PAROD', 'UN', 200.000, 7500.0000, 1500000.00, ''),
(133, 58, 440, 'ROTPOS16', 'ROTULA IMP POS 16', 'UN', 100.000, 10000.0000, 1000000.00, ''),
(136, 61, 553, '300213005', 'BARRA SOPORTADA TEMPLADA P/SBR25X100MM', 'UN', 126.000, 8760.0000, 1103760.00, ''),
(139, 61, 556, '100203004', 'SBR25UU', 'UN', 16.000, 17417.0000, 278672.00, ''),
(142, 61, 559, '900101410', 'CORTE DE PRECISION 20-40 TOL +- 1MM', 'UN', 9.000, 2920.0000, 26280.00, ''),
(145, 64, 562, 'CUECLOE-ROJO140', 'CUERINA CLOE - ROJO - 1,40 MTS DE ANCHO', 'MT', 2.000, 10107.0000, 20214.00, ''),
(146, 65, 571, 'CUEPESECO1', 'CUERINA PESADA  GO ECO', 'MT', 50.000, 17840.0000, 892000.00, 'REVI NOMBRE'),
(147, 65, 572, 'VISIONNEG', 'VISION NEG P+', 'MT', 30.000, 11760.0000, 352800.00, 'REVI NOMBRE JAJA'),
(148, 65, 573, 'AISL-BCO', 'AISLANTE ALUM-BCO', 'ROLLO', 2.000, 35200.0000, 70400.00, 'REVI NOMBRE'),
(149, 65, 574, 'AISL-SL', 'AISLANTE SL BCO', 'ROLLO', 1.000, 27500.0000, 27500.00, 'REVI NOMBRE'),
(150, 65, 575, 'N202000', 'N20 2000', 'UN', 1.000, 10800.0000, 10800.00, ''),
(151, 65, 576, '40X1000', 'COC. 40X1000 ARMAOZ', 'UN', 2.000, 7400.0000, 14800.00, '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `purchase_items_backup`
--

CREATE TABLE `purchase_items_backup` (
  `id` int NOT NULL DEFAULT '0',
  `purchase_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidad` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UN',
  `cantidad` decimal(12,3) NOT NULL,
  `costo_unit` decimal(12,4) NOT NULL,
  `subtotal` decimal(14,2) NOT NULL,
  `notas` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` tinyint NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'admin'),
(5, 'caja'),
(16, 'clara'),
(19, 'clara-admin-ventas-deposito-produccion'),
(3, 'deposito'),
(4, 'produccion'),
(22, 'RRHH'),
(6, 'supervisor'),
(13, 'sysadmin'),
(2, 'ventas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stock_moves`
--

CREATE TABLE `stock_moves` (
  `id` bigint NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo` enum('ENTRADA','SALIDA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo` enum('COMPRA','VENTA','PROD_CONSUMO','PROD_ALTA','AJUSTE','RESERVA','LIBERACION','ENTREGA','ENTREGA_CLIENTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `referencia_tipo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` int DEFAULT NULL,
  `observaciones` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `stock_moves`
--

INSERT INTO `stock_moves` (`id`, `fecha`, `tipo`, `motivo`, `product_id`, `cantidad`, `referencia_tipo`, `referencia_id`, `observaciones`) VALUES
(1, '2026-01-29 11:21:00', 'ENTRADA', 'COMPRA', 440, 40.000, 'PURCHASE', 2, 'Compra OTRO N-3664'),
(2, '2026-01-25 16:01:46', 'ENTRADA', 'PROD_ALTA', 20, 1.000, 'OP', 9, 'Alta PT de OP'),
(3, '2026-01-25 16:06:47', 'SALIDA', 'ENTREGA_CLIENTE', 20, 1.000, 'ORDER', 3, 'Entrega a cliente desde OP #9. Usuario: sysadmin (id: 2)'),
(4, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 158, 18.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(5, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 159, 60.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(6, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 160, 60.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(7, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 161, 30.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(8, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 162, 18.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(9, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 163, 60.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(10, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 164, 300.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(11, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 165, 10.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(12, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 166, 6.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(13, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 167, 4.500, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(14, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 168, 9.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(15, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 169, 120.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
(16, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 170, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
(17, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 171, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
(18, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 172, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
(19, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 173, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
(20, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 174, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
(21, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 175, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
(22, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 176, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
(23, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 177, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
(24, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 178, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
(25, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 179, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
(26, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 180, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
(27, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 181, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
(28, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 182, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
(29, '2026-01-25 17:24:03', 'ENTRADA', 'COMPRA', 183, 100.000, 'PURCHASE', 4, 'Compra FACTURA X-1231'),
(30, '2026-01-25 17:29:00', 'ENTRADA', 'COMPRA', 184, 100.000, 'PURCHASE', 5, 'Compra FACTURA S-1231231'),
(91, '2026-01-25 16:01:11', 'SALIDA', 'PROD_CONSUMO', 91, 1.000, 'OP', 9, 'Consumo OP'),
(92, '2026-01-29 11:31:07', 'ENTRADA', 'COMPRA', 449, 19.200, 'PURCHASE', 11, 'Compra FACTURA A-0036-00036488'),
(95, '2026-01-29 11:37:12', 'ENTRADA', 'COMPRA', 452, 1000.000, 'PURCHASE', 14, 'Compra FACTURA a-0008-00012060'),
(98, '2026-01-29 11:37:59', 'ENTRADA', 'COMPRA', 452, 5000.000, 'PURCHASE', 17, 'Compra FACTURA A-0008-00012091'),
(101, '2026-01-29 11:49:20', 'ENTRADA', 'COMPRA', 455, 30.000, 'PURCHASE', 20, 'Compra FACTURA A-0009-00029649'),
(104, '2026-01-29 12:06:46', 'ENTRADA', 'COMPRA', 458, 100.000, 'PURCHASE', 23, 'Compra FACTURA A-0004-00005552'),
(107, '2026-01-29 12:06:46', 'ENTRADA', 'COMPRA', 461, 100.000, 'PURCHASE', 23, 'Compra FACTURA A-0004-00005552'),
(110, '2026-01-29 12:06:46', 'ENTRADA', 'COMPRA', 464, 100.000, 'PURCHASE', 23, 'Compra FACTURA A-0004-00005552'),
(113, '2026-01-29 12:06:46', 'ENTRADA', 'COMPRA', 467, 100.000, 'PURCHASE', 23, 'Compra FACTURA A-0004-00005552'),
(116, '2026-01-29 12:06:46', 'ENTRADA', 'COMPRA', 470, 400.000, 'PURCHASE', 23, 'Compra FACTURA A-0004-00005552'),
(119, '2026-01-29 12:06:46', 'ENTRADA', 'COMPRA', 473, 50.000, 'PURCHASE', 23, 'Compra FACTURA A-0004-00005552'),
(122, '2026-01-29 12:06:46', 'ENTRADA', 'COMPRA', 440, 50.000, 'PURCHASE', 23, 'Compra FACTURA A-0004-00005552'),
(125, '2026-01-29 12:06:46', 'ENTRADA', 'COMPRA', 476, 50.000, 'PURCHASE', 23, 'Compra FACTURA A-0004-00005552'),
(128, '2026-01-29 12:07:44', 'ENTRADA', 'COMPRA', 461, 30.000, 'PURCHASE', 26, 'Compra OTRO N-3671'),
(131, '2026-01-29 12:11:49', 'ENTRADA', 'COMPRA', 479, 20.000, 'PURCHASE', 29, 'Compra FACTURA A-002-00014854'),
(134, '2026-01-29 12:11:49', 'ENTRADA', 'COMPRA', 482, 4.500, 'PURCHASE', 29, 'Compra FACTURA A-002-00014854'),
(137, '2026-01-29 12:11:49', 'ENTRADA', 'COMPRA', 485, 9.000, 'PURCHASE', 29, 'Compra FACTURA A-002-00014854'),
(140, '2026-01-29 12:15:28', 'ENTRADA', 'COMPRA', 488, 20.000, 'PURCHASE', 32, 'Compra FACTURA A-003-0020'),
(143, '2026-01-29 12:15:28', 'ENTRADA', 'COMPRA', 491, 20.000, 'PURCHASE', 32, 'Compra FACTURA A-003-0020'),
(146, '2026-01-29 12:15:28', 'ENTRADA', 'COMPRA', 494, 6.000, 'PURCHASE', 32, 'Compra FACTURA A-003-0020'),
(149, '2026-01-29 12:22:38', 'ENTRADA', 'COMPRA', 449, 192.000, 'PURCHASE', 35, 'Compra FACTURA A-0036-00036614'),
(152, '2026-01-29 12:22:38', 'ENTRADA', 'COMPRA', 497, 128.000, 'PURCHASE', 35, 'Compra FACTURA A-0036-00036614'),
(155, '2026-01-29 12:22:38', 'ENTRADA', 'COMPRA', 500, 128.000, 'PURCHASE', 35, 'Compra FACTURA A-0036-00036614'),
(158, '2026-01-29 12:22:38', 'ENTRADA', 'COMPRA', 503, 0.500, 'PURCHASE', 35, 'Compra FACTURA A-0036-00036614'),
(161, '2026-01-29 12:24:55', 'ENTRADA', 'COMPRA', 506, 10.000, 'PURCHASE', 38, 'Compra FACTURA B-0036-00171754'),
(164, '2026-01-29 12:31:16', 'ENTRADA', 'COMPRA', 509, 6.000, 'PURCHASE', 41, 'Compra FACTURA A-0010-00034269'),
(167, '2026-01-29 12:33:35', 'ENTRADA', 'COMPRA', 512, 24.400, 'PURCHASE', 44, 'Compra FACTURA A-002-009676'),
(170, '2026-01-29 12:33:35', 'ENTRADA', 'COMPRA', 515, 4.200, 'PURCHASE', 44, 'Compra FACTURA A-002-009676'),
(173, '2026-01-29 12:38:37', 'ENTRADA', 'COMPRA', 518, 2.000, 'PURCHASE', 47, 'Compra FACTURA A-002-008489'),
(176, '2026-01-29 12:38:37', 'ENTRADA', 'COMPRA', 521, 2.000, 'PURCHASE', 47, 'Compra FACTURA A-002-008489'),
(179, '2026-01-29 12:38:37', 'ENTRADA', 'COMPRA', 524, 2.000, 'PURCHASE', 47, 'Compra FACTURA A-002-008489'),
(182, '2026-01-29 12:38:37', 'ENTRADA', 'COMPRA', 527, 2.000, 'PURCHASE', 47, 'Compra FACTURA A-002-008489'),
(185, '2026-01-29 12:45:53', 'ENTRADA', 'COMPRA', 530, 6.000, 'PURCHASE', 50, 'Compra FACTURA A-0010-00034331'),
(188, '2026-01-29 12:45:53', 'ENTRADA', 'COMPRA', 533, 30.000, 'PURCHASE', 50, 'Compra FACTURA A-0010-00034331'),
(191, '2026-01-29 12:45:53', 'ENTRADA', 'COMPRA', 536, 1.200, 'PURCHASE', 50, 'Compra FACTURA A-0010-00034331'),
(194, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 537, 18.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(195, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 538, 60.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(196, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 539, 60.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(197, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 540, 30.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(198, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 541, 18.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(199, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 542, 60.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(200, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 533, 300.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(201, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 543, 10.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(202, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 482, 4.500, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(203, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 485, 9.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(204, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 544, 6.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(205, '2026-01-29 13:01:21', 'ENTRADA', 'COMPRA', 545, 120.000, 'PURCHASE', 51, 'Compra FACTURA A-0010-0034329'),
(208, '2026-01-30 14:09:12', 'ENTRADA', 'COMPRA', 440, 40.000, 'PURCHASE', 52, 'Compra FACTURA B-0017-0082172'),
(211, '2026-01-30 14:09:12', 'ENTRADA', 'COMPRA', 547, 50.000, 'PURCHASE', 52, 'Compra FACTURA B-0017-0082172'),
(214, '2026-01-30 14:09:12', 'ENTRADA', 'COMPRA', 461, 50.000, 'PURCHASE', 52, 'Compra FACTURA B-0017-0082172'),
(217, '2026-01-30 14:11:11', 'ENTRADA', 'COMPRA', 550, 1.000, 'PURCHASE', 55, 'Compra FACTURA A-0016-00000169'),
(220, '2026-01-30 14:12:46', 'ENTRADA', 'COMPRA', 461, 200.000, 'PURCHASE', 58, 'Compra FACTURA A-006-004987'),
(223, '2026-01-30 14:12:46', 'ENTRADA', 'COMPRA', 440, 100.000, 'PURCHASE', 58, 'Compra FACTURA A-006-004987'),
(226, '2026-01-30 14:18:13', 'ENTRADA', 'COMPRA', 553, 126.000, 'PURCHASE', 61, 'Compra FACTURA a-003-007534'),
(229, '2026-01-30 14:18:13', 'ENTRADA', 'COMPRA', 556, 16.000, 'PURCHASE', 61, 'Compra FACTURA a-003-007534'),
(232, '2026-01-30 14:18:13', 'ENTRADA', 'COMPRA', 559, 9.000, 'PURCHASE', 61, 'Compra FACTURA a-003-007534'),
(235, '2026-01-30 16:24:06', 'ENTRADA', 'COMPRA', 562, 2.000, 'PURCHASE', 64, 'Compra FACTURA A-009-0030049'),
(236, '2026-02-04 11:54:25', 'ENTRADA', 'COMPRA', 571, 50.000, 'PURCHASE', 65, 'Compra REMITO X-883854'),
(237, '2026-02-04 11:54:25', 'ENTRADA', 'COMPRA', 572, 30.000, 'PURCHASE', 65, 'Compra REMITO X-883854'),
(238, '2026-02-04 11:54:25', 'ENTRADA', 'COMPRA', 573, 2.000, 'PURCHASE', 65, 'Compra REMITO X-883854'),
(239, '2026-02-04 11:54:25', 'ENTRADA', 'COMPRA', 574, 1.000, 'PURCHASE', 65, 'Compra REMITO X-883854'),
(240, '2026-02-04 11:54:25', 'ENTRADA', 'COMPRA', 575, 1.000, 'PURCHASE', 65, 'Compra REMITO X-883854'),
(241, '2026-02-04 11:54:25', 'ENTRADA', 'COMPRA', 576, 2.000, 'PURCHASE', 65, 'Compra REMITO X-883854');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pass_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol_id` tinyint NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `role` enum('ADMIN','VENTAS','DEPOSITO','PRODUCCION','CAJA','LECTURA','RRHH') COLLATE utf8mb4_unicode_ci DEFAULT 'LECTURA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `nombre`, `email`, `pass_hash`, `rol_id`, `activo`, `created_at`, `role`) VALUES
(1, 'Paola', 'Pao@uf', '$2y$12$xkzKuU0zo6.zrFY39gNYVufnRviOUe5rbyxpTPJOTvel68HNkuxOy', 1, 1, '2026-01-30 19:30:04', 'ADMIN'),
(2, 'sysadmin', 'sysadmin@local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, '2025-10-16 15:46:37', 'ADMIN'),
(3, 'superviso', 'sup@uf', '$2y$10$534V3igq1.non2P03drBR.7SpyK9Bsy04cBYyoUXlszGf36mxhZAa', 6, 1, '2025-10-16 15:54:53', 'LECTURA'),
(4, 'caja', 'caja@uf', '$2y$10$vnhPeyN8PJUOIGZrzeMyuO3m607jnYtLOs8G/NUTfWXXwwJiytlT.', 5, 1, '2025-10-16 15:56:11', 'CAJA'),
(5, 'deposito', 'deposito@uf', '$2y$10$o3VL6Ccdu1E0VHyvI7betOpOhaeJ91byG2YteglUbQ52JU3ZAVbXW', 3, 1, '2025-10-16 15:56:23', 'DEPOSITO'),
(6, 'produccion', 'prod@uf', '$2y$10$5vrgaSIAYkpybwSEoN8IcuNf3yZxjtj3J81Cfs5tQxazaItYfa1t6', 4, 1, '2025-10-16 15:56:35', 'PRODUCCION'),
(7, 'ventas', 'ventas@uf', '$2y$10$Cw9IpgjAYVOQ6r6XVWNiGuqdOhjscZOSKuEiTzZZWg4OdJtLlKirm', 2, 1, '2025-10-16 15:56:51', 'VENTAS'),
(8, 'Tomi', 'tomi@jmr.com', '$2y$10$0d1.D8HWfAfrQfpCUnPnx.Am/uhRpe./1uQX5.8oM04tNtqA37yD6', 1, 1, '2025-10-16 16:10:35', 'ADMIN'),
(9, 'Admin', 'admin@local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, '2025-10-16 13:41:01', 'ADMIN'),
(16, 'Clarita', 'clara@adm', '$2y$12$WXheQOapayKliKhKAPGlzOpBvTFLuWL68lsxyMuOjDSLm1DqNEKH6', 1, 1, '2026-01-30 20:38:28', 'RRHH');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_production_orders_estado`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_production_orders_estado` (
`id` int
,`order_id` int
,`product_pt_id` int
,`cantidad` decimal(12,3)
,`estado` enum('PENDIENTE','EN_CURSO','FINALIZADA','OBSERVADA')
,`fecha_ini` datetime
,`fecha_fin` datetime
,`observaciones` text
,`estado_actual` varchar(30)
,`color_personalizado` varchar(100)
,`tapizado_personalizado` varchar(100)
,`bloqueada_razon` text
,`ticket_impreso` tinyint(1)
,`qr_code` varchar(100)
,`producto_codigo` varchar(50)
,`producto_nombre` varchar(150)
,`customer_id` int
,`cliente_nombre` varchar(150)
,`ultimo_estado` varchar(30)
,`ultimo_cambio` datetime
,`ultimo_operario` varchar(255)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_production_orders_estado`
--
DROP TABLE IF EXISTS `v_production_orders_estado`;

CREATE ALGORITHM=UNDEFINED DEFINER=`a0011086`@`%` SQL SECURITY DEFINER VIEW `v_production_orders_estado`  AS SELECT `po`.`id` AS `id`, `po`.`order_id` AS `order_id`, `po`.`product_pt_id` AS `product_pt_id`, `po`.`cantidad` AS `cantidad`, `po`.`estado` AS `estado`, `po`.`fecha_ini` AS `fecha_ini`, `po`.`fecha_fin` AS `fecha_fin`, `po`.`observaciones` AS `observaciones`, `po`.`estado_actual` AS `estado_actual`, `po`.`color_personalizado` AS `color_personalizado`, `po`.`tapizado_personalizado` AS `tapizado_personalizado`, `po`.`bloqueada_razon` AS `bloqueada_razon`, `po`.`ticket_impreso` AS `ticket_impreso`, `po`.`qr_code` AS `qr_code`, `p`.`codigo` AS `producto_codigo`, `p`.`nombre` AS `producto_nombre`, `o`.`customer_id` AS `customer_id`, `c`.`nombre` AS `cliente_nombre`, `ps`.`estado` AS `ultimo_estado`, `ps`.`timestamp_inicio` AS `ultimo_cambio`, `e`.`nombre` AS `ultimo_operario` FROM (((((`production_orders` `po` join `products` `p` on((`p`.`id` = `po`.`product_pt_id`))) left join `orders` `o` on((`o`.`id` = `po`.`order_id`))) left join `customers` `c` on((`c`.`id` = `o`.`customer_id`))) left join `production_states` `ps` on(((`ps`.`production_order_id` = `po`.`id`) and (`ps`.`id` = (select max(`production_states`.`id`) from `production_states` where (`production_states`.`production_order_id` = `po`.`id`)))))) left join `employees` `e` on((`e`.`id` = `ps`.`operario_id`))) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cash_expenses`
--
ALTER TABLE `cash_expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cc_movimientos`
--
ALTER TABLE `cc_movimientos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `customer_ledger`
--
ALTER TABLE `customer_ledger`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `employee_advances`
--
ALTER TABLE `employee_advances`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `employee_discounts`
--
ALTER TABLE `employee_discounts`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `employee_incidents`
--
ALTER TABLE `employee_incidents`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `employee_loans`
--
ALTER TABLE `employee_loans`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `employee_payroll`
--
ALTER TABLE `employee_payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_period` (`period_id`);

--
-- Indices de la tabla `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `payment_vouchers`
--
ALTER TABLE `payment_vouchers`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `payroll_periods`
--
ALTER TABLE `payroll_periods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fechas` (`fecha_inicio`,`fecha_fin`);

--
-- Indices de la tabla `production_component_stages`
--
ALTER TABLE `production_component_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_type_etapa` (`component_type`,`etapa_stock`);

--
-- Indices de la tabla `production_orders`
--
ALTER TABLE `production_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_qr_code` (`qr_code`),
  ADD KEY `idx_estado_actual` (`estado_actual`);

--
-- Indices de la tabla `production_states`
--
ALTER TABLE `production_states`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po_id` (`production_order_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_timestamps` (`timestamp_inicio`,`timestamp_fin`);

--
-- Indices de la tabla `production_stock_movements`
--
ALTER TABLE `production_stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_state` (`production_state_id`),
  ADD KEY `idx_po` (`production_order_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_etapa` (`etapa`);

--
-- Indices de la tabla `production_tickets`
--
ALTER TABLE `production_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po` (`production_order_id`),
  ADD KEY `idx_estado` (`estado_ticket`);

--
-- Indices de la tabla `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `product_bom`
--
ALTER TABLE `product_bom`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `stock_moves`
--
ALTER TABLE `stock_moves`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `users_ibfk_1` (`rol_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `cash_expenses`
--
ALTER TABLE `cash_expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=361;

--
-- AUTO_INCREMENT de la tabla `cc_movimientos`
--
ALTER TABLE `cc_movimientos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT de la tabla `customer_ledger`
--
ALTER TABLE `customer_ledger`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT de la tabla `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT de la tabla `employee_advances`
--
ALTER TABLE `employee_advances`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `employee_attendance`
--
ALTER TABLE `employee_attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT de la tabla `employee_discounts`
--
ALTER TABLE `employee_discounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `employee_incidents`
--
ALTER TABLE `employee_incidents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `employee_loans`
--
ALTER TABLE `employee_loans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `employee_payroll`
--
ALTER TABLE `employee_payroll`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT de la tabla `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de la tabla `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205;

--
-- AUTO_INCREMENT de la tabla `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT de la tabla `payment_vouchers`
--
ALTER TABLE `payment_vouchers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `payroll_periods`
--
ALTER TABLE `payroll_periods`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `production_component_stages`
--
ALTER TABLE `production_component_stages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de la tabla `production_orders`
--
ALTER TABLE `production_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=207;

--
-- AUTO_INCREMENT de la tabla `production_states`
--
ALTER TABLE `production_states`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `production_stock_movements`
--
ALTER TABLE `production_stock_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `production_tickets`
--
ALTER TABLE `production_tickets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=577;

--
-- AUTO_INCREMENT de la tabla `product_bom`
--
ALTER TABLE `product_bom`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT de la tabla `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT de la tabla `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=152;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `stock_moves`
--
ALTER TABLE `stock_moves`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=242;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `production_states`
--
ALTER TABLE `production_states`
  ADD CONSTRAINT `production_states_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `production_stock_movements`
--
ALTER TABLE `production_stock_movements`
  ADD CONSTRAINT `production_stock_movements_ibfk_1` FOREIGN KEY (`production_state_id`) REFERENCES `production_states` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_stock_movements_ibfk_2` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_stock_movements_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Filtros para la tabla `production_tickets`
--
ALTER TABLE `production_tickets`
  ADD CONSTRAINT `production_tickets_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
