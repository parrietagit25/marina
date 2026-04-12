<?php
/**
 * Ajustes incrementales de esquema (idempotente; ignora si la columna ya existe).
 */
declare(strict_types=1);

function marina_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $stmts = [
        "ALTER TABLE contratos ADD COLUMN numero_recibo VARCHAR(100) NULL DEFAULT NULL COMMENT 'Número de recibo al cliente' AFTER observaciones",
        "ALTER TABLE cuotas_movimientos ADD COLUMN concepto VARCHAR(255) NULL DEFAULT NULL COMMENT 'Término / descripción del pago de cuota' AFTER referencia",
        "ALTER TABLE clientes ADD COLUMN dueno_capitan VARCHAR(150) NULL DEFAULT NULL COMMENT 'Dueño / Capitán' AFTER direccion",
    ];
    foreach ($stmts as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // Columna duplicada u otro entorno ya migrado
        }
    }

    // slips: instalaciones antiguas sin muelle_id (requerido por JOIN con muelles)
    try {
        $pdo->exec('ALTER TABLE slips ADD COLUMN muelle_id INT UNSIGNED NULL DEFAULT NULL AFTER id');
    } catch (Throwable $e) {
        // columna ya existe
    }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM slips LIKE 'muelle_id'")->fetch(PDO::FETCH_ASSOC);
        if ($col !== false) {
            $stMin = $pdo->query('SELECT MIN(id) AS mid FROM muelles');
            $rowMin = $stMin ? $stMin->fetch(PDO::FETCH_ASSOC) : false;
            $firstMuelle = isset($rowMin['mid']) ? (int) $rowMin['mid'] : 0;
            if ($firstMuelle > 0) {
                $pdo->prepare('UPDATE slips SET muelle_id = ? WHERE muelle_id IS NULL')->execute([$firstMuelle]);
            }
        }
    } catch (Throwable $e) {
        // sin tabla o sin muelles
    }
    try {
        $pdo->exec('ALTER TABLE slips MODIFY muelle_id INT UNSIGNED NOT NULL');
    } catch (Throwable $e) {
        // aún hay NULL o sin columna
    }
    try {
        $pdo->exec("
            UPDATE slips s
            INNER JOIN (
                SELECT muelle_id, nombre, MIN(id) AS keep_id
                FROM slips
                GROUP BY muelle_id, nombre
                HAVING COUNT(*) > 1
            ) dup ON s.muelle_id = dup.muelle_id AND s.nombre = dup.nombre AND s.id <> dup.keep_id
            SET s.nombre = CONCAT(TRIM(s.nombre), ' (id ', s.id, ')')
        ");
    } catch (Throwable $e) {
        // sin columna muelle_id o tabla ausente
    }
    foreach (['ALTER TABLE slips ADD CONSTRAINT fk_slips_muelle FOREIGN KEY (muelle_id) REFERENCES muelles(id) ON DELETE RESTRICT',
        'ALTER TABLE slips ADD UNIQUE KEY uk_slip_muelle (muelle_id, nombre)',
    ] as $sqlSlip) {
        try {
            $pdo->exec($sqlSlip);
        } catch (Throwable $e) {
            // FK o índice ya existente, o datos duplicados
        }
    }

    $combustibleTables = [
        "CREATE TABLE IF NOT EXISTS combustible_precios (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          tipo_combustible VARCHAR(20) NOT NULL COMMENT 'diesel|gasolina',
          precio_compra_galon DECIMAL(14,4) NOT NULL DEFAULT 0,
          precio_venta_galon DECIMAL(14,4) NOT NULL DEFAULT 0,
          vigente_desde DATE NOT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          created_by INT UNSIGNED NULL,
          updated_by INT UNSIGNED NULL,
          UNIQUE KEY uk_comb_precio (tipo_combustible, vigente_desde),
          KEY idx_comb_precio_tipo (tipo_combustible)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS combustible_pedidos (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          tipo_combustible VARCHAR(20) NOT NULL,
          fecha_pedido DATE NOT NULL,
          gls_pedido DECIMAL(14,3) NOT NULL DEFAULT 0,
          fecha_recibido DATE NULL,
          gls_recibido DECIMAL(14,3) NULL,
          numero_factura VARCHAR(100) NULL,
          estado_pago VARCHAR(20) NOT NULL DEFAULT 'por_pagar',
          costo_total DECIMAL(14,2) NOT NULL DEFAULT 0,
          cuenta_id INT UNSIGNED NULL COMMENT 'Cuenta sugerida para abonos',
          observaciones TEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          created_by INT UNSIGNED NULL,
          updated_by INT UNSIGNED NULL,
          KEY idx_cp_fecha_pedido (fecha_pedido),
          KEY idx_cp_tipo (tipo_combustible),
          CONSTRAINT fk_cp_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS combustible_pedido_pagos (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          pedido_id INT UNSIGNED NOT NULL,
          monto DECIMAL(14,2) NOT NULL,
          fecha_pago DATE NOT NULL,
          cuenta_id INT UNSIGNED NULL,
          forma_pago_id INT UNSIGNED NULL,
          referencia VARCHAR(100) NULL,
          gasto_id INT UNSIGNED NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          created_by INT UNSIGNED NULL,
          KEY idx_cpp_pedido (pedido_id),
          CONSTRAINT fk_cpp_pedido FOREIGN KEY (pedido_id) REFERENCES combustible_pedidos(id) ON DELETE CASCADE,
          CONSTRAINT fk_cpp_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE SET NULL,
          CONSTRAINT fk_cpp_forma FOREIGN KEY (forma_pago_id) REFERENCES formas_pago(id) ON DELETE SET NULL,
          CONSTRAINT fk_cpp_gasto FOREIGN KEY (gasto_id) REFERENCES gastos(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS combustible_despachos (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          tipo_combustible VARCHAR(20) NOT NULL,
          fecha DATE NOT NULL,
          embarcacion VARCHAR(200) NOT NULL,
          gls DECIMAL(14,3) NOT NULL,
          monto_total DECIMAL(14,2) NOT NULL,
          cuenta_id INT UNSIGNED NOT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          created_by INT UNSIGNED NULL,
          updated_by INT UNSIGNED NULL,
          KEY idx_cd_fecha (fecha),
          CONSTRAINT fk_cd_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS combustible_ajustes (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          tipo_combustible VARCHAR(20) NOT NULL,
          fecha DATE NOT NULL,
          gls_delta DECIMAL(14,3) NOT NULL COMMENT 'Positivo suma inventario, negativo resta',
          motivo TEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          created_by INT UNSIGNED NULL,
          updated_by INT UNSIGNED NULL,
          KEY idx_ca_fecha (fecha),
          KEY idx_ca_tipo (tipo_combustible)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($combustibleTables as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // entorno parcial o FK no disponible
        }
    }

    $combustibleAlters = [
        "ALTER TABLE combustible_pedidos ADD COLUMN gasto_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'Gasto egreso costo al recibir' AFTER cuenta_id",
        "ALTER TABLE combustible_pedidos ADD CONSTRAINT fk_cp_gasto FOREIGN KEY (gasto_id) REFERENCES gastos(id) ON DELETE SET NULL",
    ];
    foreach ($combustibleAlters as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // columna o FK ya existente
        }
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS marina_config (
          clave VARCHAR(64) NOT NULL PRIMARY KEY,
          valor TEXT NOT NULL,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO marina_config (clave, valor) VALUES ('font_size_percent', '100')");
    } catch (Throwable $e) {
        // permisos o motor
    }

    require_once __DIR__ . '/combustible_helpers.php';
    try {
        marina_combustible_seed_catalog($pdo);
    } catch (Throwable $e) {
        // sin permisos o catálogo ya existe
    }
}
