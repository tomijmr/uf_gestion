# SISTEMA DE PRODUCCIÓN AVANZADO
## Implementación Completa para OP.php

---

## 📋 RESUMEN DEL SISTEMA

Sistema de producción con **9 estados secuenciales**, descuento automático de stock por etapa, generación de tickets térmicos ESC/POS 80mm con QR codes, y validaciones de flujo (guardrails).

---

## 🗄️ 1. ESTRUCTURA DE BASE DE DATOS

### Tablas Creadas:

#### `production_states`
Almacena cada cambio de estado con:
- `production_order_id`: ID de la orden de producción
- `estado`: ENUM con los 9 estados
- `operario_id`: Empleado asignado
- `timestamp_inicio` / `timestamp_fin`: Control de tiempos
- `aprobado_qc`: Flag de aprobación QC
- `qc_aprobado_por`: Usuario que aprobó QC
- `notas`: Observaciones

#### `production_stock_movements`
Registro de descuentos de stock por etapa:
- `production_state_id`: Estado que originó el descuento
- `product_id`: Componente consumido
- `cantidad`: Cantidad descontada
- `etapa`: CORTE, PINTURA o ENSAMBLE
- `timestamp_descuento`: Fecha/hora del descuento

#### `production_component_stages`
Configuración de qué tipo de componente se descuenta en cada etapa:
- Componentes por defecto ya insertados:
  - **CORTE**: Perfiles, Chapas, Tubos
  - **PINTURA**: Pintura, Químicos
  - **ENSAMBLE**: Rodamientos, Poleas, Tornillería, Tapizados

#### `production_tickets`
Histórico de tickets impresos:
- `qr_token`: Token único para QR
- `url_qr`: URL para escaneo
- `impreso_por`: Usuario que imprimió
- `fecha_impresion`: Timestamp

### Columnas Agregadas a `production_orders`:
- `estado_actual`: Estado actual (ENUM)
- `color_personalizado`: Color seleccionado
- `tapizado_personalizado`: Tipo de tapizado
- `bloqueada_razon`: Motivo de bloqueo
- `ticket_impreso`: Flag de impresión
- `qr_code`: Token QR único

### Vista: `v_production_orders_estado`
Consulta optimizada que junta toda la info de una OP.

---

## ⚙️ 2. FLUJO DE ESTADOS

### Estados Secuenciales:
1. **SELECCIÓN** → Selección de materiales
2. **CORTE** → Corte de perfiles y chapas (descuenta stock)
3. **ARMADO** → Armado de estructura
4. **SOLDADURA** → Soldadura y refuerzos
5. **LIMPIEZA** → Limpieza y preparación
6. **PINTURA** → Pintura y acabados (descuenta stock)
7. **ENSAMBLE** → Ensamble de componentes (descuenta stock)
8. **QC_FINAL** → Control de calidad
9. **DESPACHO** → Despacho y embalaje

### Reglas de Avance:
- ✅ Debe seguir el orden secuencial (no se puede saltar estados)
- ✅ El estado actual debe estar finalizado antes de avanzar
- ✅ **GUARDRAIL 1**: No se puede pasar a PINTURA sin QC aprobado en ARMADO
- ✅ **GUARDRAIL 2**: No se puede iniciar CORTE sin stock suficiente (bloquea la OP)

---

## 🔧 3. FUNCIONES PRINCIPALES

### Archivo: `/app/produccion.php`

#### `obtener_estado_actual($po_id)`
Devuelve el estado actual de una OP con datos del operario.

#### `puede_avanzar_estado($po_id, $estado_destino)`
Valida si es posible avanzar al siguiente estado.
- Valida flujo secuencial
- Verifica QC de ARMADO antes de PINTURA
- Valida stock antes de CORTE

#### `cambiar_estado_produccion($po_id, $nuevo_estado, $operario_id, $notas)`
Cambia el estado de una OP:
1. Valida el cambio
2. Finaliza el estado actual
3. Crea el nuevo estado
4. Actualiza `estado_actual` en `production_orders`
5. Descuenta stock si corresponde (CORTE, PINTURA, ENSAMBLE)

#### `descontar_stock_por_etapa($po_id, $state_id, $etapa)`
Descuenta componentes del BOM según la etapa:
- Lee el BOM del PT
- Determina el tipo de componente (PERFIL, CHAPA, PINTURA, etc.)
- Descuenta solo los componentes que corresponden a esa etapa
- Registra en `stock_moves` y `production_stock_movements`

#### `determinar_tipo_componente($codigo, $nombre)`
Identifica el tipo de componente por su nombre/código usando regex.

#### `validar_stock_para_corte($po_id)`
Valida que haya stock suficiente de perfiles, chapas y tubos antes de iniciar CORTE.
Si no hay stock, bloquea la OP y devuelve el error.

#### `aprobar_qc_estado($state_id, $user_id)`
Marca un estado como QC aprobado.

#### `generar_qr_token($po_id)`
Genera un token único para el código QR de la OP.

---

## 🎫 4. GENERACIÓN DE TICKETS

### Archivo: `/app/ticket_produccion.php`

#### `imprimir_ticket_produccion($po_id, $metodo)`
Genera el ticket en formato ESC/POS o HTML.

### Formato ESC/POS (Impresoras Térmicas 80mm):
- Header: ID de OP en negrita
- Cliente y Pedido
- Producto y código
- Personalización (color/tapizado)
- **QR Code** con URL dinámica para cambiar estado
- Checklist de los 9 estados para firma manual
- Espacio para operario, fecha y firma

### Formato HTML (Previsualización):
- Mismo contenido que ESC/POS
- QR generado con API externa
- Botón de impresión
- Optimizado para papel 80mm

### URL del QR:
```
/op.php?qr={token_unico}
```
Al escanear, el operario puede cambiar el estado directamente.

---

## 🚨 5. GUARDRAILS (VALIDACIONES)

### 1. Flujo Secuencial Obligatorio
No se puede saltar estados. Ejemplo: No se puede ir de CORTE a PINTURA sin pasar por ARMADO y SOLDADURA.

### 2. QC de ARMADO Obligatorio para PINTURA
```php
if ($estado_destino === 'PINTURA') {
    // Buscar aprobación QC en estado ARMADO
    if (!aprobado_qc) {
        return error;
    }
}
```

### 3. Validación de Stock en CORTE
```php
if ($estado_destino === 'CORTE') {
    $validacion = validar_stock_para_corte($po_id);
    if (!$validacion['ok']) {
        // Bloquear OP
        UPDATE production_orders SET estado_actual='BLOQUEADA';
    }
}
```

### 4. Finalización de Estado Actual
No se puede avanzar al siguiente estado si el actual no tiene `timestamp_fin`.

---

## 📊 6. DESCUENTO DE STOCK POR ETAPA

### CORTE:
Descuenta:
- Perfiles
- Chapas
- Tubos

### PINTURA:
Descuenta:
- Pintura
- Químicos (limpiadores, preparadores)

### ENSAMBLE:
Descuenta:
- Rodamientos
- Poleas
- Tornillería
- Tapizados

### Lógica:
1. Al cambiar a uno de estos 3 estados, se ejecuta `descontar_stock_por_etapa()`
2. Se lee el BOM del producto PT
3. Se determina el tipo de cada componente
4. Se descuentan solo los que corresponden a esa etapa
5. Se registra en `stock_moves` (motivo: `PRODUCCION_{etapa}`)
6. Se registra en `production_stock_movements`

---

## 🔄 7. INTEGRACIÓN EN OP.PHP

### Endpoints a Agregar:

#### POST: Cambiar Estado
```php
if ($_POST['action'] === 'cambiar_estado') {
    $po_id = (int)$_POST['po_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    $operario_id = (int)$_POST['operario_id'];
    $notas = $_POST['notas'] ?? null;
    
    $resultado = cambiar_estado_produccion($po_id, $nuevo_estado, $operario_id, $notas);
    // Mostrar mensaje
}
```

#### POST: Aprobar QC
```php
if ($_POST['action'] === 'aprobar_qc') {
    $state_id = (int)$_POST['state_id'];
    $user_id = $_SESSION['user']['id'];
    
    aprobar_qc_estado($state_id, $user_id);
}
```

#### GET: Imprimir Ticket
```php
if ($_GET['print_ticket'] ?? false) {
    $po_id = (int)$_GET['po_id'];
    $html = imprimir_ticket_produccion($po_id, 'html');
    echo $html;
    exit;
}
```

#### GET: Escaneo QR
```php
if ($_GET['qr'] ?? false) {
    $token = $_GET['qr'];
    // Buscar OP por token
    // Mostrar formulario para cambiar estado
}
```

### UI Sugerida:

**Vista de OP:**
```html
<tr>
  <td>#123</td>
  <td>HACK 50° - Cliente: Gimnasio XYZ</td>
  <td>Estado Actual: <badge>ARMADO</badge></td>
  <td>
    <button>Ver Timeline</button>
    <button>Cambiar Estado</button>
    <button>Imprimir Ticket</button>
  </td>
</tr>

<!-- Modal: Cambiar Estado -->
<form method="post">
  <input type="hidden" name="action" value="cambiar_estado">
  <input type="hidden" name="po_id" value="123">
  
  <select name="nuevo_estado">
    <option value="SOLDADURA">SOLDADURA</option>
  </select>
  
  <select name="operario_id">
    <option value="1">Juan Pérez</option>
  </select>
  
  <textarea name="notas"></textarea>
  <button>Cambiar Estado</button>
</form>

<!-- Timeline de Estados -->
<div class="timeline">
  <div class="step completed">
    ✓ SELECCION - Juan Pérez - 01/02 08:00 → 09:00
  </div>
  <div class="step completed">
    ✓ CORTE - María López - 01/02 09:15 → 11:30
  </div>
  <div class="step active">
    ⏱ ARMADO - Pedro García - 01/02 13:00 → ...
  </div>
</div>
```

---

## 📦 8. ARCHIVOS GENERADOS

1. **`sistema_produccion_avanzado.sql`** - Estructura de BD completa
2. **`/app/produccion.php`** - Lógica de estados y stock
3. **`/app/ticket_produccion.php`** - Generación de tickets
4. **Este README** - Documentación completa

---

## 🚀 9. PASOS DE IMPLEMENTACIÓN

### Paso 1: Base de Datos
```bash
mysql -u usuario -p nombre_bd < sistema_produccion_avanzado.sql
```

### Paso 2: Integrar en op.php
```php
require_once __DIR__ . '/../app/produccion.php';
require_once __DIR__ . '/../app/ticket_produccion.php';

// Agregar endpoints POST/GET según sección 7
```

### Paso 3: Crear UI
- Botón "Cambiar Estado" en cada OP
- Modal con select de estado y operario
- Timeline visual de estados
- Botón "Imprimir Ticket"

### Paso 4: Configurar Componentes
Revisar y ajustar `production_component_stages` según tu catálogo real:
```sql
INSERT INTO production_component_stages (component_type, etapa, descripcion) 
VALUES ('TIPO_CUSTOM', 'CORTE', 'Descripción');
```

### Paso 5: Ajustar `determinar_tipo_componente()`
En `/app/produccion.php`, ajustar los regex según nomenclatura de tus productos.

---

## 🎯 10. EJEMPLO DE USO

### Flujo Completo:

1. **Crear OP** (desde pedidos.php o manualmente)
   - OP #123 creada para HACK 50° x1 unidad
   - Estado: NULL

2. **Imprimir Ticket**
   - Genera QR con token único
   - Imprime checklist

3. **Iniciar SELECCIÓN**
   - Operario: Juan Pérez
   - Timestamp inicio: 01/02 08:00
   - Estado actual: SELECCIÓN

4. **Finalizar SELECCIÓN y pasar a CORTE**
   - Timestamp fin SELECCIÓN: 01/02 09:00
   - Valida stock de perfiles, chapas, tubos
   - Si hay stock: Avanza a CORTE y descuenta materiales
   - Si no hay stock: Bloquea OP con mensaje

5. **Continuar con ARMADO, SOLDADURA, etc.**
   - Cada cambio registra timestamps y operario

6. **Aprobar QC en ARMADO**
   - Supervisor marca QC como aprobado
   - Ahora puede avanzar a PINTURA

7. **PINTURA**
   - Descuenta pintura y químicos

8. **ENSAMBLE**
   - Descuenta rodamientos, poleas, tornillería, tapizados

9. **QC FINAL y DESPACHO**
   - Control final de calidad
   - Listo para entrega

---

## 📱 11. ESCANEO QR (Operarios)

### URL del QR:
```
https://tu-dominio.com/op.php?qr=abc123def456_123
```

### Flujo al Escanear:
1. El operario escanea el QR del ticket
2. Se identifica la OP por el token
3. Se muestra formulario simple:
   - Estado actual: ARMADO
   - Próximo estado: SOLDADURA
   - Botón "Finalizar ARMADO e Iniciar SOLDADURA"
4. Al confirmar, se ejecuta `cambiar_estado_produccion()`

---

## ⚠️ 12. CONSIDERACIONES

### Performance:
- Índices creados en todas las tablas
- Vista materializada para consultas rápidas

### Seguridad:
- Validar tokens QR
- Verificar permisos de usuario
- Transacciones DB para consistencia

### Impresión:
- ESC/POS requiere impresora térmica compatible
- HTML funciona en cualquier navegador
- Ajustar comandos QR según modelo de impresora

### Personalización:
- Ajustar regex en `determinar_tipo_componente()`
- Modificar `production_component_stages` según catálogo
- Agregar más estados si es necesario (modificar ENUM)

---

## 📞 SOPORTE

Este sistema está diseñado para ser modular y extensible. 
Cualquier ajuste se puede hacer fácilmente en los archivos PHP o SQL.

**¡El sistema está listo para implementar!** 🚀
