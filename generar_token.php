<?php
// generar_token.php

require_once 'conexion.php'; // Incluye la conexión a la base de datos

header("Content-Type: application/json");
// Update CORS headers to handle both domains
header("Access-Control-Allow-Origin: https://sheerit.com.co");
// Add additional headers needed for CORS
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

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
error_log("Datos en bruto recibidos: " . $inputData);

$datos = json_decode($inputData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Error al decodificar JSON: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['error' => 'Error al decodificar JSON: ' . json_last_error_msg()]);
    exit;
}
error_log("Datos decodificados: " . print_r($datos, true));

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

// Configuración de integración de Bold usando datos de la plataforma
$apiKey         = '1y0D48xaDriWO_CNz7oXUopfkKx5VjiExsdDW0gj2eA';
$integrityKey   = 'fn6G5OztUmMcvQX6YXU2Tg';
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