<?php
// /ecobici/cliente/finalizar_viaje.php
declare(strict_types=1);
require_once __DIR__ . '/cliente_boot.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$userId = (int)($_SESSION['user']['id'] ?? 0);
if (!$userId) { header('Location: /ecobici/login.php'); exit; }

$tripId = (int)($_POST['trip_id'] ?? 0);
$endStationId = (int)($_POST['end_station_id'] ?? 0);

if ($tripId <= 0 || $endStationId <= 0) {
  $_SESSION['flash_error'] = "Datos incompletos para finalizar el viaje.";
  header('Location: /ecobici/cliente/historial.php'); exit;
}

// Helper haversine (km)
function haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float {
  $R = 6371.0; // km
  $dLat = deg2rad($lat2 - $lat1);
  $dLon = deg2rad($lon2 - $lon1);
  $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
  $c = 2 * atan2(sqrt($a), sqrt(1-$a));
  return $R * $c;
}

try {
  $pdo->beginTransaction();

  // 1) Viaje activo del usuario (FOR UPDATE)
  $qt = $pdo->prepare("
    SELECT t.*, b.codigo AS bike_codigo, b.id AS bike_id
    FROM trips t
    JOIN bikes b ON b.id = t.bike_id
    WHERE t.id = ? AND t.user_id = ? AND t.end_at IS NULL
    FOR UPDATE
  ");
  $qt->execute([$tripId, $userId]);
  $trip = $qt->fetch(PDO::FETCH_ASSOC);

  if (!$trip) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = "No se encontró un viaje activo para cerrar.";
    header('Location: /ecobici/cliente/historial.php'); exit;
  }

  // 2) Estación inicio y fin
  $qs = $pdo->prepare("SELECT id, lat, lng, nombre FROM stations WHERE id IN (?, ?)");
  $qs->execute([(int)$trip['start_station_id'], $endStationId]);
  $sts = $qs->fetchAll(PDO::FETCH_ASSOC);

  $m = [];
  foreach ($sts as $s) { $m[(int)$s['id']] = $s; }

  $startS = $m[(int)$trip['start_station_id']] ?? null;
  $endS   = $m[$endStationId] ?? null;

  if (!$endS) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = "La estación de destino no existe.";
    header('Location: /ecobici/cliente/historial.php'); exit;
  }

  // 3) Calcular distancia (si no hay estación de inicio, asumimos 0)
  $distKm = 0.00;
  if ($startS) {
    $distKm = haversine_km(
      (float)$startS['lat'], (float)$startS['lng'],
      (float)$endS['lat'],   (float)$endS['lng']
    );
  }

  // 4) Factor CO2 desde settings (co2_factor_kg_km)
  $factor = 0.0;
  try {
    $g = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'co2_factor_kg_km' LIMIT 1");
    $g->execute();
    $factor = (float)($g->fetchColumn() ?: 0.0);
  } catch (Throwable $e) { $factor = 0.0; }
  $co2 = $distKm * $factor; // kg

  // (Tarifa opcional) Por ahora costo 0.00; aquí podrías aplicar regla por plan o por km.
  $costo = 0.00;

  // 5) Cerrar viaje
  $updT = $pdo->prepare("
    UPDATE trips
    SET end_station_id = ?, end_at = NOW(),
        distancia_km = ?, co2_kg = ?, costo = ?
    WHERE id = ?
  ");
  $updT->execute([$endStationId, $distKm, $co2, $costo, $tripId]);

  // 6) Regresar bicicleta a estación y marcar disponible
  $updB = $pdo->prepare("UPDATE bikes SET estado='disponible', station_id=? WHERE id=?");
  $updB->execute([$endStationId, (int)$trip['bike_id']]);

  $pdo->commit();

  $_SESSION['flash_ok'] = "Viaje finalizado. Distancia: ".number_format($distKm,2)." km · CO₂: ".number_format($co2,3)." kg.";
  header('Location: /ecobici/cliente/historial.php'); exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION['flash_error'] = "Error al finalizar el viaje: ".$e->getMessage();
  header('Location: /ecobici/cliente/historial.php'); exit;
}
