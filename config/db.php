<?php
// config/db.php
// Configuración de la conexión
$DB_HOST = '127.0.0.1';
$DB_NAME = 'ecobici';
$DB_USER = 'root';
$DB_PASS = ''; 

try {
    // Conectar con PDO
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Errores lanzan excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch como arrays asociativos
        ]
    );
} catch (Throwable $e) {
    // Si falla la conexión, mostrar error
    http_response_code(500);
    echo "<h2>Error de conexión a la base de datos</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}
