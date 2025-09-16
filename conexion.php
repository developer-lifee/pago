<?php
// conexion.php

// --- INICIO DE CONFIGURACIÓN DE LOGGING ---
// Define una ruta única para el log de la base de datos
$db_log_file = __DIR__ . '/logs/database.log';

// --> AÑADIDO: Función de logging robusta para evitar conflictos
// Comprueba si la función ya fue declarada por otro script (como generar_token.php)
if (!function_exists('write_db_log')) {
    function write_db_log($message) {
        global $db_log_file;
        // --> AÑADIDO: Asegurarse de que el directorio de logs exista
        $log_dir = dirname($db_log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $log_entry = sprintf(
            "[%s] [DATABASE] [%s] %s\n",
            date('Y-m-d H:i:s'),
            $_SERVER['SCRIPT_NAME'] ?? 'CLI', // Ver qué script está llamando
            $message
        );
        error_log($log_entry, 3, $db_log_file);
    }
}
// --- FIN DE CONFIGURACIÓN DE LOGGING ---

write_db_log("--- Inicio de ejecución de conexion.php ---");

// --> AÑADIDO: Log antes de incluir el archivo de configuración
write_db_log("Intentando incluir 'config.php'...");
if (file_exists('config.php')) {
    require_once 'config.php';
    write_db_log("'config.php' incluido correctamente.");
} else {
    write_db_log("ERROR CRÍTICO: El archivo 'config.php' no existe.");
    // Detener la ejecución para evitar más errores
    http_response_code(500);
    echo json_encode(['error' => 'Error de configuración del servidor.']);
    exit;
}

// --> AÑADIDO: Verificar que el array $config exista y no esté vacío
if (empty($config)) {
    write_db_log("ERROR CRÍTICO: El array \$config está vacío o no fue definido en 'config.php'.");
    http_response_code(500);
    echo json_encode(['error' => 'Error de configuración del servidor.']);
    exit;
}
write_db_log("El array \$config se cargó correctamente.");

// Extraer variables de configuración de forma segura
$host = $config['db_host'] ?? null;
$db_name = $config['db_name'] ?? null;
$username = $config['db_user'] ?? null;
$password = $config['db_pass'] ?? null;

// --> AÑADIDO: Log para verificar las credenciales (sin mostrar la contraseña)
write_db_log("Credenciales cargadas -> Host: {$host}, DB: {$db_name}, Usuario: {$username}");

// --> AÑADIDO: Validar que todas las credenciales estén presentes
if (!$host || !$db_name || !$username || !$password) {
    write_db_log("ERROR CRÍTICO: Una o más variables de la base de datos no están definidas en 'config.php'.");
    http_response_code(500);
    echo json_encode(['error' => 'Error de configuración del servidor.']);
    exit;
}

try {
    write_db_log("Intentando crear una nueva instancia de PDO para la conexión...");
    
    $conn = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    
    // Configurar atributos después de una conexión exitosa
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8mb4");
    
    write_db_log("¡ÉXITO! Conexión a la base de datos establecida y configurada.");

} catch (PDOException $exception) {
    // --> MEJORADO: Capturar y registrar el error específico de PDO
    write_db_log("ERROR DE CONEXIÓN PDO: " . $exception->getMessage());
    
    // Detener la ejecución para que el script que lo llama no continúe sin BD
    http_response_code(500);
    // Es importante no mostrar el mensaje de la excepción al usuario final por seguridad
    echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
    exit;
}
?>