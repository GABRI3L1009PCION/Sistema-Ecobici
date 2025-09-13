<?php
// /ecobici/cliente/pago.php
session_start();
if (empty($_SESSION['user']['id'])) {
  header('Location: /ecobici/login.php'); exit;
}
$userId = (int)$_SESSION['user']['id'];

require_once __DIR__ . '/../config/db.php';

/* -------------------- Resolver plan -------------------- */
$plan = null;
$planId = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
if ($planId > 0) {
  $st = $pdo->prepare("SELECT id,nombre,descripcion,precio FROM plans WHERE id=? LIMIT 1");
  $st->execute([$planId]);
  $plan = $st->fetch(PDO::FETCH_ASSOC);
}
if (!$plan) {
  $slug = strtolower(trim($_GET['plan'] ?? ''));
  if ($slug) {
    $map = ['paseo'=>'Paseo','ruta'=>'Ruta','maraton'=>'Maratón'];
    $nombre = $map[$slug] ?? $slug;
    $st = $pdo->prepare("SELECT id,nombre,descripcion,precio FROM plans WHERE nombre=? LIMIT 1");
    $st->execute([$nombre]);
    $plan = $st->fetch(PDO::FETCH_ASSOC);
  }
}
if (!$plan) { header('Location: /ecobici/cliente/membresia.php'); exit; }
$planId = (int)$plan['id'];

/* -------------------- POST: pago simulado -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $metodo = $_POST['metodo'] ?? '';
  $ok = false; $error = '';

  if ($metodo === 'paypal') {
    $ppEmail = trim($_POST['pp_email'] ?? '');
    $ppPass  = trim($_POST['pp_pass'] ?? '');
    if (!filter_var($ppEmail, FILTER_VALIDATE_EMAIL) || $ppPass==='') {
      $error = 'Completa correo y contraseña.';
    } else { $ok = true; }

  } elseif ($metodo === 'card') {
    $name   = trim($_POST['cc_name'] ?? '');
    $email  = trim($_POST['cc_email'] ?? '');
    $num    = preg_replace('/\D/','', $_POST['cc_number'] ?? '');
    $exp    = trim($_POST['cc_exp'] ?? '');
    $cvc    = trim($_POST['cc_cvc'] ?? '');
    $pais   = trim($_POST['cc_country'] ?? '');

    $valid = true;
    if ($name==='' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $pais==='') $valid=false;
    if (!preg_match('/^\d{13,19}$/', $num)) $valid=false;                   // 13-19 dígitos
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $exp)) $valid=false;      // Formato MM/AA
    if (!preg_match('/^\d{3}$/', $cvc)) $valid=false;                       // 3 dígitos

    // Validar que la fecha no esté vencida
    if ($valid) {
      [$mm,$yy] = explode('/', $exp);
      $yy = 2000 + (int)$yy;
      $mm = (int)$mm;
      $lastDay = (new DateTime("$yy-$mm-01"))->modify('last day of this month')->setTime(23,59,59);
      if ($lastDay < new DateTime()) {
        $valid = false;
        $error = 'La tarjeta está vencida.';
      }
    }

    if (!$valid && !$error) $error = 'Revisa los campos del formulario.';
    else if ($valid) $ok = true;

  } else {
    $error = 'Método inválido.';
  }

  if ($ok) {
    // === BLOQUEO: no permitir más de una suscripción activa ===
    try {
      $chk = $pdo->prepare(
        "SELECT id, fecha_fin 
           FROM subscriptions 
          WHERE user_id=? 
            AND estado='activa' 
            AND fecha_fin >= CURDATE()
          LIMIT 1"
      );
      $chk->execute([$userId]);
      $active = $chk->fetch(PDO::FETCH_ASSOC);

      if ($active) {
        $hasta = date('d/m/Y', strtotime($active['fecha_fin']));
        $_SESSION['checkout_error'] = "Ya tienes una suscripción activa hasta el $hasta. No puedes crear otra.";
        header('Location: '.$_SERVER['REQUEST_URI']); exit;
      }

      // === Registrar en BD (no hay activa previa) ===
      $pdo->beginTransaction();

      $DAYS = 30;
      $sqlSub = "INSERT INTO subscriptions (user_id,plan_id,fecha_inicio,fecha_fin,estado,created_at,updated_at)
                 VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'activa', NOW(), NOW())";
      $pdo->prepare($sqlSub)->execute([$userId, $planId, $DAYS]);
      $subscriptionId = (int)$pdo->lastInsertId();

      $ref = 'SIM-'.strtoupper(bin2hex(random_bytes(4)));
      $sqlPay = "INSERT INTO payments (subscription_id,monto,metodo,referencia,estado,created_at,updated_at)
                 VALUES (?, ?, ?, ?, 'completado', NOW(), NOW())";
      $pdo->prepare($sqlPay)->execute([$subscriptionId, (float)$plan['precio'], $metodo, $ref]);

      $pdo->commit();

      $_SESSION['membership'] = [
        'plan_id'=>$planId,'plan'=>$plan['nombre'],'metodo'=>$metodo,'ref'=>$ref,'since'=>date('Y-m-d H:i:s'),
      ];
      header('Location: /ecobici/cliente/dashboard.php?paid=1'); exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = 'No se pudo registrar el pago.';
    }
  }

  $_SESSION['checkout_error'] = $error ?: 'Error desconocido.';
  header('Location: '.$_SERVER['REQUEST_URI']); exit;
}

$flashError = $_SESSION['checkout_error'] ?? '';
unset($_SESSION['checkout_error']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Pago de membresía | EcoBici</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="/ecobici/cliente/styles/membresia.css">
  <style>
    .card-eco{ border:1px solid var(--eco-soft); border-radius:16px; box-shadow:0 10px 24px rgba(0,0,0,.07); background:#fff; }
    .btn-eco{ background:var(--eco-green); border:none; }
    .btn-eco:hover{ background:var(--eco-green-dark); }
    .pp-btn{ background:#FFC439; border:none; font-weight:700;
      box-shadow:0 6px 16px rgba(0,0,0,.08), inset 0 -2px 0 rgba(0,0,0,.15); }
    .pp-btn:hover{ filter:brightness(.98); }
    .tabs .nav-link.active{ color:#0b3d22; font-weight:600; border-bottom:3px solid var(--eco-green); }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg eco-navbar sticky-top bg-white border-bottom">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="/ecobici/index.php">
        <img src="/ecobici/cliente/styles/logo.jpg" alt="EcoBici" height="38">
      </a>
      <div class="ms-auto">
        <a href="/ecobici/cliente/membresia.php" class="btn btn-outline-success btn-sm">Volver a planes</a>
      </div>
    </div>
  </nav>

  <main class="container py-4">
    <?php if($flashError): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashError) ?>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Checkout -->
      <div class="col-12 col-lg-7">
        <div class="card card-eco">
          <div class="card-body">
            <h4 class="mb-1">Pagar membresía</h4>
            <p class="text-secondary mb-3">Plan <strong><?=htmlspecialchars($plan['nombre'])?></strong>.</p>

            <!-- Tabs -->
            <ul class="nav nav-tabs tabs mb-3" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabCard" type="button" role="tab">
                  Tarjeta (Crédito/Débito)
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPayPal" type="button" role="tab">
                  PayPal
                </button>
              </li>
            </ul>

            <div class="tab-content">
              <!-- Tarjeta -->
              <div class="tab-pane fade show active" id="tabCard" role="tabpanel">
                <form method="post" class="row g-3">
                  <input type="hidden" name="metodo" value="card">
                  <div class="col-12">
                    <label class="form-label">Tipo de tarjeta</label><br>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="card_tipo" id="tCredito" value="credito" checked>
                      <label class="form-check-label" for="tCredito">Crédito</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="radio" name="card_tipo" id="tDebito" value="debito">
                      <label class="form-check-label" for="tDebito">Débito</label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Nombre en la tarjeta</label>
                    <input name="cc_name" class="form-control" placeholder="Ej. Juan Pérez" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Correo</label>
                    <input name="cc_email" type="email" class="form-control" placeholder="correo@ejemplo.com" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Número de tarjeta</label>
                    <input name="cc_number" id="cc-number" class="form-control" placeholder="4111 1111 1111 1111"
                           maxlength="19" inputmode="numeric" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Vencimiento (MM/AA)</label>
                    <input name="cc_exp" id="cc-exp" class="form-control" placeholder="MM/AA"
                           maxlength="5" inputmode="numeric" pattern="(0[1-9]|1[0-2])\/\d{2}" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">CVC</label>
                    <input name="cc_cvc" class="form-control" placeholder="123"
                           maxlength="3" pattern="\d{3}" inputmode="numeric" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">País</label>
                    <select name="cc_country" class="form-select" required>
                      <option value="">Selecciona…</option>
                      <option value="GT">Guatemala</option><option value="MX">México</option>
                      <option value="US">Estados Unidos</option><option value="ES">España</option>
                      <option value="AR">Argentina</option><option value="CO">Colombia</option><option value="PE">Perú</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <button class="btn btn-eco w-100">Pagar con tarjeta</button>
                  </div>
                </form>
              </div>

              <!-- PayPal -->
              <div class="tab-pane fade" id="tabPayPal" role="tabpanel">
                <form method="post" class="vstack gap-3">
                  <input type="hidden" name="metodo" value="paypal">
                  <div>
                    <label class="form-label">Correo</label>
                    <input type="email" name="pp_email" class="form-control" required>
                  </div>
                  <div>
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="pp_pass" class="form-control" required>
                  </div>
                  <div class="d-grid">
                    <button class="btn pp-btn">Continuar con PayPal</button>
                  </div>
                </form>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- Resumen -->
      <div class="col-12 col-lg-5">
        <div class="card card-eco">
          <div class="card-body">
            <h5 class="card-title">Resumen</h5>
            <div class="d-flex justify-content-between"><span>Plan</span><strong><?=htmlspecialchars($plan['nombre'])?></strong></div>
            <div class="d-flex justify-content-between"><span>Subtotal</span><span id="sum-sub">Q <?=number_format($plan['precio'],2)?></span></div>
            <div class="d-flex justify-content-between"><span>Comisión (2.9% + 0.30)</span><span id="sum-fee">Q 0.00</span></div>
            <hr>
            <div class="d-flex justify-content-between fs-5 fw-bold"><span>Total</span><span id="sum-total">Q 0.00</span></div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <footer class="py-4 mt-4 border-top">
    <div class="container text-center small">© 2025 EcoBici · Puerto Barrios, Izabal</div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Formato visual de número
    const ccNum = document.getElementById('cc-number');
    if (ccNum) ccNum.addEventListener('input', e=>{
      let v = e.target.value.replace(/\D/g,'').slice(0,19);
      e.target.value = v.replace(/(.{4})/g,'$1 ').trim();
    });

    // Validación de fecha (mes 01–12 y año >= actual)
    const ccExp = document.getElementById('cc-exp');
    if (ccExp) ccExp.addEventListener('input', e=>{
      let v = e.target.value.replace(/\D/g,'').slice(0,4);
      if (v.length>=3) v = v.slice(0,2)+'/'+v.slice(2);
      e.target.value = v;

      const [mm,yy] = v.split('/');
      let valid = true;
      if (mm && (parseInt(mm)<1 || parseInt(mm)>12)) valid = false;
      if (yy && yy.length===2) {
        const fullYear = 2000+parseInt(yy,10);
        const now = new Date();
        const nowYear = now.getFullYear();
        const nowMonth = now.getMonth()+1;
        if (fullYear<nowYear) valid=false;
        if (fullYear===nowYear && parseInt(mm)<nowMonth) valid=false;
      }
      e.target.setCustomValidity(valid? "" : "Fecha de vencimiento inválida");
    });

    // Resumen
    const precio = <?= json_encode((float)$plan['precio']) ?>;
    const fee = amt => (amt*0.029 + 0.30);
    const moneyQ = n => 'Q ' + (Math.round(n*100)/100).toFixed(2);
    (function(){
      const sub=document.getElementById('sum-sub');
      const feeBox=document.getElementById('sum-fee');
      const tot=document.getElementById('sum-total');
      const f=fee(precio);
      sub.textContent=moneyQ(precio);
      feeBox.textContent=moneyQ(f);
      tot.textContent=moneyQ(precio+f);
    })();

    // Alertas auto-desvanecidas
    document.addEventListener("DOMContentLoaded", ()=>{
      document.querySelectorAll('.alert').forEach(al=>{
        setTimeout(()=>{
          al.classList.remove('show');
          al.classList.add('fade');
          setTimeout(()=> al.remove(),500);
        },5000);
      });
    });
  </script>
</body>
</html>
