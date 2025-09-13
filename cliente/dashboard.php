<?php
// /ecobici/cliente/dashboard.php
require_once __DIR__ . '/cliente_boot.php';

$BASE       = '/ecobici';
$clientName = $_SESSION['user_name'] ?? ($_SESSION['user']['name'] ?? 'Cliente');
$userId     = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));

// ===== Ejemplos de consultas sencillas (opcionales) =====
// Ajusta los nombres de tus tablas/columnas si difieren
$membership = null;
$ridesCount = 0;
$lastRide   = null;
$payments   = 0.00;

try {
    // Estado de membresía (la más reciente activa/vigente)
    $st = $pdo->prepare("
        SELECT plan, monto, inicio, fin, estado
        FROM memberships
        WHERE user_id = ? 
        ORDER BY fin DESC
        LIMIT 1
    ");
    $st->execute([$userId]);
    $membership = $st->fetch(PDO::FETCH_ASSOC);

    // Conteo de viajes
    if ($pdo->query("SHOW TABLES LIKE 'rides'")->rowCount()) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE user_id=?");
        $st->execute([$userId]);
        $ridesCount = (int)$st->fetchColumn();

        // Último viaje
        $st = $pdo->prepare("
            SELECT r.id, r.created_at, r.station_start_id, r.station_end_id, r.bike_id
            FROM rides r
            WHERE r.user_id=?
            ORDER BY r.created_at DESC
            LIMIT 1
        ");
        $st->execute([$userId]);
        $lastRide = $st->fetch(PDO::FETCH_ASSOC);
    }

    // Total pagado (ejemplo)
    if ($pdo->query("SHOW TABLES LIKE 'payments'")->rowCount()) {
        $st = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM payments WHERE user_id=?");
        $st->execute([$userId]);
        $payments = (float)$st->fetchColumn();
    }
} catch (Throwable $e) {
    // En desarrollo puedes mostrar el error:
    // echo "<pre>Error: " . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Mi panel | EcoBici</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap + Iconos -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Consistencia visual con login/register -->
  <link rel="stylesheet" href="<?= $BASE ?>/cliente/styles/register.css">
  <style>
    .card-smooth { border:0; border-radius:1rem; box-shadow:0 4px 16px rgba(0,0,0,.06); }
    .metric { font-size: 1.75rem; font-weight: 800; }
    .btn-pill { border-radius: 999px; }
  </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="<?= $BASE ?>/cliente/dashboard.php">
      <i class="fa fa-bicycle me-2"></i>EcoBici
    </a>

    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="text-white small d-none d-md-inline">
        <i class="fa fa-user-circle me-1"></i><?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?>
      </span>
      <a class="btn btn-sm btn-outline-light btn-pill" href="<?= $BASE ?>/logout.php">
        <i class="fa fa-right-from-bracket me-1"></i> Salir
      </a>
    </div>
  </div>
</nav>

<main class="container my-4">
  <div class="row g-3 mb-3">
    <div class="col-12">
      <div class="card card-smooth">
        <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between">
          <div>
            <h1 class="h4 mb-1">¡Hola, <?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p class="text-muted mb-0">Bienvenido a tu panel de EcoBici Puerto Barrios.</p>
          </div>
          <div class="mt-3 mt-md-0 d-flex gap-2">
            <a href="<?= $BASE ?>/cliente/perfil.php" class="btn btn-outline-success btn-pill">
              <i class="fa fa-id-badge me-1"></i> Mi perfil
            </a>
            <a href="<?= $BASE ?>/cliente/membresia.php" class="btn btn-success btn-pill">
              <i class="fa fa-crown me-1"></i> Membresía
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Métricas rápidas -->
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card card-smooth h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Mis viajes</div>
              <div class="metric"><?= $ridesCount ?></div>
            </div>
            <i class="fa fa-route fa-lg text-success"></i>
          </div>
          <?php if ($lastRide): ?>
            <div class="text-muted small mt-2">
              Último: <?= htmlspecialchars($lastRide['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php else: ?>
            <div class="text-muted small mt-2">Aún no has realizado viajes.</div>
          <?php endif; ?>
          <a href="<?= $BASE ?>/cliente/viajes.php" class="btn btn-outline-success btn-sm btn-pill mt-3">
            Ver historial
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card card-smooth h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Membresía</div>
              <div class="metric">
                <?= htmlspecialchars($membership['plan'] ?? 'Sin plan', ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
            <i class="fa fa-crown fa-lg text-warning"></i>
          </div>
          <div class="text-muted small mt-2">
            <?php if ($membership): ?>
              Estado: <span class="text-capitalize"><?= htmlspecialchars($membership['estado'], ENT_QUOTES, 'UTF-8') ?></span><br>
              Vence: <?= htmlspecialchars($membership['fin'], ENT_QUOTES, 'UTF-8') ?>
            <?php else: ?>
              Aún no cuentas con una membresía activa.
            <?php endif; ?>
          </div>
          <a href="<?= $BASE ?>/cliente/membresia.php" class="btn btn-success btn-sm btn-pill mt-3">
            Gestionar membresía
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card card-smooth h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="text-muted small">Pagos totales</div>
              <div class="metric">Q <?= number_format($payments, 2) ?></div>
            </div>
            <i class="fa fa-receipt fa-lg text-primary"></i>
          </div>
          <div class="text-muted small mt-2">Consulta tus recibos y métodos de pago.</div>
          <a href="<?= $BASE ?>/cliente/pagos.php" class="btn btn-outline-success btn-sm btn-pill mt-3">
            Ver pagos
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Accesos rápidos -->
  <div class="row g-3 mt-1">
    <div class="col-lg-6">
      <div class="card card-smooth h-100">
        <div class="card-body">
          <h2 class="h6 mb-2"><i class="fa fa-map-location-dot me-2"></i> Estaciones cercanas</h2>
          <p class="text-muted small mb-2">Próximamente: mapa/listado con disponibilidad.</p>
          <a href="<?= $BASE ?>/cliente/estaciones.php" class="btn btn-outline-success btn-sm btn-pill">
            Ver estaciones
          </a>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card card-smooth h-100">
        <div class="card-body">
          <h2 class="h6 mb-2"><i class="fa fa-shield-heart me-2"></i> Soporte</h2>
          <p class="text-muted small mb-2">¿Dudas o ayuda con tu cuenta?</p>
          <a href="<?= $BASE ?>/cliente/soporte.php" class="btn btn-outline-success btn-sm btn-pill">
            Contactar soporte
          </a>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
