<?php
// config.example.php

/**
 * Define el entorno actual.
 * Cambia 'desarrollo' por 'produccion' cuando vayas a producción.
 * 
 * NOTA: Este es un archivo de ejemplo. Cópialo como 'config.php' y decodifica
 * las credenciales del README.md usando base64_decode()
 */
define('ENVIRONMENT', 'desarrollo');

// Almacena todas las configuraciones en un array
$config = [];

if (ENVIRONMENT === 'desarrollo') {
    // --- Configuración para Desarrollo (Pruebas) ---
    // Decodifica las credenciales del README.md usando:
    // echo base64_decode('tu_credencial_codificada');
    
    // Base de Datos de Prueba
    $config['db_host'] = ''; // localhost
    $config['db_name'] = ''; // Tu BD de desarrollo
    $config['db_user'] = ''; // Tu usuario de desarrollo
    $config['db_pass'] = ''; // Tu contraseña de desarrollo

    // Llaves de Pruebas de Bold (decodifícalas del README.md)
    $config['bold_identity_key'] = ''; // Llave de identidad de prueba
    $config['bold_secret_key']   = ''; // Llave secreta de prueba
    
} else {
    // --- Configuración para Producción ---
    // ADVERTENCIA: Nunca subas las credenciales de producción al repositorio
    
    // Base de Datos de Producción
    $config['db_host'] = '';
    $config['db_name'] = '';
    $config['db_user'] = '';
    $config['db_pass'] = '';

    // Llaves de Producción de Bold
    $config['bold_identity_key'] = ''; // Llave de identidad de producción
    $config['bold_secret_key']   = ''; // Llave secreta de producción
}
?>