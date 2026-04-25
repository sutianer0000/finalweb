<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function appBaseUrl(): string
{
    $appUrl = trim((string) (getenv('APP_URL') ?: ''));
    if ($appUrl !== '') {
        return rtrim($appUrl, '/');
    }

    $baseUrl = trim((string) (getenv('BASE_URL') ?: ''));
    if (preg_match('#^https?://#i', $baseUrl)) {
        return rtrim($baseUrl, '/');
    }

    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
    if ($host !== '') {
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
            ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        return rtrim($proto . '://' . $host . '/' . ltrim($baseUrl, '/'), '/');
    }

    return 'https://subnauewallet.fly.dev';
}

function appUrl(string $path = ''): string
{
    return appBaseUrl() . '/' . ltrim($path, '/');
}

function sendMailNow(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): array
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($htmlBody);

        $mail->send();
        return ['ok' => true, 'error' => null];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        error_log("[mailer] send failed: $err");
        return ['ok' => false, 'error' => $err];
    }
}

function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): array
{
    $result = sendMailNow($toEmail, $toName, $subject, $htmlBody, $altBody);
    return $result;
}

function sendRegistrationEmail(string $toEmail, string $fullName, string $phone, string $password): array
{
    $safeName  = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($toEmail,  ENT_QUOTES, 'UTF-8');
    $safePhone = htmlspecialchars($phone,    ENT_QUOTES, 'UTF-8');
    $safePass  = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    $loginUrl  = htmlspecialchars(appUrl('login.php'), ENT_QUOTES, 'UTF-8');

    $subject = 'E-Wallet Registration - Your Login Credentials';
    $html = "
        <div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:20px;border:1px solid #eee;border-radius:8px'>
            <h2 style='color:#0d6efd;margin-top:0'>Welcome to E-Wallet, {$safeName}!</h2>
            <p>Your account has been created successfully. Use either your email <strong>or</strong> phone number as your username.</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                <tr><td style='padding:8px;border:1px solid #ddd;background:#f8f9fa'><strong>Email</strong></td><td style='padding:8px;border:1px solid #ddd'>{$safeEmail}</td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd;background:#f8f9fa'><strong>Phone</strong></td><td style='padding:8px;border:1px solid #ddd'>{$safePhone}</td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd;background:#f8f9fa'><strong>Password</strong></td><td style='padding:8px;border:1px solid #ddd;font-family:monospace;font-size:16px'>{$safePass}</td></tr>
            </table>
            <p style='color:#b45309'><strong>Important:</strong> You will be asked to change this password on your first login. Your account is pending administrator verification.</p>
            <p style='margin:24px 0 10px'>
                <a href='{$loginUrl}' style='display:inline-block;padding:12px 20px;border-radius:999px;background:#0b1e3f;color:#ffffff;text-decoration:none;font-weight:600'>Log in to E-Wallet</a>
            </p>
            <p style='color:#6c757d;font-size:12px;margin-top:24px'>If you did not register for this account, please ignore this email.</p>
        </div>
    ";
    $alt = "Welcome to E-Wallet, {$fullName}!\n\n"
         . "Email:    {$toEmail}\n"
         . "Phone:    {$phone}\n"
         . "Password: {$password}\n\n"
         . "Login: " . appUrl('login.php') . "\n\n"
         . "You will be asked to change this password on first login.";

    return sendMail($toEmail, $fullName, $subject, $html, $alt);
}
function sendPasswordResetOtp(string $toEmail, string $fullName, string $otp): array
{
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeOtp  = htmlspecialchars($otp,      ENT_QUOTES, 'UTF-8');
    $loginUrl = htmlspecialchars(appUrl('login.php'), ENT_QUOTES, 'UTF-8');

    $subject = 'E-Wallet Password Reset - Your OTP Code';
    $html = "
        <div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:20px;border:1px solid #eee;border-radius:8px'>
            <h2 style='color:#0d6efd;margin-top:0'>Password Reset Request</h2>
            <p>Hi {$safeName}, use this code to reset your E-Wallet password:</p>
            <div style='font-size:32px;font-weight:bold;letter-spacing:8px;text-align:center;background:#f8f9fa;padding:16px;border-radius:6px;margin:16px 0'>{$safeOtp}</div>
            <p>This code expires in 10 minutes. If you did not request a password reset, you can safely ignore this email.</p>
            <p style='margin:24px 0 10px'>
                <a href='{$loginUrl}' style='display:inline-block;padding:12px 20px;border-radius:999px;background:#0b1e3f;color:#ffffff;text-decoration:none;font-weight:600'>Open E-Wallet</a>
            </p>
            <p style='color:#6c757d;font-size:12px;margin-top:24px'>For your security, never share this code with anyone.</p>
        </div>
    ";
    $alt = "Hi {$fullName},\n\n"
         . "Your E-Wallet password reset code is: {$otp}\n\n"
         . "This code expires in 10 minutes.\n"
         . "Login: " . appUrl('login.php') . "\n"
         . "If you did not request this, please ignore this email.";

    return sendMail($toEmail, $fullName, $subject, $html, $alt);
}
