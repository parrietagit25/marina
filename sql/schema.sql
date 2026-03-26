-- Marina - Sistema de gestión
-- Una marina, muelles, slips, contratos por cuotas, gastos por partidas jerárquicas

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Usuarios (por ahora solo admin)
CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol VARCHAR(50) NOT NULL DEFAULT 'admin',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Bancos (un banco puede tener varias cuentas)
CREATE TABLE bancos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Cuentas de la marina (pertenecen a un banco)
CREATE TABLE cuentas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  banco_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL COMMENT 'Nombre o número de cuenta',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (banco_id) REFERENCES bancos(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Clientes
CREATE TABLE clientes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  documento VARCHAR(50) NULL,
  telefono VARCHAR(50) NULL,
  email VARCHAR(100) NULL,
  direccion TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Muelles
CREATE TABLE muelles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Grupos (similar a muelles)
CREATE TABLE grupos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Slips (pertenecen a un muelle; el nombre lo define el usuario)
CREATE TABLE slips (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  muelle_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(50) NOT NULL COMMENT 'Nombre/número que asigna el usuario',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (muelle_id) REFERENCES muelles(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id),
  UNIQUE KEY uk_slip_muelle (muelle_id, nombre)
) ENGINE=InnoDB;

-- Inmuebles (pertenecen a un grupo)
CREATE TABLE inmuebles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grupo_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id),
  UNIQUE KEY uk_inmueble_grupo (grupo_id, nombre)
) ENGINE=InnoDB;

-- Formas de pago
CREATE TABLE formas_pago (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  tipo_movimiento VARCHAR(20) NOT NULL DEFAULT 'ingreso' COMMENT 'ingreso | costo',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Partidas (jerárquicas: partida > subpartida > sub-subpartida...)
-- En la partida hoja (sin hijos) se registran los gastos
CREATE TABLE partidas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  nombre VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (parent_id) REFERENCES partidas(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Proveedores
CREATE TABLE proveedores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  documento VARCHAR(50) NULL,
  telefono VARCHAR(50) NULL,
  email VARCHAR(100) NULL,
  direccion TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Contratos: cliente, cuenta donde se acreditan pagos, muelle, slip, período, monto total
CREATE TABLE contratos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  cuenta_id INT UNSIGNED NOT NULL COMMENT 'Cuenta de la marina donde se acreditan los pagos',
  muelle_id INT UNSIGNED NULL,
  slip_id INT UNSIGNED NULL,
  grupo_id INT UNSIGNED NULL,
  inmueble_id INT UNSIGNED NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  monto_total DECIMAL(12,2) NOT NULL,
  observaciones TEXT NULL,
  numero_recibo VARCHAR(100) NULL COMMENT 'Número de recibo emitido al cliente',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
  FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE RESTRICT,
  FOREIGN KEY (muelle_id) REFERENCES muelles(id) ON DELETE RESTRICT,
  FOREIGN KEY (slip_id) REFERENCES slips(id) ON DELETE RESTRICT,
  FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE RESTRICT,
  FOREIGN KEY (inmueble_id) REFERENCES inmuebles(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Cuotas del contrato (vencimiento y monto los define quien registra)
CREATE TABLE cuotas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contrato_id INT UNSIGNED NOT NULL,
  numero_cuota INT UNSIGNED NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  fecha_pago DATE NULL,
  forma_pago_id INT UNSIGNED NULL,
  referencia VARCHAR(100) NULL COMMENT 'Número de comprobante, etc.',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON DELETE CASCADE,
  FOREIGN KEY (forma_pago_id) REFERENCES formas_pago(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Movimientos de pago/abono por cuota (permite abonos parciales)
CREATE TABLE cuotas_movimientos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cuota_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(10) NOT NULL COMMENT 'pago | abono',
  monto DECIMAL(12,2) NOT NULL,
  fecha_pago DATE NOT NULL,
  forma_pago_id INT UNSIGNED NULL,
  referencia VARCHAR(100) NULL COMMENT 'Número de comprobante, etc.',
  concepto VARCHAR(255) NULL COMMENT 'Término o texto del pago de cuota (estado de cuenta)',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (cuota_id) REFERENCES cuotas(id) ON DELETE CASCADE,
  FOREIGN KEY (forma_pago_id) REFERENCES formas_pago(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id),
  INDEX idx_cuota_fecha (cuota_id, fecha_pago),
  INDEX idx_tipo_fecha (tipo, fecha_pago)
) ENGINE=InnoDB;

-- Gastos (se registran en partida hoja, con proveedor)
CREATE TABLE gastos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partida_id INT UNSIGNED NOT NULL,
  proveedor_id INT UNSIGNED NOT NULL,
  cuenta_id INT UNSIGNED NULL COMMENT 'Cuenta de la marina de donde sale el pago',
  forma_pago_id INT UNSIGNED NULL,
  monto DECIMAL(12,2) NOT NULL,
  fecha_gasto DATE NOT NULL,
  referencia VARCHAR(100) NULL,
  observaciones TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE RESTRICT,
  FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE RESTRICT,
  FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE SET NULL,
  FOREIGN KEY (forma_pago_id) REFERENCES formas_pago(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Movimientos bancarios manuales (no ligados a cuota/gasto)
CREATE TABLE movimientos_bancarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cuenta_id INT UNSIGNED NOT NULL,
  forma_pago_id INT UNSIGNED NOT NULL,
  tipo_movimiento VARCHAR(20) NOT NULL COMMENT 'ingreso | costo',
  monto DECIMAL(12,2) NOT NULL,
  fecha_movimiento DATE NOT NULL,
  referencia VARCHAR(100) NULL,
  descripcion TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE RESTRICT,
  FOREIGN KEY (forma_pago_id) REFERENCES formas_pago(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES usuarios(id),
  FOREIGN KEY (updated_by) REFERENCES usuarios(id),
  INDEX idx_mov_banc_fecha (fecha_movimiento),
  INDEX idx_mov_banc_cuenta_fecha (cuenta_id, fecha_movimiento),
  INDEX idx_mov_banc_tipo_fecha (tipo_movimiento, fecha_movimiento)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- Usuario admin: ejecutar install/crear_admin.php para crear con password admin123
-- o insertar manualmente: INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES
-- ('Administrador', 'admin@marina.local', '<hash de password_hash("admin123", PASSWORD_DEFAULT)>', 'admin');
