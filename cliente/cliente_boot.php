<?php
// /ecobici/cliente/cliente_boot.php

// Ajusta si tu app vive en otra subcarpeta
$BASE = '/ecobici';

// Endurecer cookie de sesión (opcional)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => false, // true si sirves por HTTPS
    ]);
    session_start();
}

/**
 * Compatibilidad:
 * - Tu login guarda user_id / user_role / user_name
 * - Y ahora también $_SESSION['user'] (id,name,email,role)
 * Validamos primero las claves nuevas; si no están, probamos el array 'user'.
 */
$role = $_SESSION['user_role'] ?? ($_SESSION['user']['role'] ?? '');
$uid  = $_SESSION['user_id']   ?? ($_SESSION['user']['id']   ?? null);

if (!$uid || $role !== 'cliente') {
    header("Location: {$BASE}/login.php");
    exit;
}

// (Opcional) Conexión a BD si la necesitas en páginas cliente
require_once __DIR__ . '/../config/db.php';
