<?php
// index.php
require_once __DIR__ . '/config/db.php';

// Obtener planes desde la base de datos
$planes = [];
try {
    $stmt = $pdo->query("SELECT id, nombre, descripcion, precio FROM plans ORDER BY precio ASC");
    $planes = $stmt->fetchAll();
} catch (Throwable $e) {
    $planes = [];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>EcoBici Puerto Barrios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap (general) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="cliente/styles/index.css">
  <!-- Font Awesome (iconos) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">EcoBici</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="#inicio">Inicio</a></li>
        <li class="nav-item"><a class="nav-link" href="#planes">Planes</a></li>
        <li class="nav-item"><a class="nav-link" href="register.php">Registrarse</a></li>
        <li class="nav-item"><a class="btn btn-success ms-lg-2" href="login.php"><i class="fa fa-right-to-bracket me-1"></i> Iniciar sesión</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero -->
<header id="inicio" class="py-5 bg-light">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <h1 class="display-5 fw-bold">EcoBici Puerto Barrios</h1>
        <p class="lead mb-4">
          Muévete rápido, económico y sin humo. Regístrate, elige tu membresía y empieza a rodar hoy.
        </p>
        <div class="d-flex gap-2">
          <a href="register.php" class="btn btn-primary btn-lg"><i class="fa fa-id-card me-2"></i>Crear cuenta</a>
          <a href="#planes" class="btn btn-outline-primary btn-lg"><i class="fa fa-bicycle me-2"></i>Ver planes</a>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ratio ratio-16x9 rounded-4 shadow-sm bg-white d-flex align-items-center justify-content-center">
          <div class="text-center p-4">
            <i class="fa-solid fa-person-biking fa-3x mb-3"></i>
            <h5 class="mb-1">Movilidad Sostenible</h5>
            <p class="text-muted mb-0">Cada kilómetro en bici ayuda a reducir emisiones.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Planes -->
<section id="planes" class="py-5 bg-body-tertiary">
  <div class="container">
    <h2 class="h3 mb-4">Planes de membresía</h2>
    <div class="row g-4">
      <?php if ($planes): foreach ($planes as $p): ?>
        <div class="col-md-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title mb-1"><?= htmlspecialchars($p['nombre']) ?></h5>
              <p class="card-text text-muted small mb-3"><?= nl2br(htmlspecialchars($p['descripcion'] ?? '')) ?></p>
              <div class="mt-auto d-flex align-items-center justify-content-between">
                <span class="fw-bold fs-5">Q <?= number_format((float)$p['precio'], 2) ?></span>
                <a class="btn btn-primary" href="register.php">Elegir</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div class="col-12">
          <div class="alert alert-warning">No hay planes cargados todavía.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="py-4 border-top mt-5">
  <div class="container text-center small text-muted">
    © <?= date('Y') ?> EcoBici Puerto Barrios — Movilidad sostenible.
  </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
