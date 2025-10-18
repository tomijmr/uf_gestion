<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_role('ADMIN','DEPOSITO');
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_err = '';
$flash_ok  = '';

// Cargar catálogo de productos activos
$prodRows = db()->query("
  SELECT id, codigo, nombre, tipo, unidad, costo_std
  FROM products
  WHERE activo = 1
  ORDER BY tipo, codigo
")->fetchAll();

$products = [];
foreach ($prodRows as $p) {
  $products[] = [
    'id'        => (int)$p['id'],
    'codigo'    => (string)$p['codigo'],
    'nombre'    => (string)$p['nombre'],
    'tipo'      => (string)$p['tipo'],
    'unidad'    => (string)$p['unidad'],
    'costo_std' => (float)$p['costo_std'],
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // Datos encabezado
    $fecha      = trim($_POST['fecha'] ?? '');
    $proveedor  = trim($_POST['proveedor'] ?? '');
    $comp_tipo  = trim($_POST['comp_tipo'] ?? 'FACTURA');
    $comp_serie = trim($_POST['comp_serie'] ?? '');
    $comp_num   = trim($_POST['comp_numero'] ?? '');
    $moneda     = trim($_POST['moneda'] ?? 'ARS');
    $notas      = trim($_POST['notas'] ?? '');

    if ($fecha === '') $fecha = date('Y-m-d H:i:s');
    if ($proveedor === '' || $comp_num === '') throw new Exception('Proveedor y número de comprobante son obligatorios.');

    // Ítems (arrays paralelos)
    $product_ids = $_POST['product_id'] ?? [];
    $codigos     = $_POST['codigo'] ?? [];
    $nombres     = $_POST['nombre'] ?? [];
    $unidades    = $_POST['unidad'] ?? [];
    $cantidades  = $_POST['cantidad'] ?? [];
    $costos      = $_POST['costo_unit'] ?? [];
    $notas_i     = $_POST['notas_item'] ?? [];

    if (!is_array($codigos) || count($codigos) === 0) throw new Exception('Debe cargar al menos un ítem.');

    // Archivo (opcional)
    $archivo_path = null;
    if (!empty($_FILES['archivo']['name'])) {
      $dir = __DIR__ . '/../storage/comprobantes';
      if (!is_dir($dir)) mkdir($dir, 0775, true);
      $fname = date('YmdHis') . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $_FILES['archivo']['name']);
      $destFS = $dir . '/' . $fname;
      if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destFS)) {
        throw new Exception('No se pudo guardar el archivo del comprobante.');
      }
      $archivo_path = 'storage/comprobantes/' . $fname; // ruta relativa web
    }

    db()->beginTransaction();

    // Insert purchase (total calculado luego)
    $insP = db()->prepare("INSERT INTO purchases
      (fecha, proveedor, comp_tipo, comp_serie, comp_numero, total, moneda, archivo_path, notas, created_by)
      VALUES (?,?,?,?,?,0,?,?,?,?)");
    $insP->execute([$fecha, $proveedor, $comp_tipo, $comp_serie, $comp_num, $moneda, $archivo_path, $notas, (int)user()['id']]);
    $purchase_id = (int)db()->lastInsertId();

    $total = 0.0;

    // Prepared statements
    $selProdById   = db()->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $selProdByCode = db()->prepare("SELECT * FROM products WHERE codigo = ? LIMIT 1");
    $insProd = db()->prepare("INSERT INTO products (codigo, nombre, tipo, unidad, costo_std, precio_std, stock_actual, stock_reservado, stock_minimo, activo)
                              VALUES (?,?,?,?,?, 0, 0, 0, 0, 1)");
    $updCosto = db()->prepare("UPDATE products SET costo_std = ?, unidad = ? WHERE id = ?");
    $updStock = db()->prepare("UPDATE products SET stock_actual = stock_actual + ? WHERE id = ?");

    $insItem = db()->prepare("INSERT INTO purchase_items
      (purchase_id, product_id, codigo, nombre, unidad, cantidad, costo_unit, subtotal, notas)
      VALUES (?,?,?,?,?,?,?,?,?)");

    $insStockMove = db()->prepare("INSERT INTO stock_moves
      (fecha, tipo, motivo, product_id, cantidad, referencia_tipo, referencia_id, observaciones)
      VALUES (CURRENT_TIMESTAMP, 'ENTRADA', 'COMPRA', ?, ?, 'PURCHASE', ?, ?)");

    foreach ($codigos as $i => $codigo) {
      $codigo = trim($codigo ?? '');
      $nombre = trim($nombres[$i] ?? '');
      $unidad = trim($unidades[$i] ?? 'UN');
      $cant   = (float)($cantidades[$i] ?? 0);
      $costoU = (float)($costos[$i] ?? 0);
      $notaI  = trim($notas_i[$i] ?? '');
      $pidRaw = $product_ids[$i] ?? '';

      if ($codigo === '' || $nombre === '') throw new Exception("Ítem #".($i+1).": código y nombre son obligatorios.");
      if ($cant <= 0 || $costoU < 0) throw new Exception("Ítem #".($i+1).": cantidad y costo deben ser válidos.");

      $product_id = null;

      // 1) Si vino product_id del picker, usarlo
      if ($pidRaw !== '' && ctype_digit((string)$pidRaw)) {
        $pid = (int)$pidRaw;
        $selProdById->execute([$pid]);
        $prod = $selProdById->fetch();
        if ($prod) {
          $product_id = (int)$prod['id'];
          // Sincronizar valores base
          $codigo = $prod['codigo'];
          if ($nombre === '') $nombre = $prod['nombre'];
          if ($unidad === '') $unidad = $prod['unidad'];
        }
      }

      // 2) Si no hay product_id, buscar por código
      if (!$product_id) {
        $selProdByCode->execute([$codigo]);
        $prod = $selProdByCode->fetch();
        if ($prod) {
          $product_id = (int)$prod['id'];
          if ($nombre === '') $nombre = $prod['nombre'];
          if ($unidad === '') $unidad = $prod['unidad'];
        }
      }

      // 3) Si no existe, alta rápida como MP
      if (!$product_id) {
        $insProd->execute([$codigo, $nombre, 'MP', ($unidad?:'UN'), $costoU]);
        $product_id = (int)db()->lastInsertId();
      } else {
        // Si existe, actualizar costo_std y unidad (último costo & unidad preferida)
        $updCosto->execute([$costoU, $unidad ?: $prod['unidad'], $product_id]);
      }

      // Stock +
      $updStock->execute([$cant, $product_id]);

      // Movimiento de stock (COMPRA)
      $obs = "Compra $comp_tipo $comp_serie-$comp_num";
      $insStockMove->execute([$product_id, $cant, $purchase_id, $obs]);

      // Ítem compra
      $subtotal = round($cant * $costoU, 2);
      $total += $subtotal;
      $insItem->execute([$purchase_id, $product_id, $codigo, $nombre, $unidad?:'UN', $cant, $costoU, $subtotal, $notaI]);
    }

    // Total compra
    db()->prepare("UPDATE purchases SET total=? WHERE id=?")->execute([$total, $purchase_id]);

    db()->commit();
    header('Location: ' . url('compras.php?ok=' . urlencode('Compra registrada.')));
    exit;

  } catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    $flash_err = $e->getMessage();
  }
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Registrar compra de MP</h5>
    <a class="btn btn-outline-secondary" href="<?= url('compras.php') ?>">Volver</a>
  </div>

  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card shadow-sm">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Fecha</label>
          <input type="datetime-local" class="form-control" name="fecha" value="<?= date('Y-m-d\TH:i') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Proveedor *</label>
          <input class="form-control" name="proveedor" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Tipo</label>
          <select class="form-select" name="comp_tipo">
            <option>FACTURA</option>
            <option>REMITO</option>
            <option>TICKET</option>
            <option>OTRO</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label">Serie</label>
          <input class="form-control" name="comp_serie" placeholder="A">
        </div>
        <div class="col-md-2">
          <label class="form-label">Número *</label>
          <input class="form-control" name="comp_numero" required placeholder="0001-00000001">
        </div>
        <div class="col-md-4">
          <label class="form-label">Comprobante (PDF/JPG/PNG)</label>
          <input class="form-control" type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png">
        </div>
        <div class="col-md-8">
          <label class="form-label">Notas</label>
          <input class="form-control" name="notas">
        </div>
      </div>

      <hr>

      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Ítems</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()">Agregar ítem</button>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle" id="itemsTable">
          <thead class="table-light">
            <tr>
              <th style="width:120px;">Código *</th>
              <th>Nombre *</th>
              <th style="width:90px;">Unidad</th>
              <th style="width:120px;">Cantidad *</th>
              <th style="width:140px;">Costo unit. *</th>
              <th>Notas</th>
              <th style="width:120px;">Producto</th>
              <th style="width:50px;"></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="text-end">
        <strong>Total estimado: $ <span id="totalSpan">0,00</span></strong>
      </div>
    </div>
    <div class="card-footer text-end">
      <button class="btn btn-primary">Guardar compra</button>
    </div>
  </form>
</div>

<!-- Modal Picker de Productos -->
<div class="modal fade" id="productPickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Seleccionar producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <input type="text" id="pickerSearch" class="form-control" placeholder="Buscar por código o nombre...">
        </div>
        <div class="table-responsive" style="max-height:55vh;">
          <table class="table table-sm table-hover" id="pickerTable">
            <thead class="table-light">
              <tr>
                <th style="width:140px;">Código</th>
                <th>Nombre</th>
                <th style="width:90px;">Tipo</th>
                <th style="width:80px;">Unidad</th>
                <th class="text-end" style="width:120px;">Costo std</th>
                <th style="width:90px;"></th>
              </tr>
            </thead>
            <tbody><!-- rows por JS --></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
// Catálogo desde PHP
const PRODUCTS = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

function money(n){ return (Math.round(n*100)/100).toFixed(2).replace('.',','); }

function addItem(data={}) {
  const tb = document.querySelector('#itemsTable tbody');
  const idx = tb.querySelectorAll('tr').length;
  const tr = document.createElement('tr');
  tr.dataset.index = idx;

  tr.innerHTML = `
    <td>
      <input class="form-control form-control-sm code" name="codigo[]" required value="${data.codigo||''}">
    </td>
    <td>
      <input class="form-control form-control-sm name" name="nombre[]" required value="${data.nombre||''}">
    </td>
    <td>
      <input class="form-control form-control-sm unit" name="unidad[]" value="${data.unidad||'UN'}">
    </td>
    <td>
      <input type="number" step="0.001" min="0" class="form-control form-control-sm qty" name="cantidad[]" required value="${data.cantidad||''}">
    </td>
    <td>
      <input type="number" step="0.0001" min="0" class="form-control form-control-sm cost" name="costo_unit[]" required value="${data.costo_unit||''}">
    </td>
    <td>
      <input class="form-control form-control-sm" name="notas_item[]" value="${data.notas||''}">
    </td>
    <td class="text-nowrap">
      <input type="hidden" class="pid" name="product_id[]" value="${data.product_id||''}">
      <button type="button" class="btn btn-sm btn-outline-secondary picker-btn" data-open-picker="1">Elegir…</button>
      <small class="text-muted d-block picked-label">${data.codigo? 'Sel: '+data.codigo : ''}</small>
    </td>
    <td class="text-end">
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); updateTotal();">&times;</button>
    </td>
  `;
  tb.appendChild(tr);

  // Listeners de la fila
  tr.querySelector('.qty').addEventListener('input', updateTotal);
  tr.querySelector('.cost').addEventListener('input', updateTotal);
  tr.querySelector('.code').addEventListener('blur', () => autoFillFromCode(tr));
  tr.querySelector('.picker-btn').addEventListener('click', () => openPickerForRow(tr));

  updateTotal();
}

// Autocompletar por código
function autoFillFromCode(tr) {
  const code = (tr.querySelector('.code').value || '').trim();
  if (!code) return;
  const p = PRODUCTS.find(x => x.codigo.toLowerCase() === code.toLowerCase());
  if (!p) {
    // Nuevo producto → limpiar product_id, mantener inputs del usuario
    tr.querySelector('.pid').value = '';
    tr.querySelector('.name').placeholder = 'Nuevo producto';
    tr.querySelector('.unit').placeholder = 'UN';
    tr.querySelector('.picked-label').innerText = '';
    return;
  }
  // Rellenar datos
  tr.querySelector('.pid').value = p.id;
  tr.querySelector('.code').value = p.codigo;
  if (!tr.querySelector('.name').value) tr.querySelector('.name').value = p.nombre;
  if (!tr.querySelector('.unit').value) tr.querySelector('.unit').value = p.unidad || 'UN';
  if (!tr.querySelector('.cost').value) tr.querySelector('.cost').value = p.costo_std || 0;
  tr.querySelector('.picked-label').innerText = 'Sel: ' + p.codigo;
}

// ---------- Picker de productos (con delegación de eventos) ----------
let CURRENT_ROW = null;
const pickerModalEl = document.getElementById('productPickerModal');
let pickerModal = null;

function ensureModal() {
  if (pickerModal) return pickerModal;
  if (window.bootstrap && bootstrap.Modal) {
    pickerModal = new bootstrap.Modal(pickerModalEl);
    return pickerModal;
  }
  // Fallback minimalista si no está bootstrap.Modal
  pickerModal = {
    show(){ pickerModalEl.classList.add('show'); pickerModalEl.style.display='block'; pickerModalEl.removeAttribute('aria-hidden'); },
    hide(){ pickerModalEl.classList.remove('show'); pickerModalEl.style.display='none'; pickerModalEl.setAttribute('aria-hidden','true'); }
  };
  return pickerModal;
}

function openPickerForRow(tr) {
  CURRENT_ROW = tr;
  renderPickerRows(); // primera carga
  ensureModal().show();
  const ps = document.getElementById('pickerSearch');
  ps.value = '';
  ps.focus();
}

function renderPickerRows(filter='') {
  const tb = document.querySelector('#pickerTable tbody');
  tb.innerHTML = '';
  const f = (filter || '').toLowerCase();
  PRODUCTS
    .filter(p => !f || p.codigo.toLowerCase().includes(f) || p.nombre.toLowerCase().includes(f))
    .forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(p.codigo)}</td>
        <td>${escapeHtml(p.nombre)}</td>
        <td>${escapeHtml(p.tipo)}</td>
        <td>${escapeHtml(p.unidad||'UN')}</td>
        <td class="text-end">${(p.costo_std||0).toFixed(2)}</td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-primary pick-btn" data-id="${p.id}">Elegir</button>
        </td>
      `;
      tb.appendChild(tr);
    });
}

// Delegación: un solo listener para todos los botones "Elegir"
document.querySelector('#pickerTable tbody').addEventListener('click', (e) => {
  const btn = e.target.closest('.pick-btn');
  if (!btn) return;
  const id = parseInt(btn.dataset.id, 10);
  const p = PRODUCTS.find(x => x.id === id);
  if (p) pickProduct(p);
});

document.getElementById('pickerSearch').addEventListener('input', (e)=>{
  renderPickerRows(e.target.value);
});

function pickProduct(p) {
  if (!CURRENT_ROW) return;
  CURRENT_ROW.querySelector('.pid').value  = p.id;
  CURRENT_ROW.querySelector('.code').value = p.codigo;
  CURRENT_ROW.querySelector('.name').value = p.nombre;
  CURRENT_ROW.querySelector('.unit').value = p.unidad || 'UN';
  if (!CURRENT_ROW.querySelector('.cost').value) {
    CURRENT_ROW.querySelector('.cost').value = p.costo_std || 0;
  }
  CURRENT_ROW.querySelector('.picked-label').innerText = 'Sel: ' + p.codigo;
  ensureModal().hide();
  updateTotal();
}

// Utilidades
function updateTotal(){
  let t = 0;
  document.querySelectorAll('#itemsTable tbody tr').forEach(tr=>{
    const q = parseFloat(tr.querySelector('.qty')?.value || '0');
    const c = parseFloat(tr.querySelector('.cost')?.value || '0');
    t += (q*c);
  });
  document.getElementById('totalSpan').innerText = money(t);
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

// agrega una fila por defecto
addItem();
</script>
