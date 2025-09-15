<?php
require_once 'config.php'; // Incluir la configuración

// Usar las variables del array de configuración
$host = $config['db_host'];
$db_name = $config['db_name'];
$username = $config['db_user'];
$password = $config['db_pass'];

// Define una ruta para el archivo de log
$log_file = __DIR__ . '/db_debug.log';

// Función para escribir en el log
function write_log($message) {
    global $log_file;
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, $log_file);
}

try {
    write_log("Intentando conectar a la BD - Host: {$host}, DB: {$db_name}, Usuario: {$username}");
    $conn = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8mb4");
    write_log("Conexión exitosa a la base de datos en modo: " . ENVIRONMENT);

} catch (PDOException $exception) {
    error_log("Error de conexión: " . $exception->getMessage());
    exit;
}
?>