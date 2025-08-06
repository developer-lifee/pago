<?php
// checkout_bold.php

// Configuración de integración (datos quemados)
$apiKey         = 'YCJ9yFnOlrWiS9Mq4KZLfize2ApawYb8rqrj0pge6pM';  // Llave de identidad (publica)
$integrityKey   = 'VIJ4-2mCZXIPOvT3NJEAsg';                      // Llave secreta para generar la firma
$orderId        = 'ORDEN-' . time();                            // Identificador único de la venta
$amount         = '20000';                                     // Monto en COP sin decimales (por ejemplo, 300000 equivale a $300.000 COP)
$currency       = 'COP';                                        // Divisa (COP o USD)
$description    = 'Compra de plataforma';                             // Descripción de la venta (mínimo 2 caracteres y máximo 100)
$tax            = 'vat-19';                                     // Impuesto a aplicar (en este ejemplo IVA del 19%)
$redirectionUrl = 'https://sheerit.com.co/procesar-pago.php'; // URL a la que se redirige tras finalizar el pago

// Genera la firma de integridad (concatenación en el orden: orderId, amount, currency, integrityKey)
$cadenaConcatenada   = $orderId . $amount . $currency . $integrityKey;
$integritySignature  = hash("sha256", $cadenaConcatenada);
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
    <title>Pago con Bold</title>
    <!-- Se carga la librería del botón de pagos Bold -->
    <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>
  </head>
  <body>
    <h1>Pagar con Bold</h1>
    <!--
      Este script genera el botón de pagos de Bold.
      Al incluir los atributos, Bold se encarga de redirigir al usuario a la pasarela de pagos.
      Nota: Si deseas usar Embedded Checkout (modal dentro de tu página) agrega el atributo data-render-mode="embedded"
    -->
    <script
      data-bold-button
      data-api-key="<?php echo $apiKey; ?>"
      data-order-id="<?php echo $orderId; ?>"
      data-amount="<?php echo $amount; ?>"
      data-currency="<?php echo $currency; ?>"
      data-description="<?php echo $description; ?>"
      data-tax="<?php echo $tax; ?>"
      data-integrity-signature="<?php echo $integritySignature; ?>"
      data-redirection-url="<?php echo $redirectionUrl; ?>"
    ></script>
  </body>
</html>