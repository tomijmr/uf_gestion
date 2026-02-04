<?php
/**
 * SISTEMA DE PRODUCCIÓN AVANZADO
 * Gestión de estados, stock y tickets de producción
 */

require_once __DIR__ . '/db.php';

// Estados válidos en orden secuencial
const ESTADOS_PRODUCCION = [
    'SELECCION',
    'CORTE',
    'ARMADO',
    'SOLDADURA',
    'LIMPIEZA',
    'PINTURA',
    'ENSAMBLE',
    'QC_FINAL',
    'DESPACHO'
];

// Etapas que requieren descuento de stock
const ETAPAS_STOCK = ['CORTE', 'PINTURA', 'ENSAMBLE'];

/**
 * Obtener el estado actual de una orden de producción
 */
function obtener_estado_actual(int $po_id): ?array {
    $sql = "SELECT ps.*, e.nombre as operario_nombre
            FROM production_states ps
            LEFT JOIN employees e ON e.id = ps.operario_id
            WHERE ps.production_order_id = ?
            ORDER BY ps.timestamp_inicio DESC
            LIMIT 1";
    $st = db()->prepare($sql);
    $st->execute([$po_id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Validar si se puede avanzar al siguiente estado
 */
function puede_avanzar_estado(int $po_id, string $estado_destino): array {
    $estado_actual = obtener_estado_actual($po_id);
    
    // Si no hay estado actual, solo puede ir a SELECCION
    if (!$estado_actual && $estado_destino !== 'SELECCION') {
        return ['ok' => false, 'error' => 'Debe iniciar en estado SELECCION'];
    }
    
    // Validar flujo secuencial
    if ($estado_actual) {
        $idx_actual = array_search($estado_actual['estado'], ESTADOS_PRODUCCION);
        $idx_destino = array_search($estado_destino, ESTADOS_PRODUCCION);
        
        if ($idx_destino !== $idx_actual + 1) {
            return ['ok' => false, 'error' => 'Debe seguir el flujo secuencial de estados'];
        }
        
        // El estado actual debe estar finalizado
        if (!$estado_actual['timestamp_fin']) {
            return ['ok' => false, 'error' => 'Debe finalizar el estado actual primero'];
        }
    }
    
    // GUARDRAIL: No permitir PINTURA sin QC de ARMADO
    if ($estado_destino === 'PINTURA') {
        $sql = "SELECT aprobado_qc FROM production_states 
                WHERE production_order_id = ? AND estado = 'ARMADO' AND aprobado_qc = 1";
        $st = db()->prepare($sql);
        $st->execute([$po_id]);
        if (!$st->fetchColumn()) {
            return ['ok' => false, 'error' => 'Requiere aprobación QC de Armado antes de Pintura'];
        }
    }
    
    // GUARDRAIL: Validar stock antes de CORTE
    if ($estado_destino === 'CORTE') {
        $validacion = validar_stock_para_corte($po_id);
        if (!$validacion['ok']) {
            // Bloquear la orden
            db()->prepare("UPDATE production_orders SET estado_actual='BLOQUEADA', bloqueada_razon=? WHERE id=?")
                ->execute([$validacion['error'], $po_id]);
            return $validacion;
        }
    }
    
    return ['ok' => true];
}

/**
 * Validar stock disponible para etapa de CORTE
 */
function validar_stock_para_corte(int $po_id): array {
    // Obtener componentes del BOM
    $sql = "SELECT po.product_pt_id, po.cantidad,
                   b.component_id, b.cant_por_unidad, p.codigo, p.nombre, p.stock_actual,
                   pcs.component_type
            FROM production_orders po
            JOIN product_bom b ON b.product_pt_id = po.product_pt_id
            JOIN products p ON p.id = b.component_id
            LEFT JOIN production_component_stages pcs ON pcs.etapa = 'CORTE' 
            WHERE po.id = ?";
    $st = db()->prepare($sql);
    $st->execute([$po_id]);
    $componentes = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $faltantes = [];
    foreach ($componentes as $comp) {
        // Buscar si este componente es de tipo CORTE
        $tipo_comp = determinar_tipo_componente($comp['codigo'], $comp['nombre']);
        if (in_array($tipo_comp, ['PERFIL', 'CHAPA', 'TUBO'])) {
            $necesario = (float)$comp['cant_por_unidad'] * (float)$comp['cantidad'];
            $disponible = (float)$comp['stock_actual'];
            
            if ($disponible < $necesario) {
                $faltantes[] = "{$comp['codigo']} - {$comp['nombre']} (Necesario: {$necesario}, Disponible: {$disponible})";
            }
        }
    }
    
    if ($faltantes) {
        return [
            'ok' => false,
            'error' => 'Stock insuficiente para CORTE: ' . implode(', ', $faltantes)
        ];
    }
    
    return ['ok' => true];
}

/**
 * Determinar tipo de componente por nombre/código
 */
function determinar_tipo_componente(string $codigo, string $nombre): string {
    $nombre_upper = strtoupper($codigo . ' ' . $nombre);
    
    if (preg_match('/PERFIL|PROFILE/i', $nombre_upper)) return 'PERFIL';
    if (preg_match('/CHAPA|SHEET/i', $nombre_upper)) return 'CHAPA';
    if (preg_match('/TUBO|PIPE/i', $nombre_upper)) return 'TUBO';
    if (preg_match('/PINTURA|PAINT/i', $nombre_upper)) return 'PINTURA';
    if (preg_match('/QUIMICO|CHEMICAL|LIMPIADOR/i', $nombre_upper)) return 'QUIMICO';
    if (preg_match('/RODAMIENTO|BEARING/i', $nombre_upper)) return 'RODAMIENTO';
    if (preg_match('/POLEA|PULLEY/i', $nombre_upper)) return 'POLEA';
    if (preg_match('/TORNILL|SCREW|BOLT/i', $nombre_upper)) return 'TORNILLERIA';
    if (preg_match('/TAPIZ|UPHOLSTERY/i', $nombre_upper)) return 'TAPIZADO';
    
    return 'OTRO';
}

/**
 * Cambiar estado de la orden de producción
 */
function cambiar_estado_produccion(int $po_id, string $nuevo_estado, ?int $operario_id = null, ?string $notas = null): array {
    try {
        db()->beginTransaction();
        
        // Validar que puede avanzar
        $validacion = puede_avanzar_estado($po_id, $nuevo_estado);
        if (!$validacion['ok']) {
            throw new Exception($validacion['error']);
        }
        
        // Finalizar estado actual si existe
        $estado_actual = obtener_estado_actual($po_id);
        if ($estado_actual && !$estado_actual['timestamp_fin']) {
            db()->prepare("UPDATE production_states SET timestamp_fin = NOW() WHERE id = ?")
                ->execute([$estado_actual['id']]);
        }
        
        // Crear nuevo estado
        $sql = "INSERT INTO production_states (production_order_id, estado, operario_id, timestamp_inicio, notas)
                VALUES (?, ?, ?, NOW(), ?)";
        db()->prepare($sql)->execute([$po_id, $nuevo_estado, $operario_id, $notas]);
        $state_id = (int)db()->lastInsertId();
        
        // Actualizar estado actual en production_orders
        db()->prepare("UPDATE production_orders SET estado_actual = ? WHERE id = ?")
            ->execute([$nuevo_estado, $po_id]);
        
        // Descontar stock según la etapa
        if (in_array($nuevo_estado, ETAPAS_STOCK)) {
            descontar_stock_por_etapa($po_id, $state_id, $nuevo_estado);
        }
        
        db()->commit();
        
        return ['ok' => true, 'state_id' => $state_id, 'message' => "Estado cambiado a {$nuevo_estado}", 'nuevo_estado' => $nuevo_estado];
        
        
    } catch (Throwable $e) {
        db()->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Descontar stock de componentes según la etapa
 */
function descontar_stock_por_etapa(int $po_id, int $state_id, string $etapa): void {
    // Obtener datos de la OP
    $po = db()->prepare("SELECT product_pt_id, cantidad FROM production_orders WHERE id=?");
    $po->execute([$po_id]);
    $po_data = $po->fetch(PDO::FETCH_ASSOC);
    
    if (!$po_data) return;
    
    // Obtener BOM
    $sql = "SELECT b.component_id, b.cant_por_unidad, p.codigo, p.nombre, p.stock_actual
            FROM product_bom b
            JOIN products p ON p.id = b.component_id
            WHERE b.product_pt_id = ?";
    $st = db()->prepare($sql);
    $st->execute([$po_data['product_pt_id']]);
    $componentes = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $tipos_etapa = [];
    switch ($etapa) {
        case 'CORTE':
            $tipos_etapa = ['PERFIL', 'CHAPA', 'TUBO'];
            break;
        case 'PINTURA':
            $tipos_etapa = ['PINTURA', 'QUIMICO'];
            break;
        case 'ENSAMBLE':
            $tipos_etapa = ['RODAMIENTO', 'POLEA', 'TORNILLERIA', 'TAPIZADO'];
            break;
    }
    
    foreach ($componentes as $comp) {
        $tipo = determinar_tipo_componente($comp['codigo'], $comp['nombre']);
        
        if (in_array($tipo, $tipos_etapa)) {
            $cantidad_descontar = (float)$comp['cant_por_unidad'] * (float)$po_data['cantidad'];
            
            // Descontar del stock
            db()->prepare("UPDATE products SET stock_actual = stock_actual - ? WHERE id = ?")
                ->execute([$cantidad_descontar, $comp['component_id']]);
            
            // Registrar movimiento
            $obs = "Descuento por etapa {$etapa} - OP #{$po_id}";
            db()->prepare("INSERT INTO stock_moves (fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
                          VALUES (NOW(), 'SALIDA', 'PRODUCCION_{$etapa}', ?, ?, 'PRODUCTION_ORDER', ?, ?)")
                ->execute([$comp['component_id'], $cantidad_descontar, $po_id, $obs]);
            
            // Registrar en production_stock_movements
            db()->prepare("INSERT INTO production_stock_movements (production_state_id, production_order_id, product_id, cantidad, etapa, timestamp_descuento, observaciones)
                          VALUES (?, ?, ?, ?, ?, NOW(), ?)")
                ->execute([$state_id, $po_id, $comp['component_id'], $cantidad_descontar, $etapa, $obs]);
        }
    }
}

/**
 * Aprobar QC de un estado
 */
function aprobar_qc_estado(int $state_id, int $user_id): bool {
    db()->prepare("UPDATE production_states SET aprobado_qc = 1, qc_aprobado_por = ? WHERE id = ?")
        ->execute([$user_id, $state_id]);
    return true;
}

/**
 * Generar token QR único para una OP
 */
function generar_qr_token(int $po_id): string {
    $token = bin2hex(random_bytes(16)) . '_' . $po_id;
    db()->prepare("UPDATE production_orders SET qr_code = ? WHERE id = ?")
        ->execute([$token, $po_id]);
    return $token;
}

/**
 * Obtener datos completos para impresión de ticket
 */
function obtener_datos_ticket(int $po_id): array {
    $sql = "SELECT po.*, p.codigo, p.nombre as producto_nombre, 
                   c.nombre as cliente_nombre, o.id as order_id
            FROM production_orders po
            LEFT JOIN products p ON p.id = po.product_pt_id
            LEFT JOIN orders o ON o.id = po.order_id
            LEFT JOIN customers c ON c.id = o.customer_id
            WHERE po.id = ?";
    $st = db()->prepare($sql);
    $st->execute([$po_id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: [];
}
