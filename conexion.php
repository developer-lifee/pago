<?php
require_once 'config.php'; // Incluir la configuración

// Usar las variables del array de configuración
$host = $config['db_host'];
$db_name = $config['db_name'];
$username = $config['db_user'];
$password = $config['db_pass'];

try {
    $conn = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8mb4");

    // Opcional: registrar en qué modo se está conectando
    error_log("Conexión exitosa a la base de datos en modo: " . ENVIRONMENT);

} catch (PDOException $exception) {
    error_log("Error de conexión: " . $exception->getMessage());
    exit;
}
?>