<?php
require_once __DIR__ . '/../app/db.php';

echo "<h2>Test de Movimientos</h2>";

try {
  // 1. Verificar empleados
  echo "<h3>1. Empleados</h3>";
  $employees = db()->query("SELECT id, nombre, apellido FROM employees WHERE activo=1 LIMIT 5")->fetchAll();
  echo "Encontrados: " . count($employees) . "<br>";
  foreach ($employees as $e) {
    echo "ID: {$e['id']}, Nombre: {$e['nombre']} {$e['apellido']}<br>";
  }
  
  if (!$employees) {
    echo "NO HAY EMPLEADOS. Crea uno primero en empleados.php<br>";
    die();
  }
  
  $test_emp_id = $employees[0]['id'];
  echo "<br><strong>Usando empleado ID: $test_emp_id</strong><br>";
  
  // 2. Verificar descuentos
  echo "<h3>2. Descuentos</h3>";
  $descuentos = db()->query("SELECT * FROM employee_discounts WHERE employee_id=$test_emp_id")->fetchAll();
  echo "Count: " . count($descuentos) . "<br>";
  if ($descuentos) {
    echo "<pre>" . json_encode($descuentos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
  }
  
  // 3. Verificar adelantos
  echo "<h3>3. Adelantos</h3>";
  $adelantos = db()->query("SELECT * FROM employee_advances WHERE employee_id=$test_emp_id")->fetchAll();
  echo "Count: " . count($adelantos) . "<br>";
  if ($adelantos) {
    echo "<pre>" . json_encode($adelantos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
  }
  
  // 4. Verificar préstamos
  echo "<h3>4. Préstamos</h3>";
  $prestamos = db()->query("SELECT * FROM employee_loans WHERE employee_id=$test_emp_id")->fetchAll();
  echo "Count: " . count($prestamos) . "<br>";
  if ($prestamos) {
    echo "<pre>" . json_encode($prestamos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
  }
  
  // 5. Probar las queries de movimientos
  echo "<h3>5. Query de Descuentos (como en empleados.php)</h3>";
  $query = "SELECT 'DESCUENTO' AS tipo, id, fecha, monto_descuento AS monto, razon AS detalle FROM employee_discounts WHERE employee_id=?";
  $stmt = db()->prepare($query);
  $stmt->execute([$test_emp_id]);
  $result = $stmt->fetchAll();
  echo "Count: " . count($result) . "<br>";
  if ($result) {
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
  }
  
  echo "<h3>6. Query de Adelantos (como en empleados.php)</h3>";
  $query = "SELECT 'ADELANTO' AS tipo, id, fecha_solicitud AS fecha, monto, razon AS detalle FROM employee_advances WHERE employee_id=?";
  $stmt = db()->prepare($query);
  $stmt->execute([$test_emp_id]);
  $result = $stmt->fetchAll();
  echo "Count: " . count($result) . "<br>";
  if ($result) {
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
  }
  
  echo "<h3>7. Query de Préstamos (como en empleados.php)</h3>";
  $query = "SELECT 'PRESTAMO' AS tipo, id, fecha_solicitud AS fecha, monto_solicitado AS monto, razon AS detalle FROM employee_loans WHERE employee_id=?";
  $stmt = db()->prepare($query);
  $stmt->execute([$test_emp_id]);
  $result = $stmt->fetchAll();
  echo "Count: " . count($result) . "<br>";
  if ($result) {
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
  }
  
} catch (Throwable $e) {
  echo "<div style='color: red; background: #ffe0e0; padding: 10px; border: 1px solid red;'>";
  echo "<strong>ERROR:</strong> " . $e->getMessage();
  echo "</div>";
}
?>
