<?php
// /ecobici/cliente/perfil_guardar.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

function backWith(string $type, string $msg){
  $_SESSION['flash'] = ['type'=>$type,'msg'=>$msg];
  header('Location: /ecobici/cliente/perfil.php'); exit;
}
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (!isset($_SESSION['user'])) backWith('danger','Sesión expirada.');
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) backWith('danger','CSRF inválido.');

$uid   = (int)($_SESSION['user']['id'] ?? 0);
$name  = trim($_POST['name'] ?? '');
$apel  = trim($_POST['apellido'] ?? '');
$email = trim($_POST['email'] ?? '');
$dpi   = trim($_POST['dpi'] ?? '');
$tel   = trim($_POST['telefono'] ?? '');
$fnac  = $_POST['fecha_nacimiento'] ?? null;

// Validaciones básicas
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) backWith('warning','Revisa nombre y correo.');
if ($fnac && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fnac)) $fnac = null;

// Obtener foto actual
$st = $pdo->prepare("SELECT foto FROM users WHERE id=?");
$st->execute([$uid]);
$curr = $st->fetchColumn();

// Subida de imagen (opcional)
$newPath = $curr;
if (!empty($_FILES['foto']['name'])) {
  $f = $_FILES['foto'];
  if ($f['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) backWith('warning','Formato de imagen no permitido.');
    if ($f['size'] > 3*1024*1024) backWith('warning','La imagen supera 3 MB.');
    $dir = __DIR__ . '/../uploads/users';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $fname   = bin2hex(random_bytes(8)).'_'.time().'.'.$ext;
    $destAbs = $dir . '/' . $fname;
    if (!move_uploaded_file($f['tmp_name'], $destAbs)) backWith('danger','No se pudo guardar la imagen.');
    $newPath = 'uploads/users/'.$fname;
    // (Opcional) borrar anterior si era local
    if ($curr && str_starts_with($curr, 'uploads/users/') && file_exists(__DIR__.'/../'.$curr)) {
      @unlink(__DIR__.'/../'.$curr);
    }
  } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
    backWith('danger','Error al subir la imagen.');
  }
}

// Evitar duplicados de correo
$st = $pdo->prepare("SELECT COUNT(1) FROM users WHERE email=? AND id<>?");
$st->execute([$email, $uid]);
if ((int)$st->fetchColumn() > 0) backWith('warning','Ese correo ya está en uso.');

$st = $pdo->prepare("
  UPDATE users
     SET name=?, apellido=?, email=?, dpi=?, telefono=?, fecha_nacimiento=?, foto=?, updated_at=NOW()
   WHERE id=? LIMIT 1
");
$st->execute([$name, $apel ?: null, $email, $dpi ?: null, $tel ?: null, $fnac ?: null, $newPath ?: null, $uid]);

// Actualizar sesión visible
$_SESSION['user']['name']  = $name;
$_SESSION['user']['email'] = $email;

backWith('success','Perfil actualizado correctamente.');
