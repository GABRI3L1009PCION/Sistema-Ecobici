<?php
require_once __DIR__ . '/admin_boot.php';
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido', 'danger');
        redirect('/ecobici/administrador/ajustes.php');
    }
    try {
        $pairs = [
            'co2_factor_kg_km' => trim($_POST['co2_factor_kg_km'] ?? '0.21'),
            'points_per_km'    => trim($_POST['points_per_km'] ?? '1'),
        ];
        $st = $pdo->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        foreach ($pairs as $k => $v) {
            $st->execute([$k, $v]);
        }
        flash('Ajustes guardados.');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/administrador/ajustes.php');
}

$co2 = $pdo->query("SELECT `value` FROM settings WHERE `key`='co2_factor_kg_km'")->fetchColumn() ?: '0.21';
$pts = $pdo->query("SELECT `value` FROM settings WHERE `key`='points_per_km'")->fetchColumn() ?: '1';
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoBici • Ajustes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php admin_nav('ajustes'); ?>
    <main class="container py-4">
        <?php if ($f = flash()): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endif; ?>
        <h4 class="mb-3">Ajustes</h4>
        <form method="post" class="p-3 border rounded-3">
            <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Factor CO₂ (kg por km)</label>
                    <input name="co2_factor_kg_km" class="form-control" value="<?= e($co2) ?>" required>
                    <small class="text-muted">Se usa para calcular CO₂ evitado: <code>km * factor</code></small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Puntos por km</label>
                    <input name="points_per_km" class="form-control" value="<?= e($pts) ?>" required>
                </div>
            </div>
            <div class="mt-3"><button class="btn btn-success">Guardar</button></div>
        </form>
    </main>
</body>

</html>