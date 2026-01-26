    -- ==========================================
    -- INSERTS: DATOS EN DEV QUE NO ESTAN EN PROD
    -- ==========================================

    INSERT INTO `a0011086_erp_mvp`.customers (`id`, `nombre`, `gym`, `cuit_dni`, `telefono`, `email`, `direccion`, `condicion_iva`, `limite_credito`, `notas`, `activo`, `created_at`) VALUES
    (21, 'SANTIAGO JULIAN RAMIRO', 'Zona Fit Gym', '20-31907435-0', '2392567250', NULL, 'Hipólito Yrigoyen 969, Tres Lomas', NULL, 0.00, 'Fecha compra: 2024-06-06; Color tapizado: Olímpicos; Color estructura: Toro gris / Maquina gris; Máquinas: 1 POLEA DOBLE V REG; 1 SILLÓN CUÁDRICEPS; 1 CAMILLA FEMORAL; Problema: cable', 0, '2026-01-25 13:09:22'),
    (22, 'JAVIER GUSTAVO RUFFET', '—', '20-21454112-3', '1136160006', NULL, 'Angel Marcelo T de Alvear 2272, Don Torcuato, Partido Tigre', NULL, 0.00, 'Fecha compra: 2024-06-07; Observación: 18/09/25; Color tapizado: Estándar; Color estructura: Negro brillante / Móvil rojo; Máquinas: 1 REMO T; 1 ABDUCTOR DE PIE; 1 BELT SQUAT; 1 ABDUCTOR ARTICULADO PRO; Problema: no', 1, '2026-01-25 13:09:22'),
    (23, 'Alfredo Omar Fernandez', 'Yago Gym', '23-14275741-9', '2213646566', NULL, '134 esquina 66 nro 1949, La Plata (Los Hornos)', NULL, 0.00, 'Fecha compra: 2024-06-18; Observación: 23/09/25; Color tapizado: Olímpicos; Color estructura: Negro brillante / negro; Máquinas: 1 POLEA ALTA; 1 HIP THRUST; Problema: no', 1, '2026-01-25 13:09:22'),
    (24, 'Susana Carneli', 'Aereo Gym', '27-18.107.650-5', '26146990633', NULL, 'Manuel Estrada 243, Maipú, Mendoza', NULL, 0.00, 'Fecha compra: 2024-06-24; Color tapizado: Gris humo; Color estructura: Verde telefónica; Máquinas: 1 banco remo; Problema: Demora en plazo de entrega', 0, '2026-01-25 13:09:22'),
    (25, 'Daniela Goglia', NULL, '27-34650458-2', NULL, NULL, 'Josefina B de Marquez 3893, Morón', NULL, 0.00, 'Fecha compra: 2024-06-24; Color tapizado: Negro; Color estructura: azul; Máquinas: (no listado)', 1, '2026-01-25 13:09:22'),
    (26, 'Marcantoni Hernán', NULL, NULL, '2317449576', NULL, 'Compayre 656, Nueve de Julio, Buenos Aires', NULL, 0.00, 'Fecha compra: 2024-06-27; Color tapizado: Negro mate; Color estructura: amarillo; Máquinas: 1 banco remo; 1 set de accesorios; Problema: no', 1, '2026-01-25 13:09:22'),
    (27, 'Martin Alberto Villalba', NULL, '20-31611712-1', '2914198865', NULL, 'Pilmaiquen 1421, Bahía Blanca', NULL, 0.00, 'Fecha compra: 2024-07-17; Color tapizado: Gris humo; Color estructura: Rojo tapizado; Máquinas: 1 torre de polea; Problema: no', 0, '2026-01-25 13:09:22'),
    (28, 'Eduardo Condori', 'Acuarius gym', '20-27726755-2', '3884074873', NULL, 'Dorrego 134, El Carmen, Jujuy', NULL, 0.00, 'Fecha compra: 2024-07-17; Color tapizado: olímpico; Color estructura: Negro mate / rojo; Máquinas: 1 femoral parado; Problema: Demora de entrega', 0, '2026-01-25 13:09:22'),
    (29, 'Alejandro Ferrini', 'Endorfinas gym', NULL, '2474666693', NULL, 'Pueblos originarios 254, Rojas, Provincia de Buenos Aires', NULL, 0.00, 'Fecha compra: 2024-07-17; Color tapizado: Estándar; Color estructura: Gris / Negro toro; Máquinas: 1 HIPEREXTENSION INV.; Problema: no', 0, '2026-01-25 13:09:22'),
    (30, 'Ricardo Melgarejo', NULL, '20-33399492-6', '27319304', NULL, 'Aeronautica Argentina 1303, Moreno (Bs As)', NULL, 0.00, 'Fecha compra: 2024-07-25; Color tapizado: Gris humo; Color estructura: Gris plata toro; Máquinas: 1 PANTORRILLA DE PIE', 0, '2026-01-25 13:09:22'),
    (31, 'Pablo Carlos Silvestri', 'The Crab Gym', '20-28801951-8', NULL, NULL, '14 de Julio 1240, Mar del Plata', NULL, 0.00, 'Fecha compra: 2024-11-12; Color tapizado: olimpico / Negro mate; Color estructura: Gris; Máquinas: 1 ELEVACIÓN DE PELVIS; 1 HACK INVERTIDA; Problema: no', 1, '2026-01-25 13:09:22'),
    (32, 'Retamero Dario', 'Bunker Gym', NULL, NULL, NULL, 'Calle Buenos Aires / J.A.Roca y Pueyrredón, Ituzaingó, Corrientes', NULL, 0.00, 'Fecha compra: 2024-11-14; Máquinas: 1 ADUCTOR/ABDUCTOR; 1 APERTURA POSTERIOR; 1 POLEA ENFRENTADA REG.; 1 SYSSY; Problema: Pintura / sissy', 0, '2026-01-25 13:09:22'),
    (33, 'Marcos Damián Cossi', 'Mark Gym', '23-33286272-3', NULL, NULL, 'San Fernando, Buenos Aires', NULL, 0.00, 'Fecha compra: 2024-10-11; Máquinas: 1 BELT SQUAT; 1 POLEA ENFRENTADA REGULABLE; 1 VUELO LATERAL DE PIE; Problemas: Pintura / Golpeadas / Cableado / demora', 0, '2026-01-25 13:09:22'),
    (34, 'Monica Fernandez', NULL, NULL, NULL, NULL, 'Cliente Pao', NULL, 0.00, 'Notas: Cliente Pao', 1, '2026-01-25 13:09:22'),
    (35, 'Mario Gonzalo Pachao', NULL, NULL, NULL, NULL, 'Cliente Pao', NULL, 0.00, 'Notas: Cliente Pao', 1, '2026-01-25 13:09:22'),
    (36, 'Yauck Ulises', 'Arena Gym', '20267869821', NULL, NULL, 'Moreno 3700, Buenos Aires', NULL, 0.00, 'Fecha compra: 2024-08-06; Color tapizado: Turquesa; Color estructura: Gris humo; Máquinas: 2 POLEA ALTA; 2 POLEA ENFRENTADA SIMPLE; 1 POLEA ENFRENTADA; 1 SILLÓN DE CUADRICEPS; 1 CAMILLA FEMORAL; 1 VUELO LATERAL DE PIE; 1 HIP THRUST DE PIE; 1 ABDUCTOR ARTICULADO; 1 REMO T; 1 FEMORAL DE PIE; 1 PANTORRILLA DE PIE; 1 BANCO LUMBAR; 1 MANCUERNERO X 2 PISOS; 1 BANCO REMO; Problemas: Tiempo de demora / pintura', 1, '2026-01-25 13:09:22'),
    (37, 'Carlos Calderon', NULL, NULL, NULL, NULL, 'Namuncura 187 / Bolivar 6550', NULL, 0.00, 'Fecha compra: (08 de enero); Máquinas: 1 BANCO ARTICULADO; 1 POLEA ENFRENTADA REG; Problema: no', 0, '2026-01-25 13:09:22'),
    (38, 'Fabián Osvaldo Alcántara', 'Evolución Fitness', '20-29378261-0', '3755648820', NULL, 'Juan B. Alberdi 866, Obera Misiones 3360', NULL, 0.00, 'Fecha compra: 2025-01-16; Color tapizado: Negro mate; Color estructura: gris; Máquinas: 1 ABDUCTOR ARTICULADO; 1 HIP THRUST DE PIE; 1 HACK CIRCULAR; Problema: no', 1, '2026-01-25 13:09:22'),
    (39, 'Gonzalo Duran', 'Universal Fitness', '2018802534', '3875120019', 'gonzalo@universal.com.ar', 'PUEYRREDON 2070', 'Consumidor Final', 0.00, '', 1, '2026-01-25 14:51:13');

    INSERT INTO `a0011086_erp_mvp`.orders (`id`, `customer_id`, `fecha`, `fecha_entrega`, `estado`, `total_bruto`, `descuento`, `total_neto`, `senia`, `saldo`, `observaciones`) VALUES
    (21, 1, '2026-01-25 15:48:23', NULL, 'EN_PRODUCCION', 0.00, 0.00, 0.00, 0.00, 0.00, 'Color negro con bordado rojo'),
    (23, 20, '2025-12-25 15:58:16', NULL, 'ENTREGADO', 3750000.00, 0.00, 3750000.00, 1875000.00, 0.00, 'Bonificado Transporte.\r\nChasis negro, moviles rojo, tapizado negro con bordado rojo\r\nTransfiere a cuenta Acerlot $937500\r\nTransfiere a cuenta Tomas Duran $937500');

    INSERT INTO `a0011086_erp_mvp`.order_items (`id`, `order_id`, `product_id`, `cant`, `precio_unit`, `subtotal`) VALUES
    (10, 21, 27, 1.000, 0.00, 0.00),
    (11, 21, 28, 1.000, 0.00, 0.00),
    (12, 21, 14, 1.000, 0.00, 0.00),
    (13, 21, 54, 1.000, 0.00, 0.00),
    (14, 21, 31, 1.000, 0.00, 0.00),
    (15, 23, 41, 1.000, 1200000.00, 1200000.00),
    (16, 23, 22, 1.000, 950000.00, 950005.75),
    (17, 23, 47, 1.7588235294117647e+6, 1e+6, 999999.88),
    (18, 23, 27, -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 * -4e-6 *, NULL);    

    INSERT INTO `a0011086_erp_mvp`.payments (`id`, `customer_id`, `order_id`, `fecha`, `medio`, `importe`, `referencia`) VALUES
    (3, 20, 23, '2026-01-25 15:58:16', 'TRANSFER', 1875000.00, 'Seña'),
    (4, 20, 23, '2026-01-25 16:03:20', 'TRANSFER', 1000000.00, 'Transferencia a cuenta ACERLOT'),
    (5, 20, 23, '2026-01-25 16:03:35', 'TRANSFER', 875000.00, 'Transferencia a cuenta Gomacar');

    INSERT INTO `a0011086_erp_mvp`.production_orders (`id`, `order_id`, `product_pt_id`, `cantidad`, `estado`, `fecha_ini`, `fecha_fin`, `observaciones`) VALUES
    (1, 21, 27, 1.000, 'PENDIENTE', NULL, NULL, NULL),
    (2, 21, 28, 1.000, 'PENDIENTE', NULL, NULL, NULL),
    (3, 21, 14, 1.000, 'PENDIENTE', NULL, NULL, NULL),
    (4, 21, 54, 1.000, 'PENDIENTE', NULL, NULL, NULL),
    (5, 21, 31, 1.000, 'PENDIENTE', NULL, NULL, NULL),
    (6, 23, 41, 1.000, 'PENDIENTE', NULL, NULL, NULL),
    (7, 23, 22, 1.000, 'PENDIENTE', NULL, NULL, NULL),
    (8, 23, 47, 1.000, 'PENDIENTE', NULL, NULL, NULL),
    (9, 23, 27, 1.000, 'FINALIZADA', '2026-01-25 16:01:11', '2026-01-25 16:01:46', NULL);

    INSERT INTO `a0011086_erp_mvp`.stock_moves (`id`, `fecha`, `tipo`, `motivo`, `product_id`, `cantidad`, `referencia_tipo`, `referencia_id`, `observaciones`) VALUES
    (1, '2026-01-25 16:01:11', 'SALIDA', 'PROD_CONSUMO', 91, 1.000, 'OP', 9, 'Consumo OP'),
    (2, '2026-01-25 16:01:46', 'ENTRADA', 'PROD_ALTA', 27, 1.000, 'OP', 9, 'Alta PT de OP'),
    (3, '2026-01-25 16:06:47', 'SALIDA', 'ENTREGA_CLIENTE', 20, 1.000, 'ORDER', 3, 'Entrega a cliente desde OP #9. Usuario: sysadmin (id: 2)'),
    (4, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 158, 18.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
    (5, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 159, 60.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
    (6, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 160, 60.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
    (7, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 161, 30.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
    (8, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 162, 18.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
    (9, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 163, 60.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
    (10, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 164, 300.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
    (11, '2026-01-25 17:09:41', 'ENTRADA', 'COMPRA', 165, 10.000, 'PURCHASE', 1, 'Compra FACTURA A-0010-0034329'),
    (19, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 173, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
    (20, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 174, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
    (21, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 175, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
    (22, '2026-01-25 17:15:40', 'ENTRADA', 'COMPRA', 176, 100.000, 'PURCHASE', 2, 'Compra REMITO X-00000001'),
    (23, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 177, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
    (24, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 178, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
    (25, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 179, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1<231'),
    (26, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 180, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
    (27, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 181, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
    (28, '2026-01-25 17:21:09', 'ENTRADA', 'COMPRA', 182, 100.000, 'PURCHASE', 3, 'Compra FACTURA X-1231'),
    (29, '2026-01-25 17:24:03', 'ENTRADA', 'COMPRA', 183, 100.000, 'PURCHASE', 4, 'Compra FACTURA X-1231'),
    (30, '2026-01-25 17:29:00', 'ENTRADA', 'COMPRA', 184, 100.000, 'PURCHASE', 5, 'Compra FACTURA S-1231');

   
    INSERT INTO `a0011086_erp_mvp`.purchase_items (`id`, `purchase_id`, `product_id`, `codigo`, `nombre`, `unidad`, `cantidad`, `costo_unit`, `subtotal`, `notas`) VALUES
    (13, 2, 170, 'POL115', 'POLEA 115', 'UN', 100.000, 4665.0000, 466500.00, ''),
    (14, 2, 171, 'POL90', 'POLEA 90', 'UN', 100.000, 4147.0000, 414700.00, ''),
    (15, 2, 172, 'BUJREDCUA', 'BUJE REDUCTOR CUADRADO CORTO', 'UN', 100.000, 555.0000, 55500.00, ''),
    (16, 2, 173, 'BUJREDCUALARG', 'BUJE REDUCTOR CUADRADO LARGO', 'UN', 100.000, 1159.0000, 115900.00, ''),
    (17, 2, 174, 'REGSTAN', 'REGISTRO ESTANDAR', 'UN', 100.000, 8326.0000, 832600.00, ''),
    (18, 2, 175, 'REGEXTRALARGO', 'REGISTRO EXTRA LARGO', 'UN', 100.000, 9037.0000, 903700.00, ''),
    (19, 2, 176, 'PUÑ25', 'PUÑOS 25 X 135MM', 'UN', 100.000, 1335.0000, 133500.00, ''),
    (20, 3, 177, 'PINTBANCOPLANO', 'PINTURA BANCO PLANO', 'UN', 100.000, 80000.0000, 8000000.00, ''),
    (21, 3, 178, 'CAB5MM', 'CABLE 5MM', 'MT', 100.000, 2490.0000, 249000.00, ''),
    (22, 3, 179, 'LINGOTE', 'LINGOTE', 'UN', 100.000, 40000.0000, 4000000.00, ''),
    (23, 3, 180, 'TAP-ASIENTO', 'TAPIZADO ASIENTO', 'UN', 100.000, 40000.0000, 4000000.00, ''),
    (24, 3, 181, 'TAP-RESPA', 'TAPIZADO RESPALDO', 'UN', 100.000, 60000.0000, 6000000.00, ''),
    (25, 3, 182, 'TAP-CAB', 'TAPIZADO CABEZAL', 'UN', 100.000, 30000.0000, 3000000.00, ''),
    (26, 4, 183, 'TAP-RODILLO180', 'TAPIZADO RODILLO 180MM', 'UN', 100.000, 10000.0000, 1000000.00, ''),
    (27, 5, 184, 'PINT-BANCO2', 'PINTURA BANCO CUADRICEP', 'UN', 100.000, 150000.0000, 15000000.00, '');

    INSERT INTO `a0011086_erp_mvp`.customer_ledger (`id`, `customer_id`, `fecha`, `tipo`, `origen`, `referencia_id`, `detalle`, `monto`, `saldo_resultante`) VALUES
    (4, 1, '2026-01-25 15:48:23', 'CARGO', 'VENTA', 2, 'Venta pedido #2', 0.00, 0.00),
    (5, 20, '2026-01-25 15:58:16', 'CARGO', 'VENTA', 3, 'Venta pedido #3', 3750000.00, 3750000.00),
    (6, 20, '2026-01-25 15:58:16', 'ABONO', 'PAGO', 3, 'Seña pedido #3', 1875000.00, 1875000.00),
    (7, 20, '2026-01-25 16:03:20', 'ABONO', 'PAGO', 3, 'Pago registrado en caja', 1000000.00, 875000.00),
    (8, 20, '2026-01-25 16:03:35', 'ABONO', 'PAGO', 3, 'Pago registrado en caja', 875000.00, 0.00);

    INSERT INTO `a0011086_erp_mvp`.audit_logs (`id`, `user_id`, `fecha`, `accion`, `entidad`, `entidad_id`, `detalle`) VALUES
    (1, 2, '2026-01-25 16:06:47', 'ENTREGA_OP', 'production_orders', 9, '{\"op_id\":9,\"order_id\":3,\"product_pt_id\":20,\"cantidad\":1,\"usuario_id\":2,\"usuario_nombre\":\"sysadmin\",\"obs\":\"Entrega a cliente desde OP #9. Usuario: sysadmin (id: 2)\"}');

    INSERT INTO `a0011086_erp_mvp`.cash_expenses (`id`, `fecha`, `categoria`, `descripcion`, `medio`, `importe`, `created_by`, `created_at`) VALUES
    (1, '2025-12-03 10:23:00', 'SUELDOS', '', 'EFECTIVO', 1.00, 1, '2025-12-03 09:23:14'),
    (2, '2025-12-03 10:23:00', 'OTROS', 'ajuste caja', 'EFECTIVO', 0.30, 1, '2025-12-03 09:23:27');
