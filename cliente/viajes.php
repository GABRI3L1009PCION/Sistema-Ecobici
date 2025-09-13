<?php
require_once __DIR__ . '/client_boot.php';
$uid = (int)($_SESSION['user']['id'] ?? 0);

$viajes = $pdo->prepare("
  SELECT t.id,t.start_at,t.end_at,t.distancia_km,
         st1.nombre ini, st2.nombre fin
  FROM trips t
  LEFT JOIN stations st1 ON st1.id=t.start_station_id
  LEFT JOIN stations st2 ON st2.id=t.end_station_id
  WHERE t.user_id=?
  ORDER BY t.start_at DESC
");
$viajes->execute([$uid]);
$viajes = $viajes->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EcoBici • Mis viajes</title>
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
    <?php client_nav('viajes'); ?>
    <main class="container py-4">
        <h4 class="mb-3">Mis viajes</h4>
        <div class="card-elev p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Inicio</th>
                            <th class="d-none d-sm-table-cell">Fin</th>
                            <th>Origen</th>
                            <th class="d-none d-md-table-cell">Destino</th>
                            <th>Km</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viajes as $v): ?>
                            <tr>
                                <td>#<?= e($v['id']) ?></td>
                                <td><?= e($v['start_at']) ?></td>
                                <td class="d-none d-sm-table-cell"><?= e($v['end_at'] ?? '—') ?></td>
                                <td><?= e($v['ini'] ?? '—') ?></td>
                                <td class="d-none d-md-table-cell"><?= e($v['fin'] ?? '—') ?></td>
                                <td class="fw-semibold"><?= number_format((float)$v['distancia_km'], 2) ?></td>
                            </tr>
                        <?php endforeach;
                        if (!$viajes): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Sin viajes aún.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>