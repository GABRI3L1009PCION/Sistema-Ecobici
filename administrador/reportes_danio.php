<?php
require_once __DIR__ . '/admin_boot.php';
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function save_upload($f)
{ // retorna ruta relativa o null
    if (!isset($f['tmp_name']) || $f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Error al subir foto.');
    if ($f['size'] > 2 * 1024 * 1024) throw new Exception('Foto <= 2MB.');
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($f['tmp_name']) ?: '';
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) throw new Exception('Formato no permitido.');
    $dir = dirname(__DIR__) . '/uploads/damage';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'dr_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception('No se pudo guardar.');
    return 'uploads/damage/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido', 'danger');
        redirect('/ecobici/administrador/reportes_danio.php');
    }
    $a = $_POST['action'] ?? '';
    try {
        if ($a === 'create') {
            $foto = !empty($_FILES['foto']) ? save_upload($_FILES['foto']) : null;
            $st = $pdo->prepare("INSERT INTO damage_reports(bike_id,user_id,nota,foto,estado) VALUES(?,?,?,?,?)");
            $st->execute([(int)$_POST['bike_id'], ($_POST['user_id'] ?: null), trim($_POST['nota']), $foto, $_POST['estado'] ?? 'nuevo']);
            flash('Reporte creado.');
        } elseif ($a === 'update') {
            $id = (int)$_POST['id'];
            $st = $pdo->prepare("UPDATE damage_reports SET estado=? WHERE id=?");
            $st->execute([$_POST['estado'], $id]);
            flash('Estado actualizado.');
        } elseif ($a === 'delete') {
            $id = (int)$_POST['id'];
            $cur = $pdo->prepare("SELECT foto FROM damage_reports WHERE id=?");
            $cur->execute([$id]);
            $old = $cur->fetchColumn();
            if ($old) {
                $p = dirname(__DIR__) . '/' . $old;
                if (is_file($p)) @unlink($p);
            }
            $pdo->prepare("DELETE FROM damage_reports WHERE id=?")->execute([$id]);
            flash('Reporte eliminado.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/administrador/reportes_danio.php');
}

$f_estado = $_GET['estado'] ?? '';
$w = ($f_estado && in_array($f_estado, ['nuevo', 'en_proceso', 'resuelto'], true)) ? "WHERE dr.estado=" . $pdo->quote($f_estado) : '';
$rows = $pdo->query("
 SELECT dr.*, b.codigo bici, u.name usuario
 FROM damage_reports dr
 JOIN bikes b ON b.id=dr.bike_id
 LEFT JOIN users u ON u.id=dr.user_id
 $w
 ORDER BY dr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
$bikes = $pdo->query("SELECT id,codigo FROM bikes ORDER BY codigo")->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT id,name FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoBici • Daños</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card-elev {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(2, 6, 23, .06)
        }

        .thumb {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e5e7eb
        }
    </style>
</head>

<body>
    <?php admin_nav('rep_danio'); ?>
    <main class="container py-4">
        <?php if ($f = flash()): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Reportes de daño</h4>
            <div class="d-flex gap-2">
                <form class="d-flex" method="get">
                    <select class="form-select" name="estado" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <option value="nuevo" <?= $f_estado === 'nuevo' ? 'selected' : '' ?>>nuevo</option>
                        <option value="en_proceso" <?= $f_estado === 'en_proceso' ? 'selected' : '' ?>>en_proceso</option>
                        <option value="resuelto" <?= $f_estado === 'resuelto' ? 'selected' : '' ?>>resuelto</option>
                    </select>
                </form>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mdlCreate"><i class="bi bi-plus-lg me-1"></i>Nuevo</button>
            </div>
        </div>

        <div class="card-elev p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bici</th>
                            <th>Usuario</th>
                            <th>Nota</th>
                            <th>Foto</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows): foreach ($rows as $r): ?>
                                <tr>
                                    <td>#<?= e($r['id']) ?></td>
                                    <td class="fw-semibold"><?= e($r['bici']) ?></td>
                                    <td><?= e($r['usuario'] ?: '—') ?></td>
                                    <td class="small"><?= nl2br(e($r['nota'])) ?></td>
                                    <td><?php if ($r['foto']): ?><img class="thumb" src="/ecobici/<?= e($r['foto']) ?>"><?php else: ?>—<?php endif; ?></td>
                                    <td><span class="badge text-bg-<?= $r['estado'] === 'nuevo' ? 'danger' : ($r['estado'] === 'en_proceso' ? 'warning' : 'success') ?>"><?= e($r['estado']) ?></span></td>
                                    <td class="small text-muted"><?= e($r['created_at']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-success me-1" data-bs-toggle="modal" data-bs-target="#mdlEdit"
                                            data-id="<?= $r['id'] ?>" data-estado="<?= $r['estado'] ?>"><i class="bi bi-pencil"></i></button>
                                        <form class="d-inline" method="post" onsubmit="return confirm('¿Eliminar reporte?');">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Sin reportes</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Crear -->
    <div class="modal fade" id="mdlCreate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Nuevo reporte</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div><label class="form-label">Bicicleta</label>
                            <select name="bike_id" class="form-select" required>
                                <option value="">Selecciona…</option>
                                <?php foreach ($bikes as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['codigo']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="form-label">Usuario (opcional)</label>
                            <select name="user_id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div><label class="form-label">Nota</label><textarea name="nota" class="form-control" rows="3" required></textarea></div>
                        <div><label class="form-label">Foto (opcional)</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
                        <div><label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="nuevo">nuevo</option>
                                <option value="en_proceso">en_proceso</option>
                                <option value="resuelto">resuelto</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Guardar</button></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Editar -->
    <div class="modal fade" id="mdlEdit" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Actualizar estado</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Estado</label>
                        <select name="estado" id="edit_estado" class="form-select">
                            <option value="nuevo">nuevo</option>
                            <option value="en_proceso">en_proceso</option>
                            <option value="resuelto">resuelto</option>
                        </select>
                    </div>
                    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Actualizar</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mdlEdit')?.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            edit_id.value = b.dataset.id;
            edit_estado.value = b.dataset.estado;
        });
    </script>
</body>

</html>