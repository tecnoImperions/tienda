<?php
/**
 * Configuración de Email con PHPMailer
 */

require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuración del servidor de correo
define('SMTP_HOST', 'smtp.gmail.com');  // Cambiar según tu proveedor
define('SMTP_PORT', 587);
define('SMTP_USER', 'rivaldiramirez@gmail.com');  // TU EMAIL
define('SMTP_PASS', 'dxlg qnyd mirt mesc');   // Contraseña de aplicación de Gmail
define('FROM_EMAIL', 'rivaldiramirez@gmail.com');
define('FROM_NAME', 'Bike Store');

/**
 * Función para enviar email de verificación
 */
function enviarEmailVerificacion($email, $nombre, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Remitente y destinatario
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email, $nombre);
        
        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = 'Verifica tu cuenta - Bike Store';
        
        $verification_link = "https://probwebii.gamer.gd/tecweb1/views/auth/verificar_cuenta.php?token=" . $token;
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 15px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🚴 Bike Store</h1>
                    <p>Bienvenido a nuestra tienda</p>
                </div>
                <div class="content">
                    <h2>Hola ' . htmlspecialchars($nombre) . ',</h2>
                    <p>Gracias por registrarte en Bike Store. Para completar tu registro y poder iniciar sesión, necesitamos que verifiques tu dirección de correo electrónico.</p>
                    <p>Por favor, haz clic en el siguiente botón para verificar tu cuenta:</p>
                    <center>
                        <a href="' . $verification_link . '" class="button">✅ Verificar mi cuenta</a>
                    </center>
                    <p>O copia y pega este enlace en tu navegador:</p>
                    <p style="background: #eee; padding: 10px; border-radius: 5px; word-break: break-all;">' . $verification_link . '</p>
                    <p><strong>Este enlace expirará en 24 horas.</strong></p>
                    <p>Si no te registraste en Bike Store, puedes ignorar este mensaje.</p>
                </div>
                <div class="footer">
                    <p>© 2025 Bike Store - Sistema de Gestión</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar email de verificación: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Función para enviar email de recuperación de contraseña
 */
function enviarEmailRecuperacion($email, $nombre, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Remitente y destinatario
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email, $nombre);
        
        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = 'Recuperación de contraseña - Bike Store';
        
        $recovery_link = "https://probwebii.gamer.gd/tecweb1/views/auth/restablecer_password.php?token=" . $token;
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 15px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔐 Recuperación de Contraseña</h1>
                    <p>Bike Store</p>
                </div>
                <div class="content">
                    <h2>Hola ' . htmlspecialchars($nombre) . ',</h2>
                    <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en Bike Store.</p>
                    <p>Haz clic en el siguiente botón para crear una nueva contraseña:</p>
                    <center>
                        <a href="' . $recovery_link . '" class="button">🔑 Restablecer Contraseña</a>
                    </center>
                    <p>O copia y pega este enlace en tu navegador:</p>
                    <p style="background: #eee; padding: 10px; border-radius: 5px; word-break: break-all;">' . $recovery_link . '</p>
                    <div class="warning">
                        <strong>⚠️ Importante:</strong>
                        <ul>
                            <li>Este enlace expirará en 1 hora.</li>
                            <li>Solo puedes usar este enlace una vez.</li>
                        </ul>
                    </div>
                    <p><strong>Si no solicitaste restablecer tu contraseña, puedes ignorar este mensaje.</strong> Tu cuenta está segura.</p>
                </div>
                <div class="footer">
                    <p>© 2025 Bike Store - Sistema de Gestión</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar email de recuperación: {$mail->ErrorInfo}");
        return false;
    }
}
?>