# Sistema de Tickets por Estado de Producción

## ✅ Implementación Completada

Se ha implementado un sistema de tickets específicos para cada estado de producción, mostrando información relevante según la etapa.

---

## 🎫 Funcionamiento

### Generación Automática
Cuando cambias el estado de una OP (SELECCION → CORTE → ARMADO, etc.), el sistema:
1. Guarda el nuevo estado
2. Genera automáticamente un ticket específico para ese estado
3. Abre el ticket en una nueva ventana del navegador
4. Registra la impresión en la base de datos

### Botón Manual
También puedes reimprimir el ticket del estado actual:
- En `op.php`: Botón **"📋 Ticket Estado"** (verde)
- En `panel_produccion.php`: Botón **"Ticket Estado Actual"**

---

## 📋 Contenido de Tickets por Estado

### 1. SELECCION - Selección de Materiales
**Muestra:**
- BOM completo con todos los componentes
- Cantidad necesaria por unidad
- Cantidad total para la OP
- Stock actual de cada componente

**Instrucciones:**
- ✓ Verificar disponibilidad de todos los materiales
- ✓ Separar materiales en zona de preparación
- ✓ Confirmar medidas y cantidades contra BOM
- ✓ Reportar faltantes al supervisor

---

### 2. CORTE - Corte de Materiales
**Muestra:**
- Solo componentes a cortar: **Perfiles, Chapas, Tubos**
- Cantidades exactas a cortar

**Instrucciones:**
- ✓ Verificar medidas en planos
- ✓ Marcar materiales antes de cortar
- ✓ Usar equipo de protección personal
- ✓ Verificar ángulos de corte
- ✓ Etiquetar piezas cortadas

**⚠️ IMPORTANTE:** Al avanzar a CORTE se descuenta automáticamente el stock.

---

### 3. ARMADO - Armado de Estructura
**Muestra:**
- Información del producto
- Personalizaciones (color/tapizado)

**Instrucciones:**
- ✓ Verificar secuencia de armado
- ✓ Usar plantillas de posicionamiento
- ✓ Verificar escuadras y niveles
- ✓ Punto de soldadura temporal si es necesario
- ✓ Solicitar QC antes de continuar

**⚠️ CRÍTICO:** Este estado requiere aprobación de QC para avanzar a PINTURA.

---

### 4. SOLDADURA
**Instrucciones:**
- ✓ Verificar puntos de soldadura en plano
- ✓ Limpiar superficies antes de soldar
- ✓ Usar parámetros correctos de soldadura
- ✓ Verificar penetración de soldadura
- ✓ Dejar enfriar adecuadamente

---

### 5. LIMPIEZA - Limpieza y Preparación
**Instrucciones:**
- ✓ Remover escoria de soldadura
- ✓ Lijar superficies a pintar
- ✓ Desengrasado completo
- ✓ Aplicar desoxidante si corresponde
- ✓ Secar completamente

---

### 6. PINTURA - Pintura y Acabado
**Muestra:**
- Componentes de pintura: **Pinturas, Químicos, Diluyentes**
- **COLOR PERSONALIZADO destacado** (si aplica)

**Instrucciones:**
- ✓ Verificar color especificado
- ✓ Aplicar imprimación si corresponde
- ✓ Respetar tiempos de secado entre capas
- ✓ Aplicar número de capas indicado
- ✓ Verificar acabado sin defectos

**⚠️ IMPORTANTE:** 
- Requiere QC aprobado en ARMADO
- Se descuenta automáticamente el stock de pinturas

---

### 7. ENSAMBLE - Ensamble Final
**Muestra:**
- Componentes de ensamble: **Rodamientos, Poleas, Tornillería, Tapizados**
- **TAPIZADO PERSONALIZADO destacado** (si aplica)

**Instrucciones:**
- ✓ Verificar componentes contra lista
- ✓ Usar torque especificado en tornillería
- ✓ Verificar funcionamiento de partes móviles
- ✓ Aplicar lubricantes si corresponde
- ✓ Instalar tapizado según especificación

**⚠️ IMPORTANTE:** Se descuenta automáticamente el stock de componentes.

---

### 8. QC_FINAL - Control de Calidad
**Instrucciones:**
- ✓ Inspección visual completa
- ✓ Verificar dimensiones finales
- ✓ Verificar funcionamiento de mecanismos
- ✓ Verificar acabado de pintura
- ✓ Verificar firmeza de ensambles
- ✓ Fotografiar producto terminado

---

### 9. DESPACHO - Despacho y Embalaje
**Instrucciones:**
- ✓ Limpieza final del producto
- ✓ Protección de superficies pintadas
- ✓ Embalaje según especificación
- ✓ Etiquetar con datos de cliente
- ✓ Preparar documentación de entrega

---

## 🗂️ Estructura del Ticket

Cada ticket incluye:

### Header
- Título: "ORDEN DE PRODUCCIÓN"
- Número de OP
- **Estado específico destacado**

### Información General
- Cliente
- Pedido asociado
- Producto (código y nombre)
- Cantidad

### Personalizaciones (si aplican)
- 🎨 **Color personalizado** (destacado en PINTURA)
- 🪑 **Tapizado personalizado** (destacado en ENSAMBLE)

### Lista de Materiales
Solo muestra los materiales **relevantes para ese estado**:
- SELECCION: Todo el BOM
- CORTE: Perfiles, chapas, tubos
- PINTURA: Pinturas y químicos
- ENSAMBLE: Rodamientos, poleas, tornillería, tapizados

Con columnas:
- Código
- Nombre del material
- Cantidad por unidad
- **Cantidad total necesaria**

### Instrucciones Específicas
Checklist de verificación adaptado a la etapa

### Firma
- Campo para operario
- Fecha y hora
- Firma

---

## 🔧 Configuración Técnica

### Base de Datos
```sql
-- Columna agregada a production_tickets
ALTER TABLE production_tickets 
ADD COLUMN estado_ticket VARCHAR(30) NULL;
```

### Archivos Modificados
- `app/ticket_produccion.php`: Nueva función `generar_ticket_por_estado()`
- `app/produccion.php`: Devuelve el nuevo estado en respuesta
- `public/op.php`: Endpoint y botón para tickets por estado
- `public/panel_produccion.php`: Botón para tickets por estado

### Endpoint
```
GET /op.php?print_ticket_estado=1&po_id=123&estado=CORTE
```

---

## 💡 Ventajas del Sistema

1. **Información Específica**: Cada operario ve solo lo que necesita para su etapa
2. **Reducción de Errores**: Instrucciones claras y verificables
3. **Trazabilidad**: Cada ticket queda registrado en la base de datos
4. **Eficiencia**: No buscar en un BOM completo cuando solo necesitas 5 componentes
5. **Personalización Visible**: Color y tapizado destacados donde son relevantes
6. **Automatización**: Se genera automáticamente al cambiar estado

---

## 📱 Uso Práctico

### Para el Operario de Corte:
1. Escanea QR o cambia estado a CORTE
2. Se abre automáticamente el ticket de CORTE
3. Ve solo: perfiles, chapas, tubos
4. Marca cada item mientras corta
5. Firma al terminar

### Para el Operario de Pintura:
1. Cambia estado a PINTURA
2. Ve automáticamente:
   - Tipo y cantidad de pintura
   - **COLOR PERSONALIZADO en grande**
   - Instrucciones de aplicación
3. Evita errores de color
4. Firma al terminar

### Para el Operario de Ensamble:
1. Cambia estado a ENSAMBLE
2. Ve automáticamente:
   - Rodamientos y poleas
   - Tornillería con cantidades
   - **TAPIZADO PERSONALIZADO en grande**
3. No pierde tiempo buscando en todo el BOM
4. Firma al terminar

---

## 🎯 Casos de Uso

### Caso 1: OP con Color Personalizado
1. Cliente pide cinta de correr en color **"Rojo Ferrari"**
2. Al llegar a PINTURA, el ticket muestra:
   ```
   🎨 COLOR: ROJO FERRARI
   ```
   En grande y destacado
3. Imposible equivocarse de color

### Caso 2: OP con Materiales Complejos
1. Producto tiene 50 componentes en el BOM
2. En CORTE, el operario ve solo 8 (perfiles y chapas)
3. En PINTURA, ve solo 3 (pinturas)
4. En ENSAMBLE, ve 15 (rodamientos, poleas, etc.)
5. Cada etapa es más clara y rápida

### Caso 3: Auditoría de Producción
1. Revisar `production_tickets` filtrando por estado
2. Ver cuántas veces se reimprimió cada ticket
3. Identificar estados problemáticos (muchas reimpresiones)

---

## 🚀 Mejoras Futuras Sugeridas

1. **Fotos de Referencia**: Agregar imágenes del producto en cada estado
2. **Video Tutoriales**: QR que lleva a video de cómo hacer esa etapa
3. **Tiempo Estimado**: Mostrar tiempo promedio que debería tomar
4. **Alertas**: Si una etapa demora más del tiempo estimado
5. **Firma Digital**: Capturar firma en tablet en lugar de papel
6. **Impresión Térmica Real**: Adaptar a impresora térmica física

---

**Fecha:** 4 de febrero de 2026  
**Estado:** ✅ Funcional y en producción
