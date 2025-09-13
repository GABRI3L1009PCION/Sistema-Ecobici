<?php
// /ecobici/cliente/membresia.php
session_start();

// Marca navbar activo
$current = 'membresia';

// Catálogo con los textos originales de tus planes
$planes = [
  [
    'slug'   => 'paseo',
    'titulo' => 'Paseo',
    'badge'  => 'Inicio',
    'desc'   => 'Ideal para pasear por la ciudad.',
    'precio' => 30.00,
    'features' => [
      'Bicis tradicionales',
      'Hasta 2 horas diarias',
      'Estaciones del malecón y parques',
      'CO₂ reducido visible',
    ],
  ],
  [
    'slug'   => 'ruta',
    'titulo' => 'Ruta',
    'badge'  => 'Popular',
    'desc'   => 'Para rodadas medias con rutas recomendadas.',
    'precio' => 45.00,
    'features' => [
      'Tradicional y eléctrica',
      'Hasta 4 horas diarias',
      'Rutas a Santo Tomás (recomendadas)',
      'Historial + estadísticas básicas',
    ],
  ],
  [
    'slug'   => 'maraton',
    'titulo' => 'Maratón',
    'badge'  => 'Pro',
    'desc'   => 'Para entrenos intensos y eventos.',
    'precio' => 60.00,
    'features' => [
      'Todas las bicis (tradicional/eléctrica)',
      'Uso ilimitado diario',
      'Rutas avanzadas (entrenos/eventos)',
      'Prioridad carga + Puntos verdes',
    ],
  ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Membresías | EcoBici</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Tu CSS -->
  <link rel="stylesheet" href="styles/membresia.css">
</head>
<body>

  <!-- Navbar con botón de regresar -->
  <nav class="navbar navbar-expand-lg eco-navbar sticky-top bg-white border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="/ecobici/index.php">
        <img src="/ecobici/cliente/styles/logo.jpg" alt="EcoBici" height="38">
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navEco" aria-controls="navEco" aria-expanded="false" aria-label="Menú">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navEco">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link <?php echo ($current==='inicio'?'active':'');?>" href="/ecobici/index.php">Inicio</a></li>
          <li class="nav-item"><a class="nav-link <?php echo ($current==='membresia'?'active':'');?>" href="/ecobici/cliente/membresia.php">Membresía</a></li>
          <li class="nav-item"><a class="nav-link" href="/ecobici/logout.php">Salir</a></li>
        </ul>

        <!-- Botón volver al dashboard -->
        <div class="ms-lg-3 mt-3 mt-lg-0">
          <a href="/ecobici/cliente/dashboard.php" class="btn btn-outline-success btn-sm">
            ← Volver al dashboard
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Planes -->
  <section class="section-pad bg-white">
    <div class="container">
      <h1 class="text-center display-6 fw-bold mb-4">Planes de membresía</h1>
      <div class="row g-4 justify-content-center">

        <?php foreach ($planes as $p): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card plan p-4 h-100">
              <?php if(!empty($p['badge'])): ?>
                <span class="plan-badge"><?php echo htmlspecialchars($p['badge']); ?></span>
              <?php endif; ?>

              <h3 class="card-title mb-1"><?php echo htmlspecialchars($p['titulo']); ?></h3>
              <p class="mb-2"><?php echo htmlspecialchars($p['desc']); ?></p>

              <!-- FEATURES -->
              <ul class="plan-features">
                <?php foreach ($p['features'] as $f): ?>
                  <li><?php echo htmlspecialchars($f); ?></li>
                <?php endforeach; ?>
              </ul>

              <!-- Precio -->
              <div class="price fs-5 mb-3">Q <?php echo number_format($p['precio'], 2); ?></div>

              <div class="mt-auto">
                <a href="/ecobici/cliente/pago.php?plan=<?php echo urlencode($p['slug']); ?>"
                   class="btn btn-primary btn-animate w-100">
                  Elegir este plan
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="py-4 mt-4 border-top">
    <div class="container text-center small">
      © 2025 EcoBici · Puerto Barrios, Izabal
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
