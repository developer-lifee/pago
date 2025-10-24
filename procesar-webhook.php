<?php
// procesar-webhook.php

// Configuración de logging
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/webhook.log';

// Función para escribir en el log de manera sencilla
function write_log($message) {
    global $log_file;
    $log_entry = sprintf(
        "[%s] [WEBHOOK] [%s] %s\n",
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP',
        $message
    );
    // IMPORTANTE: Si el log no funciona, revisa los permisos de escritura del directorio /logs
    error_log($log_entry, 3, $log_file);
}

write_log("--- INICIO DE NUEVA PETICIÓN WEBHOOK ---");

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
write_log("Cuerpo recibido: " . $body);

// Validar que el JSON se haya decodificado correctamente
$data = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    write_log("ERROR: JSON inválido");
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

// Suponiendo que $config está disponible desde conexion.php o un archivo similar
if (!isset($config['bold_secret_key'])) {
    write_log("ERROR: Llave secreta de Bold no configurada.");
    // Manejar el error de configuración apropiadamente
    http_response_code(500);
    echo json_encode(['error' => 'Error de configuración del servidor.']);
    exit;
}

// Validar la firma recibida (usando la llave secreta)
$signatureHeader = $_SERVER['HTTP_X_BOLD_SIGNATURE'] ?? '';
$secretKey = $config['bold_secret_key']; // Usar llave de config
$encodedBody = base64_encode($body);
$computedSignature = hash_hmac('sha256', $encodedBody, $secretKey);
if (!hash_equals($computedSignature, $signatureHeader)) {
    write_log("ERROR: Firma no válida. Recibida: " . $signatureHeader . " Calculada: " . $computedSignature);
    http_response_code(400);
    echo json_encode(['error' => 'Firma no válida']);
    exit;
}
write_log("Firma validada correctamente.");

// Extraer el evento y el order_id (intenta primero del webhook)
$eventType = $data['type'] ?? '';
$orderId = null;
if (isset($data['data']['metadata']['reference'])) {
    $orderId = $data['data']['metadata']['reference'];
} else if (isset($data['subject'])) {
    $orderId = $data['subject'];
}
write_log("Tipo de evento: " . $eventType . " | Order ID (Webhook): " . ($orderId ?? 'N/A'));


// Si no se encontró order_id o el evento no es SALE_APPROVED, se puede usar el fallback
if (!$orderId || $eventType !== 'SALE_APPROVED') {
    // Usa el fallback: consulta Bold con el order_id (que en este caso es tu referencia externa)
    if (!$orderId) {
        // Si no se obtuvo order_id vía webhook, intenta obtenerlo de un parámetro GET (si se ha enviado)
        $orderId = $_GET['order_id'] ?? null;
    }
    
    if ($orderId) {
        write_log("Activando Fallback para Order ID: " . $orderId);
        // Tu llave de identidad (para autorización en la consulta fallback)
        $identityKey = $config['bold_identity_key'] ?? null; // Usar llave de config
        if (!$identityKey) {
             write_log("ERROR: Llave de identidad de Bold no configurada para Fallback.");
             http_response_code(500);
             echo json_encode(['error' => 'Error de configuración para Fallback.']);
             exit;
        }

        $fallbackResponse = getFallbackNotification($orderId, $identityKey);
        
        if ($fallbackResponse && isset($fallbackResponse['notifications'])) {
            // Tomamos la primera notificación
            $notification = $fallbackResponse['notifications'][0] ?? null;
            if ($notification && isset($notification['type']) && $notification['type'] === 'SALE_APPROVED') {
                $eventType = 'SALE_APPROVED';
                write_log("Fallback exitoso. Evento actualizado a: SALE_APPROVED");
            } else {
                // Si la notificación no indica aprobación, respondemos y salimos
                write_log("Fallback obtenido, pero evento no es SALE_APPROVED.");
                http_response_code(200);
                echo json_encode(['message' => 'Evento no aprobado según fallback']);
                exit;
            }
        } else {
            // No se pudo obtener fallback
            write_log("No se pudo obtener fallback o la respuesta fue inválida.");
            http_response_code(200);
            echo json_encode(['message' => 'No se pudo obtener fallback para order_id ' . $orderId]);
            exit;
        }
    } else {
        write_log("ERROR: No se encontró order id por ningún método.");
        http_response_code(400);
        echo json_encode(['error' => 'No se encontró order id']);
        exit;
    }
}

if ($eventType === 'SALE_APPROVED') {
    $conn->beginTransaction();
    try {
        write_log("Iniciando transacción para SALE_APPROVED con Order ID: " . $orderId);
        
        // 1. Buscar en la tabla temporal (customers_temp) el registro asociado al order_id
        $stmt = $conn->prepare("SELECT * FROM customers_temp WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $tempData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tempData) {
            $conn->commit();
            write_log("No hay datos pendientes para este order id en customers_temp. Proceso finalizado.");
            http_response_code(200);
            echo json_encode(['message' => 'No hay datos pendientes para este order id']);
            exit;
        }
        
        // 2. Insertar en la tabla definitiva "customers"
        // *** CAMBIO CLAVE AQUÍ: Se agrega 'bold_order_id' a la consulta INSERT ***
        $stmt = $conn->prepare("INSERT INTO customers (firstName, lastName, email, whatsapp, numbers, order_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tempData['firstName'],
            $tempData['lastName'],
            $tempData['email'],
            $tempData['whatsapp'],
            $tempData['numbers'],
            $orderId // <--- Nuevo campo para el Order ID de Bold
        ]);
        
        // 3. Eliminar el registro de la tabla temporal
        $stmt = $conn->prepare("DELETE FROM customers_temp WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        $conn->commit();
        write_log("EXITO: Compra aprobada y datos registrados para Order ID: " . $orderId);
        http_response_code(200);
        echo json_encode(['message' => 'Compra aprobada y datos registrados']);
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error al persistir datos (Order ID: " . $orderId . "): " . $e->getMessage());
        write_log("ERROR: Error al persistir datos: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error al persistir datos']);
    }
} else {
    // Otros tipos de eventos: responde 200
    write_log("Evento recibido, pero no procesado (no SALE_APPROVED): " . $eventType);
    http_response_code(200);
    echo json_encode(['message' => 'Evento recibido: ' . $eventType]);
}
?>