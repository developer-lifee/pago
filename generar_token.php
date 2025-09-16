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

// Configuración de Headers y CORS (limpia, sin duplicados)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://sheerit.com.co");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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
    echo json_encode(['error' => 'Error al decodificar JSON']);
    exit;
}
write_log("Datos decodificados correctamente: " . print_r($datos, true));

// Extraer datos del cliente desde la clave 'customer'
$customer = $datos['customer'] ?? null;
$platformData = $datos['platform'] ?? null; // Puede o no venir

write_log("Validando campos obligatorios...");
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
// 'platform' puede no venir si el frontend ya sabe el precio; no lo marcamos como faltante

if (!empty($camposFaltantes)) {
    write_log("ERROR: Faltan los siguientes campos obligatorios: " . implode(', ', $camposFaltantes));
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

// Derivar amount y description de manera robusta
$description = $datos['description'] ?? null;
// 1) Preferir platform.price y platform.name si existen
if (isset($platformData['price'])) {
    $rawAmount = $platformData['price'];
    if (!$description && isset($platformData['name'])) {
        $description = 'Suscripción a ' . $platformData['name'];
    }
}
// 2) Si viene amount como escalar directo
elseif (isset($datos['amount']) && !is_array($datos['amount'])) {
    $rawAmount = $datos['amount'];
}
// 3) Si se reconoce por el texto de numbers (fallback)
else {
    $subText = $numbers[0] ?? '';
    if (stripos($subText, 'Netflix Extra') !== false) {
        $rawAmount = 31115; // Precio conocido
    } else {
        write_log("ERROR: No se pudo determinar el monto (amount)");
        http_response_code(400);
        echo json_encode(['error' => 'Monto no especificado']);
        exit;
    }
    if (!$description) {
        $description = $subText ?: 'Suscripción';
    }
}

// Normalizar amount como string entero sin separadores
$amount = number_format((float)$rawAmount, 0, '', '');
$currency = 'COP';
if (!$description) {
    $description = 'Suscripción';
}
write_log("Valores normalizados -> amount={$amount}, currency={$currency}, description='{$description}'");

// Configuración de integración de Bold usando datos del array de config
write_log("Configurando datos de integración con Bold...");

try {
    if (!isset($config['bold_identity_key']) || !isset($config['bold_secret_key'])) {
        write_log("ERROR: Falta configuración de Bold en el archivo de configuración");
        throw new Exception("Configuración de Bold incompleta");
    }

    $apiKey       = $config['bold_identity_key']; // Llave de identidad (publica)
    $integrityKey = $config['bold_secret_key'];   // Llave secreta (privada)

    // Validación y log mínimo de llaves (sin exponerlas)
    write_log("Bold Keys -> identity(8): " . substr($apiKey, 0, 8) . ", secret(ult4): ..." . substr($integrityKey, -4));

    // Generar un ID único recomendado por Bold
    $orderId = 'ORD-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
    write_log("OrderId generado: " . $orderId);

    $tax            = 'vat-19';
    $redirectionUrl = 'https://sheerit.com.co/';
    write_log("Datos de integración listos");
} catch (Exception $e) {
    write_log("ERROR en la configuración de Bold: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error en la configuración del pago']);
    exit;
}

// Generar la firma de integridad: {Identificador}{Monto}{Divisa}{LlaveSecreta}
$cadena_concatenada = trim($orderId) . trim($amount) . trim(strtoupper($currency)) . trim($integrityKey);
$integritySignature = hash('sha256', $cadena_concatenada);
write_log("Firma generada para orderId {$orderId}");

// Guardar en la base de datos temporal
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
    echo json_encode(['error' => 'Error al procesar la solicitud']);
    exit;
}

// Respuesta simple para poblar atributos del botón Bold
$response = [
    'orderId' => $orderId,                    // data-order-id
    'currency' => $currency,                  // data-currency
    'amount' => $amount,                      // data-amount
    'apiKey' => $apiKey,                      // data-api-key
    'integritySignature' => $integritySignature, // data-integrity-signature
    'redirectionUrl' => $redirectionUrl,      // data-redirection-url
    'description' => $description,            // data-description
    'tax' => $tax                              // data-tax
];

echo json_encode($response);
?>