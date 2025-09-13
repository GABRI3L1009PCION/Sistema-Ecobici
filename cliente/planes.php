<?php
require_once __DIR__ . '/client_boot.php';
$uid = (int)($_SESSION['user']['id'] ?? 0);

// POST: suscribirse
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido', 'danger');
        redirect('/ecobici/cliente/planes.php');
    }
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    try {
        if ($plan_id <= 0) throw new Exception('Plan inválido.');
        // ¿Tiene otra activa?
        $c = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id=? AND estado='activa'");
        $c->execute([$uid]);
        if ((int)$c->fetchColumn() > 0) throw new Exception('Ya tienes una suscripción activa.');
        // Crear pendiente
        $st = $pdo->prepare("INSERT INTO subscriptions(user_id,plan_id,fecha_inicio,estado) VALUES(?,?,CURDATE(),'pendiente')");
        $st->execute([$uid, $plan_id]);
        flash('Solicitud de suscripción creada (pendiente).');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/cliente/planes.php');
}

// Datos
$planes = $pdo->query("SELECT id,nombre,descripcion,precio FROM plans ORDER BY precio ASC")->fetchAll(PDO::FETCH_ASSOC);
$subs = $pdo->prepare("SELECT s.id,s.estado,s.plan_id,p.nombre,p.precio FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.user_id=? ORDER BY s.created_at DESC LIMIT 1");
$subs->execute([$uid]);
$miSub = $subs->fetch(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EcoBici • Planes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card-elev {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(2, 6, 23, .06)
        }
    </style>
</head>

<body>
    <?php client_nav('planes'); ?>
    <main class="container py-4">
        <?php client_flash(flash()); ?>
        <h4 class="mb-3">Planes disponibles</h4>

        <?php if ($miSub): ?>
            <div class="alert alert-info">
                Tu suscripción más reciente: <strong><?= e($miSub['nombre']) ?></strong> —
                <span class="badge text-bg-<?= $miSub['estado'] === 'activa' ? 'success' : ($miSub['estado'] === 'pendiente' ? 'warning' : 'secondary') ?>"><?= e($miSub['estado']) ?></span>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <?php foreach ($planes as $p): $isMine = $miSub && (int)$miSub['plan_id'] === (int)$p['id']; ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card-elev p-3 h-100">
                        <div class="d-flex justify-content-between">
                            <h5 class="mb-1"><?= e($p['nombre']) ?></h5>
                            <div class="fw-bold">Q <?= number_format((float)$p['precio'], 2) ?></div>
                        </div>
                        <div class="text-muted small mb-3" style="min-height:3em"><?= nl2br(e($p['descripcion'] ?? '')) ?></div>
                        <?php if ($isMine): ?>
                            <button class="btn btn-outline-secondary w-100" disabled>Tienes este plan</button>
                        <?php else: ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-success w-100">Suscribirme</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach;
            if (!$planes): ?>
                <div class="col-12 text-center text-muted">No hay planes.</div>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>