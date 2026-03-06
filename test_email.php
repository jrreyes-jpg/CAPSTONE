<?php
require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$config = Config::getInstance();
$mailConfig = $config->getMailConfig();

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mailConfig['username'];
    $mail->Password = $mailConfig['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $mailConfig['port'];

    $mail->setFrom($mailConfig['from_address'], $mailConfig['from_name']);
    $mail->addAddress('jeshowap@gmail.com'); // Gmail mo para test

    $mail->Subject = 'Test Email';
    $mail->Body = 'This is a test email from Edge Automation Portal.';

    $mail->send();
    echo "✅ Email sent successfully!";
} catch (Exception $e) {
    echo "❌ Email failed: " . $mail->ErrorInfo;
}