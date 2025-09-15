<?php
// generar_token.php

// Configuración de logging
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/token.log';

// Función para escribir en el log de manera sencilla
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

write_log("--- INICIO DE NUEVA PETICIÓN DE TOKEN ---");

// Configurar zona horaria
date_default_timezone_set('America/Bogota');

// Capturar errores fatales
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    write_log("ERROR FATAL: [$errno] $errstr en $errfile:$errline");
    return false;
});

try {
    require_once 'conexion.php'; // Incluye la conexión a la base de datos
    write_log("Conexión a la base de datos establecida correctamente");
} catch (Exception $e) {
    write_log("ERROR: No se pudo conectar a la base de datos - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
    exit;
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://sheerit.com.co");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

write_log("Headers CORS configurados correctamente");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$inputData = file_get_contents('php://input');
write_log("Datos en bruto recibidos: " . $inputData);

if (empty($inputData)) {
    write_log("ERROR: No se recibieron datos en el cuerpo de la petición");
    http_response_code(400);
    echo json_encode(['error' => 'No se recibieron datos']);
    exit;
}

$datos = json_decode($inputData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    write_log("Error al decodificar JSON: " . json_last_error_msg());
    write_log("Datos recibidos que causaron el error: " . $inputData);
    http_response_code(400);
    echo json_encode(['error' => 'Error al decodificar JSON: ' . json_last_error_msg()]);
    exit;
}
write_log("Datos decodificados correctamente: " . print_r($datos, true));

// Extraer datos del cliente desde la clave 'customer'
$customer = $datos['customer'] ?? null;
$platformData = $datos['platform'] ?? null;

if (
    !$customer ||
    empty($customer['firstName']) ||
    empty($customer['lastName']) ||
    empty($customer['email']) ||
    empty($customer['whatsapp']) ||
    empty($datos['numbers']) ||
    !$platformData
) {
    error_log("Faltan campos obligatorios.");
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos obligatorios']);
    exit;
}

$firstName = $customer['firstName'];
$lastName = $customer['lastName'];
$email = $customer['email'];
$whatsapp = $customer['whatsapp'];
$numbers = $datos['numbers'];
$numbersStr = implode(',', $numbers);

// Configuración de integración de Bold usando datos del array de config
$apiKey         = $config['bold_identity_key']; // Llave de identidad
$integrityKey   = $config['bold_secret_key'];   // Llave secreta
$orderId        = 'ORDEN-' . time();
$amount         = strval($platformData['price']);  // Convertir el precio a string
$currency       = 'COP';
$description    = 'Suscripción a ' . $platformData['name'];
$tax            = 'vat-19';
$redirectionUrl = 'https://sheerit.com.co/';

// Generar la firma de integridad
$cadena_concatenada = $orderId . $amount . $currency . $integrityKey;
$integritySignature = hash("sha256", $cadena_concatenada);

// Insertar en la tabla temporal (por ejemplo, customers_temp)
try {
    $conn->beginTransaction();
    $stmt = $conn->prepare("INSERT INTO customers_temp (order_id, firstName, lastName, email, whatsapp, numbers) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$orderId, $firstName, $lastName, $email, $whatsapp, $numbersStr]);
    $conn->commit();
    error_log("Datos insertados en customers_temp con order_id: " . $orderId);
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error al guardar datos: " . $e->getMessage());
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

echo json_encode($response);
?>