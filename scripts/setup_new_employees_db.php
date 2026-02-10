<?php
require_once __DIR__ . '/../app/db.php';

try {
    $pdo = db();
    echo "Connected to DB.\n";

    // 1. Modificar employees
    echo "Checking employees table...\n";
    $stm = $pdo->query("SHOW COLUMNS FROM employees LIKE 'valor_hora'");
    if ($stm->rowCount() == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN valor_hora DECIMAL(10,2) DEFAULT 0.00 AFTER sueldo_base_semanal");
        echo "Added 'valor_hora' to employees.\n";
    } else {
        echo "'valor_hora' already exists.\n";
    }

    // 2. Modificar attendance
    echo "Checking attendance table...\n";
    $stm = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'egreso_manana'");
    if ($stm->rowCount() == 0) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN egreso_manana TIME NULL AFTER ingreso_manana");
        echo "Added 'egreso_manana' to attendance.\n";
    }
    $stm = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'egreso_tarde'");
    if ($stm->rowCount() == 0) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN egreso_tarde TIME NULL AFTER ingreso_tarde");
        echo "Added 'egreso_tarde' to attendance.\n";
    }

    // 3. Crear employee_financials (Descuentos, Adelantos, Prestamos)
    $sqlFinancials = "CREATE TABLE IF NOT EXISTS employee_financials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        fecha DATE NOT NULL,
        tipo ENUM('ADELANTO', 'PRESTAMO', 'DESCUENTO', 'DEVOLUCION_PRESTAMO', 'BONO') NOT NULL,
        monto DECIMAL(12,2) NOT NULL,
        observacion TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        settlement_id INT DEFAULT NULL -- Para vincular con la liquidacion cuando se descuente
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sqlFinancials);
    echo "Table 'employee_financials' ready.\n";

    // 4. Crear employee_settlements (Liquidaciones)
    $sqlSettlements = "CREATE TABLE IF NOT EXISTS employee_settlements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        fecha_desde DATE NOT NULL,
        fecha_hasta DATE NOT NULL,
        total_horas DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        valor_hora_snapshot DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        monto_bruto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_descuentos DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Suma de adelantos, prestamos, descuentos cobrados',
        monto_neto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        estado ENUM('PENDIENTE', 'PAGADO') DEFAULT 'PENDIENTE',
        fecha_pago DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sqlSettlements);
    echo "Table 'employee_settlements' ready.\n";

    echo "Database setup completed successfully.\n";

} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
