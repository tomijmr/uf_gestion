<?php
/**
 * GENERADOR DE TICKETS TÉRMICOS ESC/POS 80mm
 * Para impresoras térmicas de producción
 */

require_once __DIR__ . '/produccion.php';
require_once __DIR__ . '/helpers.php';

/**
 * Generar ticket específico por estado de producción
 */
function generar_ticket_por_estado(int $po_id, string $estado): string {
    $datos = obtener_datos_ticket($po_id);
    if (!$datos) {
        throw new Exception("Orden de producción no encontrada");
    }
    
    // Obtener BOM completo
    $bom = db()->prepare("
        SELECT b.component_id, b.cant_por_unidad, p.codigo, p.nombre, p.unidad, p.stock_actual
        FROM product_bom b
        JOIN products p ON p.id = b.component_id
        WHERE b.product_pt_id = ?
        ORDER BY p.nombre
    ");
    $bom->execute([$datos['product_pt_id']]);
    $componentes = $bom->fetchAll(PDO::FETCH_ASSOC);
    
    // Registrar impresión del ticket de estado
    $user_id = $_SESSION['user']['id'] ?? null;
    db()->prepare("INSERT INTO production_tickets (production_order_id, estado_ticket, impreso_por, fecha_impresion)
                   VALUES (?, ?, ?, NOW())")
        ->execute([$po_id, $estado, $user_id]);
    
    // Generar HTML según el estado
    $html = generar_ticket_html_estado($po_id, $estado, $datos, $componentes);
    
    return $html;
}

/**
 * Generar contenido HTML del ticket por estado
 */
function generar_ticket_html_estado(int $po_id, string $estado, array $datos, array $componentes): string {
    $cantidad_op = (float)$datos['cantidad'];
    
    // Filtrar componentes según el estado
    $componentes_filtrados = [];
    switch ($estado) {
        case 'SELECCION':
            $componentes_filtrados = $componentes; // Mostrar todo el BOM
            break;
        case 'CORTE':
            foreach ($componentes as $comp) {
                $tipo = determinar_tipo_componente($comp['codigo'], $comp['nombre']);
                if (in_array($tipo, ['PERFIL', 'CHAPA', 'TUBO'])) {
                    $componentes_filtrados[] = $comp;
                }
            }
            break;
        case 'PINTURA':
            foreach ($componentes as $comp) {
                $tipo = determinar_tipo_componente($comp['codigo'], $comp['nombre']);
                if (in_array($tipo, ['PINTURA', 'QUIMICO'])) {
                    $componentes_filtrados[] = $comp;
                }
            }
            break;
        case 'ENSAMBLE':
            foreach ($componentes as $comp) {
                $tipo = determinar_tipo_componente($comp['codigo'], $comp['nombre']);
                if (in_array($tipo, ['RODAMIENTO', 'POLEA', 'TORNILLERIA', 'TAPIZADO'])) {
                    $componentes_filtrados[] = $comp;
                }
            }
            break;
        default:
            $componentes_filtrados = []; // Otros estados sin lista de materiales
    }
    
    $titulo_estado = [
        'SELECCION' => 'SELECCIÓN DE MATERIALES',
        'CORTE' => 'CORTE DE MATERIALES',
        'ARMADO' => 'ARMADO DE ESTRUCTURA',
        'SOLDADURA' => 'SOLDADURA',
        'LIMPIEZA' => 'LIMPIEZA Y PREPARACIÓN',
        'PINTURA' => 'PINTURA Y ACABADO',
        'ENSAMBLE' => 'ENSAMBLE FINAL',
        'QC_FINAL' => 'CONTROL DE CALIDAD',
        'DESPACHO' => 'DESPACHO Y EMBALAJE'
    ];
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Ticket OP #<?= $po_id ?> - <?= e($estado) ?></title>
        <style>
            /* Print-optimized styles for thermal printers: larger and bolder text */
            @media print {
                @page { margin: 3mm; size: 80mm auto; }
                body { margin: 0; }
                .no-print { display: none; }
                /* Impresion agresiva: forzar tamaño y grosor en todos los elementos para impresoras térmicas */
                body * {
                    font-size: 18px !important;
                    font-weight: 900 !important;
                    -webkit-text-stroke: 1px #000 !important;
                    text-shadow: 1px 0 0 #000, -1px 0 0 #000 !important;
                }
            }
            body {
                /* Use a heavy sans-serif font for better thermal output */
                font-family: 'Arial Black', 'Impact', 'Helvetica', 'Trebuchet MS', sans-serif;
                font-weight: 900;
                font-size: 18px; /* base increased for better legibility */
                line-height: 1.1;
                width: 80mm;
                margin: 0 auto;
                padding: 3mm 4mm;
                background: white;
                color: #000;
                -webkit-print-color-adjust: exact;
                text-rendering: optimizeLegibility;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                -webkit-text-stroke: 1px #000; /* add stroke for thickness */
            }
            /* Thicken text using stroke + shadows to improve legibility on low-res printers */
            .thick {
                font-weight: 900;
                -webkit-text-stroke: 0.9px #000;
                text-shadow: 1px 0 0 #000, -1px 0 0 #000, 0 1px 0 #000, 0 -1px 0 #000;
            }
            .header {
                text-align: center;
                border-bottom: 3px solid #000;
                padding-bottom: 6px;
                margin-bottom: 10px;
            }
            .header h1 {
                font-size: 22px;
                font-weight: 900;
                margin: 3px 0;
                text-transform: uppercase;
                letter-spacing: 0.6px;
                -webkit-text-stroke: 1px #000;
                text-shadow: 1px 0 0 #000, -1px 0 0 #000;
            }
            .header h2 {
                font-size: 17px;
                font-weight: 900;
                margin: 2px 0;
                text-decoration: underline;
                -webkit-text-stroke: 0.9px #000;
                text-shadow: 0.8px 0 0 #000, -0.8px 0 0 #000;
            }
            .section {
                margin: 8px 0;
                border-bottom: 1px dashed #000;
                padding-bottom: 6px;
            }
            .section-title {
                font-weight: 900;
                font-size: 16px;
                margin-bottom: 6px;
                text-decoration: underline;
                -webkit-text-stroke: 0.7px #000;
                text-shadow: 0.6px 0 0 #000, -0.6px 0 0 #000;
            }
            .field {
                margin: 3px 0;
                font-size: 14px;
            }
            .field-label {
                font-weight: 900;
                -webkit-text-stroke: 0.6px #000;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 6px 0;
                font-size: 14px;
            }
            table th {
                border-bottom: 1px solid #000;
                text-align: left;
                padding: 5px 2px;
                font-weight: 900;
                -webkit-text-stroke: 0.6px #000;
            }
            table td {
                padding: 5px 2px;
                border-bottom: 1px dotted #666;
                font-size: 14px;
                font-weight: 700;
            }
            .qty {
                text-align: right;
                font-weight: 900;
                -webkit-text-stroke: 0.6px #000;
            }
            .checklist {
                margin: 8px 0;
            }
            .checklist-item {
                margin: 4px 0;
                font-size: 13px;
            }
            .firma {
                margin-top: 12px;
                border-top: 2px solid #000;
                padding-top: 10px;
                font-size: 13px;
            }
            .footer {
                text-align: center;
                font-size: 11px;
                margin-top: 8px;
                color: #000;
            }
            .highlight {
                padding: 5px;
                margin: 5px 0;
                font-weight: 800;
                background: transparent;
                border: 1px solid #000;
            }
            .no-print {
                text-align: center;
                margin: 8px 0;
            }
            .btn {
                padding: 8px 14px;
                margin: 5px;
                font-size: 14px;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button class="btn" onclick="window.print()">🖨️ Imprimir</button>
            <button class="btn" onclick="window.close()">✖️ Cerrar</button>
        </div>
        
        <div class="header">
            <h1>ORDEN DE PRODUCCIÓN</h1>
            <h2>OP #<?= str_pad($po_id, 6, '0', STR_PAD_LEFT) ?></h2>
            <h2><?= e($titulo_estado[$estado] ?? $estado) ?></h2>
        </div>
        
        <div class="section">
            <div class="field">
                <span class="field-label">Cliente:</span> <?= e($datos['cliente_nombre'] ?: 'N/A') ?>
            </div>
            <div class="field">
                <span class="field-label">Pedido:</span> #<?= $datos['order_id'] ?: 'N/A' ?>
            </div>
        </div>
        
        <div class="section">
            <div class="field">
                <span class="field-label">Producto:</span> <?= e($datos['producto_nombre']) ?>
            </div>
            <div class="field">
                <span class="field-label">Código:</span> <?= e($datos['codigo']) ?>
            </div>
            <div class="field">
                <span class="field-label">Cantidad:</span> <strong><?= (int)$cantidad_op ?> unidades</strong>
            </div>
        </div>
        
        <?php if ($datos['color_personalizado'] || $datos['tapizado_personalizado']): ?>
        <div class="section">
            <div class="section-title">PERSONALIZACIÓN</div>
            <?php if ($datos['color_personalizado']): ?>
                <div class="highlight">🎨 COLOR: <?= strtoupper(e($datos['color_personalizado'])) ?></div>
            <?php endif; ?>
            <?php if ($datos['tapizado_personalizado']): ?>
                <div class="highlight">🪑 TAPIZADO: <?= strtoupper(e($datos['tapizado_personalizado'])) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($componentes_filtrados)): ?>
        <div class="section">
            <div class="section-title">LISTA DE MATERIALES</div>
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Material</th>
                        <th class="qty">Cant.</th>
                        <th class="qty">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($componentes_filtrados as $comp): ?>
                        <?php
                        $cant_unit = (float)$comp['cant_por_unidad'];
                        $total_necesario = $cant_unit * $cantidad_op;
                        ?>
                        <tr>
                            <td><?= e($comp['codigo']) ?></td>
                            <td><?= e(substr($comp['nombre'], 0, 20)) ?></td>
                            <td class="qty"><?= number_format($cant_unit, 2) ?></td>
                            <td class="qty"><strong><?= number_format($total_necesario, 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php
        // Instrucciones específicas por estado
        $instrucciones = [
            'SELECCION' => [
                '✓ Verificar disponibilidad de todos los materiales',
                '✓ Separar materiales en zona de preparación',
                '✓ Confirmar medidas y cantidades contra BOM',
                '✓ Reportar faltantes al supervisor'
            ],
            'CORTE' => [
                '✓ Verificar medidas en planos',
                '✓ Marcar materiales antes de cortar',
                '✓ Usar equipo de protección personal',
                '✓ Verificar ángulos de corte',
                '✓ Etiquetar piezas cortadas'
            ],
            'ARMADO' => [
                '✓ Verificar secuencia de armado',
                '✓ Usar plantillas de posicionamiento',
                '✓ Verificar escuadras y niveles',
                '✓ Punto de soldadura temporal si es necesario',
                '✓ Solicitar QC antes de continuar'
            ],
            'SOLDADURA' => [
                '✓ Verificar puntos de soldadura en plano',
                '✓ Limpiar superficies antes de soldar',
                '✓ Usar parámetros correctos de soldadura',
                '✓ Verificar penetración de soldadura',
                '✓ Dejar enfriar adecuadamente'
            ],
            'LIMPIEZA' => [
                '✓ Remover escoria de soldadura',
                '✓ Lijar superficies a pintar',
                '✓ Desengrasado completo',
                '✓ Aplicar desoxidante si corresponde',
                '✓ Secar completamente'
            ],
            'PINTURA' => [
                '✓ Verificar color especificado',
                '✓ Aplicar imprimación si corresponde',
                '✓ Respetar tiempos de secado entre capas',
                '✓ Aplicar número de capas indicado',
                '✓ Verificar acabado sin defectos'
            ],
            'ENSAMBLE' => [
                '✓ Verificar componentes contra lista',
                '✓ Usar torque especificado en tornillería',
                '✓ Verificar funcionamiento de partes móviles',
                '✓ Aplicar lubricantes si corresponde',
                '✓ Instalar tapizado según especificación'
            ],
            'QC_FINAL' => [
                '✓ Inspección visual completa',
                '✓ Verificar dimensiones finales',
                '✓ Verificar funcionamiento de mecanismos',
                '✓ Verificar acabado de pintura',
                '✓ Verificar firmeza de ensambles',
                '✓ Fotografiar producto terminado'
            ],
            'DESPACHO' => [
                '✓ Limpieza final del producto',
                '✓ Protección de superficies pintadas',
                '✓ Embalaje según especificación',
                '✓ Etiquetar con datos de cliente',
                '✓ Preparar documentación de entrega'
            ]
        ];
        ?>
        
        <?php if (isset($instrucciones[$estado])): ?>
        <div class="section">
            <div class="section-title">INSTRUCCIONES</div>
            <div class="checklist">
                <?php foreach ($instrucciones[$estado] as $instruccion): ?>
                    <div class="checklist-item">☐ <?= e($instruccion) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="firma">
            <div class="field">Operario: _____________________________</div>
            <div class="field">Fecha: ____/____/______ Hora: ____:____</div>
            <div class="field">Firma: _________________________________</div>
        </div>
        
        <div class="footer">
            Impreso: <?= date('d/m/Y H:i:s') ?>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Generar contenido del ticket de producción en formato ESC/POS
 */
function generar_ticket_escpos(int $po_id): string {
    $datos = obtener_datos_ticket($po_id);
    if (!$datos) {
        throw new Exception("Orden de producción no encontrada");
    }
    
    // Generar o recuperar QR token
    $qr_token = $datos['qr_code'] ?: generar_qr_token($po_id);
    $url_qr = url("op.php?qr={$qr_token}");
    
    // Registrar impresión
    $user_id = $_SESSION['user']['id'] ?? null;
    db()->prepare("INSERT INTO production_tickets (production_order_id, qr_token, url_qr, impreso_por, fecha_impresion)
                   VALUES (?, ?, ?, ?, NOW())")
        ->execute([$po_id, $qr_token, $url_qr, $user_id]);
    
    db()->prepare("UPDATE production_orders SET ticket_impreso = 1 WHERE id = ?")
        ->execute([$po_id]);
    
    // Comandos ESC/POS
    $ESC = chr(27);
    $GS = chr(29);
    $LF = chr(10);
    $ticket = "";
    
    // Inicializar impresora
    $ticket .= $ESC . "@"; // Reset
    
    // HEADER - Centrado y en negrita
    $ticket .= $ESC . "a" . chr(1); // Centrar
    $ticket .= $ESC . "!" . chr(0x38); // Negrita + Doble altura
    $ticket .= "ORDEN DE PRODUCCION" . $LF;
    $ticket .= $ESC . "!" . chr(0x00); // Reset fuente
    $ticket .= str_repeat("=", 48) . $LF;
    
    // ID de Orden (Bold)
    $ticket .= $ESC . "!" . chr(0x08); // Negrita
    $ticket .= "OP #" . str_pad($po_id, 6, '0', STR_PAD_LEFT) . $LF;
    $ticket .= $ESC . "!" . chr(0x00); // Reset
    $ticket .= $LF;
    
    // Cliente y Pedido
    $ticket .= $ESC . "a" . chr(0); // Alinear izquierda
    $ticket .= "Cliente: " . mb_strtoupper(substr($datos['cliente_nombre'] ?: 'N/A', 0, 30)) . $LF;
    $ticket .= "Pedido: #" . ($datos['order_id'] ?: 'N/A') . $LF;
    $ticket .= $LF;
    
    // Producto
    $ticket .= $ESC . "!" . chr(0x10); // Ancho doble
    $ticket .= wordwrap($datos['producto_nombre'], 24, $LF) . $LF;
    $ticket .= $ESC . "!" . chr(0x00); // Reset
    $ticket .= "Codigo: " . $datos['codigo'] . $LF;
    $ticket .= "Cantidad: " . (int)$datos['cantidad'] . " unidades" . $LF;
    $ticket .= str_repeat("-", 48) . $LF;
    
    // Personalización
    if ($datos['color_personalizado'] || $datos['tapizado_personalizado']) {
        $ticket .= $ESC . "!" . chr(0x08); // Negrita
        $ticket .= "PERSONALIZACION:" . $LF;
        $ticket .= $ESC . "!" . chr(0x00);
        
        if ($datos['color_personalizado']) {
            $ticket .= " Color: " . strtoupper($datos['color_personalizado']) . $LF;
        }
        if ($datos['tapizado_personalizado']) {
            $ticket .= " Tapizado: " . strtoupper($datos['tapizado_personalizado']) . $LF;
        }
        $ticket .= str_repeat("-", 48) . $LF;
    }
    
    // QR Code (si la impresora soporta QR ESC/POS)
    $ticket .= $ESC . "a" . chr(1); // Centrar
    $ticket .= $LF;
    
    // Comando QR (modelo 2, tamaño 6, corrección nivel M)
    $qr_data = $url_qr;
    $qr_len = strlen($qr_data);
    $pl = $qr_len % 256;
    $ph = floor($qr_len / 256);
    
    $ticket .= $GS . "(k" . chr(4) . chr(0) . chr(49) . chr(65) . chr(50) . chr(0); // Modelo 2
    $ticket .= $GS . "(k" . chr(3) . chr(0) . chr(49) . chr(67) . chr(6); // Tamaño 6
    $ticket .= $GS . "(k" . chr(3) . chr(0) . chr(49) . chr(69) . chr(48); // Corrección M
    $ticket .= $GS . "(k" . chr($pl + 3) . chr($ph) . chr(49) . chr(80) . chr(48) . $qr_data; // Datos
    $ticket .= $GS . "(k" . chr(3) . chr(0) . chr(49) . chr(81) . chr(48); // Imprimir
    
    $ticket .= $LF . $LF;
    $ticket .= "Escanea para cambiar estado" . $LF;
    $ticket .= $LF;
    
    // Checklist de estados
    $ticket .= $ESC . "a" . chr(0); // Izquierda
    $ticket .= str_repeat("=", 48) . $LF;
    $ticket .= $ESC . "!" . chr(0x08); // Negrita
    $ticket .= "CHECKLIST DE PRODUCCION" . $LF;
    $ticket .= $ESC . "!" . chr(0x00);
    $ticket .= str_repeat("-", 48) . $LF;
    
    $estados_checklist = [
        'SELECCION' => 'Seleccion de materiales',
        'CORTE' => 'Corte de perfiles y chapas',
        'ARMADO' => 'Armado de estructura',
        'SOLDADURA' => 'Soldadura y refuerzos',
        'LIMPIEZA' => 'Limpieza y preparacion',
        'PINTURA' => 'Pintura y acabados',
        'ENSAMBLE' => 'Ensamble de componentes',
        'QC_FINAL' => 'Control de calidad',
        'DESPACHO' => 'Despacho y embalaje'
    ];
    
    foreach ($estados_checklist as $estado => $desc) {
        $ticket .= "[ ] " . $desc . $LF;
    }
    
    $ticket .= str_repeat("-", 48) . $LF;
    
    // Firma
    $ticket .= $LF . $LF;
    $ticket .= "Operario: ___________________" . $LF;
    $ticket .= "Fecha: ___/___/___" . $LF;
    $ticket .= "Firma: ______________________" . $LF;
    $ticket .= $LF;
    
    // Footer
    $ticket .= $ESC . "a" . chr(1); // Centrar
    $ticket .= str_repeat("=", 48) . $LF;
    $ticket .= "Impreso: " . date('d/m/Y H:i') . $LF;
    $ticket .= $LF . $LF . $LF;
    
    // Cortar papel
    $ticket .= $GS . "V" . chr(66) . chr(0); // Corte parcial
    
    return $ticket;
}

/**
 * Generar ticket en formato HTML (para previsualización o impresión desde navegador)
 */
function generar_ticket_html(int $po_id): string {
    $datos = obtener_datos_ticket($po_id);
    if (!$datos) {
        throw new Exception("Orden de producción no encontrada");
    }
    
    $qr_token = $datos['qr_code'] ?: generar_qr_token($po_id);
    $url_qr = url("op.php?qr={$qr_token}");
    
    // Generar QR con API externa (Google Charts o similar)
    $qr_image_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($url_qr);
    
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ticket Producción OP #{$po_id}</title>
    <style>
        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { margin: 0; }
            .no-print { display: none; }
        }
        body {
            font-family: 'Courier New', monospace;
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
            font-size: 12px;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .big { font-size: 18px; }
        .checklist { list-style: none; padding: 0; }
        .checklist li { margin: 8px 0; }
        .checklist li:before { content: '☐ '; font-size: 16px; }
        hr { border: 1px dashed #000; }
        .firma { margin-top: 30px; border-top: 1px solid #000; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="center bold big">ORDEN DE PRODUCCION</div>
    <hr>
    <div class="center bold">OP #{$po_id}</div>
    <br>
    <div><strong>Cliente:</strong> {$datos['cliente_nombre']}</div>
    <div><strong>Pedido:</strong> #{$datos['order_id']}</div>
    <br>
    <div class="bold big">{$datos['producto_nombre']}</div>
    <div><strong>Código:</strong> {$datos['codigo']}</div>
    <div><strong>Cantidad:</strong> {$datos['cantidad']} unidades</div>
    <hr>
HTML;

    if ($datos['color_personalizado'] || $datos['tapizado_personalizado']) {
        $html .= "<div class='bold'>PERSONALIZACIÓN:</div>";
        if ($datos['color_personalizado']) {
            $html .= "<div>• Color: " . strtoupper($datos['color_personalizado']) . "</div>";
        }
        if ($datos['tapizado_personalizado']) {
            $html .= "<div>• Tapizado: " . strtoupper($datos['tapizado_personalizado']) . "</div>";
        }
        $html .= "<hr>";
    }
    
    $html .= <<<HTML
    <div class="center">
        <img src="{$qr_image_url}" alt="QR Code" style="width:150px;height:150px;">
        <div><small>Escanea para cambiar estado</small></div>
    </div>
    <hr>
    <div class="bold">CHECKLIST DE PRODUCCIÓN:</div>
    <ul class="checklist">
        <li>Selección de materiales</li>
        <li>Corte de perfiles y chapas</li>
        <li>Armado de estructura</li>
        <li>Soldadura y refuerzos</li>
        <li>Limpieza y preparación</li>
        <li>Pintura y acabados</li>
        <li>Ensamble de componentes</li>
        <li>Control de calidad</li>
        <li>Despacho y embalaje</li>
    </ul>
    <hr>
    <div class="firma">
        <div>Operario: _________________________</div>
        <div>Fecha: ___/___/______</div>
        <div>Firma: _________________________</div>
    </div>
    <br>
    <div class="center"><small>Impreso: " . date('d/m/Y H:i') . "</small></div>
    <br><br>
    <div class="center no-print">
        <button onclick="window.print()">Imprimir</button>
        <a href="/uf_gestion/public/op.php?download_escpos=1&po_id={$po_id}" class="btn">Descargar ESC/POS (.prn)</a>
        <button onclick="window.close()">Cerrar</button>
    </div>
</body>
</html>
HTML;
    
    // Fecha ya embebida directamente en el HTML para evitar variable no definida
    
    return $html;
}

/**
 * Enviar ticket a impresora térmica (vía endpoint o archivo)
 */
function imprimir_ticket_produccion(int $po_id, string $metodo = 'html'): string {
    if ($metodo === 'escpos') {
        // Generar contenido ESC/POS
        $ticket_data = generar_ticket_escpos($po_id);
        
        // Opción 1: Guardar en archivo para impresión directa
        $filename = "/tmp/ticket_po_{$po_id}_" . time() . ".prn";
        file_put_contents($filename, $ticket_data);
        
        // Opción 2: Enviar a impresora directamente (en Linux)
        // exec("cat {$filename} > /dev/usb/lp0");
        
        return $filename;
        
    } else {
        // Método HTML (default)
        return generar_ticket_html($po_id);
    }
}
