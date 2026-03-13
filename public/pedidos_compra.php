<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/compra.php';

// Mostrar errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$user = user();

// Manejo de formulario de alta de pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_pedido'])) {
    $proveedor_id = (int)($_POST['proveedor_id'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $monto = (float)($_POST['monto'] ?? 0);
    $usuario_id = (int)$user['id'];
    $mp_ids = $_POST['mp_id'] ?? [];
    $cantidades = $_POST['mp_cantidad'] ?? [];
    $ok = $proveedor_id && is_array($mp_ids) && is_array($cantidades) && count($mp_ids) > 0;
    if ($ok) {
        db()->beginTransaction();
        $st = db()->prepare("INSERT INTO pedidos_compra (proveedor_id, usuario_id, descripcion, monto) VALUES (?, ?, ?, ?)");
        $st->execute([$proveedor_id, $usuario_id, $descripcion, $monto]);
        $pedido_id = db()->lastInsertId();
        $stmp = db()->prepare("INSERT INTO pedidos_compra_items (pedido_id, producto_id, cantidad, unidad) VALUES (?, ?, ?, ?)");
        foreach ($mp_ids as $i => $mp_id) {
            $mp_id = (int)$mp_id;
            $cant = (float)($cantidades[$i] ?? 0);
            if ($mp_id > 0 && $cant > 0) {
                // Buscar unidad
                $unidad = db()->query("SELECT unidad FROM products WHERE id=$mp_id")->fetchColumn();
                $stmp->execute([$pedido_id, $mp_id, $cant, $unidad]);
            }
        }
        db()->commit();
        $flash_ok = "Pedido de compra registrado correctamente.";
    } else {
        $flash_err = "Debe seleccionar un proveedor y al menos un producto.";
    }
}

// Acciones de gestión de pedidos con validaciones extra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_pedido']) && isset($_POST['pedido_id'])) {
    $pedido_id = (int)$_POST['pedido_id'];
    $pedido = db()->query("SELECT * FROM pedidos_compra WHERE id=" . $pedido_id)->fetch();
    if (!$pedido) {
        $flash_err = "Pedido no encontrado.";
    } else {
        if ($_POST['accion_pedido'] === 'aprobar') {
            if ($pedido['estado'] !== 'PENDIENTE') {
                $flash_err = "No se puede aprobar: el pedido ya cambió de estado.";
            } else {
                db()->prepare("UPDATE pedidos_compra SET estado='APROBADO', fecha_aprobacion=NOW() WHERE id=?")->execute([$pedido_id]);
                $flash_ok = "Pedido aprobado.";
            }
        } elseif ($_POST['accion_pedido'] === 'rechazar') {
            if ($pedido['estado'] !== 'PENDIENTE') {
                $flash_err = "No se puede rechazar: el pedido ya cambió de estado.";
            } else {
                db()->prepare("UPDATE pedidos_compra SET estado='RECHAZADO' WHERE id=?")->execute([$pedido_id]);
                $flash_ok = "Pedido rechazado.";
            }
        } elseif ($_POST['accion_pedido'] === 'pagar' && isset($_POST['monto_pago'])) {
            if ($pedido['estado'] !== 'APROBADO') {
                $flash_err = "Solo se puede registrar pago si el pedido está aprobado.";
            } else {
                $observaciones = trim($_POST['observaciones_pago'] ?? '');
                $comprobante = trim($_POST['comprobante_pago'] ?? '');
                $comprobante_archivo = null;
                if (isset($_FILES['comprobante_archivo']) && $_FILES['comprobante_archivo']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['comprobante_archivo']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','pdf'])) {
                        $dir = __DIR__ . '/../storage/comprobantes_pedidos/';
                        if (!is_dir($dir)) mkdir($dir, 0777, true);
                        $nombre_archivo = 'pedido_' . $pedido_id . '_' . time() . '.' . $ext;
                        $destino = $dir . $nombre_archivo;
                        if (move_uploaded_file($_FILES['comprobante_archivo']['tmp_name'], $destino)) {
                            $comprobante_archivo = $nombre_archivo;
                        } else {
                            $flash_err = "Error al guardar el archivo de comprobante.";
                        }
                    } else {
                        $flash_err = "Solo se permiten archivos JPG, PNG o PDF.";
                    }
                }
                if (strlen($comprobante) > 255) {
                    $flash_err = "El comprobante es demasiado largo.";
                } else if (empty($flash_err)) {
                    // Validar que no exista ya un pago para este pedido
                    $ya_pagado = db()->query("SELECT COUNT(*) FROM pedidos_compra WHERE id=$pedido_id AND estado='PAGADO'")->fetchColumn();
                    if ($ya_pagado) {
                        $flash_err = "Este pedido ya tiene un pago registrado.";
                    } else {
                        $monto_pago = (float)$_POST['monto_pago'];
                        try {
                            // Registrar pago en pagos_proveedores
                            db()->prepare("INSERT INTO pagos_proveedores (proveedor_id, fecha, monto, comprobante, observaciones, created_at, archivo) VALUES (?, NOW(), ?, ?, ?, NOW(), ?)")
                                ->execute([$pedido['proveedor_id'], $monto_pago, $comprobante, $observaciones, $comprobante_archivo]);
                            $pago_id = db()->lastInsertId();
                        } catch (Throwable $e) {
                            $flash_err = "Error al registrar pago: " . $e->getMessage();
                            error_log('PEDIDO_PAGO_ERROR: ' . $e->getMessage());
                        }
                        if (empty($flash_err)) {
                            try {
                                db()->prepare("UPDATE pedidos_compra SET estado='PAGADO', fecha_pago=NOW(), comprobante_pago=?, observaciones=?, archivo_comprobante=? WHERE id=?")
                                    ->execute([$comprobante, $observaciones, $comprobante_archivo, $pedido_id]);
                            } catch (Throwable $e) {
                                $flash_err = "Error al actualizar pedido: " . $e->getMessage();
                                error_log('PEDIDO_UPDATE_ERROR: ' . $e->getMessage());
                            }
                        }
                        if (empty($flash_err)) {
                            try {
                                $proveedor_nombre = db()->query("SELECT nombre FROM proveedores WHERE id=" . (int)$pedido['proveedor_id'])->fetchColumn();
                                $compra_id = compra_create([
                                    'proveedor' => $proveedor_nombre,
                                    'fecha' => date('Y-m-d'),
                                    'comp_tipo' => 'OTRO',
                                    'comp_serie' => '',
                                    'comp_numero' => 'PEDIDO-' . $pedido_id,
                                    'total' => $monto_pago,
                                    'moneda' => 'ARS',
                                    'archivo_path' => $comprobante_archivo ? ('storage/comprobantes_pedidos/' . $comprobante_archivo) : null,
                                    'notas' => ($pedido['descripcion'] ? $pedido['descripcion'] : 'Pedido de compra ID ' . $pedido_id),
                                    'created_by' => (int)$user['id'],
                                    'incluye_iva' => 1,
                                    'estado' => 'CONSOLIDADA',
                                    'pago_id' => $pago_id
                                ], true);
                                if (!$compra_id) {
                                    $flash_err = "No se pudo registrar la compra (ID vacío).";
                                    error_log('COMPRA_CREATE_ERROR: compra_id vacío para pedido ' . $pedido_id);
                                }
                            } catch (Throwable $e) {
                                $flash_err = "Error al registrar compra: " . $e->getMessage();
                                error_log('COMPRA_CREATE_ERROR: ' . $e->getMessage());
                            }
                        }
                        if (empty($flash_err)) {
                            try {
                                compra_consolidar($compra_id, $pago_id);
                            } catch (Throwable $e) {
                                $flash_err = "Error al consolidar compra: " . $e->getMessage();
                                error_log('COMPRA_CONSOLIDAR_ERROR: ' . $e->getMessage());
                            }
                        }
                        if (empty($flash_err)) {
                            // Actualizar stock de cada MP comprada
                            $items = db()->prepare("SELECT producto_id, cantidad FROM pedidos_compra_items WHERE pedido_id = ?");
                            $items->execute([$pedido_id]);
                            foreach ($items->fetchAll() as $item) {
                                db()->prepare("UPDATE products SET stock_actual = stock_actual + ? WHERE id = ?")
                                    ->execute([(float)$item['cantidad'], (int)$item['producto_id']]);
                            }
                            $flash_ok = "Pago registrado, compra consolidada y stock actualizado.";
                        }
                    }
                }
            }
        } elseif ($_POST['accion_pedido'] === 'completar') {
            if ($pedido['estado'] !== 'PAGADO') {
                $flash_err = "Solo se puede registrar la compra si el pedido está pagado.";
            } else {
                db()->prepare("UPDATE pedidos_compra SET estado='COMPLETADO', fecha_compra=NOW() WHERE id=?")->execute([$pedido_id]);
                $flash_ok = "Compra registrada.";
            }
        }
    }
}

// Filtros
$f_estado = $_GET['f_estado'] ?? '';
$f_proveedor = $_GET['f_proveedor'] ?? '';
$f_fecha = $_GET['f_fecha'] ?? '';

$where = [];
$params = [];
if ($f_estado !== '' && in_array($f_estado, ['PENDIENTE','APROBADO','PAGADO','COMPLETADO','RECHAZADO'])) {
    $where[] = 'pc.estado = ?';
    $params[] = $f_estado;
}
if ($f_proveedor !== '' && is_numeric($f_proveedor)) {
    $where[] = 'pc.proveedor_id = ?';
    $params[] = $f_proveedor;
}
if ($f_fecha !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $f_fecha)) {
    $where[] = 'DATE(pc.fecha) = ?';
    $params[] = $f_fecha;
}
$sql = "SELECT pc.*, p.nombre AS proveedor, u.nombre AS usuario FROM pedidos_compra pc JOIN proveedores p ON p.id = pc.proveedor_id JOIN users u ON u.id = pc.usuario_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY pc.fecha DESC, pc.id DESC';
$st = db()->prepare($sql);
$st->execute($params);
$pedidos = $st->fetchAll();

// Listado de proveedores para el select
$proveedores = db()->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll();
// Listado de materias primas activas
$materias_primas = db()->query("SELECT id, nombre, unidad, precio_std as precio FROM products WHERE tipo='MP' AND activo=1 ORDER BY nombre")->fetchAll();

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
    <h2>Pedidos de Compra</h2>
    <?php if (!empty($flash_ok)): ?>
        <div class="alert alert-success"><?= e($flash_ok) ?></div>
    <?php endif; ?>
    <?php if (!empty($flash_err)): ?>
        <div class="alert alert-danger"><?= e($flash_err) ?></div>
    <?php endif; ?>



    <div class="card mb-4">
        <div class="card-header">Nuevo Pedido de Compra</div>
        <div class="card-body">
            <form method="post" id="pedidoForm">
                <input type="hidden" name="nuevo_pedido" value="1">
                <div class="mb-3">
                    <label for="proveedor_id" class="form-label">Proveedor</label>
                    <select name="proveedor_id" id="proveedor_id" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($proveedores as $prov): ?>
                            <option value="<?= $prov['id'] ?>"><?= e($prov['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <textarea name="descripcion" id="descripcion" class="form-control"></textarea>
                </div>
                <div class="mb-3">
                    <label for="monto" class="form-label">Monto estimado</label>
                    <input type="number" step="0.01" min="0" name="monto" id="monto" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Materias Primas a comprar</label>
                    <button type="button" class="btn btn-outline-secondary btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#modalMP">Agregar MP</button>
                    <div id="mp-items-list"></div>
                    <div class="mt-2 text-end">
                        <strong>Total pedido: $ <span id="pedido-total">0.00</span></strong>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Registrar Pedido</button>
                <a href="#" class="btn btn-outline-secondary" id="export-pdf-precio">Exportar PDF (con precios)</a>
                <a href="#" class="btn btn-outline-secondary" id="export-pdf-sin-precio">Exportar PDF (sin precios)</a>
            </form>

            <!-- Modal de selección de MP (nuevo diseño) -->
            <div class="modal fade" id="modalMP" tabindex="-1" aria-labelledby="modalMPTitulo" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="modalMPTitulo">Agregar Materia Prima</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body">
                    <input type="text" id="mp-buscar" class="form-control mb-2" placeholder="Buscar materia prima...">
                    <div style="max-height:300px;overflow:auto">
                      <table class="table table-sm table-hover mb-0" id="mp-table">
                        <thead><tr><th>Nombre</th><th>Unidad</th><th>Precio</th></tr></thead>
                        <tbody>
                            <?php foreach ($materias_primas as $mp): ?>
                            <tr class="mp-row" data-id="<?= $mp['id'] ?>" data-nombre="<?= e($mp['nombre']) ?>" data-unidad="<?= e($mp['unidad']) ?>" data-precio="<?= (float)$mp['precio'] ?>">
                                <td><?= e($mp['nombre']) ?></td>
                                <td><?= e($mp['unidad']) ?></td>
                                <td>$ <?= number_format((float)$mp['precio'],2,',','.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <div class="mt-2" id="mp-resultados-info" style="font-size:0.95em;color:#666"></div>
                    <div class="mt-3" id="mp-cantidad-form" style="display:none">
                      <label>Materia prima: <span id="mp-cant-nombre" class="fw-bold"></span></label><br>
                      <label>Cantidad:</label>
                      <input type="number" id="mp-cantidad-input" class="form-control d-inline-block" style="width:120px" min="0.01" step="0.01">
                      <button type="button" class="btn btn-primary btn-sm" id="mp-confirmar-cantidad">Agregar</button>
                      <button type="button" class="btn btn-secondary btn-sm" id="mp-cancelar-cantidad">Cancelar</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
            <script>
            function actualizarTotalPedido() {
                let total = 0;
                document.querySelectorAll('#mp-items-list [data-mp-id]').forEach(function(row) {
                    let precio = parseFloat(row.getAttribute('data-precio')) || 0;
                    let cant = parseFloat(row.getAttribute('data-cantidad')) || 0;
                    total += precio * cant;
                });
                document.getElementById('pedido-total').textContent = total.toFixed(2);
            }
            document.addEventListener('DOMContentLoaded', function() {
                let mpSeleccionada = null;
                // Búsqueda robusta
                function normalize(str) {
                    return (str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
                }
                function actualizarMensajeResultadosMP() {
                    let total = 0;
                    document.querySelectorAll('#mp-table tbody tr').forEach(function(tr) {
                        if (tr.style.display !== 'none') total++;
                    });
                    let info = document.getElementById('mp-resultados-info');
                    if (info) {
                        if (total === 0) {
                            info.textContent = 'No se encontraron resultados.';
                        } else {
                            info.textContent = total + ' resultado' + (total === 1 ? '' : 's') + ' encontrado' + (total === 1 ? '' : 's') + '.';
                        }
                    }
                }
                document.getElementById('mp-buscar').addEventListener('input', function() {
                    let q = this.value.trim();
                    let nq = normalize(q);
                    document.querySelectorAll('#mp-table tbody tr').forEach(function(tr) {
                        let nombre = normalize(tr.getAttribute('data-nombre'));
                        let unidad = normalize(tr.getAttribute('data-unidad'));
                        let precio = (tr.getAttribute('data-precio') || '').toString();
                        let hay = nq === '' || nombre.includes(nq) || unidad.includes(nq) || precio.includes(q);
                        tr.style.display = hay ? '' : 'none';
                    });
                    actualizarMensajeResultadosMP();
                });
                actualizarMensajeResultadosMP();
                // Selección de fila
                document.querySelectorAll('.mp-row').forEach(function(tr) {
                    tr.onclick = function() {
                        mpSeleccionada = {
                            id: tr.getAttribute('data-id'),
                            nombre: tr.getAttribute('data-nombre'),
                            unidad: tr.getAttribute('data-unidad'),
                            precio: tr.getAttribute('data-precio')
                        };
                        document.getElementById('mp-cant-nombre').textContent = mpSeleccionada.nombre + ' (' + mpSeleccionada.unidad + ')';
                        document.getElementById('mp-cantidad-input').value = '';
                        document.getElementById('mp-cantidad-form').style.display = '';
                    };
                });
                // Confirmar cantidad
                document.getElementById('mp-confirmar-cantidad').onclick = function() {
                    if (!mpSeleccionada) return;
                    let cant = parseFloat(document.getElementById('mp-cantidad-input').value);
                    if (!cant || cant <= 0) return alert('Ingrese una cantidad válida');
                    let list = document.getElementById('mp-items-list');
                    if (list.querySelector('[data-mp-id="'+mpSeleccionada.id+'"]')) {
                        alert('Ya agregaste esta materia prima');
                        return;
                    }
                    let row = document.createElement('div');
                    row.className = 'row g-2 align-items-center mb-1';
                    row.setAttribute('data-mp-id', mpSeleccionada.id);
                    row.setAttribute('data-precio', mpSeleccionada.precio);
                    row.setAttribute('data-cantidad', cant);
                    row.innerHTML = `<input type="hidden" name="mp_id[]" value="${mpSeleccionada.id}">
                        <input type="hidden" name="mp_cantidad[]" value="${cant}">
                        <div class="col-5">${mpSeleccionada.nombre} <span class="text-muted">(${mpSeleccionada.unidad})</span></div>
                        <div class="col-3">${cant} ${mpSeleccionada.unidad}</div>
                        <div class="col-3">$ ${parseFloat(mpSeleccionada.precio).toFixed(2)} x u</div>
                        <div class="col-1"><button type="button" class="btn btn-danger btn-sm mp-del">&times;</button></div>`;
                    list.appendChild(row);
                    mpSeleccionada = null;
                    document.getElementById('mp-cantidad-form').style.display = 'none';
                    actualizarTotalPedido();
                    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMP'));
                    modal.hide();
                };
                // Cancelar cantidad
                document.getElementById('mp-cancelar-cantidad').onclick = function() {
                    mpSeleccionada = null;
                    document.getElementById('mp-cantidad-form').style.display = 'none';
                };
                // Eliminar MP de la lista
                document.getElementById('mp-items-list').addEventListener('click', function(e) {
                    if (e.target.classList.contains('mp-del')) {
                        e.target.closest('.row').remove();
                        actualizarTotalPedido();
                    }
                });
                actualizarTotalPedido();
            });
            </script>
        </div>
    </div>
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
            <span>Listado de Pedidos</span>
            <form class="row row-cols-lg-auto g-2 align-items-center" method="get" style="margin-bottom:0">
                <div class="col">
                    <select name="f_estado" class="form-select form-select-sm">
                        <option value="">Estado</option>
                        <?php foreach (["PENDIENTE","APROBADO","PAGADO","COMPLETADO","RECHAZADO"] as $est): ?>
                            <option value="<?= $est ?>" <?= $f_estado===$est?'selected':'' ?>><?= $est ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <select name="f_proveedor" class="form-select form-select-sm">
                        <option value="">Proveedor</option>
                        <?php foreach ($proveedores as $prov): ?>
                            <option value="<?= $prov['id'] ?>" <?= $f_proveedor==$prov['id']?'selected':'' ?>><?= e($prov['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <input type="date" name="f_fecha" value="<?= e($f_fecha) ?>" class="form-control form-control-sm" placeholder="Fecha">
                </div>
                <div class="col">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Filtrar</button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Usuario</th>
                        <th>Descripción</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $p): ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td><?= e($p['fecha']) ?></td>
                            <td><?= e($p['proveedor']) ?></td>
                            <td><?= e($p['usuario']) ?></td>
                            <td><?= e($p['descripcion']) ?></td>
                            <td>$ <?= number_format($p['monto'], 2, ',', '.') ?></td>
                            <td><?= e($p['estado']) ?></td>
                            <td>
                                <a href="pedido_ver.php?id=<?= e($p['id']) ?>" class="btn btn-outline-primary btn-sm mb-1">Ver</a>
                                <?php if ($p['estado'] === 'PENDIENTE'): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                        <button name="accion_pedido" value="aprobar" class="btn btn-success btn-sm">Aprobar</button>
                                        <button name="accion_pedido" value="rechazar" class="btn btn-danger btn-sm">Rechazar</button>
                                    </form>
                                <?php elseif ($p['estado'] === 'APROBADO'): ?>
                                    <form method="post" enctype="multipart/form-data" style="display:inline">
                                        <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="accion_pedido" value="pagar">
                                        <input type="number" name="monto_pago" step="0.01" min="0" placeholder="Monto" required class="form-control form-control-sm mb-1" style="width:90px;display:inline-block">
                                        <input type="text" name="observaciones_pago" placeholder="Obs." class="form-control form-control-sm mb-1" style="width:120px;display:inline-block">
                                        <input type="file" name="comprobante_archivo" accept=".jpg,.jpeg,.png,.pdf" class="form-control form-control-sm mb-1" style="width:180px;display:inline-block">
                                        <button type="submit" class="btn btn-primary btn-sm">Registrar Pago</button>
                                    </form>
                                <?php elseif ($p['estado'] === 'PAGADO'): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                        <button name="accion_pedido" value="completar" class="btn btn-info btn-sm">Registrar Compra</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
