-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-04-2026 a las 04:56:22
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
-- Base de datos: `marina`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bancos`
--

CREATE TABLE `bancos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bancos`
--

INSERT INTO `bancos` (`id`, `nombre`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'General', '2026-03-16 19:11:09', '2026-03-16 19:11:09', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `documento` varchar(50) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `documento`, `telefono`, `email`, `direccion`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'Merluza', 'bosque', '60026773', 'pedroarrieta25@hotmail.com', 'San Miguelito, Provincia de Panamá', '2026-03-16 19:11:56', '2026-03-16 19:11:56', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `combustible_ajustes`
--

CREATE TABLE `combustible_ajustes` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo_combustible` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `gls_delta` decimal(14,3) NOT NULL COMMENT 'Positivo suma inventario, negativo resta',
  `motivo` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `combustible_ajustes`
--

INSERT INTO `combustible_ajustes` (`id`, `tipo_combustible`, `fecha`, `gls_delta`, `motivo`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'diesel', '2026-03-29', -2.000, 'se rompio la manguera', '2026-03-29 17:37:34', '2026-03-29 17:37:34', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `combustible_despachos`
--

CREATE TABLE `combustible_despachos` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo_combustible` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `embarcacion` varchar(200) NOT NULL,
  `gls` decimal(14,3) NOT NULL,
  `monto_total` decimal(14,2) NOT NULL,
  `cuenta_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `combustible_despachos`
--

INSERT INTO `combustible_despachos` (`id`, `tipo_combustible`, `fecha`, `embarcacion`, `gls`, `monto_total`, `cuenta_id`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'diesel', '2026-03-29', 'popos', 15.000, 67.50, 1, '2026-03-29 16:30:07', '2026-03-29 16:30:07', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `combustible_pedidos`
--

CREATE TABLE `combustible_pedidos` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo_combustible` varchar(20) NOT NULL,
  `fecha_pedido` date NOT NULL,
  `gls_pedido` decimal(14,3) NOT NULL DEFAULT 0.000,
  `fecha_recibido` date DEFAULT NULL,
  `gls_recibido` decimal(14,3) DEFAULT NULL,
  `numero_factura` varchar(100) DEFAULT NULL,
  `estado_pago` varchar(20) NOT NULL DEFAULT 'por_pagar',
  `costo_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cuenta_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Cuenta sugerida para abonos',
  `gasto_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Gasto egreso costo al recibir',
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `combustible_pedidos`
--

INSERT INTO `combustible_pedidos` (`id`, `tipo_combustible`, `fecha_pedido`, `gls_pedido`, `fecha_recibido`, `gls_recibido`, `numero_factura`, `estado_pago`, `costo_total`, `cuenta_id`, `gasto_id`, `observaciones`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'diesel', '2026-03-29', 100.000, '2026-03-29', 95.000, '002', 'pagado', 332.50, 1, 3, 'faltaron 5 galones', '2026-03-29 16:27:01', '2026-03-29 17:48:41', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `combustible_pedido_pagos`
--

CREATE TABLE `combustible_pedido_pagos` (
  `id` int(10) UNSIGNED NOT NULL,
  `pedido_id` int(10) UNSIGNED NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `cuenta_id` int(10) UNSIGNED DEFAULT NULL,
  `forma_pago_id` int(10) UNSIGNED DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `gasto_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `combustible_pedido_pagos`
--

INSERT INTO `combustible_pedido_pagos` (`id`, `pedido_id`, `monto`, `fecha_pago`, `cuenta_id`, `forma_pago_id`, `referencia`, `gasto_id`, `created_at`, `created_by`) VALUES
(1, 1, 300.00, '2026-03-29', 1, 8, '002', NULL, '2026-03-29 17:47:53', 1),
(2, 1, 32.50, '2026-03-29', 1, 8, '00252', NULL, '2026-03-29 17:48:41', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `combustible_precios`
--

CREATE TABLE `combustible_precios` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo_combustible` varchar(20) NOT NULL COMMENT 'diesel|gasolina',
  `precio_compra_galon` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `precio_venta_galon` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `vigente_desde` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `combustible_precios`
--

INSERT INTO `combustible_precios` (`id`, `tipo_combustible`, `precio_compra_galon`, `precio_venta_galon`, `vigente_desde`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'diesel', 3.5000, 4.5000, '2026-03-29', '2026-03-29 15:51:44', '2026-03-29 16:21:42', 1, 1),
(2, 'gasolina', 3.0000, 4.0000, '2026-03-29', '2026-03-29 15:51:44', '2026-03-29 16:20:42', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contratos`
--

CREATE TABLE `contratos` (
  `id` int(10) UNSIGNED NOT NULL,
  `cliente_id` int(10) UNSIGNED NOT NULL,
  `cuenta_id` int(10) UNSIGNED NOT NULL COMMENT 'Cuenta de la marina donde se acreditan los pagos',
  `muelle_id` int(10) UNSIGNED DEFAULT NULL,
  `slip_id` int(10) UNSIGNED DEFAULT NULL,
  `grupo_id` int(10) UNSIGNED DEFAULT NULL,
  `inmueble_id` int(10) UNSIGNED DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `monto_total` decimal(12,2) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `numero_recibo` varchar(100) DEFAULT NULL COMMENT 'Número de recibo al cliente',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `contratos`
--

INSERT INTO `contratos` (`id`, `cliente_id`, `cuenta_id`, `muelle_id`, `slip_id`, `grupo_id`, `inmueble_id`, `fecha_inicio`, `fecha_fin`, `monto_total`, `observaciones`, `numero_recibo`, `activo`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 1, 1, 4, 4, NULL, NULL, '2026-03-20', '2026-04-20', 500.00, 'se quedara un mes, posiblemente mas', NULL, 1, '2026-03-16 19:15:21', '2026-03-19 18:06:34', 1, 1),
(2, 1, 1, NULL, NULL, 1, 1, '2026-04-01', '2026-06-30', 5000.00, 'prueba', NULL, 1, '2026-03-19 07:37:22', '2026-03-19 07:37:22', 1, 1),
(3, 1, 1, 3, 10, NULL, NULL, '2026-03-01', '2026-04-30', 5000.00, 'alquiler por esos dias', '124536', 1, '2026-03-25 20:34:22', '2026-03-25 20:34:22', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuentas`
--

CREATE TABLE `cuentas` (
  `id` int(10) UNSIGNED NOT NULL,
  `banco_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL COMMENT 'Nombre o número de cuenta',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cuentas`
--

INSERT INTO `cuentas` (`id`, `banco_id`, `nombre`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 1, '120120120120', '2026-03-16 19:11:25', '2026-03-16 19:11:25', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuotas`
--

CREATE TABLE `cuotas` (
  `id` int(10) UNSIGNED NOT NULL,
  `contrato_id` int(10) UNSIGNED NOT NULL,
  `numero_cuota` int(10) UNSIGNED NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `fecha_pago` date DEFAULT NULL,
  `forma_pago_id` int(10) UNSIGNED DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL COMMENT 'Número de comprobante, etc.',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cuotas`
--

INSERT INTO `cuotas` (`id`, `contrato_id`, `numero_cuota`, `monto`, `fecha_vencimiento`, `fecha_pago`, `forma_pago_id`, `referencia`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 1, 2, 250.00, '2026-03-31', '2026-03-19', NULL, '545454', '2026-03-16 19:15:49', '2026-03-18 20:01:33', 1, 1),
(2, 1, 2, 250.00, '2026-04-20', '2026-03-19', NULL, '212121', '2026-03-16 19:16:00', '2026-03-18 20:01:43', 1, 1),
(3, 2, 1, 1000.00, '2026-04-30', NULL, NULL, NULL, '2026-03-19 07:38:26', '2026-03-19 07:38:26', 1, 1),
(4, 2, 2, 1000.00, '2026-05-30', NULL, NULL, NULL, '2026-03-19 07:38:45', '2026-03-19 07:38:45', 1, 1),
(5, 2, 3, 3000.00, '2026-06-30', NULL, NULL, NULL, '2026-03-19 07:38:58', '2026-03-19 07:38:58', 1, 1),
(6, 3, 1, 2500.00, '2026-03-31', '2026-03-26', 16, 'banco calimba', '2026-03-25 20:37:11', '2026-03-25 20:38:47', 1, 1),
(7, 3, 2, 2500.00, '2026-04-30', NULL, NULL, NULL, '2026-03-25 20:37:22', '2026-03-25 20:37:22', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuotas_movimientos`
--

CREATE TABLE `cuotas_movimientos` (
  `id` int(10) UNSIGNED NOT NULL,
  `cuota_id` int(10) UNSIGNED NOT NULL,
  `tipo` varchar(10) NOT NULL COMMENT 'pago | abono',
  `monto` decimal(12,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `forma_pago_id` int(10) UNSIGNED DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL COMMENT 'Número de comprobante, etc.',
  `concepto` varchar(255) DEFAULT NULL COMMENT 'Término / descripción del pago de cuota',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cuotas_movimientos`
--

INSERT INTO `cuotas_movimientos` (`id`, `cuota_id`, `tipo`, `monto`, `fecha_pago`, `forma_pago_id`, `referencia`, `concepto`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 1, 'abono', 100.00, '2026-03-19', NULL, '00202020', NULL, '2026-03-18 19:35:38', '2026-03-18 19:35:38', 1, 1),
(2, 1, 'pago', 150.00, '2026-03-19', NULL, '545454', NULL, '2026-03-18 20:01:33', '2026-03-18 20:01:33', 1, 1),
(3, 2, 'pago', 250.00, '2026-03-19', NULL, '212121', NULL, '2026-03-18 20:01:43', '2026-03-18 20:01:43', 1, 1),
(4, 6, 'pago', 2500.00, '2026-03-26', 16, 'banco calimba', 'pago de cuota de marzo', '2026-03-25 20:38:47', '2026-03-25 20:38:47', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `formas_pago`
--

CREATE TABLE `formas_pago` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `tipo_movimiento` varchar(20) NOT NULL DEFAULT 'ingreso',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `formas_pago`
--

INSERT INTO `formas_pago` (`id`, `nombre`, `tipo_movimiento`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(5, 'NOTA DE DEBITO', 'costo', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(6, 'NOTA DE CREDITO', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(7, 'TRANSFERENCIA(ENTRADA)', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(8, 'TRANSFERENCIA(SALIDA)', 'costo', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(9, 'AJUSTE(ENTRADA)', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(10, 'AJUSTE(SALIDA)', 'costo', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(11, 'PRESTAMO INTERNO DE SOCIOS', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(12, 'CARTA DE CREDITO', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(13, 'ACH', 'costo', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(14, 'CHEQUE DE GERENCIA', 'costo', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(15, 'DEPOSITO', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(16, 'EFECTIVO', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(17, 'AJUSTE', 'costo', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(18, 'DEPOSITO CHQ', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(19, 'INTERES', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(20, 'RAMPA', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(21, 'DIESEL', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(22, 'GASOLINA', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(23, 'TARJETA DE CREDITO', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(24, 'CARTA DE CREDITO(INGRESO)', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(25, 'MUELLE', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(26, 'PAGO SERVICIOS', 'costo', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(27, 'FACTURA SERVICIO', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(28, 'CINTILLOS / COMISIONES CONTRATISTAS', 'costo', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(29, 'ELECTRICIDAD', 'costo', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(30, 'PAGO DE FACTURA', 'costo', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(31, 'HABITACION', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(32, 'MAQUINA DE PRESION DE AGUA', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL),
(33, 'ANDAMIO', 'ingreso', '2026-03-19 15:16:46', '2026-03-19 15:16:46', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gastos`
--

CREATE TABLE `gastos` (
  `id` int(10) UNSIGNED NOT NULL,
  `partida_id` int(10) UNSIGNED NOT NULL,
  `proveedor_id` int(10) UNSIGNED NOT NULL,
  `cuenta_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Cuenta de la marina de donde sale el pago',
  `forma_pago_id` int(10) UNSIGNED DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `fecha_gasto` date NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `gastos`
--

INSERT INTO `gastos` (`id`, `partida_id`, `proveedor_id`, `cuenta_id`, `forma_pago_id`, `monto`, `fecha_gasto`, `referencia`, `observaciones`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 2, 1, 1, NULL, 1000.00, '2026-03-15', 'nose', 'nose', '2026-03-16 19:27:27', '2026-03-16 19:27:27', 1, 1),
(2, 4, 1, 1, 13, 1000.00, '2026-03-17', '00252', 'pago', '2026-03-19 17:26:34', '2026-03-19 17:26:34', 1, 1),
(3, 5, 2, 1, NULL, 332.50, '2026-03-29', '002', 'Pedido combustible #1 — diesel — 95 GLS recibidos', '2026-03-29 16:49:19', '2026-03-29 16:49:19', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos`
--

CREATE TABLE `grupos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `grupos`
--

INSERT INTO `grupos` (`id`, `nombre`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'locales comerciales', '2026-03-19 07:35:06', '2026-03-19 07:35:06', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inmuebles`
--

CREATE TABLE `inmuebles` (
  `id` int(10) UNSIGNED NOT NULL,
  `grupo_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inmuebles`
--

INSERT INTO `inmuebles` (`id`, `grupo_id`, `nombre`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 1, 'Snack deport', '2026-03-19 07:35:37', '2026-03-19 07:35:37', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_bancarios`
--

CREATE TABLE `movimientos_bancarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `cuenta_id` int(10) UNSIGNED NOT NULL,
  `forma_pago_id` int(10) UNSIGNED NOT NULL,
  `tipo_movimiento` varchar(20) NOT NULL COMMENT 'ingreso | costo',
  `monto` decimal(12,2) NOT NULL,
  `fecha_movimiento` date NOT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `movimientos_bancarios`
--

INSERT INTO `movimientos_bancarios` (`id`, `cuenta_id`, `forma_pago_id`, `tipo_movimiento`, `monto`, `fecha_movimiento`, `referencia`, `descripcion`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 1, 15, 'ingreso', 5000.00, '2026-03-19', '002', 'ingreso a la cuenta', '2026-03-19 16:04:19', '2026-03-19 16:04:19', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `muelles`
--

CREATE TABLE `muelles` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `muelles`
--

INSERT INTO `muelles` (`id`, `nombre`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'M1', '2026-03-16 19:12:09', '2026-03-16 19:12:09', 1, 1),
(3, 'M3', '2026-03-18 20:13:00', '2026-03-18 20:13:00', 1, 1),
(4, 'M4', '2026-03-18 20:13:06', '2026-03-18 20:13:06', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partidas`
--

CREATE TABLE `partidas` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `partidas`
--

INSERT INTO `partidas` (`id`, `parent_id`, `nombre`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, NULL, 'administrativos', '2026-03-16 19:13:41', '2026-03-16 19:13:41', 1, 1),
(2, 1, 'planilla', '2026-03-16 19:13:54', '2026-03-16 19:13:54', 1, 1),
(3, 1, 'luz', '2026-03-19 16:09:03', '2026-03-19 16:09:03', 1, 1),
(4, 3, 'muelles', '2026-03-19 16:09:10', '2026-03-19 16:09:10', 1, 1),
(5, NULL, 'Combustible', '2026-03-29 15:51:44', '2026-03-29 15:51:44', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `documento` varchar(50) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `nombre`, `documento`, `telefono`, `email`, `direccion`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'Marina', '', '6262626262', 'marina@marina.com', 'porhay', '2026-03-16 19:14:32', '2026-03-16 19:14:32', 1, 1),
(2, 'Combustible (compras)', NULL, NULL, NULL, NULL, '2026-03-29 15:51:44', '2026-03-29 15:51:44', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `slips`
--

CREATE TABLE `slips` (
  `id` int(10) UNSIGNED NOT NULL,
  `muelle_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL COMMENT 'Nombre/número que asigna el usuario',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `slips`
--

INSERT INTO `slips` (`id`, `muelle_id`, `nombre`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 1, 'Slip 01', '2026-03-16 19:12:24', '2026-03-16 19:12:24', 1, 1),
(3, 3, 'Slip 03', '2026-03-18 20:13:42', '2026-03-18 20:13:42', 1, 1),
(4, 4, 'Slip 01', '2026-03-18 20:13:49', '2026-03-18 20:13:49', 1, 1),
(5, 1, 'Slip 02', '2026-03-18 20:13:59', '2026-03-18 20:13:59', 1, 1),
(6, 1, 'Slip 03', '2026-03-18 20:14:26', '2026-03-18 20:14:26', 1, 1),
(9, 3, 'Slip 02', '2026-03-18 20:15:58', '2026-03-18 20:15:58', 1, 1),
(10, 3, 'Slip 01', '2026-03-18 20:16:07', '2026-03-18 20:16:07', 1, 1),
(11, 4, 'Slip 02', '2026-03-18 20:16:18', '2026-03-18 20:16:18', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` varchar(50) NOT NULL DEFAULT 'admin',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password_hash`, `rol`, `activo`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'Administrador', 'admin@marina.local', '$2y$10$bBV5sUIvwI8wurKs6v62hOWV6JBgTMS/AsYoV1QIih0rl4z.M3XR6', 'admin', 1, '2026-03-16 18:56:44', '2026-03-18 18:46:48', NULL, 1),
(2, 'Pedro', 'pedroarrieta25@hotmail.com', '$2y$10$TRKTME/wa8eM6Ig20yI5mO6gfx7f7xxC/rDnO63WqpftWczbWhLye', 'admin', 1, '2026-03-18 18:44:16', '2026-03-18 18:44:16', 1, 1),
(3, 'jorse', 'schiavonea@ostarhotels.com', '$2y$10$tjReMUFmbD5.C03TXp.zOOWNrYGJFwM.AQQgoeugkpHoSLwhBHoi.', 'admin', 1, '2026-03-18 18:47:08', '2026-03-18 18:47:08', 1, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `bancos`
--
ALTER TABLE `bancos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `combustible_ajustes`
--
ALTER TABLE `combustible_ajustes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ca_fecha` (`fecha`),
  ADD KEY `idx_ca_tipo` (`tipo_combustible`);

--
-- Indices de la tabla `combustible_despachos`
--
ALTER TABLE `combustible_despachos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cd_fecha` (`fecha`),
  ADD KEY `fk_cd_cuenta` (`cuenta_id`);

--
-- Indices de la tabla `combustible_pedidos`
--
ALTER TABLE `combustible_pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cp_fecha_pedido` (`fecha_pedido`),
  ADD KEY `idx_cp_tipo` (`tipo_combustible`),
  ADD KEY `fk_cp_cuenta` (`cuenta_id`),
  ADD KEY `fk_cp_gasto` (`gasto_id`);

--
-- Indices de la tabla `combustible_pedido_pagos`
--
ALTER TABLE `combustible_pedido_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cpp_pedido` (`pedido_id`),
  ADD KEY `fk_cpp_cuenta` (`cuenta_id`),
  ADD KEY `fk_cpp_forma` (`forma_pago_id`),
  ADD KEY `fk_cpp_gasto` (`gasto_id`);

--
-- Indices de la tabla `combustible_precios`
--
ALTER TABLE `combustible_precios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_comb_precio` (`tipo_combustible`,`vigente_desde`),
  ADD KEY `idx_comb_precio_tipo` (`tipo_combustible`);

--
-- Indices de la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `cuenta_id` (`cuenta_id`),
  ADD KEY `muelle_id` (`muelle_id`),
  ADD KEY `slip_id` (`slip_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `fk_contratos_grupo` (`grupo_id`),
  ADD KEY `fk_contratos_inmueble` (`inmueble_id`);

--
-- Indices de la tabla `cuentas`
--
ALTER TABLE `cuentas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `banco_id` (`banco_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `cuotas`
--
ALTER TABLE `cuotas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contrato_id` (`contrato_id`),
  ADD KEY `forma_pago_id` (`forma_pago_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `cuotas_movimientos`
--
ALTER TABLE `cuotas_movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `forma_pago_id` (`forma_pago_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_cuota_fecha` (`cuota_id`,`fecha_pago`),
  ADD KEY `idx_tipo_fecha` (`tipo`,`fecha_pago`);

--
-- Indices de la tabla `formas_pago`
--
ALTER TABLE `formas_pago`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `partida_id` (`partida_id`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `cuenta_id` (`cuenta_id`),
  ADD KEY `forma_pago_id` (`forma_pago_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `grupos`
--
ALTER TABLE `grupos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `inmuebles`
--
ALTER TABLE `inmuebles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_inmueble_grupo` (`grupo_id`,`nombre`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `movimientos_bancarios`
--
ALTER TABLE `movimientos_bancarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `forma_pago_id` (`forma_pago_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_mov_banc_fecha` (`fecha_movimiento`),
  ADD KEY `idx_mov_banc_cuenta_fecha` (`cuenta_id`,`fecha_movimiento`),
  ADD KEY `idx_mov_banc_tipo_fecha` (`tipo_movimiento`,`fecha_movimiento`);

--
-- Indices de la tabla `muelles`
--
ALTER TABLE `muelles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `partidas`
--
ALTER TABLE `partidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `slips`
--
ALTER TABLE `slips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_slip_muelle` (`muelle_id`,`nombre`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `bancos`
--
ALTER TABLE `bancos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `combustible_ajustes`
--
ALTER TABLE `combustible_ajustes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `combustible_despachos`
--
ALTER TABLE `combustible_despachos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `combustible_pedidos`
--
ALTER TABLE `combustible_pedidos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `combustible_pedido_pagos`
--
ALTER TABLE `combustible_pedido_pagos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `combustible_precios`
--
ALTER TABLE `combustible_precios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `contratos`
--
ALTER TABLE `contratos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `cuentas`
--
ALTER TABLE `cuentas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cuotas`
--
ALTER TABLE `cuotas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `cuotas_movimientos`
--
ALTER TABLE `cuotas_movimientos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `formas_pago`
--
ALTER TABLE `formas_pago`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `gastos`
--
ALTER TABLE `gastos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `grupos`
--
ALTER TABLE `grupos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `inmuebles`
--
ALTER TABLE `inmuebles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `movimientos_bancarios`
--
ALTER TABLE `movimientos_bancarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `muelles`
--
ALTER TABLE `muelles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `partidas`
--
ALTER TABLE `partidas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `slips`
--
ALTER TABLE `slips`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `bancos`
--
ALTER TABLE `bancos`
  ADD CONSTRAINT `bancos_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `bancos_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `clientes_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `combustible_despachos`
--
ALTER TABLE `combustible_despachos`
  ADD CONSTRAINT `fk_cd_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`);

--
-- Filtros para la tabla `combustible_pedidos`
--
ALTER TABLE `combustible_pedidos`
  ADD CONSTRAINT `fk_cp_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cp_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `combustible_pedido_pagos`
--
ALTER TABLE `combustible_pedido_pagos`
  ADD CONSTRAINT `fk_cpp_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cpp_forma` FOREIGN KEY (`forma_pago_id`) REFERENCES `formas_pago` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cpp_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cpp_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `combustible_pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD CONSTRAINT `contratos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `contratos_ibfk_2` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`),
  ADD CONSTRAINT `contratos_ibfk_3` FOREIGN KEY (`muelle_id`) REFERENCES `muelles` (`id`),
  ADD CONSTRAINT `contratos_ibfk_4` FOREIGN KEY (`slip_id`) REFERENCES `slips` (`id`),
  ADD CONSTRAINT `contratos_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `contratos_ibfk_6` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_contratos_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`),
  ADD CONSTRAINT `fk_contratos_inmueble` FOREIGN KEY (`inmueble_id`) REFERENCES `inmuebles` (`id`);

--
-- Filtros para la tabla `cuentas`
--
ALTER TABLE `cuentas`
  ADD CONSTRAINT `cuentas_ibfk_1` FOREIGN KEY (`banco_id`) REFERENCES `bancos` (`id`),
  ADD CONSTRAINT `cuentas_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `cuentas_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `cuotas`
--
ALTER TABLE `cuotas`
  ADD CONSTRAINT `cuotas_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cuotas_ibfk_2` FOREIGN KEY (`forma_pago_id`) REFERENCES `formas_pago` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cuotas_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `cuotas_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `cuotas_movimientos`
--
ALTER TABLE `cuotas_movimientos`
  ADD CONSTRAINT `cuotas_movimientos_ibfk_1` FOREIGN KEY (`cuota_id`) REFERENCES `cuotas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cuotas_movimientos_ibfk_2` FOREIGN KEY (`forma_pago_id`) REFERENCES `formas_pago` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cuotas_movimientos_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `cuotas_movimientos_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `formas_pago`
--
ALTER TABLE `formas_pago`
  ADD CONSTRAINT `formas_pago_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `formas_pago_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD CONSTRAINT `gastos_ibfk_1` FOREIGN KEY (`partida_id`) REFERENCES `partidas` (`id`),
  ADD CONSTRAINT `gastos_ibfk_2` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  ADD CONSTRAINT `gastos_ibfk_3` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `gastos_ibfk_4` FOREIGN KEY (`forma_pago_id`) REFERENCES `formas_pago` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `gastos_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `gastos_ibfk_6` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `grupos`
--
ALTER TABLE `grupos`
  ADD CONSTRAINT `grupos_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `grupos_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `inmuebles`
--
ALTER TABLE `inmuebles`
  ADD CONSTRAINT `fk_inmuebles_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`),
  ADD CONSTRAINT `inmuebles_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `inmuebles_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `movimientos_bancarios`
--
ALTER TABLE `movimientos_bancarios`
  ADD CONSTRAINT `movimientos_bancarios_ibfk_1` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`),
  ADD CONSTRAINT `movimientos_bancarios_ibfk_2` FOREIGN KEY (`forma_pago_id`) REFERENCES `formas_pago` (`id`),
  ADD CONSTRAINT `movimientos_bancarios_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `movimientos_bancarios_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `muelles`
--
ALTER TABLE `muelles`
  ADD CONSTRAINT `muelles_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `muelles_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `partidas`
--
ALTER TABLE `partidas`
  ADD CONSTRAINT `partidas_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `partidas` (`id`),
  ADD CONSTRAINT `partidas_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `partidas_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD CONSTRAINT `proveedores_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `proveedores_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `slips`
--
ALTER TABLE `slips`
  ADD CONSTRAINT `slips_ibfk_1` FOREIGN KEY (`muelle_id`) REFERENCES `muelles` (`id`),
  ADD CONSTRAINT `slips_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `slips_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
