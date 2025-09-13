<?php
// /ecobici/cliente/reportar_dano_guardar.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

// Redirección PRG (Post-Redirect-Get)
function endAndGo(string $to){
  session_write_close();
  header('Location: ' . $to, true, 303); // 303 See Other
  exit;
}
function fail(string $msg){ $_SESSION['flash']=['type'=>'danger','msg'=>$msg]; endAndGo('/ecobici/cliente/reporte_dano.php?err=1'); }
function ok(string $msg){ $_SESSION['flash']=['type'=>'success','msg'=>$msg]; endAndGo('/ecobici/cliente/reporte_dano.php?ok=1'); }

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') endAndGo('/ecobici/cliente/reporte_dano.php');

// Sesión + CSRF
if (!isset($_SESSION['user'])) fail('Sesión expirada.');
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) fail('CSRF inválido.');

$uid    = (int)$_SESSION['user']['id'];
$nota   = trim($_POST['nota'] ?? '');
$codigo = trim($_POST['codigo'] ?? '');
$bikeId = isset($_POST['bike_id']) ? (int)$_POST['bike_id'] : null;

if ($nota === '') fail('Describe el problema.');

try {
  $pdo->beginTransaction();

  // Resolver bicicleta (prioriza código si lo hay)
  if ($codigo !== '') {
    $q = $pdo->prepare("SELECT id FROM bikes WHERE codigo=? LIMIT 1");
    $q->execute([$codigo]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $pdo->rollBack(); fail('Código de bicicleta no válido.'); }
    $bikeId = (int)$row['id'];
  }
  if (!$bikeId) { $pdo->rollBack(); fail('No se pudo determinar la bicicleta.'); }

  // Foto opcional – máx. 5 MB
  $path = null;
  if (!empty($_FILES['foto']['name'])) {
    $f = $_FILES['foto'];
    if ($f['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($f['error'] !== UPLOAD_ERR_OK) { $pdo->rollBack(); fail('Error al subir la imagen.'); }
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp'])) { $pdo->rollBack(); fail('Formato no permitido.'); }
      if ($f['size'] > 5*1024*1024) { $pdo->rollBack(); fail('La imagen supera 5 MB.'); }

      $dir = __DIR__ . '/../uploads/damages';
      if (!is_dir($dir)) mkdir($dir, 0777, true);
      $name = bin2hex(random_bytes(8)).'_'.time().'.'.$ext;
      $abs  = $dir.'/'.$name;
      if (!move_uploaded_file($f['tmp_name'], $abs)) { $pdo->rollBack(); fail('No se pudo guardar la imagen.'); }
      $path = 'uploads/damages/'.$name;
    }
  }

  // Insertar reporte (estado por defecto: 'nuevo')
  $ins = $pdo->prepare("INSERT INTO damage_reports (bike_id,user_id,nota,foto) VALUES (?,?,?,?)");
  $ins->execute([$bikeId, $uid, $nota, $path]);

  $pdo->commit();
  ok('Reporte enviado. ¡Gracias por avisar!');
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fail('Ocurrió un error al guardar el reporte.');
}