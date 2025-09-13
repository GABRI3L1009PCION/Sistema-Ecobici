<?php
// /ecobici/index.php
require_once __DIR__ . '/config/db.php';

// Cargar planes desde la BD
$planes = [];
try {
  $planes = $pdo->query("SELECT id, nombre, descripcion, precio FROM plans ORDER BY precio ASC")->fetchAll();
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

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Iconos -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- AOS (animaciones scroll) -->
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
  <!-- Tu CSS -->
  <link rel="stylesheet" href="cliente/styles/index.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top eco-navbar">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2 scrollto" href="#inicio">
      <i class="fa-solid fa-bicycle"></i> EcoBici
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link scrollto active" href="#inicio">Inicio</a></li>
        <li class="nav-item"><a class="nav-link scrollto" href="#mision">Misión</a></li>
        <li class="nav-item"><a class="nav-link scrollto" href="#como-funciona">Cómo funciona</a></li>
        <li class="nav-item"><a class="nav-link scrollto" href="#planes">Planes</a></li>
        <li class="nav-item ms-lg-3">
          <a class="btn btn-success btn-animate" href="login.php">
            <i class="fa fa-right-to-bracket me-1"></i> Iniciar sesión
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero -->
<header id="inicio" class="section-pad">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-6" data-aos="fade-right">
        <span class="badge text-bg-success mb-2">Prototipo EcoBici</span>
        <h1 class="display-5 fw-bold">Movilidad sostenible para Puerto Barrios</h1>
        <p class="lead mb-4">
          Regístrate, elige tu membresía y empieza a rodar. Consulta estaciones, crea rutas
          y visualiza tu impacto ambiental en tiempo real.
        </p>
        <div class="d-flex gap-2">
          <a href="register.php" class="btn btn-primary btn-lg btn-animate">
            <i class="fa fa-id-card me-2"></i>Crear cuenta
          </a>
          <a href="#planes" class="btn btn-outline-primary btn-lg btn-animate scrollto">
            <i class="fa fa-bicycle me-2"></i>Ver planes
          </a>
        </div>
      </div>

      <!-- Cuadro héroe (centrado + mensaje positivo) -->
      <div class="col-lg-6" data-aos="fade-left">
        <div class="ratio ratio-16x9 rounded-4 hero-card glass d-flex align-items-center justify-content-center">
          <div class="text-center p-4 hero-card-content">
            <i class="fa-solid fa-person-biking fa-3x mb-3"></i>
            <h5 class="mb-2">Pedaleá Puerto Barrios</h5>
            <p class="text-muted mb-0">Cada pedaleada suma verde al Malecón y a Santo Tomás. ¡Movete libre, movete en bici! 🌿</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Features principales -->
<section class="section-pad">
  <div class="container">
    <div class="row g-4 text-center">
      <div class="col-md-4">
        <div class="p-4 feature h-100">
          <i class="fa-solid fa-map-location-dot fa-2x mb-2"></i>
          <h5>Estaciones</h5>
          <p class="text-muted">Disponibilidad y cercanía en segundos.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-4 feature h-100">
          <i class="fa-solid fa-route fa-2x mb-2"></i>
          <h5>Rutas personalizadas</h5>
          <p class="text-muted">Planifica y guarda tus recorridos.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-4 feature h-100">
          <i class="fa-solid fa-leaf fa-2x mb-2"></i>
          <h5>Impacto ambiental</h5>
          <p class="text-muted">Mide tu CO₂ reducido.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Misión / Visión -->
<section id="mision" class="section-pad bg-white">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-6" data-aos="zoom-in">
        <h2 class="h3 fw-bold mb-3">Nuestra misión</h2>
        <p class="mb-3">
          Impulsar la movilidad urbana sostenible mediante un sistema de bicicletas compartidas
          accesible, seguro e inteligente, conectando estaciones y usuarios con tecnología simple y útil.
        </p>
        <h3 class="h5 fw-semibold">Visión</h3>
        <p class="mb-0">
          Convertir a Puerto Barrios en una ciudad referente de movilidad limpia, con datos que
          inspiren decisiones y hábitos saludables.
        </p>
      </div>
      <div class="col-lg-6" data-aos="zoom-in">
        <div class="row g-3">
          <div class="col-6">
            <div class="p-4 border rounded-4 h-100 text-center feature">
              <i class="fa-solid fa-leaf fa-2x mb-2"></i>
              <div class="fw-semibold">Menos CO₂</div>
              <small class="text-muted">Conciencia ambiental</small>
            </div>
          </div>
          <div class="col-6">
            <div class="p-4 border rounded-4 h-100 text-center feature">
              <i class="fa-solid fa-route fa-2x mb-2"></i>
              <div class="fw-semibold">Rutas seguras</div>
              <small class="text-muted">Planifica y guarda</small>
            </div>
          </div>
          <div class="col-6">
            <div class="p-4 border rounded-4 h-100 text-center feature">
              <i class="fa-solid fa-map-location-dot fa-2x mb-2"></i>
              <div class="fw-semibold">Estaciones</div>
              <small class="text-muted">Disponibilidad en vivo</small>
            </div>
          </div>
          <div class="col-6">
            <div class="p-4 border rounded-4 h-100 text-center feature">
              <i class="fa-solid fa-shield-heart fa-2x mb-2"></i>
              <div class="fw-semibold">Confianza</div>
              <small class="text-muted">Reporte de daños</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Cómo funciona -->
<section id="como-funciona" class="section-pad bg-white">
  <div class="container">
    <h2 class="h3 fw-bold mb-4 text-center">¿Cómo funciona?</h2>
    <div class="row g-4">
      <div class="col-md-4" data-aos="flip-left">
        <div class="p-4 step h-100 text-center">
          <div class="display-6 fw-bold text-success">1</div>
          <h5 class="mt-2">Regístrate</h5>
          <p class="text-muted">Crea tu cuenta y elige un plan de membresía.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="flip-left" data-aos-delay="150">
        <div class="p-4 step h-100 text-center">
          <div class="display-6 fw-bold text-success">2</div>
          <h5 class="mt-2">Activa tu plan</h5>
          <p class="text-muted">Realiza el pago y desbloquea el uso de bicicletas.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="flip-left" data-aos-delay="300">
        <div class="p-4 step h-100 text-center">
          <div class="display-6 fw-bold text-success">3</div>
          <h5 class="mt-2">Empieza a rodar</h5>
          <p class="text-muted">Toma una bici en una estación y registra tus recorridos.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Planes + Comparativa -->
<section id="planes" class="section-pad bg-white">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h2 class="h3 mb-0">Planes de membresía</h2>
    </div>

    <?php
    // Normalizar a: Paseo, Ruta, Maratón (si existen)
    $ordenDeseado = ['paseo','ruta','maratón','maraton'];
    $colPlans = [];
    foreach ($planes as $p) {
      $key = mb_strtolower(trim($p['nombre']), 'UTF-8');
      $colPlans[$key] = $p;
    }
    $cols = [];
    foreach ($ordenDeseado as $k) {
      if (isset($colPlans[$k])) { $cols[] = $colPlans[$k]; }
    }
    ?>

    <!-- Tarjetas -->
    <div class="row g-4">
      <?php if (!empty($cols)): ?>
        <?php foreach ($cols as $p): ?>
          <?php $n = mb_strtolower($p['nombre'],'UTF-8'); ?>
          <div class="col-md-4" data-aos="fade-up">
            <div class="card h-100 shadow-sm rounded-4">
              <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <h5 class="card-title mb-0"><?= htmlspecialchars($p['nombre']) ?></h5>
                  <?php $badge = ($n==='ruta') ? 'Popular' : (($n==='maratón'||$n==='maraton') ? 'Pro' : 'Inicio'); ?>
                  <span class="badge text-bg-success"><?= $badge ?></span>
                </div>
                <p class="card-text text-muted small mb-3">
                  <?= nl2br(htmlspecialchars($p['descripcion'] ?? '')) ?>
                </p>
                <ul class="list-unstyled small mb-4">
                  <?php if ($n === 'paseo'): ?>
                    <li><i class="fa fa-check text-success me-2"></i>Bicis tradicionales</li>
                    <li><i class="fa fa-check text-success me-2"></i>Hasta 2 horas diarias</li>
                    <li><i class="fa fa-check text-success me-2"></i>Estaciones del malecón y parques</li>
                    <li><i class="fa fa-check text-success me-2"></i>CO₂ reducido visible</li>
                  <?php elseif ($n === 'ruta'): ?>
                    <li><i class="fa fa-check text-success me-2"></i>Tradicional y eléctrica</li>
                    <li><i class="fa fa-check text-success me-2"></i>Hasta 4 horas diarias</li>
                    <li><i class="fa fa-check text-success me-2"></i>Rutas a Santo Tomás (recomendadas)</li>
                    <li><i class="fa fa-check text-success me-2"></i>Historial + estadísticas básicas</li>
                  <?php else: /* Maratón */ ?>
                    <li><i class="fa fa-check text-success me-2"></i>Todas las bicis (tradicional/eléctrica)</li>
                    <li><i class="fa fa-check text-success me-2"></i>Uso ilimitado diario</li>
                    <li><i class="fa fa-check text-success me-2"></i>Rutas avanzadas (entrenos/eventos)</li>
                    <li><i class="fa fa-check text-success me-2"></i>Prioridad carga + Puntos verdes</li>
                  <?php endif; ?>
                </ul>
                <div class="mt-auto d-flex align-items-center justify-content-between">
                  <span class="price">Q <?= number_format((float)$p['precio'], 2) ?></span>
                  <a class="btn btn-primary btn-animate" href="register.php?plan_id=<?= (int)$p['id'] ?>">Elegir</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="alert alert-warning">No hay planes cargados todavía.</div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Comparativa -->
    <?php if (count($cols) >= 2): ?>
      <div class="mt-5">
        <h3 class="h5 mb-3">Comparar planes</h3>
        <div class="table-responsive">
          <table class="table align-middle table-animate">
            <thead>
              <tr>
                <th class="text-muted small">Beneficio</th>
                <?php foreach ($cols as $p): ?>
                  <th class="text-center"><?= htmlspecialchars($p['nombre']) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php
              $chk = '<i class="fa fa-check text-success"></i>';
              $x   = '<i class="fa fa-xmark text-muted"></i>';
              $nn  = array_map(fn($p)=>mb_strtolower($p['nombre'],'UTF-8'), $cols);
              $filas = [
                ['label'=>'Bicicletas tradicionales','vals'=>array_fill(0,count($cols),$chk)],
                ['label'=>'Bicicletas eléctricas','vals'=>array_map(fn($n)=>($n==='ruta'||$n==='maratón'||$n==='maraton')?$chk:$x,$nn)],
                ['label'=>'Horas diarias','vals'=>array_map(fn($n)=>$n==='paseo'?'Hasta 2h':($n==='ruta'?'Hasta 4h':'Ilimitado'),$nn)],
                ['label'=>'Rutas personalizadas','vals'=>array_map(fn($n)=>$n==='paseo'?'Básicas':($n==='ruta'?'Intermedias':'Avanzadas'),$nn)],
                ['label'=>'Cobertura local (Puerto Barrios)','vals'=>array_fill(0,count($cols),'Centro & barrios')],
                ['label'=>'Rutas hacia Santo Tomás','vals'=>array_map(fn($n)=>($n==='ruta'||$n==='maratón'||$n==='maraton')?$chk:$x,$nn)],
                ['label'=>'Prioridad en estaciones de carga','vals'=>array_map(fn($n)=>($n==='maratón'||$n==='maraton')?$chk:$x,$nn)],
                ['label'=>'Cálculo de CO₂ reducido','vals'=>array_fill(0,count($cols),$chk)],
                ['label'=>'Puntos verdes (recompensas)','vals'=>array_map(fn($n)=>($n==='maratón'||$n==='maraton')?$chk:$x,$nn)],
              ];
              ?>
              <?php foreach ($filas as $f): ?>
                <tr>
                  <td class="text-muted"><?= htmlspecialchars($f['label']) ?></td>
                  <?php foreach ($f['vals'] as $v): ?>
                    <td class="text-center"><?= $v ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Footer -->
<footer class="py-4 border-top">
  <div class="container text-center small text-muted">
    © <?= date('Y') ?> EcoBici Puerto Barrios — Movilidad sostenible.
  </div>
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
  AOS.init({ once:true, duration:700, easing:'ease-out' });

  // --- Scroll suave con offset del header ---
  const header = document.querySelector('.eco-navbar');
  const offset = () => (header?.offsetHeight || 0) + 8;

  document.querySelectorAll('a.scrollto[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const id = a.getAttribute('href');
      const el = document.querySelector(id);
      if (!el) return;
      e.preventDefault();
      const top = el.getBoundingClientRect().top + window.pageYOffset - offset();
      window.scrollTo({ top, behavior: 'smooth' });
    });
  });

  // --- Scrollspy (activa link según sección visible) ---
  const sections = ['#inicio','#mision','#como-funciona','#planes']
    .map(s => document.querySelector(s)).filter(Boolean);
  const navLinks = [...document.querySelectorAll('.nav-link.scrollto')];

  const spy = () => {
    const y = window.scrollY + offset() + 10;
    let current = sections[0]?.id || '';
    sections.forEach(sec => { if (y >= sec.offsetTop) current = sec.id; });
    navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#'+current));
  };
  window.addEventListener('scroll', spy); spy();

  // --- Navbar shrink/shadow al hacer scroll ---
  const shrink = () => {
    if (window.scrollY > 12) header?.classList.add('scrolled');
    else header?.classList.remove('scrolled');
  };
  window.addEventListener('scroll', shrink); shrink();

  // --- Micro-animación al hacer click en botones (onda/ripple simple) ---
  document.querySelectorAll('.btn-animate').forEach(btn => {
    btn.addEventListener('click', function(){
      this.classList.remove('clicked'); // reinicia
      void this.offsetWidth;            // reflow
      this.classList.add('clicked');
      setTimeout(() => this.classList.remove('clicked'), 450);
    });
  });

  // --- Efecto tilt suave en cards ---
  const tilt = (el, k=4) => {
    el.addEventListener('mousemove', e=>{
      const r = el.getBoundingClientRect();
      const x = (e.clientX - r.left)/r.width - .5;
      const y = (e.clientY - r.top)/r.height - .5;
      el.style.transform = `rotateX(${(-y*k)}deg) rotateY(${(x*k)}deg) translateY(-6px)`;
    });
    ['mouseleave','blur'].forEach(evt=>el.addEventListener(evt,()=>{ el.style.transform=''; }));
  };
  document.querySelectorAll('.hero-card,.feature,.step,.card').forEach(el=>tilt(el,4));
</script>
</body>
</html>
