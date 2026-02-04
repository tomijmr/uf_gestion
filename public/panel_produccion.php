<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/produccion.php';

$page_title = 'Panel de Producción';
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';

// Obtener estadísticas generales
$stats = db()->query("
    SELECT 
        COUNT(*) as total_ops,
        SUM(CASE WHEN estado_actual IS NULL OR estado_actual = '' THEN 1 ELSE 0 END) as sin_iniciar,
        SUM(CASE WHEN estado_actual IN ('SELECCION','CORTE','ARMADO','SOLDADURA','LIMPIEZA','PINTURA','ENSAMBLE','QC_FINAL') THEN 1 ELSE 0 END) as en_proceso,
        SUM(CASE WHEN estado_actual = 'DESPACHO' THEN 1 ELSE 0 END) as despachadas,
        SUM(CASE WHEN bloqueada_razon IS NOT NULL THEN 1 ELSE 0 END) as bloqueadas
    FROM production_orders
    WHERE estado IN ('PENDIENTE', 'EN_CURSO', 'FINALIZADA')
")->fetch(PDO::FETCH_ASSOC);

// Filtro por estado
$filtro_estado = $_GET['estado'] ?? 'TODAS';

// Consulta principal
$whereClauses = ["po.estado IN ('PENDIENTE', 'EN_CURSO', 'FINALIZADA')"];
$params = [];

if ($filtro_estado !== 'TODAS') {
    if ($filtro_estado === 'SIN_INICIAR') {
        $whereClauses[] = "(po.estado_actual IS NULL OR po.estado_actual = '')";
    } elseif ($filtro_estado === 'BLOQUEADAS') {
        $whereClauses[] = "po.bloqueada_razon IS NOT NULL";
    } elseif ($filtro_estado === 'DESPACHO') {
        $whereClauses[] = "po.estado_actual = 'DESPACHO'";
    } else {
        $whereClauses[] = "po.estado_actual = ?";
        $params[] = $filtro_estado;
    }
}

$whereSQL = implode(' AND ', $whereClauses);

$sql = "SELECT po.*, 
               p.codigo, p.nombre as producto_nombre,
               c.nombre as cliente_nombre,
               o.id as order_id,
               (SELECT COUNT(*) FROM production_states ps WHERE ps.production_order_id = po.id) as total_estados,
               (SELECT estado FROM production_states ps WHERE ps.production_order_id = po.id ORDER BY timestamp_inicio DESC LIMIT 1) as ultimo_estado,
               (SELECT e.nombre FROM production_states ps 
                LEFT JOIN employees e ON e.id = ps.operario_id 
                WHERE ps.production_order_id = po.id 
                ORDER BY timestamp_inicio DESC LIMIT 1) as ultimo_operario,
               (SELECT timestamp_inicio FROM production_states ps 
                WHERE ps.production_order_id = po.id 
                ORDER BY timestamp_inicio DESC LIMIT 1) as ultimo_cambio
        FROM production_orders po
        JOIN products p ON p.id = po.product_pt_id
        LEFT JOIN orders o ON o.id = po.order_id
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE {$whereSQL}
        ORDER BY 
            CASE WHEN po.bloqueada_razon IS NOT NULL THEN 0 ELSE 1 END,
            CASE WHEN po.estado_actual IS NULL THEN 0 ELSE 1 END,
            po.estado_actual,
            po.id DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$ops = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función helper para badge de estado
function estado_badge(string $estado): string {
    $map = [
        'SELECCION' => 'info',
        'CORTE' => 'primary',
        'ARMADO' => 'secondary',
        'SOLDADURA' => 'dark',
        'LIMPIEZA' => 'light text-dark',
        'PINTURA' => 'warning',
        'ENSAMBLE' => 'info',
        'QC_FINAL' => 'primary',
        'DESPACHO' => 'success'
    ];
    $color = $map[$estado] ?? 'secondary';
    return "<span class='badge bg-{$color}'>{$estado}</span>";
}

// Calcular progreso
function calcular_progreso(?string $estado_actual): int {
    if (!$estado_actual) return 0;
    $idx = array_search($estado_actual, ESTADOS_PRODUCCION);
    if ($idx === false) return 0;
    return (int)((($idx + 1) / count(ESTADOS_PRODUCCION)) * 100);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-kanban"></i> Panel de Producción</h2>
        <a href="<?= url('op.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-list-ul"></i> Ver Lista Completa
        </a>
    </div>

    <!-- Estadísticas Generales -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h3 class="mb-0"><?= (int)$stats['total_ops'] ?></h3>
                    <small class="text-muted">Total OPs</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center h-100 bg-light">
                <div class="card-body">
                    <h3 class="mb-0"><?= (int)$stats['sin_iniciar'] ?></h3>
                    <small class="text-muted">Sin Iniciar</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center h-100 bg-warning bg-opacity-10">
                <div class="card-body">
                    <h3 class="mb-0"><?= (int)$stats['en_proceso'] ?></h3>
                    <small class="text-muted">En Proceso</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center h-100 bg-success bg-opacity-10">
                <div class="card-body">
                    <h3 class="mb-0"><?= (int)$stats['despachadas'] ?></h3>
                    <small class="text-muted">Despachadas</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center h-100 bg-danger bg-opacity-10">
                <div class="card-body">
                    <h3 class="mb-0"><?= (int)$stats['bloqueadas'] ?></h3>
                    <small class="text-muted">Bloqueadas</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center h-100 bg-info bg-opacity-10">
                <div class="card-body">
                    <h3 class="mb-0"><?= count(ESTADOS_PRODUCCION) ?></h3>
                    <small class="text-muted">Etapas Total</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="btn-group btn-group-sm" role="group">
                <a href="?estado=TODAS" class="btn btn-<?= $filtro_estado === 'TODAS' ? 'primary' : 'outline-secondary' ?>">
                    Todas
                </a>
                <a href="?estado=SIN_INICIAR" class="btn btn-<?= $filtro_estado === 'SIN_INICIAR' ? 'primary' : 'outline-secondary' ?>">
                    Sin Iniciar
                </a>
                <?php foreach (ESTADOS_PRODUCCION as $est): ?>
                    <a href="?estado=<?= $est ?>" class="btn btn-<?= $filtro_estado === $est ? 'primary' : 'outline-secondary' ?>">
                        <?= $est ?>
                    </a>
                <?php endforeach; ?>
                <a href="?estado=BLOQUEADAS" class="btn btn-<?= $filtro_estado === 'BLOQUEADAS' ? 'danger' : 'outline-danger' ?>">
                    Bloqueadas
                </a>
            </div>
        </div>
    </div>

    <!-- Lista de OPs -->
    <div class="row g-3">
        <?php if (empty($ops)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No hay órdenes de producción para mostrar con el filtro seleccionado.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($ops as $op): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 <?= $op['bloqueada_razon'] ? 'border-danger' : '' ?>">
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <strong>OP #<?= (int)$op['id'] ?></strong>
                            <?php if ($op['estado_actual']): ?>
                                <?= estado_badge($op['estado_actual']) ?>
                            <?php else: ?>
                                <span class="badge bg-secondary">SIN INICIAR</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <!-- Producto -->
                            <h6 class="card-title mb-2" style="font-size: 0.9rem;">
                                <?= e($op['codigo']) ?><br>
                                <small class="text-muted"><?= e($op['producto_nombre']) ?></small>
                            </h6>

                            <!-- Cliente -->
                            <?php if ($op['cliente_nombre']): ?>
                                <p class="mb-2 small">
                                    <i class="bi bi-person"></i> <?= e($op['cliente_nombre']) ?>
                                </p>
                            <?php endif; ?>

                            <!-- Cantidad -->
                            <p class="mb-2 small">
                                <i class="bi bi-box"></i> Cantidad: <strong><?= (float)$op['cantidad'] ?></strong>
                            </p>

                            <!-- Progreso -->
                            <?php if ($op['estado_actual']): ?>
                                <?php $progreso = calcular_progreso($op['estado_actual']); ?>
                                <div class="mb-2">
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar <?= $op['bloqueada_razon'] ? 'bg-danger' : 'bg-success' ?>" 
                                             style="width: <?= $progreso ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= $progreso ?>% completado</small>
                                </div>
                            <?php endif; ?>

                            <!-- Último operario -->
                            <?php if ($op['ultimo_operario']): ?>
                                <p class="mb-2 small">
                                    <i class="bi bi-person-badge"></i> <?= e($op['ultimo_operario']) ?>
                                </p>
                            <?php endif; ?>

                            <!-- Último cambio -->
                            <?php if ($op['ultimo_cambio']): ?>
                                <p class="mb-2 small text-muted">
                                    <i class="bi bi-clock"></i> <?= date('d/m H:i', strtotime($op['ultimo_cambio'])) ?>
                                </p>
                            <?php endif; ?>

                            <!-- Bloqueada -->
                            <?php if ($op['bloqueada_razon']): ?>
                                <div class="alert alert-danger p-2 mb-2 small">
                                    <strong>⚠️ BLOQUEADA:</strong><br>
                                    <?= e($op['bloqueada_razon']) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Personalizaciones -->
                            <?php if ($op['color_personalizado']): ?>
                                <p class="mb-1 small">
                                    <i class="bi bi-palette"></i> Color: <strong><?= e($op['color_personalizado']) ?></strong>
                                </p>
                            <?php endif; ?>
                            <?php if ($op['tapizado_personalizado']): ?>
                                <p class="mb-1 small">
                                    <i class="bi bi-textarea"></i> Tapizado: <strong><?= e($op['tapizado_personalizado']) ?></strong>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white border-top-0 pt-0">
                            <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="verTimeline(<?= (int)$op['id'] ?>, '<?= (int)$op['id'] ?>')">
                                    <i class="bi bi-list-check"></i> Ver Timeline
                                </button>
                                <button class="btn btn-sm btn-outline-info" 
                                        onclick="abrirModalEstado(<?= (int)$op['id'] ?>, '<?= (int)$op['id'] ?>', '<?= e($op['estado_actual'] ?? '') ?>')">
                                    <i class="bi bi-arrow-right-circle"></i> Cambiar Estado
                                </button>
                                <?php if ($op['estado_actual']): ?>
                                <a href="<?= url('op.php?print_ticket_estado=1&po_id=' . (int)$op['id'] . '&estado=' . urlencode($op['estado_actual'])) ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-file-earmark-text"></i> Ticket Estado Actual
                                </a>
                                <?php endif; ?>
                                <a href="<?= url('op.php?print_ticket=1&po_id=' . (int)$op['id']) ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-printer"></i> Ticket
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Cambiar Estado (reutilizado de op.php) -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cambiar Estado de Producción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= url('op.php') ?>">
        <input type="hidden" name="action" value="cambiar_estado">
        <input type="hidden" name="po_id" id="modal_po_id">
        <input type="hidden" name="redirect" value="<?= url('panel_produccion.php?estado=' . $filtro_estado) ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">OP #<span id="modal_po_number"></span></label>
          </div>
          <div class="mb-3">
            <label class="form-label">Estado Actual</label>
            <input type="text" class="form-control" id="modal_estado_actual" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Nuevo Estado *</label>
            <select class="form-select" name="nuevo_estado" id="modal_nuevo_estado" required>
              <option value="">-- Seleccionar --</option>
              <option value="SELECCION">1. SELECCIÓN</option>
              <option value="CORTE">2. CORTE (descuenta perfiles/chapas)</option>
              <option value="ARMADO">3. ARMADO</option>
              <option value="SOLDADURA">4. SOLDADURA</option>
              <option value="LIMPIEZA">5. LIMPIEZA</option>
              <option value="PINTURA">6. PINTURA (descuenta pinturas) - Requiere QC en ARMADO</option>
              <option value="ENSAMBLE">7. ENSAMBLE (descuenta rodamientos/poleas/tapizados)</option>
              <option value="QC_FINAL">8. QC FINAL</option>
              <option value="DESPACHO">9. DESPACHO</option>
            </select>
            <div class="form-text">Los estados deben seguir el orden secuencial</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Operario *</label>
            <select class="form-select" name="operario_id" id="modal_operario_id" required>
              <option value="">-- Cargando operarios... --</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Notas</label>
            <textarea class="form-control" name="notas" rows="3" placeholder="Observaciones opcionales..."></textarea>
          </div>
          <div id="modal_advertencias" class="alert alert-warning d-none"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Cambiar Estado</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Ver Timeline -->
<div class="modal fade" id="modalTimeline" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Historial de Producción - OP #<span id="timeline_po_number"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="timeline_content">
          <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Cargando...</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Abrir ticket automáticamente si hay uno pendiente
<?php if (isset($_SESSION['abrir_ticket'])): ?>
window.addEventListener('DOMContentLoaded', function() {
  const ticketData = <?= json_encode($_SESSION['abrir_ticket']) ?>;
  const url = `<?= url('op.php') ?>?print_ticket_estado=1&po_id=${ticketData.po_id}&estado=${encodeURIComponent(ticketData.estado)}`;
  window.open(url, '_blank', 'width=800,height=600');
  <?php unset($_SESSION['abrir_ticket']); ?>
});
<?php endif; ?>

// Cargar operarios al abrir modal
function cargarOperarios() {
  fetch('<?= url('op.php?_ajax_empleados') ?>')
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('modal_operario_id');
      sel.innerHTML = '<option value="">-- Seleccionar operario --</option>';
      data.forEach(emp => {
        sel.innerHTML += `<option value="${emp.id}">${emp.nombre}</option>`;
      });
    })
    .catch(() => {
      document.getElementById('modal_operario_id').innerHTML = '<option value="">Error al cargar</option>';
    });
}

// Abrir modal cambiar estado
function abrirModalEstado(poId, poNumber, estadoActual) {
  document.getElementById('modal_po_id').value = poId;
  document.getElementById('modal_po_number').textContent = poNumber;
  document.getElementById('modal_estado_actual').value = estadoActual || 'Sin iniciar';
  document.getElementById('modal_nuevo_estado').value = '';
  document.getElementById('modal_advertencias').classList.add('d-none');
  
  cargarOperarios();
  
  const modal = new bootstrap.Modal(document.getElementById('modalCambiarEstado'));
  modal.show();
}

// Validar cambio de estado
document.getElementById('modal_nuevo_estado')?.addEventListener('change', function() {
  const estadoActual = document.getElementById('modal_estado_actual').value;
  const nuevoEstado = this.value;
  const advertencias = document.getElementById('modal_advertencias');
  
  let msgs = [];
  
  if (nuevoEstado === 'CORTE') {
    msgs.push('⚠️ Al avanzar a CORTE se descontará automáticamente el stock de perfiles, chapas y tubos del BOM.');
  }
  if (nuevoEstado === 'PINTURA') {
    msgs.push('⚠️ Requiere que el estado ARMADO esté aprobado por QC.');
    msgs.push('⚠️ Al avanzar a PINTURA se descontará automáticamente el stock de pinturas y químicos.');
  }
  if (nuevoEstado === 'ENSAMBLE') {
    msgs.push('⚠️ Al avanzar a ENSAMBLE se descontará automáticamente el stock de rodamientos, poleas, tornillería y tapizados.');
  }
  
  if (msgs.length > 0) {
    advertencias.innerHTML = msgs.join('<br>');
    advertencias.classList.remove('d-none');
  } else {
    advertencias.classList.add('d-none');
  }
});

// Ver timeline de producción
function verTimeline(poId, poNumber) {
  document.getElementById('timeline_po_number').textContent = poNumber;
  const content = document.getElementById('timeline_content');
  content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
  
  const modal = new bootstrap.Modal(document.getElementById('modalTimeline'));
  modal.show();
  
  fetch(`<?= url('op.php?_ajax_timeline') ?>&po_id=${poId}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        content.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
        return;
      }
      
      let html = '<div class="timeline">';
      
      if (!data.estados || data.estados.length === 0) {
        html += '<p class="text-muted">No hay historial de estados aún.</p>';
      } else {
        data.estados.forEach((estado, idx) => {
          const isCurrent = !estado.timestamp_fin;
          const isApproved = estado.aprobado_qc == 1;
          
          html += `
            <div class="timeline-item ${isCurrent ? 'active' : ''}">
              <div class="timeline-marker ${isCurrent ? 'bg-primary' : 'bg-success'}">
                ${idx + 1}
              </div>
              <div class="timeline-content">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 class="mb-1">${estado.estado}</h6>
                    <small class="text-muted">
                      Operario: ${estado.operario_nombre || 'N/A'}<br>
                      Inicio: ${estado.timestamp_inicio}<br>
                      ${estado.timestamp_fin ? 'Fin: ' + estado.timestamp_fin : '<span class="badge bg-primary">EN CURSO</span>'}
                    </small>
                  </div>
                  <div class="text-end">
                    ${isApproved ? '<span class="badge bg-success">✓ QC Aprobado</span>' : ''}
                    ${isCurrent && !isApproved && (estado.estado === 'ARMADO' || estado.estado === 'QC_FINAL') ? 
                      `<form method="POST" action="<?= url('op.php') ?>" class="d-inline" onsubmit="return confirm('¿Aprobar QC para este estado?')">
                        <input type="hidden" name="action" value="aprobar_qc">
                        <input type="hidden" name="state_id" value="${estado.id}">
                        <input type="hidden" name="redirect" value="<?= url('panel_produccion.php?estado=' . $filtro_estado) ?>">
                        <button type="submit" class="btn btn-sm btn-success">Aprobar QC</button>
                      </form>` : ''}
                  </div>
                </div>
                ${estado.notas ? `<div class="mt-2"><small><strong>Notas:</strong> ${estado.notas}</small></div>` : ''}
              </div>
            </div>
          `;
        });
      }
      
      html += '</div>';
      
      // CSS para timeline
      html += `
        <style>
          .timeline { position: relative; padding: 20px 0; }
          .timeline-item { position: relative; padding-left: 50px; padding-bottom: 30px; }
          .timeline-item:before { content: ''; position: absolute; left: 19px; top: 40px; bottom: -10px; width: 2px; background: #dee2e6; }
          .timeline-item:last-child:before { display: none; }
          .timeline-marker { position: absolute; left: 0; top: 0; width: 40px; height: 40px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
          .timeline-content { background: #f8f9fa; padding: 15px; border-radius: 8px; }
          .timeline-item.active .timeline-content { background: #e7f3ff; border: 2px solid #0d6efd; }
        </style>
      `;
      
      content.innerHTML = html;
    })
    .catch(err => {
      content.innerHTML = '<div class="alert alert-danger">Error al cargar el timeline</div>';
    });
}
</script>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
