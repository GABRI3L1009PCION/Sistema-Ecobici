<?php
require_once __DIR__ . '/admin_boot.php';

/* ========= Helpers locales ========= */
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function qurl(array $overrides = [])
{
    $q = array_merge($_GET, $overrides);
    return '/ecobici/administrador/usuarios.php?' . http_build_query($q);
}
function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">';
}
function admin_flash($f)
{
    if (!$f) return;
    echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
}
function count_other_admins(PDO $pdo, int $excludeId = 0): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='admin' AND id<>?");
    $st->execute([$excludeId]);
    return (int)$st->fetchColumn();
}

/** Guardado de avatar con validaciones */
function save_avatar(array $file, ?string $oldPath = null): ?string
{
    if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) return $oldPath;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Error al subir la imagen.');
    if ($file['size'] > 2 * 1024 * 1024) throw new Exception('La imagen debe pesar <= 2MB.');

    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($file['tmp_name']) ?: '';
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) throw new Exception('Formato no permitido (JPG/PNG/WebP).');

    $ext = $allowed[$mime];
    $dir = dirname(__DIR__) . '/uploads/avatars';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $name = 'ava_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('No se pudo guardar la imagen.');

    if ($oldPath) {
        $prev = dirname(__DIR__) . '/' . $oldPath;
        if (is_file($prev)) @unlink($prev);
    }
    return 'uploads/avatars/' . $name;
}

/* ========= Acciones (POST) ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash('Token inválido.', 'danger');
        redirect('/ecobici/administrador/usuarios.php');
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $name      = trim($_POST['name'] ?? '');
            $apellido  = trim($_POST['apellido'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $role      = ($_POST['role'] ?? 'cliente') === 'admin' ? 'admin' : 'cliente';
            $password  = $_POST['password'] ?? '';
            $dpi       = trim($_POST['dpi'] ?? '');
            $tel       = trim($_POST['telefono'] ?? '');
            $nac       = $_POST['fecha_nacimiento'] ?: null;

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                throw new Exception('Datos inválidos (nombre/correo/contraseña).');
            }
            $exists = $pdo->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
            $exists->execute([$email]);
            if ($exists->fetch()) throw new Exception('El correo ya está registrado.');

            $foto = null;
            if (!empty($_FILES['foto'])) $foto = save_avatar($_FILES['foto'], null);

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $st = $pdo->prepare("
                INSERT INTO users (name,apellido,email,password,role,dpi,telefono,fecha_nacimiento,foto)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $st->execute([$name, $apellido, $email, $hash, $role, $dpi, $tel, $nac, $foto]);

            flash('Usuario creado.');
        } elseif ($action === 'update') {
            $id        = (int)($_POST['id'] ?? 0);
            $name      = trim($_POST['name'] ?? '');
            $apellido  = trim($_POST['apellido'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $role      = ($_POST['role'] ?? 'cliente') === 'admin' ? 'admin' : 'cliente';
            $password  = $_POST['password'] ?? '';
            $dpi       = trim($_POST['dpi'] ?? '');
            $tel       = trim($_POST['telefono'] ?? '');
            $nac       = $_POST['fecha_nacimiento'] ?: null;
            $remove_foto = isset($_POST['remove_foto']);

            if ($id <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Datos inválidos.');
            }

            $cur = $pdo->prepare("SELECT role,foto FROM users WHERE id=?");
            $cur->execute([$id]);
            $row = $cur->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Usuario no existe.');

            $exists = $pdo->prepare("SELECT 1 FROM users WHERE email=? AND id<>? LIMIT 1");
            $exists->execute([$email, $id]);
            if ($exists->fetch()) throw new Exception('El correo ya está en uso por otro usuario.');

            if ($row['role'] === 'admin' && $role !== 'admin' && count_other_admins($pdo, $id) === 0) {
                throw new Exception('No puedes degradar al último administrador.');
            }

            $foto = $row['foto'] ?? null;
            if (!empty($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                $foto = save_avatar($_FILES['foto'], $foto);
            } elseif ($remove_foto && $foto) {
                $prev = dirname(__DIR__) . '/' . $foto;
                if (is_file($prev)) @unlink($prev);
                $foto = null;
            }

            if ($password !== '') {
                if (strlen($password) < 8) throw new Exception('La nueva contraseña debe tener al menos 8 caracteres.');
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $st = $pdo->prepare("
                    UPDATE users
                       SET name=?, apellido=?, email=?, role=?, dpi=?, telefono=?, fecha_nacimiento=?, foto=?, password=?
                     WHERE id=?");
                $st->execute([$name, $apellido, $email, $role, $dpi, $tel, $nac, $foto, $hash, $id]);
            } else {
                $st = $pdo->prepare("
                    UPDATE users
                       SET name=?, apellido=?, email=?, role=?, dpi=?, telefono=?, fecha_nacimiento=?, foto=?
                     WHERE id=?");
                $st->execute([$name, $apellido, $email, $role, $dpi, $tel, $nac, $foto, $id]);
            }

            if (($id === (int)($_SESSION['user']['id'] ?? 0)) && $row['role'] === 'admin' && $role !== 'admin') {
                session_destroy();
                redirect('/ecobici/login.php');
            }

            flash('Usuario actualizado.');
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');
            if ($id === (int)($_SESSION['user']['id'] ?? 0)) throw new Exception('No puedes eliminar tu propia cuenta.');

            $cur = $pdo->prepare("SELECT role,foto FROM users WHERE id=?");
            $cur->execute([$id]);
            $row = $cur->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Usuario no existe.');
            if ($row['role'] === 'admin' && count_other_admins($pdo, $id) === 0) {
                throw new Exception('No puedes eliminar al último administrador.');
            }

            if (!empty($row['foto'])) {
                $prev = dirname(__DIR__) . '/' . $row['foto'];
                if (is_file($prev)) @unlink($prev);
            }

            $st = $pdo->prepare("DELETE FROM users WHERE id=?");
            $st->execute([$id]);

            flash('Usuario eliminado (suscripciones y pagos asociados eliminados).');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('/ecobici/administrador/usuarios.php');
}

/* ========= Filtros, orden y paginación ========= */
$q       = trim($_GET['q'] ?? '');
$f_role  = trim($_GET['role'] ?? '');
$f_from  = trim($_GET['from'] ?? '');
$f_to    = trim($_GET['to'] ?? '');

$allowedSort = ['id', 'name', 'email', 'role', 'created'];
$sort = $_GET['sort'] ?? 'created';
if (!in_array($sort, $allowedSort, true)) $sort = 'created';

$dir = strtolower($_GET['dir'] ?? 'desc');
$dir = $dir === 'asc' ? 'asc' : 'desc';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where = [];
$args  = [];
if ($q !== '') {
    $where[] = "(name LIKE ? OR apellido LIKE ? OR email LIKE ?)";
    $args[] = "%$q%";
    $args[] = "%$q%";
    $args[] = "%$q%";
}
if ($f_role !== '' && in_array($f_role, ['admin', 'cliente'], true)) {
    $where[] = "role=?";
    $args[] = $f_role;
}
if ($f_from !== '') {
    $where[] = "DATE(created_at) >= ?";
    $args[] = $f_from;
}
if ($f_to   !== '') {
    $where[] = "DATE(created_at) <= ?";
    $args[] = $f_to;
}
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderMap = [
    'id'      => 'id',
    'name'    => 'name ' . $dir . ', apellido ' . $dir,
    'email'   => 'email',
    'role'    => 'role',
    'created' => 'created_at',
];
$orderExpr = $orderMap[$sort] ?? 'created_at';
$orderBy   = $orderExpr . ' ' . (($sort === 'name') ? '' : strtoupper($dir)) . ', id DESC';

/* Totales y listado */
$st = $pdo->prepare("SELECT COUNT(*) FROM users $wsql");
$st->execute($args);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$sql = "SELECT id,name,apellido,email,role,dpi,telefono,fecha_nacimiento,created_at,foto
        FROM users $wsql ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$st  = $pdo->prepare($sql);
$st->execute($args);
$users = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EcoBici • Admin • Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --ring: #e2e8f0;
            --muted: #64748b;
        }

        .card-elev {
            background: #fff;
            border: 1px solid var(--ring);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(2, 6, 23, .06)
        }

        .table td,
        .table th {
            vertical-align: middle
        }

        .avatar {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 1px solid #e2e8f0;
        }

        /* NUEVAS columnas separadas */
        .col-dpi {
            width: 140px;
            white-space: nowrap;
        }

        .col-tel {
            width: 160px;
            white-space: nowrap;
        }

        .col-nac {
            width: 120px;
        }

        .col-created {
            width: 140px;
        }

        /* Responsive: oculta DPI en ≤992px; Teléfono se mantiene visible */
        @media (max-width: 991.98px) {

            .col-dpi,
            .col-nac,
            .col-created {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php admin_nav('users'); ?>

    <main class="container py-4">
        <?php admin_flash(flash()); ?>

        <!-- Header + Filtros -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
            <h4 class="mb-0">Usuarios</h4>

            <form class="d-flex flex-wrap gap-2" method="get">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, apellido o correo…">
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                    <select class="form-select" name="role">
                        <option value="">Rol…</option>
                        <option value="admin" <?= $f_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="cliente" <?= $f_role === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                    </select>
                </div>
                <div class="input-group">
                    <span class="input-group-text">Desde</span>
                    <input type="date" class="form-control" name="from" value="<?= e($f_from) ?>">
                </div>
                <div class="input-group">
                    <span class="input-group-text">Hasta</span>
                    <input type="date" class="form-control" name="to" value="<?= e($f_to) ?>">
                </div>
                <button class="btn btn-outline-success">Aplicar</button>
                <a class="btn btn-outline-secondary" href="/ecobici/administrador/usuarios.php">Limpiar</a>
                <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#mdlCreate">
                    <i class="bi bi-plus-lg me-1"></i>Nuevo usuario
                </button>
            </form>
        </div>

        <!-- Tabla -->
        <div class="card-elev p-3">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <?php
                            $mk = function ($k, $lbl) use ($sort, $dir) {
                                $n = ($sort === $k && $dir === 'asc') ? 'desc' : 'asc';
                                $url = qurl(['sort' => $k, 'dir' => $n, 'page' => 1]);
                                $icon = '';
                                if ($sort === $k) $icon = $dir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
                                return '<a class="link-success text-decoration-none" href="' . e($url) . '">' . e($lbl) . $icon . '</a>';
                            };
                            ?>
                            <th><?= $mk('id', 'ID') ?></th>
                            <th><?= $mk('name', 'Usuario') ?></th>
                            <th><?= $mk('email', 'Correo') ?></th>
                            <th><?= $mk('role', 'Rol') ?></th>
                            <th class="col-dpi">DPI</th>
                            <th class="col-tel">Teléfono</th>
                            <th class="col-nac">Nacimiento</th>
                            <th class="col-created"><?= $mk('created', 'Alta') ?></th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users): foreach ($users as $u): ?>
                                <tr>
                                    <td>#<?= e($u['id']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($u['foto'])): ?>
                                                <img class="avatar" src="/ecobici/<?= e($u['foto']) ?>" alt="avatar">
                                            <?php else: ?>
                                                <img class="avatar" src="https://ui-avatars.com/api/?size=80&background=16a34a&color=fff&name=<?= urlencode(trim(($u['name'] ?? '') . ' ' . ($u['apellido'] ?? ''))) ?>" alt="avatar">
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-semibold"><?= e(trim(($u['name'] ?? '') . ' ' . ($u['apellido'] ?? ''))) ?></div>
                                                <small class="text-muted">#<?= e($u['id']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= e($u['email']) ?></td>
                                    <td><span class="badge text-bg-<?= $u['role'] === 'admin' ? 'success' : 'secondary' ?>"><?= e($u['role']) ?></span></td>
                                    <td class="col-dpi"><?= e($u['dpi'] ?: '—') ?></td>
                                    <td class="col-tel"><?= e($u['telefono'] ?: '—') ?></td>
                                    <td class="col-nac small"><?= e($u['fecha_nacimiento'] ?: '—') ?></td>
                                    <td class="col-created small text-muted"><?= e($u['created_at']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-success me-1"
                                            data-bs-toggle="modal" data-bs-target="#mdlEdit"
                                            data-id="<?= e($u['id']) ?>"
                                            data-name="<?= e($u['name']) ?>"
                                            data-apellido="<?= e($u['apellido']) ?>"
                                            data-email="<?= e($u['email']) ?>"
                                            data-role="<?= e($u['role']) ?>"
                                            data-dpi="<?= e($u['dpi']) ?>"
                                            data-telefono="<?= e($u['telefono']) ?>"
                                            data-nac="<?= e($u['fecha_nacimiento']) ?>"
                                            data-foto="<?= e($u['foto']) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <button class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal" data-bs-target="#mdlDelete"
                                            data-id="<?= e($u['id']) ?>"
                                            data-name="<?= e(trim(($u['name'] ?? '') . ' ' . ($u['apellido'] ?? ''))) ?>"
                                            data-role="<?= e($u['role']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Sin usuarios</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($pages > 1): ?>
                <nav class="mt-2">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $qs = $_GET;
                        $qs['page'] = 1;
                        $first = qurl($qs);
                        $qs['page'] = max(1, $page - 1);
                        $prev  = qurl($qs);
                        $qs['page'] = min($pages, $page + 1);
                        $next  = qurl($qs);
                        $qs['page'] = $pages;
                        $last  = qurl($qs);
                        ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($first) ?>">&laquo;</a></li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($prev) ?>">&lsaquo;</a></li>
                        <li class="page-item disabled"><span class="page-link">Página <?= $page ?> de <?= $pages ?></span></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($next) ?>">&rsaquo;</a></li>
                        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($last) ?>">&raquo;</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal CREAR -->
    <div class="modal fade" id="mdlCreate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Nuevo usuario</h5>
                        <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label">Nombre</label>
                                <input name="name" class="form-control" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Apellido</label>
                                <input name="apellido" class="form-control">
                            </div>
                        </div>
                        <div><label class="form-label">Correo</label><input type="email" name="email" class="form-control" required></div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label">Rol</label>
                                <select name="role" class="form-select">
                                    <option value="cliente">Cliente</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Fecha de nacimiento</label>
                                <input type="date" name="fecha_nacimiento" class="form-control">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label">DPI</label>
                                <input name="dpi" class="form-control">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Teléfono</label>
                                <input name="telefono" class="form-control">
                            </div>
                        </div>
                        <div><label class="form-label">Contraseña (min 8)</label><input name="password" type="password" class="form-control" minlength="8" required></div>
                        <div>
                            <label class="form-label">Foto (JPG/PNG/WebP, máx 2MB)</label>
                            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                        <button class="btn btn-success">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal EDITAR -->
    <div class="modal fade" id="mdlEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar usuario</h5>
                        <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                    </div>
                    <div class="modal-body vstack gap-3">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label">Nombre</label>
                                <input name="name" id="edit_name" class="form-control" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Apellido</label>
                                <input name="apellido" id="edit_apellido" class="form-control">
                            </div>
                        </div>
                        <div><label class="form-label">Correo</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label">Rol</label>
                                <select name="role" id="edit_role" class="form-select">
                                    <option value="cliente">Cliente</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Fecha de nacimiento</label>
                                <input type="date" name="fecha_nacimiento" id="edit_nac" class="form-control">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label">DPI</label>
                                <input name="dpi" id="edit_dpi" class="form-control">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Teléfono</label>
                                <input name="telefono" id="edit_tel" class="form-control">
                            </div>
                        </div>
                        <div><label class="form-label">Nueva contraseña (opcional)</label><input name="password" type="password" class="form-control" minlength="8" placeholder="Déjalo vacío para no cambiar"></div>

                        <div class="border rounded p-2">
                            <div class="d-flex align-items-center gap-2">
                                <img id="edit_preview" class="avatar" src="" alt="avatar">
                                <div class="small text-muted" id="edit_foto_label">Sin foto</div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label">Reemplazar foto</label>
                                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="form-control">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_foto" id="edit_remove_foto">
                                    <label class="form-check-label" for="edit_remove_foto">Quitar foto</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                        <button class="btn btn-success">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ELIMINAR -->
    <div class="modal fade" id="mdlDelete" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="del_id">
                    <div class="modal-header">
                        <h6 class="modal-title">Eliminar usuario</h6>
                        <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">¿Eliminar a <strong id="del_name">usuario</strong>?</p>
                        <small class="text-muted">Se eliminarán suscripciones y pagos asociados.</small>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                        <button class="btn btn-danger">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Editor: carga datos en el modal
        document.getElementById('mdlEdit')?.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            edit_id.value = b.dataset.id;
            edit_name.value = b.dataset.name || '';
            edit_apellido.value = b.dataset.apellido || '';
            edit_email.value = b.dataset.email || '';
            edit_role.value = b.dataset.role || 'cliente';
            edit_dpi.value = b.dataset.dpi || '';
            edit_tel.value = b.dataset.telefono || '';
            edit_nac.value = b.dataset.nac || '';

            const foto = b.dataset.foto || '';
            const lbl = document.getElementById('edit_foto_label');
            const img = document.getElementById('edit_preview');
            if (foto) {
                img.src = '/ecobici/' + foto;
                lbl.textContent = foto;
            } else {
                img.src = 'https://ui-avatars.com/api/?size=80&background=16a34a&color=fff&name=' +
                    encodeURIComponent((edit_name.value + ' ' + edit_apellido.value).trim());
                lbl.textContent = 'Sin foto';
            }
            document.getElementById('edit_remove_foto').checked = false;
        });

        // Eliminar: info
        document.getElementById('mdlDelete')?.addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget;
            del_id.value = b.dataset.id;
            del_name.textContent = b.dataset.name || ('#' + b.dataset.id);
        });
    </script>
</body>

</html>