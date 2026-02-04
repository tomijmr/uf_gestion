# Sistema de Producción Avanzado - Integración Completada

## ✅ Estado de Integración

El nuevo sistema de producción avanzado ha sido completamente integrado en `public/op.php`.

### Archivos Creados/Modificados

1. **sistema_produccion_avanzado.sql** - Schema de base de datos
2. **app/produccion.php** - Lógica de negocio del sistema
3. **app/ticket_produccion.php** - Generación de tickets ESC/POS y HTML
4. **public/op.php** - Interfaz de usuario integrada ✅
5. **op.php.backup** - Respaldo del archivo original

---

## 🚀 Pasos para Activar el Sistema

### 1. Ejecutar el Script SQL

Primero debes ejecutar el archivo SQL para crear las tablas necesarias:

```bash
cd /opt/lampp/htdocs/dev/uf_gestion/uf_gestion
mysql -u root -p nombre_base_datos < sistema_produccion_avanzado.sql
```

Esto creará:
- Tabla `production_states` (historial de estados)
- Tabla `production_stock_movements` (movimientos de stock)
- Tabla `production_component_stages` (mapeo componente→etapa)
- Tabla `production_tickets` (historial de impresiones)
- Columnas nuevas en `production_orders`:
  - `estado_actual` VARCHAR(30)
  - `color_personalizado` VARCHAR(100)
  - `tapizado_personalizado` VARCHAR(100)
  - `bloqueada_razon` TEXT
  - `ticket_impreso` TINYINT(1)
  - `qr_code` VARCHAR(100)
- Vista `v_production_orders_estado`

### 2. Verificar Tabla `employees`

El sistema requiere la tabla `employees` con:
```sql
CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  activo TINYINT(1) DEFAULT 1
);
```

Agrega algunos empleados de prueba:
```sql
INSERT INTO employees (nombre) VALUES 
('Juan Pérez'),
('María González'),
('Carlos Rodríguez'),
('Ana Martínez');
```

### 3. Probar el Sistema

1. Accede a `public/op.php`
2. Verás 3 nuevos botones en cada OP:
   - **Cambiar Estado** - Avanzar a siguiente etapa
   - **Ver Timeline** - Ver historial de estados
   - **Imprimir Ticket** - Generar ticket de producción

---

## 📋 Flujo de Trabajo del Nuevo Sistema

### Estados Secuenciales (Obligatorio seguir el orden)

1. **SELECCION** - Selección de materiales
2. **CORTE** - Corte de materiales → 🔴 Descuenta perfiles/chapas/tubos del stock
3. **ARMADO** - Armado de estructura
4. **SOLDADURA** - Soldadura
5. **LIMPIEZA** - Limpieza y acabado
6. **PINTURA** - Pintura → 🔴 Requiere QC aprobado en ARMADO + Descuenta pinturas/químicos
7. **ENSAMBLE** - Ensamble final → 🔴 Descuenta rodamientos/poleas/tornillería/tapizados
8. **QC_FINAL** - Control de calidad final
9. **DESPACHO** - Listo para entregar

### 🛡️ Guardrails (Validaciones Automáticas)

#### 1. Flujo Secuencial Obligatorio
- No puedes saltar estados
- Solo puedes avanzar al siguiente estado en la secuencia
- Ejemplo: Si estás en CORTE, solo puedes ir a ARMADO

#### 2. Validación de Stock (CORTE)
Antes de avanzar a **CORTE**, el sistema valida que haya stock suficiente de:
- Perfiles
- Chapas
- Tubos

Si falta stock, la OP se bloquea y registra en `bloqueada_razon`.

#### 3. Aprobación de QC (PINTURA)
Para avanzar de **ARMADO** → **PINTURA**, el estado ARMADO debe estar aprobado por QC:
1. Alguien debe hacer clic en "Aprobar QC" en el timeline del estado ARMADO
2. Solo entonces se puede avanzar a PINTURA

#### 4. Descuento Automático de Stock

El sistema descuenta automáticamente el stock en 3 etapas:

**CORTE:**
- Perfiles (`PERF-*`, `*PERFIL*`)
- Chapas (`CH-*`, `CHAPA-*`, `*CHAPA*`)
- Tubos (`TUBO-*`, `*TUBO*`)

**PINTURA:**
- Pinturas (`PINT-*`, `*PINTURA*`)
- Químicos (`*QUIMICO*`, `*DILUYENTE*`, `*SOLVENTE*`)

**ENSAMBLE:**
- Rodamientos (`ROD-*`, `*RODAMIENTO*`)
- Poleas (`*POLEA*`)
- Tornillería (`*TORNILLO*`, `*TUERCA*`, `*ARANDELA*`)
- Tapizados (`*TAPIZ*`, `*TELA*`, `*ESPUMA*`)

---

## 🎟️ Sistema de Tickets de Producción

### Imprimir Ticket

Al hacer clic en **Imprimir Ticket**, se genera un documento con:

✓ Encabezado con logo (puedes agregarlo)
✓ Información de la OP
✓ Cliente y producto
✓ Cantidad y personalizaciones (color/tapizado)
✓ **Código QR** único para escaneo móvil
✓ Checklist de los 9 estados
✓ Campos para firma del operario y QC

### Código QR

Cada ticket tiene un QR único que incluye:
- ID de la OP
- Token de seguridad
- URL para cambio de estado desde móvil

**Implementación futura:**
Crear página `public/qr_cambio_estado.php` que reciba:
```
?token=XXXX&po_id=123
```

Y permita cambiar el estado escaneando desde un celular en el taller.

---

## 🔧 Configuración Avanzada

### 1. Personalizar Tipos de Componentes

Edita la tabla `production_component_stages` para ajustar qué componentes se descargan en cada etapa:

```sql
-- Ejemplo: Agregar un tipo nuevo
INSERT INTO production_component_stages (component_type, etapa_stock, nombre_display)
VALUES ('MOTOR', 'ENSAMBLE', 'Motores y Actuadores');
```

Luego edita `app/produccion.php` → función `determinar_tipo_componente()` para agregar el regex:

```php
if (preg_match('/MOT-|MOTOR/i', $codigo_o_nombre)) return 'MOTOR';
```

### 2. Ajustar Validaciones

Edita `app/produccion.php` → función `puede_avanzar_estado()` para:
- Agregar más guardrails
- Cambiar el flujo de QC
- Modificar validaciones de stock

### 3. Personalizar Tickets

Edita `app/ticket_produccion.php` → funciones:
- `generar_ticket_html()` - Para formato HTML/PDF
- `generar_ticket_escpos()` - Para impresora térmica ESC/POS 80mm

---

## 📊 Consultas Útiles

### Ver historial de una OP

```sql
SELECT ps.*, e.nombre as operario
FROM production_states ps
LEFT JOIN employees e ON e.id = ps.operario_id
WHERE ps.production_order_id = 123
ORDER BY ps.timestamp_inicio;
```

### Ver OPs bloqueadas por falta de stock

```sql
SELECT po.id, po.bloqueada_razon, p.nombre
FROM production_orders po
JOIN products p ON p.id = po.product_pt_id
WHERE po.bloqueada_razon IS NOT NULL;
```

### Ver movimientos de stock por etapa

```sql
SELECT psm.*, po.id as op_id, p.nombre as componente
FROM production_stock_movements psm
JOIN production_orders po ON po.id = psm.production_order_id
JOIN products p ON p.id = psm.component_id
WHERE psm.etapa = 'CORTE'
ORDER BY psm.timestamp DESC;
```

### Ver qué estados necesitan aprobación de QC

```sql
SELECT po.id, po.estado_actual, ps.estado, ps.qc_aprobado
FROM production_orders po
JOIN production_states ps ON ps.production_order_id = po.id
WHERE ps.estado IN ('ARMADO', 'QC_FINAL')
  AND ps.timestamp_fin IS NULL
  AND ps.qc_aprobado = 0;
```

---

## 🧪 Casos de Prueba

### Test 1: Flujo completo sin bloqueos

1. Crear una nueva OP con BOM que tenga stock suficiente
2. Cambiar estado a SELECCION (Operario: Juan)
3. Cambiar estado a CORTE → Verificar que se descontó el stock de perfiles/chapas
4. Cambiar estado a ARMADO
5. Cambiar estado a SOLDADURA
6. Cambiar estado a LIMPIEZA
7. Ver Timeline → Click en "Aprobar QC" del estado ARMADO
8. Cambiar estado a PINTURA → Verificar que se descontó el stock de pinturas
9. Cambiar estado a ENSAMBLE → Verificar que se descontó el stock de rodamientos/tapizados
10. Cambiar estado a QC_FINAL
11. Cambiar estado a DESPACHO
12. Imprimir ticket y verificar QR

### Test 2: Bloqueo por falta de stock

1. Crear OP con BOM que tenga componentes sin stock
2. Intentar cambiar a CORTE
3. Debe aparecer error: "Stock insuficiente para CORTE"
4. Verificar que `bloqueada_razon` tiene el detalle

### Test 3: Bloqueo por falta de QC

1. Crear OP y llegar hasta LIMPIEZA
2. Intentar cambiar a PINTURA sin aprobar QC en ARMADO
3. Debe aparecer error: "El estado ARMADO debe estar aprobado por QC"

### Test 4: Saltar estados (debe fallar)

1. Crear OP en SELECCION
2. Intentar cambiar directo a PINTURA
3. Debe aparecer error de flujo secuencial

---

## 🐛 Troubleshooting

### Error: "Call to undefined function cambiar_estado_produccion()"

**Solución:** Verifica que `require_once __DIR__ . '/../app/produccion.php';` esté al inicio de `op.php`.

### Error: "Table 'production_states' doesn't exist"

**Solución:** Ejecuta el archivo `sistema_produccion_avanzado.sql`.

### Error: "No se encontraron empleados"

**Solución:** Crea la tabla `employees` y agrega registros.

### Los estados no se guardan

**Solución:** Verifica que las columnas nuevas existan en `production_orders`:
```sql
SHOW COLUMNS FROM production_orders LIKE '%estado_actual%';
```

### Stock no se descuenta

**Solución:** 
1. Verifica que los componentes del BOM tengan códigos que coincidan con los regex en `determinar_tipo_componente()`
2. Revisa la tabla `production_component_stages`
3. Activa logs de errores en `app/produccion.php`

---

## 📝 Notas Importantes

### Sistema Antiguo vs Nuevo

El sistema **NO** elimina el flujo antiguo (Iniciar/Finalizar/Entregar). Ambos coexisten:

- **Botones antiguos:** Iniciar (Antiguo), Finalizar (Antiguo), Entregar
- **Botones nuevos:** Cambiar Estado, Ver Timeline, Imprimir Ticket

Puedes seguir usando el sistema antiguo para OPs que no requieren el nuevo flujo detallado.

### Migración Gradual

Puedes activar el nuevo sistema solo para **nuevas OPs**. Las OPs antiguas seguirán funcionando con el flujo simple.

### Backup

Antes de cualquier cambio, se creó `op.php.backup`. Para revertir:
```bash
cd /opt/lampp/htdocs/dev/uf_gestion/uf_gestion/public
cp op.php.backup op.php
```

---

## 🎯 Próximos Pasos Sugeridos

1. **Implementar QR Scanner Mobile:**
   - Crear `public/qr_cambio_estado.php`
   - Interfaz móvil optimizada
   - Validar token de seguridad

2. **Dashboard de Producción:**
   - Vista Kanban de OPs por estado
   - Métricas de tiempo por etapa
   - Alertas de OPs bloqueadas

3. **Reportes:**
   - Tiempo promedio por etapa
   - Operarios más productivos
   - Componentes con stock crítico

4. **Notificaciones:**
   - Email/SMS cuando OP se bloquea
   - Alerta cuando se necesita aprobación QC
   - Notificación al avanzar a DESPACHO

5. **Integración con Impresora Térmica:**
   - Configurar `app/ticket_produccion.php` para enviar a impresora real
   - Driver ESC/POS para Linux
   - Botón "Imprimir en Impresora Térmica"

---

## 📞 Soporte

Para dudas o problemas con el sistema, consultar:
- `SISTEMA_PRODUCCION_README.md` - Documentación técnica completa
- `sistema_produccion_avanzado.sql` - Schema de base de datos
- `app/produccion.php` - Lógica de negocio comentada

---

**Fecha de Integración:** 2024  
**Versión:** 1.0  
**Estado:** ✅ Integrado y listo para pruebas
