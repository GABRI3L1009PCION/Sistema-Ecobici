<?php
// /ecobici/cliente/historial.php
declare(strict_types=1);
require_once __DIR__ . '/cliente_boot.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$userId = (int)($_SESSION['user']['id'] ?? 0);
if (!$userId) { header('Location: /ecobici/login.php'); exit; }

// Flash messages (opcionales)
$flash_ok = $_SESSION['flash_ok'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

// 1) Viaje activo (end_at IS NULL)
$active = null;
try {
  $q = $pdo->prepare("
    SELECT t.id, t.bike_id, t.start_station_id, t.start_at, b.codigo AS bike_codigo, b.tipo AS bike_tipo,
           s1.nombre AS start_nombre
    FROM trips t
    JOIN bikes b ON b.id = t.bike_id
    LEFT JOIN stations s1 ON s1.id = t.start_station_id
    WHERE t.user_id = ? AND t.end_at IS NULL
    ORDER BY t.id DESC
    LIMIT 1
  ");
  $q->execute([$userId]);
  $active = $q->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { $active = null; }

// 2) Estaciones para combo de cierre
$stations = [];
try {
  $st = $pdo->query("SELECT id, nombre, tipo FROM stations ORDER BY nombre ASC");
  $stations = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $stations = []; }

// 3) Últimos viajes
$items = [];
try {
  $h = $pdo->prepare("
    SELECT t.*, b.codigo AS bike_codigo, b.tipo AS bike_tipo,
           s1.nombre AS start_nombre, s2.nombre AS end_nombre
    FROM trips t
    JOIN bikes b   ON b.id = t.bike_id
    LEFT JOIN stations s1 ON s1.id = t.start_station_id
    LEFT JOIN stations s2 ON s2.id = t.end_station_id
    WHERE t.user_id = ?
    ORDER BY t.id DESC
    LIMIT 50
  ");
  $h->execute([$userId]);
  $items = $h->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $items = []; }

// Helper
function fmtDT(?string $dt): string {
  return $dt ? date('d/m/Y H:i', strtotime($dt)) : '—';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Historial | EcoBici</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/ecobici/cliente/styles/dashboard.css">
  <style>
    :root{ --eco-green:#1DAA4B; --eco-border:#dcf5e6; }
    body{ background:#f6fff9; }
    .navbar { background:#eafff0; border-bottom:1px solid var(--eco-border); }
    .brand { color:var(--eco-green); }
    .card{ border:1px solid var(--eco-border); box-shadow:0 6px 18px rgba(0,0,0,.05); }
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
  <h4 class="mb-3">Historial de viajes</h4>

  <?php if ($flash_ok): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($flash_error) ?></div>
  <?php endif; ?>

  <!-- Viaje activo -->
  <div class="card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center">
      <h6 class="mb-0">Viaje activo</h6>
    </div>
    <hr class="my-2">
    <?php if ($active): ?>
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
          <small class="text-muted d-block">Bicicleta</small>
          <div class="fw-semibold"><?= htmlspecialchars($active['bike_codigo']) ?> (<?= htmlspecialchars($active['bike_tipo']) ?>)</div>
        </div>
        <div class="col-12 col-md-3">
          <small class="text-muted d-block">Inicio</small>
          <div class="fw-semibold"><?= htmlspecialchars($active['start_nombre'] ?: '—') ?></div>
          <div class="text-muted small"><?= fmtDT($active['start_at']) ?></div>
        </div>
        <div class="col-12 col-md-6">
          <form class="row g-2" method="post" action="/ecobici/cliente/finalizar_viaje.php">
            <input type="hidden" name="trip_id" value="<?= (int)$active['id'] ?>">
            <div class="col-12 col-md-8">
              <label class="form-label mb-1">Finalizar en estación</label>
              <select name="end_station_id" class="form-select" required>
                <option value="">Elige estación...</option>
                <?php foreach($stations as $s): ?>
                  <option value="<?= (int)$s['id'] ?>">
                    <?= htmlspecialchars($s['nombre']) ?> (<?= htmlspecialchars(strtoupper($s['tipo'])) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-4 d-grid">
              <button class="btn btn-success"><i class="bi bi-flag-checkered"></i> Finalizar viaje</button>
            </div>
          </form>
        </div>
      </div>
    <?php else: ?>
      <p class="text-muted mb-0">No tienes viajes activos.</p>
    <?php endif; ?>
  </div>

  <!-- Historial -->
  <div class="card">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Bici</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Distancia (km)</th>
            <th>CO₂ (kg)</th>
            <th>Costo</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Aún no hay viajes.</td></tr>
        <?php else: foreach ($items as $i): ?>
          <tr>
            <td><?= (int)$i['id'] ?></td>
            <td><?= htmlspecialchars($i['bike_codigo']) ?> <span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($i['bike_tipo']) ?></span></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($i['start_nombre'] ?: '—') ?></div>
              <div class="text-muted small"><?= fmtDT($i['start_at']) ?></div>
            </td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($i['end_nombre'] ?: '—') ?></div>
              <div class="text-muted small"><?= fmtDT($i['end_at']) ?></div>
            </td>
            <td><?= number_format((float)$i['distancia_km'], 2) ?></td>
            <td><?= number_format((float)$i['co2_kg'], 3) ?></td>
            <td>Q <?= number_format((float)$i['costo'], 2) ?></td>
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
