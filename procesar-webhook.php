<?php
// procesar-webhook.php

require_once 'conexion.php'; // Conexión a la base de datos

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // Ajusta según tus necesidades

// Función para consultar el fallback de Bold
function getFallbackNotification($orderId, $identityKey) {
    $url = 'https://integrations.api.bold.co/payments/webhook/notifications/' . urlencode($orderId) . '?is_external_reference=true';
    $headers = [
        "Authorization: x-api-key $identityKey",
        "Content-Type: application/json"
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
         error_log("Curl error in fallback: " . curl_error($ch));
         curl_close($ch);
         return null;
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode != 200) {
         error_log("Fallback HTTP error code: " . $httpCode);
         return null;
    }
    return json_decode($response, true);
}

// Leer el cuerpo de la solicitud POST
$body = file_get_contents('php://input');

// Validar que el JSON se haya decodificado correctamente
$data = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

// Validar la firma recibida (usando la llave secreta)
$signatureHeader = $_SERVER['HTTP_X_BOLD_SIGNATURE'] ?? '';
$secretKey = 'fn6G5OztUmMcvQX6YXU2Tg'; // Tu llave secreta de integración
$encodedBody = base64_encode($body);
$computedSignature = hash_hmac('sha256', $encodedBody, $secretKey);
if (!hash_equals($computedSignature, $signatureHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Firma no válida']);
    exit;
}

// Extraer el evento y el order_id (intenta primero del webhook)
$eventType = $data['type'] ?? '';
$orderId = null;
if (isset($data['data']['metadata']['reference'])) {
    $orderId = $data['data']['metadata']['reference'];
} else if (isset($data['subject'])) {
    $orderId = $data['subject'];
}

// Si no se encontró order_id o el evento no es SALE_APPROVED, se puede usar el fallback
if (!$orderId || $eventType !== 'SALE_APPROVED') {
    // Usa el fallback: consulta Bold con el order_id (que en este caso es tu referencia externa)
    if (!$orderId) {
        // Si no se obtuvo order_id vía webhook, intenta obtenerlo de un parámetro GET (si se ha enviado)
        $orderId = $_GET['order_id'] ?? null;
    }
    if ($orderId) {
        // Tu llave de identidad (para autorización en la consulta fallback)
        $identityKey = '1y0D48xaDriWO_CNz7oXUopfkKx5VjiExsdDW0gj2eA';
        $fallbackResponse = getFallbackNotification($orderId, $identityKey);
        if ($fallbackResponse && isset($fallbackResponse['notifications'])) {
            // Tomamos la primera notificación
            $notification = $fallbackResponse['notifications'][0] ?? null;
            if ($notification && isset($notification['type']) && $notification['type'] === 'SALE_APPROVED') {
                $eventType = 'SALE_APPROVED';
            } else {
                // Si la notificación no indica aprobación, respondemos y salimos
                http_response_code(200);
                echo json_encode(['message' => 'Evento no aprobado según fallback']);
                exit;
            }
        } else {
            // No se pudo obtener fallback
            http_response_code(200);
            echo json_encode(['message' => 'No se pudo obtener fallback para order_id ' . $orderId]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'No se encontró order id']);
        exit;
    }
}

if ($eventType === 'SALE_APPROVED') {
    $conn->beginTransaction();
    try {
        // Buscar en la tabla temporal (customers_temp) el registro asociado al order_id
        $stmt = $conn->prepare("SELECT * FROM customers_temp WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $tempData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tempData) {
            $conn->commit();
            http_response_code(200);
            echo json_encode(['message' => 'No hay datos pendientes para este order id']);
            exit;
        }
        
        // Insertar en la tabla definitiva "customers"
        $stmt = $conn->prepare("INSERT INTO customers (firstName, lastName, email, whatsapp, numbers) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $tempData['firstName'],
            $tempData['lastName'],
            $tempData['email'],
            $tempData['whatsapp'],
            $tempData['numbers']
        ]);
        
        // Eliminar el registro de la tabla temporal
        $stmt = $conn->prepare("DELETE FROM customers_temp WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        $conn->commit();
        http_response_code(200);
        echo json_encode(['message' => 'Compra aprobada y datos registrados']);
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error al persistir datos: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error al persistir datos']);
    }
} else {
    // Otros tipos de eventos: responde 200
    http_response_code(200);
    echo json_encode(['message' => 'Evento recibido: ' . $eventType]);
}
?>