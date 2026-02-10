<?php
require_once __DIR__ . '/db.php';

class EmployeesManager {
    private $pdo;

    public function __construct() {
        $this->pdo = db();
    }

    // --- LEGAJOS ---

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM employees ORDER BY apellido, nombre");
        return $stmt->fetchAll();
    }

    public function get($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function save($data) {
        if (!empty($data['id'])) {
            // Update
            $sql = "UPDATE employees SET 
                    nombre=?, apellido=?, email=?, telefono=?, dni=?, 
                    fecha_nacimiento=?, domicilio=?, ciudad=?, sueldo_base_semanal=?, valor_hora=?, puesto=?, fecha_contratacion=?
                    WHERE id=?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nombre'], $data['apellido'], $data['email'], $data['telefono'], $data['dni'],
                $data['fecha_nacimiento'], $data['domicilio'], $data['ciudad'], $data['sueldo_base_semanal'] ?? 0, 
                $data['valor_hora'] ?? 0, $data['puesto'], $data['fecha_contratacion'], $data['id']
            ]);
        } else {
            // Insert
            $sql = "INSERT INTO employees (nombre, apellido, email, telefono, dni, fecha_nacimiento, domicilio, ciudad, sueldo_base_semanal, valor_hora, puesto, fecha_contratacion) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nombre'], $data['apellido'], $data['email'], $data['telefono'], $data['dni'],
                $data['fecha_nacimiento'], $data['domicilio'], $data['ciudad'], $data['sueldo_base_semanal'] ?? 0, 
                $data['valor_hora'] ?? 0, $data['puesto'], $data['fecha_contratacion']
            ]);
        }
    }

    // --- ASISTENCIAS ---

    public function getAttendanceByDate($date) {
        // Obtenemos todos los empleados y unite con su asistencia del dia
        $sql = "SELECT e.id as employee_id, e.nombre, e.apellido, 
                       a.id as attendance_id, a.ingreso_manana, a.egreso_manana, a.ingreso_tarde, a.egreso_tarde, a.status, a.observations
                FROM employees e
                LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ?
                ORDER BY e.apellido, e.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    public function saveAttendance($date, $attendanceData) {
        // attendanceData es un array [employee_id => [ingreso_manana => ..., egreso_manana => ...]]
        foreach ($attendanceData as $empId => $row) {
            // Check existence
            $check = $this->pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
            $check->execute([$empId, $date]);
            $exists = $check->fetchColumn();

            $im = !empty($row['ingreso_manana']) ? $row['ingreso_manana'] : null;
            $em = !empty($row['egreso_manana']) ? $row['egreso_manana'] : null;
            $it = !empty($row['ingreso_tarde']) ? $row['ingreso_tarde'] : null;
            $et = !empty($row['egreso_tarde']) ? $row['egreso_tarde'] : null;
            $status = $row['status'] ?? 'presente';
            $obs = $row['observations'] ?? null;

            if ($exists) {
                $upd = $this->pdo->prepare("UPDATE attendance SET ingreso_manana=?, egreso_manana=?, ingreso_tarde=?, egreso_tarde=?, status=?, observations=? WHERE id=?");
                $upd->execute([$im, $em, $it, $et, $status, $obs, $exists]);
            } else {
                // Solo insertar si hay algun dato relevante
                if ($im || $it || $status !== 'presente' || $obs) {
                    $ins = $this->pdo->prepare("INSERT INTO attendance (employee_id, date, ingreso_manana, egreso_manana, ingreso_tarde, egreso_tarde, status, observations) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$empId, $date, $im, $em, $it, $et, $status, $obs]);
                }
            }
        }
    }

    // --- FINANZAS / NOVEDADES ---

    public function getFinancials($employeeId = null, $unsettledOnly = false) {
        $sql = "SELECT f.*, e.nombre, e.apellido 
                FROM employee_financials f
                JOIN employees e ON f.employee_id = e.id";
        $params = [];
        $conds = [];
        if ($employeeId) {
            $conds[] = "f.employee_id = ?";
            $params[] = $employeeId;
        }
        if ($unsettledOnly) {
            $conds[] = "f.settlement_id IS NULL";
        }

        if ($conds) {
            $sql .= " WHERE " . implode(' AND ', $conds);
        }
        $sql .= " ORDER BY f.fecha DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function addFinancial($data) {
        $cuotas = isset($data['cuotas']) ? (int)$data['cuotas'] : 1;
        
        if ($data['tipo'] === 'PRESTAMO' && $cuotas > 1) {
            $totalMonto = $data['monto'];
            $installmentAmount = round($totalMonto / $cuotas, 2);
            
            // Adjust last installment for rounding errors
            $currentSum = 0;
            $baseDate = new DateTime($data['fecha']);
            
            // Start Transaction to be safe
            $this->pdo->beginTransaction();
            try {
                for ($i = 0; $i < $cuotas; $i++) {
                    $amount = $installmentAmount;
                    if ($i === $cuotas - 1) {
                        $amount = $totalMonto - $currentSum;
                    }
                    $currentSum += $amount;
                    
                    // Clone date
                    $date = clone $baseDate;
                    if ($i > 0) {
                        $date->modify("+$i weeks");
                    }
                    
                    $obs = $data['observacion'] . " (Cuota " . ($i + 1) . "/$cuotas)";
                    
                    $sql = "INSERT INTO employee_financials (employee_id, fecha, tipo, monto, observacion) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([
                        $data['employee_id'], $date->format('Y-m-d'), $data['tipo'], $amount, $obs
                    ]);
                }
                $this->pdo->commit();
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }

        } else {
            $sql = "INSERT INTO employee_financials (employee_id, fecha, tipo, monto, observacion) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['employee_id'], $data['fecha'], $data['tipo'], $data['monto'], $data['observacion']
            ]);
        }
    }

    public function deleteFinancial($id) {
        // Solo permitir borrar si no esta liquidado
        $stmt = $this->pdo->prepare("DELETE FROM employee_financials WHERE id = ? AND settlement_id IS NULL");
        $stmt->execute([$id]);
    }

    // --- LIQUIDACIONES ---

    public function previewSettlement($employeeId, $startDate, $endDate) {
        $emp = $this->get($employeeId);
        if (!$emp) return null;

        // Calcular horas
        // Regla simple: (Salida - Entrada) en horas logic
        // Se asume que ingreso/egreso son H:i:s
        
        $sqlAtt = "SELECT * FROM attendance WHERE employee_id = ? AND date BETWEEN ? AND ?";
        $stmtAtt = $this->pdo->prepare($sqlAtt);
        $stmtAtt->execute([$employeeId, $startDate, $endDate]);
        $attendances = $stmtAtt->fetchAll();

        $totalSeconds = 0;
        foreach ($attendances as $day) {
            if ($day['status'] == 'ausente') continue;

            // Turno Mañana
            if ($day['ingreso_manana'] && $day['egreso_manana']) {
                $totalSeconds += (strtotime($day['egreso_manana']) - strtotime($day['ingreso_manana']));
            }
            // Turno Tarde
            if ($day['ingreso_tarde'] && $day['egreso_tarde']) {
                $totalSeconds += (strtotime($day['egreso_tarde']) - strtotime($day['ingreso_tarde']));
            }
        }

        $totalHours = $totalSeconds / 3600;
        $valorHora = $emp['valor_hora'];
        $gross = $totalHours * $valorHora;

        // Descuentos pendientes (Adelantos, Prestamos)
        // Buscamos financials NO liquidados hasta la fecha fin (inclusive)
        $sqlFin = "SELECT * FROM employee_financials WHERE employee_id = ? AND settlement_id IS NULL AND fecha <= ?";
        $stmtFin = $this->pdo->prepare($sqlFin);
        $stmtFin->execute([$employeeId, $endDate]);
        $financials = $stmtFin->fetchAll();

        $deductions = 0;
        $bonuses = 0;
        foreach ($financials as $f) {
            if (in_array($f['tipo'], ['ADELANTO', 'PRESTAMO', 'DESCUENTO'])) {
                $deductions += $f['monto'];
            } elseif ($f['tipo'] == 'BONO' || $f['tipo'] == 'DEVOLUCION_PRESTAMO') { 
                // DEVOLUCION_PRESTAMO (empleado paga a empresa) es positivo para empresa, negativo para sueldo?
                // Depende de la logica. 
                // Si "Prestamo" es dinero que sale de empresa -> empleado (deuda).
                // Al liquidar, se descuenta.
                // Si el empleado devuelve dinero manualmente, deberia reducir su deuda?
                // Asumamos tipos: 
                // ADELANTO: Resta.
                // PRESTAMO: Resta (si se cobra entero).
                // DESCUENTO: Resta.
                // BONO: Suma.
            }
        }

        $net = $gross - $deductions + $bonuses;

        return [
            'employee' => $emp,
            'start_date' => $startDate, 
            'end_date' => $endDate,
            'total_hours' => $totalHours,
            'valor_hora' => $valorHora,
            'gross_amount' => $gross,
            'deductions' => $deductions,
            'financials' => $financials, // Para detalle
            'net_amount' => $net
        ];
    }

    public function createSettlement($employeeId, $startDate, $endDate) {
        $preview = $this->previewSettlement($employeeId, $startDate, $endDate);
        if (!$preview) return false;

        $this->pdo->beginTransaction();
        try {
            $sql = "INSERT INTO employee_settlements 
             (employee_id, fecha_desde, fecha_hasta, total_horas, valor_hora_snapshot, monto_bruto, total_descuentos, monto_neto, estado, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDIENTE', NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $employeeId, $startDate, $endDate, 
                $preview['total_hours'], $preview['valor_hora'], $preview['gross_amount'], 
                $preview['deductions'], $preview['net_amount']
            ]);
            $settlementId = $this->pdo->lastInsertId();

            // Marcar financials como liquidados
            if (!empty($preview['financials'])) {
                $ids = array_column($preview['financials'], 'id');
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $upd = $this->pdo->prepare("UPDATE employee_financials SET settlement_id = ? WHERE id IN ($inQuery)");
                $upd->execute(array_merge([$settlementId], $ids));
            }

            $this->pdo->commit();
            return $settlementId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function getSettlements() {
        $sql = "SELECT s.*, e.nombre, e.apellido 
                FROM employee_settlements s 
                JOIN employees e ON s.employee_id = e.id 
                ORDER BY s.id DESC";
        return $this->pdo->query($sql)->fetchAll();
    }
    
    public function markSettlementPaid($id) {
        $stmt = $this->pdo->prepare("UPDATE employee_settlements SET estado = 'PAGADO', fecha_pago = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }
}
