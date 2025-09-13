<?php
require_once __DIR__ . '/client_boot.php';
$uid = (int)($_SESSION['user']['id'] ?? 0);

// Crear pago simulado (opcional)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido', 'danger');
        redirect('/ecobici/cliente/pagos.php');
    }
    $sid = (int)($_POST['subscription_id'] ?? 0);
    $monto = (float)($_POST['monto'] ?? 0);
    try {
        if ($sid <= 0 || $monto <= 0) throw new Exception('Datos inválidos.');
        // validar que la suscripción pertenezca al usuario
        $ok = $pdo->prepare("SELECT 1 FROM subscriptions WHERE id=? AND user_id=?");
        $ok->execute([$sid, $uid]);
        if (!$ok->fetch()) throw new Exception('Suscripción no encontrada.');
        $st = $pdo->prepare("INSERT INTO payments (subscription_id,monto,metodo,referencia,estado) VALUES (?,?,?,?,?)");
        $st->execute([$sid, $monto, 'simulado', null, 'completado']);
        flash('Pago registrado.');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/cliente/pagos.php');
}

// Listas
$subs = $pdo->prepare("SELECT s.id, CONCAT(p.nombre,' (',s.estado,')') txt FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.user_id=? ORDER BY s.created_at DESC");
$subs->execute([$uid]);
$subs = $subs->fetchAll(PDO::FETCH_ASSOC);

$pagos = $pdo->prepare("SELECT p.id,p.monto,p.metodo,p.estado,p.created_at,pl.nombre plan
 FROM payments p JOIN subscriptions s ON s.id=p.subscription_id JOIN plans pl ON pl.id=s.plan_id
 WHERE s.user_id=? ORDER BY p.created_at DESC");
$pagos->execute([$uid]);
$pagos = $pagos->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EcoBici • Mis pagos</title>
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
    <?php client_nav('pagos'); ?>
    <main class="container py-4">
        <?php client_flash(flash()); ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Mis pagos</h4>
            <?php if ($subs): ?>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mdlPay"><i class="bi bi-plus-lg me-1"></i>Registrar pago</button>
            <?php endif; ?>
        </div>

        <div class="card-elev p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Plan</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $p): $cls = $p['estado'] === 'completado' ? 'success' : ($p['estado'] === 'pendiente' ? 'warning' : 'danger'); ?>
                            <tr>
                                <td>#<?= e($p['id']) ?></td>
                                <td><?= e($p['plan']) ?></td>
                                <td class="fw-semibold text-success">Q <?= number_format((float)$p['monto'], 2) ?></td>
                                <td><?= e($p['metodo']) ?></td>
                                <td><span class="badge text-bg-<?= $cls ?>"><?= e($p['estado']) ?></span></td>
                                <td class="small text-muted"><?= e($p['created_at']) ?></td>
                            </tr>
                        <?php endforeach;
                        if (!$pagos): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Sin pagos todavía.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal pago -->
    <div class="modal fade" id="mdlPay" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar pago</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div><label class="form-label">Suscripción</label>
                            <select name="subscription_id" class="form-select" required>
                                <option value="">Selecciona…</option>
                                <?php foreach ($subs as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['txt']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="form-label">Monto (Q)</label><input type="number" step="0.01" min="0.01" name="monto" class="form-control" required></div>
                        <div class="small text-muted">Este pago se registrará como <strong>completado (simulado)</strong>.</div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Guardar</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>