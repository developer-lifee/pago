<?php
// generar_token.php

// --- INICIO DE CONFIGURACIÓN DE LOGGING ---
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/token.log';

// Función para escribir en el log
function write_log($message) {
    global $log_file;
    $log_entry = sprintf(
        "[%s] [TOKEN] [%s] %s\n",
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP',
        $message
    );
    error_log($log_entry, 3, $log_file);
}

// --> AÑADIDO: Registrar el inicio absoluto del script
write_log("--- INICIO DE EJECUCIÓN DE generar_token.php ---");

// Configurar zona horaria y manejo de errores
date_default_timezone_set('America/Bogota');
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    write_log("ERROR FATAL: [$errno] $errstr en $errfile:$errline");
    // --> AÑADIDO: Asegurarse de que los errores fatales no silencien el script
    if (error_reporting() !== 0) {
        // Solo procesa si el error no fue suprimido con @
        http_response_code(500);
        echo json_encode(['error' => 'Error interno del servidor. Revise los logs.']);
        exit;
    }
    return false;
});
// --- FIN DE CONFIGURACIÓN DE LOGGING ---

// --> AÑADIDO: Log antes de incluir archivos
write_log("Intentando incluir 'conexion.php'...");
try {
    require_once 'conexion.php';
    write_log("'conexion.php' incluido y conexión a la base de datos establecida.");
} catch (Exception $e) {
    write_log("ERROR CRÍTICO: No se pudo conectar a la base de datos - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
    exit;
}

// Configuración de Headers y CORS
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://sheerit.com.co");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
write_log("Headers CORS configurados.");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    write_log("Petición OPTIONS recibida. Respondiendo con 200 OK.");
    http_response_code(200);
    exit;
}

// --> AÑADIDO: Log para verificar el método de la petición
write_log("Método de petición recibido: " . $_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    write_log("ERROR: Método no permitido. Se esperaba POST.");
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// --> AÑADIDO: Log antes de leer el cuerpo de la petición
write_log("Intentando leer el cuerpo de la petición (php://input)...");
$inputData = file_get_contents('php://input');
write_log("Datos en bruto recibidos: " . ($inputData ?: 'ninguno'));

if (empty($inputData)) {
    write_log("ERROR: No se recibieron datos en el cuerpo de la petición.");
    http_response_code(400);
    echo json_encode(['error' => 'No se recibieron datos']);
    exit;
}

// --> AÑADIDO: Log antes de decodificar el JSON
write_log("Intentando decodificar los datos como JSON...");
$datos = json_decode($inputData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    write_log("ERROR al decodificar JSON: " . json_last_error_msg());
    write_log("Datos que causaron el error: " . $inputData);
    http_response_code(400);
    echo json_encode(['error' => 'Error al decodificar JSON: ' . json_last_error_msg()]);
    exit;
}
write_log("Datos JSON decodificados correctamente.");

// ... (El resto de tu lógica de validación y procesamiento sigue aquí)

// Extraer datos del cliente desde la clave 'customer'
$customer = $datos['customer'] ?? null;
$platformData = $datos['platform'] ?? null;

// ... (resto del código sin cambios)
$camposFaltantes = [];

if (!$customer) {
    $camposFaltantes[] = 'customer';
} else {
    if (empty($customer['firstName'])) $camposFaltantes[] = 'firstName';
    if (empty($customer['lastName'])) $camposFaltantes[] = 'lastName';
    if (empty($customer['email'])) $camposFaltantes[] = 'email';
    if (empty($customer['whatsapp'])) $camposFaltantes[] = 'whatsapp';
}

if (empty($datos['numbers'])) $camposFaltantes[] = 'numbers';
if (!$platformData) $camposFaltantes[] = 'platform';

if (!empty($camposFaltantes)) {
    write_log("ERROR: Faltan los siguientes campos obligatorios: " . implode(', ', $camposFaltantes));
    http_response_code(400);
    echo json_encode([
        'error' => 'Faltan campos obligatorios',
        'campos_faltantes' => $camposFaltantes
    ]);
    exit;
}

write_log("Todos los campos obligatorios están presentes");

$firstName = $customer['firstName'];
$lastName = $customer['lastName'];
$email = $customer['email'];
$whatsapp = $customer['whatsapp'];
$numbers = $datos['numbers'];
$numbersStr = implode(',', $numbers);

// Configuración de integración de Bold usando datos del array de config
write_log("Configurando datos de integración con Bold...");

try {
    if (!isset($config['bold_identity_key']) || !isset($config['bold_secret_key'])) {
        write_log("ERROR: Falta configuración de Bold en el archivo de configuración");
        throw new Exception("Configuración de Bold incompleta");
    }

    $apiKey         = $config['bold_identity_key']; // Llave de identidad
    $integrityKey   = $config['bold_secret_key'];   // Llave secreta
    
    // Validación de llaves de Bold
    if (strpos($apiKey, 'test') !== false || strpos($integrityKey, 'test') !== false) {
        write_log("ADVERTENCIA: Se están usando llaves de prueba de Bold");
    }
    
    // Log de los primeros 8 caracteres de las llaves para debug (no mostrar la llave completa por seguridad)
    write_log("Validación de llaves Bold - Identity Key (primeros 8 chars): " . substr($apiKey, 0, 8));
    write_log("Validación de llaves Bold - Secret Key (primeros 8 chars): " . substr($integrityKey, 0, 8));
    
    $orderId        = 'ORDEN-' . time();
    
    if (!isset($platformData['price'])) {
        write_log("ERROR: Falta el precio en los datos de la plataforma");
        throw new Exception("Precio no especificado");
    }
    
    $amount         = strval($platformData['price']);  // Convertir el precio a string
    $currency       = 'COP';
    $description    = 'Suscripción a ' . ($platformData['name'] ?? 'servicio');
    $tax            = 'vat-19';
    $redirectionUrl = 'https://sheerit.com.co/';
    
    write_log("Datos de configuración de Bold procesados correctamente");
    write_log("OrderID generado: " . $orderId);
} catch (Exception $e) {
    write_log("ERROR en la configuración de Bold: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error en la configuración del pago']);
    exit;
}

// Generar la firma de integridad
// La cadena debe ser: monto + moneda + llave secreta (en ese orden exacto)
$cadena_concatenada = $amount . $currency . $integrityKey;
write_log("Generando firma de integridad (sin mostrar llave secreta)...");
write_log("Elementos usados: amount=" . $amount . ", currency=" . $currency);
$integritySignature = hash("sha256", $cadena_concatenada);
write_log("Firma de integridad generada exitosamente");

// Insertar en la tabla temporal (por ejemplo, customers_temp)
try {
    $conn->beginTransaction();
    $stmt = $conn->prepare("INSERT INTO customers_temp (order_id, firstName, lastName, email, whatsapp, numbers) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$orderId, $firstName, $lastName, $email, $whatsapp, $numbersStr]);
    $conn->commit();
    write_log("Datos insertados en customers_temp con order_id: " . $orderId);
} catch (PDOException $e) {
    $conn->rollBack();
    write_log("Error al guardar datos en la BD: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar los datos: ' . $e->getMessage()]);
    exit;
}

$response = [
    'orderId' => $orderId,
    'apiKey' => $apiKey,
    'amount' => $amount,
    'currency' => $currency,
    'description' => $description,
    'tax' => $tax,
    'integritySignature' => $integritySignature,
    'redirectionUrl' => $redirectionUrl,
];

// --> AÑADIDO: Log final antes de enviar la respuesta
write_log("Respuesta generada exitosamente. Enviando al cliente...");
echo json_encode($response);
exit; // --> AÑADIDO: Terminar explícitamente el script
?>