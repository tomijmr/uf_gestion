# PT (Producto Terminado/Máquina) - Cambios de Pricing Manual

## Objetivo
Deshabilitar la actualización automática de precios para productos PT (máquinas/productos terminados).
El precio ahora es **100% manual** y no se actualiza automáticamente según cambios en el BOM (Bill of Materials).

## Cambios Realizados

### 1. **producto_ver.php** - Deshabilitadas todas las actualizaciones automáticas de precio
Archivo: `/public/producto_ver.php`

**Línea ~105**: Actualización de PT dependientes cuando se modifica una MP
```php
// DESHABILITADO: Al editar una MP, ya no se actualizan automáticamente los PT que la usan
// $dependent_pt_ids = db()->query("SELECT product_pt_id FROM product_bom WHERE component_id = $id")->fetchAll(PDO::FETCH_COLUMN);
// foreach ($dependent_pt_ids as $pt_id) {
//     refresh_pt_price((int)$pt_id);
// }
```

**Línea ~144**: Deshabilitado refresh después de agregar componente a BOM
```php
// DESHABILITADO: refresh_pt_price($id); - El precio de PT ahora es 100% manual
```

**Línea ~159**: Deshabilitado refresh después de eliminar componente de BOM
```php
// DESHABILITADO: refresh_pt_price($id); - El precio de PT ahora es 100% manual
```

**Línea ~179**: Deshabilitado refresh después de actualizar cantidades en BOM
```php
// DESHABILITADO: refresh_pt_price($id); - El precio de PT ahora es 100% manual
```

**Línea ~194**: Deshabilitado refresh después de actualizar margen %
```php
// DESHABILITADO: refresh_pt_price($id); - El precio de PT ahora es 100% manual
```

### 2. **productos.php** - Ya estaba deshabilitado
- Línea 57: `update_all_pt_prices()` ya estaba comentado
- Los refresh dentro de `update_all_pt_prices()` ya no se ejecutan

### 3. **productos_terminados.php** - Ya estaba deshabilitado
- Línea 57: `update_all_pt_prices()` ya estaba comentado
- Los refresh dentro de `update_all_pt_prices()` ya no se ejecutan

## Impacto

### Operaciones que YA NO actualizan el precio PT:
- ✓ Agregar componente a BOM
- ✓ Eliminar componente de BOM
- ✓ Cambiar cantidades en BOM
- ✓ Actualizar margen %
- ✓ Editar MP (materia prima)
- ✓ Entrar a la página de productos

### Operaciones que SÍ actualizan el precio PT:
- ✓ Editar manualmente el campo "Precio de Venta" en producto_ver.php
- ✓ Crear nuevo PT (precio inicial según lo ingresado)

## Estado de la Función `refresh_pt_price()`
- **Ubicación**: producto_ver.php, líneas 26-35
- **Estado**: Existe pero nunca se llama
- **Razón**: Mantenerla evita errores si se agrega código futuro que la referencie
- **Nota**: La función tiene un guard `if ($costo <= 0) return;` pero ya no se ejecuta

## Verificación
Para confirmar que no hay más actualizaciones automáticas:
1. Editar un PT y agregar/quitar componentes del BOM → Precio no cambia
2. Cambiar cantidades en BOM → Precio no cambia
3. Cambiar margen % → Precio no cambia
4. Editar el campo "Precio de Venta" → Precio SÍ cambia (correcto)

## Notas de Desarrollo
- Función `bom_cost()` aún existe y funciona (se usa en otros contextos como mostrar el costo)
- El BOM sigue funcionando normalmente, solo el pricing está deshabilitado
- Todos los cambios son reversibles comentando el `//` que deshabilitó la funcionalidad
