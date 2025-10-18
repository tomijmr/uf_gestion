<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Clientes</h5>
    <a class="btn btn-primary" href="<?= url('cliente_ver.php') ?>">Nuevo Cliente</a>
  </div>

  <form class="row g-2 mb-3" method="get" action="<?= url('clientes.php') ?>">
    <div class="col-md-10">
      <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por Nombre / CUIT-DNI / Teléfono">
    </div>
    <div class="col-md-2 d-grid">
      <button class="btn btn-outline-secondary">Buscar</button>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 70px;">ID</th>
              <th>Nombre / Razón Social</th>
              <th>CUIT/DNI</th>
              <th>Teléfono</th>
              <th class="text-end">Saldo</th>
              <th class="text-end" style="width:120px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron clientes.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= e($r['nombre']) ?></td>
                <td><?= e($r['cuit_dni']) ?></td>
                <td><?= e($r['telefono']) ?></td>
                <td class="text-end"><?= money($r['saldo']) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('cliente_ver.php') . '?id=' . (int)$r['id'] ?>">Ver</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Paginación -->
  <div class="d-flex justify-content-between align-items-center mt-3">
    <div class="small text-muted">
      Mostrando <?= $rows ? ($off + 1) : 0 ?>–<?= $off + count($rows) ?> de <?= $total ?>
    </div>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page > 1 ? page_url($page-1, $q) : '#' ?>">&laquo; Anterior</a>
        </li>
        <li class="page-item disabled"><span class="page-link">Página <?= $page ?> / <?= $pages ?></span></li>
        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page < $pages ? page_url($page+1, $q) : '#' ?>">Siguiente &raquo;</a>
        </li>
      </ul>
    </nav>
  </div>
</div>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>