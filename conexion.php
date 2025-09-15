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
    write_log("Ambiente actual: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'no definido'));
    
    // Validar que las credenciales no sean de prueba si estamos en producción
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        if (strpos($host, 'localhost') !== false || $host === '127.0.0.1') {
            write_log("ADVERTENCIA: Usando host local en ambiente de producción");
        }
    }
    
    $conn = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8mb4");
    write_log("Conexión exitosa a la base de datos en modo: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'no definido'));

} catch (PDOException $exception) {
    error_log("Error de conexión: " . $exception->getMessage());
    exit;
}
?>