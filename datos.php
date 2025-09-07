<?php
require_once 'conexion.php'; // Incluye el archivo de conexión

header("Access-Control-Allow-Origin: https://sheerit.com.co"); // URL de tu frontend en producción
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Permite POST y OPTIONS
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

// Manejo de solicitudes OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_log("Iniciando script datos.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = file_get_contents('php://input');
    error_log("Datos en bruto recibidos: " . $inputData);

    $datos = json_decode($inputData, true);
    error_log("Datos decodificados: " . print_r($datos, true));

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error al decodificar JSON: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['error' => 'Error al decodificar JSON: ' . json_last_error_msg()]);
        exit;
    }

    if (empty($datos)) {
        error_log("No se recibieron datos.");
        http_response_code(400);
        echo json_encode(['error' => 'No se recibieron datos']);
        exit;
    }

    // Asignación de variables con validación
    $firstName = $datos['firstName'] ?? null;
    $lastName = $datos['lastName'] ?? null;
    $email = $datos['email'] ?? null;
    $whatsapp = $datos['whatsapp'] ?? null;
    $numeros = $datos['numbers'] ?? null;

    error_log("firstName: " . $firstName);
    error_log("lastName: " . $lastName);
    error_log("email: " . $email);
    error_log("whatsapp: " . $whatsapp);
    error_log("numeros: " . print_r($numeros, true));

    if (empty($firstName) || empty($lastName) || empty($email) || empty($whatsapp) || empty($numeros)) {
        error_log("Faltan campos obligatorios.");
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios']);
        exit;
    }

    // Validar si los números ya están tomados
    $numerosTomados = [];
    foreach ($numeros as $numero) {
        try {
            // Usar $conn (definida en conexion.php)
            $stmt = $conn->prepare("SELECT id FROM customers WHERE numbers LIKE ?");
            $stmt->execute(["%$numero%"]);
            if ($stmt->fetch()) {
                $numerosTomados[] = $numero;
            }
        } catch (PDOException $e) {
            error_log("Error al verificar número tomado: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al verificar número tomado: ' . $e->getMessage()]);
            exit;
        }
    }

    if (!empty($numerosTomados)) {
        error_log("Números ya tomados: " . print_r($numerosTomados, true));
        http_response_code(400);
        echo json_encode(['error' => 'Los siguientes números ya están tomados: ' . implode(', ', $numerosTomados)]);
        exit;
    }

    $conn->beginTransaction();

    try {
        // Insertar en la tabla customers
        // Usar $conn (definida en conexion.php)
        $stmt = $conn->prepare("INSERT INTO customers (firstName, lastName, email, whatsapp, numbers) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$firstName, $lastName, $email, $whatsapp, implode(',', $numeros)]);

        $customerId = $conn->lastInsertId();
        error_log("Nuevo customer ID: " . $customerId);

        $conn->commit();
        error_log("Datos guardados exitosamente.");
        echo json_encode(['message' => 'Datos guardados']);

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error al guardar en la base de datos: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar los datos: ' . $e->getMessage()]);
    }
} else {
    error_log("Método no permitido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?>