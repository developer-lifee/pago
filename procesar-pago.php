<?php
// pago/procesar-pago.php

require_once 'conexion.php';

// Obtener el ID de la orden, ya sea por redirección del usuario o por webhook de Bold
$orderId = $_GET['bold-order-id'] ?? null;
if (!$orderId) {
    $webhookPayload = json_decode(file_get_contents('php://input'), true);
    $orderId = $webhookPayload['transaction']['order_id'] ?? null;
}

if (empty($orderId)) {
    http_response_code(400);
    echo "Error: No se recibió un ID de orden.";
    exit;
}

$conn->beginTransaction();

try {
    // 1. Buscar la transacción en tu tabla temporal
    $stmt = $conn->prepare("SELECT * FROM customers_temp WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $tempCustomer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tempCustomer) {
        http_response_code(404);
        echo "Transacción no encontrada en la tabla temporal.";
        $conn->rollBack();
        exit;
    }

    // 2. Verificar el estado real de la transacción con la API de Bold
    $apiKey = '1y0D48xaDriWO_CNz7oXUopfkKx5VjiExsdDW0gj2eA'; // Tu API Key de PRODUCCIÓN
    $url = 'https://api.bold.co/v1/transactions?order_id=' . $orderId;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $apiKey]);
    $response = curl_exec($ch);
    curl_close($ch);
    $transactionDetails = json_decode($response, true);

    $boldTransaction = $transactionDetails['data'][0] ?? null;

    // 3. Si el pago fue aprobado ('APPROVED')
    if ($boldTransaction && $boldTransaction['status'] === 'APPROVED') {
        
        // Mover los datos a tu tabla final 'customers'
        $stmt_customer = $conn->prepare("INSERT INTO customers (firstName, lastName, email, whatsapp, numbers) VALUES (?, ?, ?, ?, ?)");
        $stmt_customer->execute([
            $tempCustomer['firstName'],
            $tempCustomer['lastName'],
            $tempCustomer['email'],
            $tempCustomer['whatsapp'],
            $tempCustomer['numbers']
        ]);
        
        // (Opcional, pero recomendado) Eliminar el registro de la tabla temporal
        $stmt_delete = $conn->prepare("DELETE FROM customers_temp WHERE order_id = ?");
        $stmt_delete->execute([$orderId]);

        $conn->commit();

        // Mostrar mensaje de éxito al usuario si fue redirigido
        if (isset($_GET['bold-order-id'])) {
             echo "<h2>¡Pago exitoso!</h2><p>Gracias por tu compra.</p>";
        } else {
            // Si fue un webhook, solo respondemos 200 OK
            http_response_code(200);
            echo "Webhook procesado.";
        }

    } else {
        // Si el pago no fue aprobado, simplemente deshacemos la transacción
        $conn->rollBack();
        if (isset($_GET['bold-order-id'])) {
            echo "<h2>Pago fallido o pendiente.</h2>";
        } else {
            http_response_code(200); // Respondemos OK al webhook aunque el pago fallara
            echo "Webhook: Pago no aprobado.";
        }
    }

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error al procesar pago: " . $e->getMessage());
    http_response_code(500);
    echo "Error interno del servidor.";
}

?>