<?php
$host = "localhost";
$db_name = "estavi0_sheerit";
$username = "estavi0_sheerit";
$password = "26o6ssCOA^";

try {
    $conn = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password); // Variable $conn
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8mb4");

    error_log("Conexión exitosa a la base de datos"); // Registro de conexión exitosa

} catch (PDOException $exception) {
    error_log("Error de conexión: " . $exception->getMessage());
    exit;
}
?>