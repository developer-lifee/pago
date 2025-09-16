<?php
// generar_token.php

require_once 'conexion.php'; // Incluye la conexión y la configuración

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://sheerit.com.co");
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
$datos = json_decode($inputData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Error al decodificar JSON']);
    exit;
}

// Extraer y validar datos
$customer = $datos['customer'] ?? null;
$platformData = $datos['platform'] ?? null;

if (
    !$customer || empty($customer['firstName']) || empty($customer['lastName']) ||
    empty($customer['email']) || empty($customer['whatsapp']) ||
    empty($datos['numbers']) || !$platformData || !isset($platformData['price'])
) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos obligatorios']);
    exit;
}

$firstName = $customer['firstName'];
$lastName = $customer['lastName'];
$email = $customer['email'];
$whatsapp = $customer['whatsapp'];
$numbersStr = implode(',', $datos['numbers']);

// --- CONFIGURACIÓN DE BOLD (Desde config.php) ---
$apiKey         = $config['bold_identity_key'];
$integrityKey   = $config['bold_secret_key'];

// --- CORRECCIÓN CLAVE: Generar un ID de orden verdaderamente único ---
// Usar uniqid() y time() juntos hace casi imposible una colisión.
$orderId        = 'ORDEN-' . uniqid() . '-' . time();

$amount         = strval($platformData['price']);
$currency       = 'COP';
$description    = 'Suscripción a ' . ($platformData['name'] ?? 'Sheerit');
$tax            = 'vat-19';
$redirectionUrl = 'https://sheerit.com.co/';

// --- FÓRMULA DE FIRMA CORRECTA (Según la documentación que proporcionaste) ---
$cadena_concatenada = $orderId . $amount . $currency . $integrityKey;
$integritySignature = hash("sha256", $cadena_concatenada);

// Guardar en la base de datos temporal
try {
    $conn->beginTransaction();
    $stmt = $conn->prepare("INSERT INTO customers_temp (order_id, firstName, lastName, email, whatsapp, numbers) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$orderId, $firstName, $lastName, $email, $whatsapp, $numbersStr]);
    $conn->commit();
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error BBDD: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al procesar la solicitud']);
    exit;
}

// Respuesta para el frontend
$response = [
    'orderId'           => $orderId,
    'apiKey'            => $apiKey,
    'amount'            => $amount,
    'currency'          => $currency,
    'description'       => $description,
    'tax'               => $tax,
    'integritySignature'=> $integritySignature,
    'redirectionUrl'    => $redirectionUrl,
];

echo json_encode($response);
?>