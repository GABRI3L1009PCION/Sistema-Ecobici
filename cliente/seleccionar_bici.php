<?php
// /ecobici/cliente/seleccionar_bici.php
declare(strict_types=1);
require_once __DIR__ . '/cliente_boot.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$userId     = (int)($_SESSION['user']['id'] ?? 0);
$bikeId     = isset($_GET['bike_id']) ? (int)$_GET['bike_id'] : 0;
$stationId  = isset($_GET['station_id']) ? (int)$_GET['station_id'] : 0;
$prefTipo   = isset($_GET['tipo']) ? trim($_GET['tipo']) : ''; // 'tradicional' | 'electrica' | ''

if (!$userId) {
  header('Location: /ecobici/login.php'); exit;
}

// ---------- 1) Validar suscripción activa del usuario ----------
try {
  $st = $pdo->prepare("
    SELECT s.id, s.estado, s.fecha_inicio, s.fecha_fin, p.nombre AS plan_nombre
    FROM subscriptions s
    JOIN plans p ON p.id = s.plan_id
    WHERE s.user_id = ?
      AND s.estado = 'activa'
      AND s.fecha_inicio <= CURDATE()
      AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURDATE())
    ORDER BY s.fecha_fin DESC
    LIMIT 1
  ");
  $st->execute([$userId]);
  $sub = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $sub = null;
}

if (!$sub) {
  $_SESSION['flash_error'] = "Necesitas una suscripción activa para usar una bicicleta.";
  header('Location: /ecobici/cliente/membresia.php'); exit;
}

// ---------- 2) Resolver bicicleta candidata ----------
$bike = null;

try {
  if ($bikeId > 0) {
    // Viene de "Elegir" en el modal
    $q = $pdo->prepare("
      SELECT id, codigo, tipo, estado, station_id
      FROM bikes
      WHERE id = ?
      LIMIT 1
    ");
    $q->execute([$bikeId]);
    $bike = $q->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$bike) {
      $_SESSION['flash_error'] = "La bicicleta seleccionada no existe.";
      header('Location: /ecobici/cliente/estaciones.php'); exit;
    }
    if ($bike['estado'] !== 'disponible') {
      $_SESSION['flash_error'] = "La bicicleta {$bike['codigo']} ya no está disponible.";
      header('Location: /ecobici/cliente/estaciones.php'); exit;
    }

  } elseif ($stationId > 0) {
    // Viene del botón "Usar bici" de la estación: tomar la primera disponible (respeta tipo si se envía)
    if ($prefTipo && !in_array($prefTipo, ['tradicional','electrica'], true)) {
      $prefTipo = '';
    }
    $sql = "
      SELECT id, codigo, tipo, estado, station_id
      FROM bikes
      WHERE station_id = ?
        AND estado = 'disponible'
      " . ($prefTipo ? " AND tipo = ? " : "") . "
      ORDER BY (tipo='electrica') DESC, codigo ASC
      LIMIT 1
    ";
    $q = $pdo->prepare($sql);
    $params = $prefTipo ? [$stationId, $prefTipo] : [$stationId];
    $q->execute($params);
    $bike = $q->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$bike) {
      $_SESSION['flash_error'] = "No hay bicicletas disponibles en la estación seleccionada.";
      header('Location: /ecobici/cliente/estaciones.php'); exit;
    }
  } else {
    $_SESSION['flash_error'] = "Debes seleccionar una bicicleta o una estación.";
    header('Location: /ecobici/cliente/estaciones.php'); exit;
  }
} catch (Throwable $e) {
  $_SESSION['flash_error'] = "No se pudo buscar bicicleta: " . $e->getMessage();
  header('Location: /ecobici/cliente/estaciones.php'); exit;
}

// ---------- 3) Iniciar viaje (trips) + marcar bici en uso ----------
try {
  $pdo->beginTransaction();

  // Releer la bici con FOR UPDATE para evitar carrera
  $lock = $pdo->prepare("SELECT id, codigo, tipo, estado, station_id FROM bikes WHERE id = ? FOR UPDATE");
  $lock->execute([(int)$bike['id']]);
  $locked = $lock->fetch(PDO::FETCH_ASSOC);
  if (!$locked || $locked['estado'] !== 'disponible') {
    $pdo->rollBack();
    $_SESSION['flash_error'] = "La bicicleta ya no está disponible.";
    header('Location: /ecobici/cliente/estaciones.php'); exit;
  }

  $startStationId = $locked['station_id'] ? (int)$locked['station_id'] : ($stationId ?: null);

  // Insertar trip (usa columnas reales: start_at, end_at, etc.)
  $ins = $pdo->prepare("
    INSERT INTO trips (user_id, bike_id, start_station_id, start_at, distancia_km, costo, co2_kg)
    VALUES (?, ?, ?, NOW(), 0.00, 0.00, 0.000)
  ");
  $ins->execute([$userId, (int)$locked['id'], $startStationId]);

  // Poner bici en uso y sacarla de estación (station_id = NULL)
  $upd = $pdo->prepare("UPDATE bikes SET estado='uso', station_id=NULL WHERE id=?");
  $upd->execute([(int)$locked['id']]);

  $pdo->commit();

  $_SESSION['flash_ok'] = "¡Listo! Iniciaste viaje con la bici {$locked['codigo']} ({$locked['tipo']}).";
  header('Location: /ecobici/cliente/historial.php'); exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION['flash_error'] = "No se pudo iniciar el viaje: " . $e->getMessage();
  header('Location: /ecobici/cliente/estaciones.php'); exit;
}
