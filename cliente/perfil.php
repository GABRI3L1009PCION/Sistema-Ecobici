<?php
require_once __DIR__ . '/client_boot.php';
$uid = (int)($_SESSION['user']['id'] ?? 0);

// Guardar avatar
function save_avatar(array $file, ?string $oldPath = null): ?string
{
    if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) return $oldPath;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Error al subir la imagen.');
    if ($file['size'] > 2 * 1024 * 1024) throw new Exception('La imagen debe pesar <=2MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($ok[$mime])) throw new Exception('Formato no permitido (JPG/PNG/WebP).');
    $dir = dirname(__DIR__) . '/uploads/avatars';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'ava_' . bin2hex(random_bytes(6)) . '.' . $ok[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('No se pudo guardar.');
    if ($oldPath && is_file(dirname(__DIR__) . '/' . $oldPath)) @unlink(dirname(__DIR__) . '/' . $oldPath);
    return 'uploads/avatars/' . $name;
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido', 'danger');
        redirect('/ecobici/cliente/perfil.php');
    }
    $name = trim($_POST['name'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dpi = trim($_POST['dpi'] ?? '');
    $tel = trim($_POST['telefono'] ?? '');
    $nac = $_POST['fecha_nacimiento'] ?: null;
    $pass = $_POST['password'] ?? '';
    $remove_foto = isset($_POST['remove_foto']);
    try {
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Nombre/correo inválidos.');
        $cur = $pdo->prepare("SELECT email,foto FROM users WHERE id=?");
        $cur->execute([$uid]);
        $row = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception('Usuario no encontrado.');
        // correo único
        $ex = $pdo->prepare("SELECT 1 FROM users WHERE email=? AND id<>?");
        $ex->execute([$email, $uid]);
        if ($ex->fetch()) throw new Exception('El correo ya está en uso.');
        $foto = $row['foto'] ?? null;
        if (!empty($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) $foto = save_avatar($_FILES['foto'], $foto);
        elseif ($remove_foto && $foto) {
            if (is_file(dirname(__DIR__) . '/' . $foto)) @unlink(dirname(__DIR__) . '/' . $foto);
            $foto = null;
        }

        if ($pass !== '') {
            if (strlen($pass) < 8) throw new Exception('La contraseña debe tener al menos 8 caracteres.');
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $st = $pdo->prepare("UPDATE users SET name=?,apellido=?,email=?,dpi=?,telefono=?,fecha_nacimiento=?,foto=?,password=? WHERE id=?");
            $st->execute([$name, $apellido, $email, $dpi, $tel, $nac, $foto, $hash, $uid]);
        } else {
            $st = $pdo->prepare("UPDATE users SET name=?,apellido=?,email=?,dpi=?,telefono=?,fecha_nacimiento=?,foto=? WHERE id=?");
            $st->execute([$name, $apellido, $email, $dpi, $tel, $nac, $foto, $uid]);
        }
        // refrescar datos mínimos de sesión
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        flash('Perfil actualizado.');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/cliente/perfil.php');
}

// Datos actuales
$me = $pdo->prepare("SELECT name,apellido,email,dpi,telefono,fecha_nacimiento,foto FROM users WHERE id=?");
$me->execute([$uid]);
$me = $me->fetch(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EcoBici • Mi perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card-elev {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(2, 6, 23, .06)
        }

        .avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e2e8f0
        }
    </style>
</head>

<body>
    <?php client_nav('perfil'); ?>
    <main class="container py-4">
        <?php client_flash(flash()); ?>
        <h4 class="mb-3">Mi perfil</h4>
        <div class="card-elev p-3">
            <form method="post" enctype="multipart/form-data" class="vstack gap-3">
                <?= csrf_field() ?>
                <div class="d-flex align-items-center gap-3">
                    <img class="avatar" id="preview" src="<?= $me['foto'] ? '/ecobici/' . e($me['foto']) : 'https://ui-avatars.com/api/?size=80&background=16a34a&color=fff&name=' . urlencode(trim(($me['name'] ?? '') . ' ' . ($me['apellido'] ?? ''))) ?>" alt="avatar">
                    <div class="small text-muted"><?= $me['foto'] ? e($me['foto']) : 'Sin foto' ?></div>
                </div>

                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label">Nombre</label><input name="name" class="form-control" value="<?= e($me['name'] ?? '') ?>" required></div>
                    <div class="col-sm-6"><label class="form-label">Apellido</label><input name="apellido" class="form-control" value="<?= e($me['apellido'] ?? '') ?>"></div>
                </div>

                <div><label class="form-label">Correo</label><input type="email" name="email" class="form-control" value="<?= e($me['email'] ?? '') ?>" required></div>

                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label">DPI</label><input name="dpi" class="form-control" value="<?= e($me['dpi'] ?? '') ?>"></div>
                    <div class="col-sm-6"><label class="form-label">Teléfono</label><input name="telefono" class="form-control" value="<?= e($me['telefono'] ?? '') ?>"></div>
                </div>

                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label">Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" class="form-control" value="<?= e($me['fecha_nacimiento'] ?? '') ?>"></div>
                    <div class="col-sm-6"><label class="form-label">Nueva contraseña (opcional)</label><input type="password" name="password" minlength="8" class="form-control" placeholder="••••••••"></div>
                </div>

                <div>
                    <label class="form-label">Foto (JPG/PNG/WebP, máx 2MB)</label>
                    <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="form-control">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="remove_foto" id="remove_foto">
                        <label class="form-check-label" for="remove_foto">Quitar foto</label>
                    </div>
                </div>

                <div class="text-end"><button class="btn btn-success">Guardar cambios</button></div>
            </form>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>