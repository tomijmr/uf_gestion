# Universal Fitness ERP - Sistema de Gestión

## 📋 Descripción General

Sistema ERP (Enterprise Resource Planning) completo para la gestión de operaciones de Universal Fitness SA. Incluye módulos para gestión de clientes, pedidos, inventario, producción, nómina y recursos humanos.

**Tecnología:** PHP 7.4+, PDO, MySQL/MariaDB, Bootstrap 5

---

## 🎯 Características Principales

### 1. **Gestión de Clientes**
- Registro y edición de clientes
- Historial de operaciones
- Contactos y referencias
- Búsqueda y filtrado avanzado

### 2. **Gestión de Pedidos**
- Creación de nuevos pedidos
- Seguimiento del estado (PENDIENTE, EN PRODUCCIÓN, COMPLETADO, ENTREGADO)
- Asignación de máquinas y operarios
- Cálculo automático de costos
- Generación de vouchers de pago

### 3. **Inventario**
- **Materias Primas:** Control de stock, alertas de stock bajo
- **Máquinas/Productos Terminados:** Seguimiento de producción
- Movimientos de stock con historial
- Reportes de inventario

### 4. **Producción**
- Órdenes de producción (OP)
- Asignación de recursos
- Seguimiento de estado
- Historial de operaciones

### 5. **Nómina con Sistema de Períodos** ⭐
Sistema avanzado de gestión de pagos de empleados con períodos semanales:

#### Flujo de Nómina:
- **Durante el Período Activo:**
  - Base semanal definido por empleado
  - Horas extras automáticamente sumadas
  - Descuentos, adelantos y préstamos descontados
  - **Saldo Actual = Base + Extras - Descuentos - Adelantos - Préstamos - Pagos Realizados**

- **Registro de Pagos:**
  - Se pueden hacer múltiples pagos en el mismo período
  - No hay duplicación de saldos
  - Visualización en tiempo real del saldo pendiente

- **Cierre de Período:**
  - Se calcula el saldo final del período
  - Se crea automáticamente el nuevo período
  - **El saldo del período anterior se suma al base semanal del nuevo período**
  
  **Ejemplo:**
  ```
  Período 1:
  - Base: $300, Se paga: $290
  - Saldo: $10
  
  Período 2 (después de cerrar):
  - Base: $300 + Saldo anterior $10 = $310
  - Si se paga $200: Nuevo saldo = $110
  ```

### 6. **Asistencia y Control de Personal**
- Registro de asistencia con horas entrada/salida
- Cálculo automático de horas extras
- Histórico de asistencia por empleado

### 7. **Recursos Humanos**
- Legajo digital de empleados
  - Datos personales
  - Historial de incidencias
  - Control de suspensiones y licencias
- Adelantos de sueldo
- Préstamos a empleados
- Descuentos por faltas/llegadas tarde

### 8. **Caja e Ingresos**
- Registro de pagos recibidos
- Cuenta corriente de clientes
- Gestión de comprobantes/vouchers (almacenamiento seguro)
- Desglose de ingresos por tipo de pago

### 9. **Compras**
- Registro de compras a proveedores
- Seguimiento de pagos
- Historial de compras

---

## 👥 Sistema de Roles y Permisos

### Roles Disponibles:

| Rol | Dashboard | Clientes | Pedidos | Inventario | Producción | Empleados | Nómina | Admin |
|-----|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| **ADMIN** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (Todo) | ✅ | ✅ |
| **VENTAS** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **PRODUCCION** | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **DEPOSITO** | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **CAJA** | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ (Todo) | ✅ | ❌ |
| **RRHH** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ (Solo Asistencia e Incidencias) | ❌ | ❌ |
| **LECTURA** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |

### Rol RRHH (Recursos Humanos):
- **Acceso Visual:** Dashboard, Clientes, Pedidos, Materias Primas, Máquinas, Empleados
- **En Empleados:** Solo ve
  - 📊 Asistencia (registrar y ver)
  - 📝 Legajo con Incidencias (historial de problemas)
- **No ve:** Nómina, Sueldos, Períodos, datos salariales

---

## 🗄️ Estructura de Base de Datos

### Tablas Principales:

#### Clientes
```sql
customers - id, nombre, email, telefono, direccion, etc.
customer_ledger - Historial de operaciones por cliente
```

#### Pedidos
```sql
orders - id, customer_id, fecha, estado, total
order_items - Productos/máquinas en cada pedido
```

#### Empleados
```sql
employees - id, nombre, apellido, pago_semanal, pago_por_hora, saldo_pendiente, etc.
employee_attendance - Asistencia diaria con horas extras
employee_payroll - Registro de pagos realizados
employee_incidents - Historial de incidencias/problemas
employee_advances - Adelantos de sueldo
employee_loans - Préstamos a empleados
employee_discounts - Descuentos por faltas, etc.
payroll_periods - Períodos semanales de nómina
```

#### Inventario
```sql
products - Materias primas
products_backup - Backup de productos
stock_moves - Movimientos de stock
```

#### Producción
```sql
production_orders - Órdenes de producción
product_bom - Bill of Materials (recetas de productos)
```

#### Finanzas
```sql
payments - Pagos recibidos
payment_vouchers - Comprobantes de pago
cash_expenses - Gastos desde caja
purchases - Compras a proveedores
```

#### Configuración
```sql
roles - Roles disponibles
users - Usuarios del sistema
audit_logs - Registro de auditoría de cambios
```

---

## 🔐 Seguridad

### Medidas Implementadas:
- ✅ Hashing de contraseñas con `password_hash()`
- ✅ Validación de roles en cada acción sensible
- ✅ Sanitización de entrada con `prepared statements`
- ✅ Almacenamiento seguro de vouchers fuera de `public/`
- ✅ Historial de auditoría de cambios
- ✅ Gestión de sesiones segura

### Archivos Sensibles:
- **Vouchers:** Almacenados en `/storage/vouchers/` (no público)
- **Acceso:** A través de `voucher.php` con validación de usuario

---

## 📱 Cómo Usar el Sistema

### 1. **Login**
- Ingresar email y contraseña
- El sistema verifica el rol del usuario
- Se redirige al dashboard

### 2. **Dashboard**
- Vista general del estado del negocio
- Acceso rápido a módulos principales

### 3. **Crear un Pedido**
1. Ir a **Pedidos** → **Nuevo Pedido**
2. Seleccionar cliente
3. Agregar productos/máquinas con cantidades
4. Asignar máquina/operario
5. Guardar

### 4. **Registrar Asistencia**
1. Ir a **Empleados** → **Asistencia**
2. Seleccionar empleado
3. Registrar entrada mañana/tarde
4. Ingresar horas extras si corresponde
5. Guardar

### 5. **Procesar Nómina**
1. Ir a **Empleados** → **Nómina**
2. El sistema muestra el saldo actual de cada empleado
3. Hacer clic en **PAGAR**
4. Ingresar monto a pagar
5. Confirmar pago
6. Al finalizar período: ir a **Períodos** → **Cerrar Período y Crear Nuevo**

### 6. **Gestionar Inventario**
1. Ir a **Materias Primas** o **Máquinas**
2. Ver stock actual
3. Hacer clic en producto para ver detalles
4. Registrar movimientos desde **Stock**

---

## 🔧 Configuración Inicial

### 1. Base de Datos
```bash
# Crear base de datos
mysql -u root -p < migracion_dev_a_prod.sql
```

### 2. Crear Usuario Admin
```sql
INSERT INTO users (nombre, email, pass_hash, role, activo) 
VALUES (
  'Administrador',
  'admin@ejemplo.com',
  PASSWORD_HASH('contraseña_segura'),
  'ADMIN',
  1
);
```

### 3. Crear Roles
```sql
INSERT INTO roles (nombre) VALUES ('Admin'), ('Ventas'), ('RRHH'), ('Caja'), ('Producción'), ('Depósito');
```

### 4. Crear Período Activo
```sql
INSERT INTO payroll_periods (fecha_inicio, fecha_fin, estado) 
VALUES (CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 DAY), 'ACTIVO');
```

---

## 📊 Reportes y Análisis

### Reportes Disponibles:
- 📈 Resumen de nómina por período
- 📋 Historial de asistencia
- 💰 Caja diaria
- 📦 Movimientos de stock
- 🎯 Órdenes de producción pendientes
- 👥 Incidencias de empleados

---

## 🐛 Solución de Problemas

### "El saldo no se actualiza"
- Verificar que el período esté ACTIVO en `payroll_periods`
- Asegurarse que el `period_id` sea correcto en `employee_payroll`

### "No veo el rol RRHH"
- Ejecutar: `ALTER TABLE users MODIFY COLUMN role ENUM(...,'RRHH',...)`
- Asignar rol: `UPDATE users SET role = 'RRHH' WHERE id = X`

### "Los vouchers no se descargan"
- Verificar ruta en `/storage/vouchers/`
- Asegurarse que `voucher.php` tenga permisos de lectura

### "Error en nómina después de cerrar período"
- Verificar que todos los empleados tengan `saldo_pendiente` calculado
- Confirmar que el nuevo período fue creado correctamente

---

## 📞 Soporte y Desarrollo

### Archivos Clave:
- `app/auth.php` - Autenticación y roles
- `app/db.php` - Conexión a BD
- `app/helpers.php` - Funciones auxiliares
- `public/empleados.php` - Gestión completa de nómina y empleados
- `views/partials/navbar.php` - Menú y permisos por rol

### Para Agregar Nuevas Funcionalidades:
1. Crear archivo en `/public/`
2. Agregar `require_login()` al inicio
3. Usar `require_role()` si es necesario restringir
4. Agregar entrada en navbar.php si es visible en menú

---

## 📝 Notas Importantes

- Los períodos se cierran **manualmente** desde la tab "Períodos"
- El saldo se transfiere automáticamente al nuevo período
- Las horas extras se calculan automáticamente desde asistencia
- Los vouchers se almacenan sin ruta completa (solo nombre de archivo)
- Todos los cambios se registran en `audit_logs`

---

**Versión:** 1.0  
**Última actualización:** Febrero 2026  
**Desarrollado para:** Universal Fitness SA
