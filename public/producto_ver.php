<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$flash_ok = '';
$flash_err = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ---------- Utilidades BOM / precio ---------- */
function bom_cost(int $pt_id): float {
  $sql = "SELECT SUM(b.cant_por_unidad * p.precio_std) AS costo
          FROM product_bom b
          JOIN products p ON p.id = b.component_id
          WHERE b.product_pt_id = ?";
  $st = db()->prepare($sql);
  $st->execute([$pt_id]);
  return (float)($st->fetch()['costo'] ?? 0);
}

function refresh_pt_price(int $pt_id): void {
  $s = db()->prepare("SELECT margen_pct FROM products WHERE id=? AND tipo='PT'");
  $s->execute([$pt_id]);
  $margen = (float)($s->fetchColumn() ?? 0);
  $costo = bom_cost($pt_id);
  $precio = round($costo * (1 + ($margen/100)), 2);
  $u = db()->prepare("UPDATE products SET precio_std=? WHERE id=? AND tipo='PT'");
  $u->execute([$precio, $pt_id]);
}

/* -------------------- POST: Producto / BOM -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // Guardar datos del producto
  if ($action === 'save_product') {
    try {
      $codigo = trim($_POST['codigo'] ?? '');
      $nombre = trim($_POST['nombre'] ?? '');
      $tipo   = $_POST['tipo'] ?? 'MP';
      $unidad = trim($_POST['unidad'] ?? 'UN');
      $precio = (float)($_POST['precio_std'] ?? 0);
      $activo = isset($_POST['activo']) ? 1 : 0;
      $margen = isset($_POST['margen_pct']) ? (float)$_POST['margen_pct'] : 30.0;
      $stock_minimo = isset($_POST['stock_minimo']) ? (float)$_POST['stock_minimo'] : 0;

      if ($codigo === '' || $nombre === '') throw new Exception('Código y Nombre son obligatorios.');
      if (!in_array($tipo, ['MP','PT'], true)) throw new Exception('Tipo inválido.');

      if ($id > 0) {
        $sql = "UPDATE products
                   SET codigo=?, nombre=?, tipo=?, unidad=?, precio_std=?, activo=?, margen_pct=?, stock_minimo=?
                 WHERE id=?";
        db()->prepare($sql)->execute([$codigo, $nombre, $tipo, $unidad, $precio, $activo, $margen, $stock_minimo, $id]);

        if ($tipo === 'PT') {
          refresh_pt_price($id); // sobre-escribe precio manual con BOM+margen
        }
      } else {
        $sql = "INSERT INTO products
                   (codigo, nombre, tipo, unidad, precio_std, stock_actual, stock_reservado, activo, margen_pct, stock_minimo)
                VALUES (?,?,?,?,?,0,0,?,?,?)";
        db()->prepare($sql)->execute([$codigo, $nombre, $tipo, $unidad, $precio, $activo, $margen, $stock_minimo]);
        $id = (int)db()->lastInsertId();

        if ($tipo === 'PT') {
          refresh_pt_price($id);
        }
      }
      $flash_ok = 'Producto guardado correctamente.';
    } catch (Throwable $e) {
      $flash_err = 'No se pudo guardar: ' . $e->getMessage();
    }
  }

  // BOM: agregar componente
  if ($action === 'bom_add') {
    try {
      if ($id <= 0) throw new Exception('Primero guardá el producto.');
      $component_id = (int)($_POST['component_id'] ?? 0);
      $cant = max(0.0001, (float)($_POST['cant_por_unidad'] ?? 0));

      if ($component_id <= 0) throw new Exception('Seleccioná un componente.');
      if ($component_id === $id) throw new Exception('El PT no puede ser componente de sí mismo.');

      $tpPT = db()->prepare("SELECT tipo FROM products WHERE id=?");
      $tpPT->execute([$id]);
      if ($tpPT->fetchColumn() !== 'PT') throw new Exception('La BOM aplica solo a productos PT.');

      $tpC = db()->prepare("SELECT tipo FROM products WHERE id=?");
      $tpC->execute([$component_id]);
      if ($tpC->fetchColumn() !== 'MP') throw new Exception('Solo se permiten componentes de tipo MP.');

      $st = db()->prepare("SELECT 1 FROM product_bom WHERE product_pt_id=? AND component_id=?");
      $st->execute([$id, $component_id]);
      if ($st->fetch()) {
        db()->prepare("UPDATE product_bom SET cant_por_unidad=cant_por_unidad + ? WHERE product_pt_id=? AND component_id=?")
          ->execute([$cant, $id, $component_id]);
      } else {
        db()->prepare("INSERT INTO product_bom (product_pt_id, component_id, cant_por_unidad) VALUES (?,?,?)")
          ->execute([$id, $component_id, $cant]);
      }

      refresh_pt_price($id);
      $flash_ok = 'Componente agregado a la BOM.';
    } catch (Throwable $e) {
      $flash_err = 'No se pudo agregar: ' . $e->getMessage();
    }
  }

  // BOM: eliminar componente (mismo form)
  if ($action === 'bom_update' && isset($_POST['bom_remove_id'])) {
    try {
      if ($id <= 0) throw new Exception('Producto inválido.');
      $component_id = (int)$_POST['bom_remove_id'];
      db()->prepare("DELETE FROM product_bom WHERE product_pt_id=? AND component_id=?")->execute([$id, $component_id]);

      refresh_pt_price($id);
      $flash_ok = 'Componente eliminado.';
    } catch (Throwable $e) {
      $flash_err = 'No se pudo eliminar: ' . $e->getMessage();
    }
  }

  // BOM: guardar cantidades
  if ($action === 'bom_update' && !isset($_POST['bom_remove_id'])) {
    try {
      if ($id <= 0) throw new Exception('Producto inválido.');
      $cants = $_POST['cant'] ?? [];
      foreach ($cants as $cid => $val) {
        $cid = (int)$cid;
        $v = max(0.0001, (float)$val);
        db()->prepare("UPDATE product_bom SET cant_por_unidad=? WHERE product_pt_id=? AND component_id=?")
          ->execute([$v, $id, $cid]);
      }

      refresh_pt_price($id);
      $flash_ok = 'BOM actualizada.';
    } catch (Throwable $e) {
      $flash_err = 'No se pudo actualizar: ' . $e->getMessage();
    }
  }

  // Margen: actualizar
  if ($action === 'set_margin') {
    try {
      if ($id <= 0) throw new Exception('Producto inválido.');
      $m = max(0, (float)($_POST['margen_pct'] ?? 0));
      db()->prepare("UPDATE products SET margen_pct=? WHERE id=?")->execute([$m, $id]);

      refresh_pt_price($id);
      $flash_ok = 'Margen actualizado y precio recalculado.';
    } catch (Throwable $e) {
      $flash_err = 'No se pudo actualizar el margen: ' . $e->getMessage();
    }
  }
}

/* -------------------- GET: Producto + BOM + costo -------------------- */
$row = [
  'codigo' => '',
  'nombre' => '',
  'tipo' => 'MP',
  'unidad' => 'UN',
  'precio_std' => 0,
  'activo' => 1,
  'stock_actual' => 0,
  'stock_reservado' => 0,
  'stock_minimo' => 0,
  'margen_pct' => 30.00,
];
if ($id > 0) {
  $s = db()->prepare("SELECT * FROM products WHERE id=?");
  $s->execute([$id]);
  $dbRow = $s->fetch();
  if ($dbRow) $row = $dbRow;
}

$bom = [];
$componentesSel = [];
$costo_bom = 0.0;
$precio_calc = null;

if ($id > 0 && $row['tipo'] === 'PT') {
  $sb = db()->prepare("SELECT b.component_id, p.codigo, p.nombre, p.unidad, b.cant_por_unidad, p.precio_std
                       FROM product_bom b
                       JOIN products p ON p.id=b.component_id
                       WHERE b.product_pt_id=?
                       ORDER BY p.nombre");
  $sb->execute([$id]);
  $bom = $sb->fetchAll();

  $componentesSel = db()->query("SELECT id, codigo, nombre FROM products WHERE tipo='MP' AND activo=1 ORDER BY nombre LIMIT 500")->fetchAll();

  foreach ($bom as $b) {
    $costo_bom += (float)$b['cant_por_unidad'] * (float)$b['precio_std'];
  }
  $precio_calc = round($costo_bom * (1 + ((float)$row['margen_pct']/100)), 2);
}

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= $id ? 'Editar Producto' : 'Nuevo Producto' ?></h5>
    <a class="btn btn-outline-secondary" href="<?= url('productos.php') ?>">Volver</a>
  </div>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?= e($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?= e($flash_err) ?></div><?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form class="row g-3" method="post">
        <input type="hidden" name="action" value="save_product">
        <div class="col-md-3">
          <label class="form-label">Código *</label>
          <input name="codigo" class="form-control" required value="<?= e($row['codigo']) ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label">Nombre *</label>
          <input name="nombre" class="form-control" required value="<?= e($row['nombre']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Tipo *</label>
          <select name="tipo" class="form-select">
            <option value="MP" <?= $row['tipo']==='MP'?'selected':'' ?>>MP</option>
            <option value="PT" <?= $row['tipo']==='PT'?'selected':'' ?>>PT</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Unidad</label>
          <input name="unidad" class="form-control" value="<?= e($row['unidad']) ?>">
        </div>

        <!-- Precio: si es PT, se sobrescribe desde BOM+margen -->
        <div class="col-md-2">
          <label class="form-label">Precio std.</label>
          <input name="precio_std" type="number" step="0.01" class="form-control" value="<?= (float)$row['precio_std'] ?>">
          <div class="form-text"><?= $row['tipo']==='PT' ? 'Se recalcula desde BOM + margen.' : 'Editable manual (MP).' ?></div>
        </div>

        <div class="col-md-2">
          <label class="form-label">Stock mínimo</label>
          <input name="stock_minimo" type="number" step="0.001" min="0" class="form-control" value="<?= (float)$row['stock_minimo'] ?>">
          <div class="form-text">Alerta si disponible ≤ mínimo.</div>
        </div>

        <div class="col-md-2">
          <label class="form-label d-block">Activo</label>
          <input type="checkbox" name="activo" <?= $row['activo']?'checked':'' ?>>
        </div>

        <?php if ($row['tipo'] === 'PT'): ?>
        <div class="col-md-2">
          <label class="form-label">Margen % (PT)</label>
          <input name="margen_pct" type="number" step="0.01" min="0" class="form-control" value="<?= (float)$row['margen_pct'] ?>">
          <div class="form-text">Se usa para precio desde costo BOM.</div>
        </div>
        <?php endif; ?>

        <?php if ($id > 0): ?>
        <div class="col-md-2">
          <label class="form-label">Stock actual</label>
          <input class="form-control" value="<?= (float)$row['stock_actual'] ?>" disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label">Reservado</label>
          <input class="form-control" value="<?= (float)$row['stock_reservado'] ?>" disabled>
        </div>
        <?php endif; ?>

        <div class="col-12">
          <button class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($id > 0 && $row['tipo'] === 'PT'): ?>
  <!-- Panel costo + margen + precio -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Costo BOM (estimado)</label>
          <input class="form-control" value="<?= money($costo_bom) ?>" disabled>
        </div>
        <div class="col-md-3">
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="set_margin">
            <div class="col-7">
              <label class="form-label">Margen %</label>
              <input name="margen_pct" type="number" step="0.01" min="0" class="form-control" value="<?= (float)$row['margen_pct'] ?>">
            </div>
            <div class="col-5 d-grid">
              <label class="form-label d-none d-md-block">&nbsp;</label>
              <button class="btn btn-outline-primary">Aplicar</button>
            </div>
          </form>
        </div>
        <div class="col-md-3">
          <label class="form-label">Precio calculado</label>
          <input class="form-control" value="<?= money($precio_calc ?? 0) ?>" disabled>
        </div>
        <div class="col-md-3">
          <label class="form-label">Precio vigente (guardado)</label>
          <input class="form-control" value="<?= money($row['precio_std']) ?>" disabled>
        </div>
      </div>
      <p class="small text-muted mt-2 mb-0">
        Cada cambio en la BOM o en el margen actualiza el <em>precio std.</em> del PT.
      </p>
    </div>
  </div>

  <div class="card shadow-sm" id="bom">
    <div class="card-body">
      <h6 class="mb-3">BOM — Componentes por unidad de: <strong><?= e($row['codigo']) ?></strong> <?= e($row['nombre']) ?></h6>

      <!-- Agregar componente -->
      <form class="row g-2 mb-3" method="post">
        <input type="hidden" name="action" value="bom_add">
        <div class="col-md-6">
          <select name="component_id" class="form-select" required>
            <option value="">— Seleccionar MP activo —</option>
            <?php foreach ($componentesSel as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['codigo'].' — '.$c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <input name="cant_por_unidad" type="number" step="0.0001" min="0.0001" class="form-control" placeholder="Cant. por unidad" required>
        </div>
        <div class="col-md-3 d-grid">
          <button class="btn btn-outline-primary">Agregar componente</button>
        </div>
      </form>

      <!-- BOM: un solo formulario para guardar/eliminar -->
      <form method="post">
        <input type="hidden" name="action" value="bom_update">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Unidad</th>
                <th class="text-end" style="width:180px;">$ MP</th>
                <th class="text-end" style="width:220px;">Cant. por unidad</th>
                <th class="text-end" style="width:140px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$bom): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Sin componentes aún. Agregá el primero.</td></tr>
              <?php else: foreach ($bom as $b): $cid = (int)$b['component_id']; ?>
                <tr>
                  <td><?= e($b['codigo']) ?></td>
                  <td><?= e($b['nombre']) ?></td>
                  <td><?= e($b['unidad']) ?></td>
                  <td class="text-end"><?= money($b['precio_std']) ?></td>
                  <td class="text-end">
                    <input class="form-control form-control-sm text-end" type="number" step="0.0001" min="0.0001" name="cant[<?= $cid ?>]" value="<?= (float)$b['cant_por_unidad'] ?>">
                  </td>
                  <td class="text-end">
                    <button type="submit"
                            name="bom_remove_id"
                            value="<?= $cid ?>"
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Quitar componente <?= e($b['codigo']) ?>?');">
                      Eliminar
                    </button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($bom): ?>
        <div class="d-flex justify-content-end">
          <button class="btn btn-primary" name="save_bom" value="1">Guardar cambios BOM</button>
        </div>
        <?php endif; ?>
      </form>

      <p class="small text-muted mt-3">
        Al <strong>Iniciar</strong> una OP se consume MP según esta BOM; al <strong>Finalizar</strong> se ingresa PT. El precio del PT se recalcula siempre con <em>costo BOM + margen</em>.
      </p>
    </div>
  </div>
  <?php endif; ?>

</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
