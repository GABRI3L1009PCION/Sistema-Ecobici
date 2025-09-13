<?php
// /ecobici/cliente/membresia.php
session_start();
require_once __DIR__ . '/../config/db.php';

// Solo clientes logueados
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'cliente') {
    header("Location: /ecobici/login.php");
    exit;
}

// Obtener planes de la BD
$planes = [];
try {
    $st = $pdo->query("SELECT id, nombre, descripcion, precio FROM plans ORDER BY precio ASC");
    $planes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $planes = [];
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Membresías | EcoBici</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/ecobici/index.css" rel="stylesheet">
</head>
<body>

<!-- ========== HEADER (copiado de index.php) ========== -->
<nav class="navbar navbar-expand-lg eco-navbar fixed-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/ecobici/index.php">EcoBici</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="/ecobici/index.php">Inicio</a></li>
        <li class="nav-item"><a class="nav-link active" href="/ecobici/cliente/membresia.php">Membresía</a></li>
        <li class="nav-item"><a class="nav-link" href="/ecobici/logout.php">Salir</a></li>
      </ul>
    </div>
  </div>
</nav>

<main class="container section-pad" style="padding-top:6rem;">
  <h2 class="text-center mb-5">Planes de Membresía</h2>

  <?php if (empty($planes)): ?>
    <div class="alert alert-warning text-center">No hay planes disponibles en este momento.</div>
  <?php else: ?>
    <div class="row justify-content-center">
      <?php foreach ($planes as $p): ?>
        <div class="col-md-4 mb-4">
          <div class="card h-100 shadow-sm text-center">
            <div class="card-body">
              <h5 class="card-title"><?= e($p['nombre']) ?></h5>
              <p class="card-text text-muted"><?= e($p['descripcion'] ?? '') ?></p>
              <p class="price fs-3">Q <?= number_format($p['precio'], 2) ?></p>
              <a href="/ecobici/register.php?plan_id=<?= e($p['id']) ?>" 
                 class="btn btn-primary btn-animate">Elegir este plan</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<!-- ========== FOOTER (copiado de index.php) ========== -->
<footer class="border-top py-4 mt-5">
  <div class="container text-center text-muted small">
    © <?= date("Y") ?> EcoBici · Puerto Barrios, Izabal
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// animación ripple en botones
document.querySelectorAll('.btn-animate').forEach(btn=>{
  btn.addEventListener('click',function(){
    this.classList.remove('clicked');
    void this.offsetWidth; // reflow
    this.classList.add('clicked');
  });
});
</script>
</body>
</html>
