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
            'brand_name'       => trim($_POST['brand_name'] ?? 'EcoBici'),
            'contact_email'    => trim($_POST['contact_email'] ?? '')
        ];
        foreach ($pairs as $k => $v) {
            $st = $pdo->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
            $st->execute([$k, $v]);
        }
        flash('Ajustes guardados');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/administrador/ajustes.php');
}
$rows = $pdo->query("SELECT `key`,`value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoBici • Ajustes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body><?php admin_nav('settings'); ?>
    <main class="container py-4"><?php admin_flash(flash()); ?>
        <h4 class="mb-3">Ajustes del sistema</h4>
        <div class="card p-3">
            <form method="post"><?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Factor CO₂ (kg por km)</label>
                        <input class="form-control" name="co2_factor_kg_km" type="number" step="0.001" value="<?= e($rows['co2_factor_kg_km'] ?? '0.21') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nombre de marca</label>
                        <input class="form-control" name="brand_name" value="<?= e($rows['brand_name'] ?? 'EcoBici') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Correo de contacto</label>
                        <input class="form-control" type="email" name="contact_email" value="<?= e($rows['contact_email'] ?? '') ?>">
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-success">Guardar</button>
                </div>
            </form>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

</html>