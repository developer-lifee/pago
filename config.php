<?php
// config.php

/**
 * Define el entorno actual.
 * Cambia 'desarrollo' por 'produccion' cuando vayas a producción.
 */
define('ENVIRONMENT', 'produccion');

// Almacena todas las configuraciones en un array
$config = [];

if (ENVIRONMENT === 'desarrollo') {
    // --- Configuración para Desarrollo (Pruebas) ---
    
    // Base de Datos de Prueba (si tienes una)
    $config['db_host'] = 'localhost';
    $config['db_name'] = 'estavi0_sheerit';
    $config['db_user'] = 'estavi0_sheerit';
    $config['db_pass'] = '26o6ssCOA^';

    // Llaves de Pruebas de Bold
    $config['bold_identity_key'] = 'YCJ9yFnOlrWiS9Mq4KZLfize2ApawYb8rqrj0pge6pM'; // Llave de identidad de prueba
    $config['bold_secret_key']   = 'VIJ4-2mCZXIPOvT3NJEAsg';                     // Llave secreta de prueba
    
} else {
    // --- Configuración para Producción ---

    // Base de Datos de Producción
    $config['db_host'] = 'localhost';
    $config['db_name'] = 'estavi0_sheerit';
    $config['db_user'] = 'estavi0_sheerit';
    $config['db_pass'] = '26o6ssCOA^';

    // Llaves de Producción de Bold
    $config['bold_identity_key'] = '1y0D48xaDriWO_CNz7oXUopfkKx5VjiExsdDW0gj2eA'; // Llave de identidad de producción
    $config['bold_secret_key']   = 'fn6G5OztUmMcvQX6YXU2Tg';                     // Llave secreta de producción
}
?>