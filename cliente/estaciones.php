<?php
// /ecobici/cliente/estaciones.php
declare(strict_types=1);
require_once __DIR__ . '/cliente_boot.php';

$userId = (int)($_SESSION['user']['id'] ?? 0);

// Filtros simples
$buscar = trim($_GET['q'] ?? '');
$tipo   = $_GET['tipo'] ?? ''; // dock | punto | (vacío = todos)

$params = [];
$where  = [];
if ($buscar !== '') {
    $where[] = "s.nombre LIKE ?";
    $params[] = "%{$buscar}%";
}
if (in_array($tipo, ['dock','punto'], true)) {
    $where[] = "s.tipo = ?";
    $params[] = $tipo;
}
$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// Query: estaciones + conteos de bicis por estado
$sql = "
SELECT
  s.id, s.nombre, s.tipo, s.lat, s.lng, s.capacidad,
  SUM(CASE WHEN b.estado = 'disponible'   THEN 1 ELSE 0 END) AS disponibles,
  SUM(CASE WHEN b.estado = 'uso'          THEN 1 ELSE 0 END) AS en_uso,
  SUM(CASE WHEN b.estado = 'mantenimiento'THEN 1 ELSE 0 END) AS en_mant
FROM stations s
LEFT JOIN bikes b ON b.station_id = s.id
{$whereSql}
GROUP BY s.id
ORDER BY s.nombre ASC
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Para detalle modal: listar bicis por estación
function getBikesByStation(PDO $pdo, int $stationId): array {
    $q = $pdo->prepare("
      SELECT id, codigo, tipo, estado
      FROM bikes
      WHERE station_id = ?
      ORDER BY estado, codigo
    ");
    $q->execute([$stationId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Estaciones | EcoBici</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- CSS local -->
  <link rel="stylesheet" href="/ecobici/cliente/styles/dashboard.css">

  <style>
    :root{ --eco-green:#1DAA4B; --eco-border:#dcf5e6; --eco-soft:#ecfff3; }
    body{ background:#f6fff9; }
    .navbar { background:#eafff0; border-bottom:1px solid var(--eco-border); }
    .brand { color:var(--eco-green); }
    .card{ border:1px solid var(--eco-border); box-shadow:0 6px 18px rgba(0,0,0,.05); }
    .badge-dot{ display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
    .dot-ok{ background:#17c964; } .dot-use{ background:#f5a524; } .dot-mt{ background:#f31260; }
    .filter-pill{ background:#ecfff3; border:1px dashed #c9efd7; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand fw-bold brand" href="/ecobici/index.php">EcoBici</a>
    <div class="ms-auto">
      <a class="btn btn-sm btn-outline-success" href="/ecobici/cliente/dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Estaciones</h4>
    <div class="text-muted small">Disponibilidad por estación</div>
  </div>

  <!-- Filtros -->
  <form class="card p-3 mb-3" method="get">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-6">
        <label class="form-label">Buscar por nombre</label>
        <input type="text" name="q" class="form-control" placeholder="Ej. Malecón, Centro..."
               value="<?= htmlspecialchars($buscar) ?>">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Tipo</label>
        <select class="form-select" name="tipo">
          <option value="">Todos</option>
          <option value="dock"  <?= $tipo==='dock'?'selected':'' ?>>Dock</option>
          <option value="punto" <?= $tipo==='punto'?'selected':'' ?>>Punto</option>
        </select>
      </div>
      <div class="col-6 col-md-3 d-grid">
        <button class="btn btn-success"><i class="bi bi-search"></i> Filtrar</button>
      </div>
    </div>
    <?php if ($buscar || $tipo): ?>
      <div class="mt-2">
        <span class="badge filter-pill text-success">
          <i class="bi bi-funnel"></i> Filtros aplicados
        </span>
        <a href="/ecobici/cliente/estaciones.php" class="ms-2 small">Limpiar</a>
      </div>
    <?php endif; ?>
  </form>

  <!-- Tabla -->
  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Estación</th>
            <th class="text-center">Tipo</th>
            <th class="text-center">Capacidad</th>
            <th class="text-center">
              <span class="badge-dot dot-ok"></span>Disponibles
            </th>
            <th class="text-center">
              <span class="badge-dot dot-use"></span>En uso
            </th>
            <th class="text-center">
              <span class="badge-dot dot-mt"></span>Mantto.
            </th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No hay estaciones que coincidan.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <?php
              $sid  = (int)$r['id'];
              $disp = (int)$r['disponibles'];
              $uso  = (int)$r['en_uso'];
              $mt   = (int)$r['en_mant'];
              $cap  = (int)$r['capacidad'];
              $pct  = $cap > 0 ? max(0,min(100,(int)round(($disp/$cap)*100))) : 0;
              $bikes = getBikesByStation($pdo, $sid); // para el modal
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($r['nombre']) ?></div>
                <div class="small text-muted">Lat: <?= htmlspecialchars($r['lat']) ?>, Lng: <?= htmlspecialchars($r['lng']) ?></div>
              </td>
              <td class="text-center">
                <span class="badge bg-success-subtle text-success"><?= htmlspecialchars(strtoupper($r['tipo'])) ?></span>
              </td>
              <td class="text-center"><?= $cap ?></td>
              <td class="text-center"><?= $disp ?></td>
              <td class="text-center"><?= $uso ?></td>
              <td class="text-center"><?= $mt ?></td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-2">
                  <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#m<?= $sid ?>">
                    <i class="bi bi-eye"></i> Ver bicis
                  </button>
                  <a class="btn btn-sm btn-success" href="/ecobici/cliente/seleccionar_bici.php?station_id=<?= $sid ?>">
                    <i class="bi bi-bicycle"></i> Usar bici
                  </a>
                </div>
                <!-- Modal detalle bicis -->
                <div class="modal fade" id="m<?= $sid ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h6 class="modal-title">Bicicletas en <?= htmlspecialchars($r['nombre']) ?></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <?php if (!$bikes): ?>
                          <p class="text-muted mb-0">No hay bicicletas registradas en esta estación.</p>
                        <?php else: ?>
                          <div class="table-responsive">
                            <table class="table table-sm align-middle">
                              <thead>
                                <tr>
                                  <th>Código</th><th>Tipo</th><th>Estado</th><th class="text-end">Acción</th>
                                </tr>
                              </thead>
                              <tbody>
                              <?php foreach ($bikes as $b): ?>
                                <tr>
                                  <td><?= htmlspecialchars($b['codigo']) ?></td>
                                  <td><span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($b['tipo']) ?></span></td>
                                  <td>
                                    <?php
                                      $state = $b['estado'];
                                      $badge = $state==='disponible' ? 'bg-success' : ($state==='uso' ? 'bg-warning text-dark' : 'bg-danger');
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($state) ?></span>
                                  </td>
                                  <td class="text-end">
                                    <?php if ($b['estado']==='disponible'): ?>
                                      <a class="btn btn-sm btn-success" href="/ecobici/cliente/seleccionar_bici.php?bike_id=<?= (int)$b['id'] ?>">
                                        Elegir
                                      </a>
                                    <?php else: ?>
                                      <button class="btn btn-sm btn-outline-secondary" disabled>No disponible</button>
                                    <?php endif; ?>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        <?php endif; ?>
                      </div>
                      <div class="modal-footer">
                        <button class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- /Modal -->
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
