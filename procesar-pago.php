<?php
require_once 'conexion.php';

// Iniciar la sesión (si no está iniciada)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Recibe la respuesta de Bold a través del token en la URL
if (isset($_GET['token'])) {
    $boldToken = $_GET['token'];

    // Obtener el orderId de la sesión
    $orderId = $_SESSION['orderId'] ?? null;

    if (empty($orderId)) {
        http_response_code(400);
        echo "Error: No se encontró el ID de la orden.";
        exit;
    }

    // Realiza una solicitud a la API de Bold para obtener los detalles de la transacción
    // (Esta parte puede variar según la API de Bold, consulta su documentación)
    $url = 'https://api.boldcommerce.com/v1/order/' . $orderId; // Ajusta la URL según la API de Bold
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . '1y0D48xaDriWO_CNz7oXUopfkKx5VjiExsdDW0gj2eA' // Tu API Key de PRODUCCIÓN
    ]);

    $response = curl_exec($ch);

    // Manejo de errores de la solicitud cURL
    if (curl_errno($ch)) {
        error_log("Error en la solicitud cURL: " . curl_error($ch));
        http_response_code(500);
        echo "<h2>Error al procesar el pago. Por favor, inténtalo de nuevo más tarde.</h2>";
        exit;
    }

    curl_close($ch);

    $transactionDetails = json_decode($response, true);

    // Manejo de errores al decodificar JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error al decodificar la respuesta JSON: " . json_last_error_msg());
        http_response_code(500);
        echo "<h2>Error al procesar el pago. Por favor, inténtalo de nuevo más tarde.</h2>";
        exit;
    }
    // Verifica el estado de la transacción (Ajusta según la respuesta de la API de Bold)
    if (isset($transactionDetails['status']) && $transactionDetails['status'] === 'completed') {
       // Obtener los datos del cliente desde session storage (o de donde los hayas guardado)
        // Considera una forma más segura de almacenar y recuperar estos datos, como una tabla temporal en la base de datos
        $customerData = $_SESSION['customerData'] ?? null;
        $selectedNumbers = $_SESSION['customerData']['numbers'] ?? null; // Asumiendo que los números están aquí

        // Limpiar los datos de la sesión
        unset($_SESSION['customerData']);
        unset($_SESSION['orderId']);

        if (empty($customerData) || empty($selectedNumbers)) {
            http_response_code(500);
            echo "Error: No se encontraron los datos del cliente.";
            exit;
        }

        $firstName = $customerData['firstName'];
        $lastName = $customerData['lastName'];
        $email = $customerData['email'];
        $whatsapp = $customerData['whatsapp'];

        // Validar si los números ya están tomados (importante!)
        // ... (Tu código de validación) ...

        $conn->beginTransaction();

        try {
            // Insertar en la tabla customers
            $stmt = $conn->prepare("INSERT INTO customers (firstName, lastName, email, whatsapp, numbers) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$firstName, $lastName, $email, $whatsapp, implode(',', $selectedNumbers)]);

            $customerId = $conn->lastInsertId();

            // Insertar en la tabla purchases (si es necesario)
            // ... (Tu código para insertar en la tabla purchases) ...

            $conn->commit();
            // Mostrar mensaje de éxito
            echo "<h2>Pago exitoso!</h2>";
            echo "<p>Gracias por tu compra, {$firstName}.</p>";
            echo "<p>Detalles de la transacción:</p>";
            echo "<pre>";
            print_r($transactionDetails);
            echo "</pre>";

            // Enviar correo electrónico de confirmación (opcional)
            // ... (Tu código para enviar el correo) ...

        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Error al guardar en la base de datos: " . $e->getMessage());
            http_response_code(500);
            echo "Error al guardar los datos: " . $e->getMessage();
        }

    } else {
        // Pago fallido
        echo "<h2>Error en el pago</h2>";
        echo "<p>Detalles de la transacción:</p>";
        echo "<pre>";
        print_r($transactionDetails); // Mostrar los detalles para depurar
        echo "</pre>";
    }
} else {
    // No se recibió token de Bold
    http_response_code(400);
    echo "<h2>Error: No se recibió token de Bold</h2>";
}

?>