<?php
declare(strict_types=1);

namespace LoganX;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use RuntimeException;

class Mailer
{
    /**
     * Optional interceptor for automated tests (allows testing without a live SMTP server)
     * @var callable|null
     */
    public static $testTransportInterceptor = null;

    /**
     * Create and configure a PHPMailer instance using SMTP settings
     */
    private static function createMailer(): PHPMailer
    {
        Config::load();

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';

        $host = (string)Config::get('SMTP_HOST', '127.0.0.1');
        $port = (int)Config::get('SMTP_PORT', 587);
        $user = (string)Config::get('SMTP_USERNAME', '');
        $pass = (string)Config::get('SMTP_PASSWORD', '');
        $enc  = strtolower((string)Config::get('SMTP_ENCRYPTION', 'tls'));
        $fromEmail = (string)Config::get('SMTP_FROM_EMAIL', 'downloads@loganx.com');
        $fromName  = (string)Config::get('SMTP_FROM_NAME', 'LOGANX Team');

        $mail->Host = $host;
        $mail->Port = $port;
        $mail->Timeout = 10;

        if ($user !== '' || $pass !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
        } else {
            $mail->SMTPAuth = false;
        }

        if ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom($fromEmail, $fromName);

        return $mail;
    }

    /**
     * Send Verification Email (Module 2)
     */
    public static function sendVerificationEmail(string $recipientEmail, string $resourceTitle, string $rawToken): array
    {
        $appUrl = Config::appUrl();
        $verifyUrl = "{$appUrl}/verify.php?token=" . urlencode($rawToken);
        $ttlMinutes = (int)Config::get('VERIFICATION_TOKEN_TTL_MINUTES', 30);

        $subject = 'Verify Your Email — LOGANX Download Request';

        $htmlBody = self::renderVerificationTemplate([
            'recipientEmail' => $recipientEmail,
            'resourceTitle'  => $resourceTitle,
            'verifyUrl'      => $verifyUrl,
            'ttlMinutes'     => $ttlMinutes
        ]);

        $plainBody = <<<TEXT
Hello,

You requested a digital download from LOGANX: {$resourceTitle}.

Please verify your email address to receive your download link by visiting:
{$verifyUrl}

This verification link expires in {$ttlMinutes} minutes.

If you did not request this download, you can safely ignore this email.

Regards,
LOGANX Team
TEXT;

        return self::deliverEmail($recipientEmail, $subject, $htmlBody, $plainBody, 'verification_email');
    }

    /**
     * Send Download Ready Email (Module 4)
     */
    public static function sendDownloadEmail(string $recipientEmail, string $resourceTitle, string $rawDownloadToken, int $expiryHours, int $maxDownloads): array
    {
        $appUrl = Config::appUrl();
        $downloadUrl = "{$appUrl}/download.php?token=" . urlencode($rawDownloadToken);

        $subject = 'Your Download Is Ready — LOGANX';

        $htmlBody = self::renderDownloadTemplate([
            'recipientEmail' => $recipientEmail,
            'resourceTitle'  => $resourceTitle,
            'downloadUrl'    => $downloadUrl,
            'expiryHours'    => $expiryHours,
            'maxDownloads'   => $maxDownloads,
            'appUrl'         => $appUrl
        ]);

        $plainBody = <<<TEXT
Hello,

Thank you for verifying your email address.

Your requested digital product is ready to download.

Product: {$resourceTitle}

Download Your File:
{$downloadUrl}

For your security, this download link is personal and time-limited ({$expiryHours} hours, up to {$maxDownloads} downloads).

If your link expires, you can request a new one through the LOGANX website.

Regards,
LOGANX Team
TEXT;

        return self::deliverEmail($recipientEmail, $subject, $htmlBody, $plainBody, 'download_ready');
    }

    /**
     * Generic delivery method handling PHPMailer sending, error catching, and sanitization
     */
    private static function deliverEmail(string $recipientEmail, string $subject, string $htmlBody, string $plainBody, string $type): array
    {
        // Check test interceptor first
        if (is_callable(self::$testTransportInterceptor)) {
            $interceptorResult = (self::$testTransportInterceptor)($recipientEmail, $subject, $htmlBody, $plainBody, $type);
            if ($interceptorResult !== null) {
                return $interceptorResult;
            }
        }

        try {
            $mail = self::createMailer();
            $mail->addAddress($recipientEmail);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody;

            $mail->send();

            Security::log('info', "Email delivered successfully [type={$type}] to recipient.", [
                'recipient' => $recipientEmail,
                'subject'   => $subject
            ]);

            return ['success' => true, 'message' => 'Email sent successfully.'];
        } catch (\Throwable $e) {
            // Strip password or auth details from error string
            $errorMsg = preg_replace('/(password|pass|auth)[^,\s]*/i', '[REDACTED]', $e->getMessage());

            Security::log('error', "Email delivery failed [type={$type}]: " . $errorMsg, [
                'recipient' => $recipientEmail,
                'type'      => $type
            ]);

            return [
                'success'    => false,
                'error_code' => 'SMTP_FAILURE',
                'message'    => "Failed to deliver email: " . $errorMsg
            ];
        }
    }

    /**
     * HTML template for email verification
     */
    private static function renderVerificationTemplate(array $data): string
    {
        $escapedTitle = Security::e($data['resourceTitle']);
        $verifyUrl    = Security::e($data['verifyUrl']);
        $ttlMinutes   = (int)$data['ttlMinutes'];

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Your Email</title>
</head>
<body style="margin:0;padding:0;background-color:#080a0f;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#f6f7fb;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#080a0f;padding:40px 15px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" max-width="580" cellspacing="0" cellpadding="0" border="0" style="max-width:580px;background:#10131b;border:1px solid rgba(255,255,255,0.12);border-radius:18px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
          <!-- Header Banner -->
          <tr>
            <td style="padding:32px 36px;border-bottom:1px solid rgba(255,255,255,0.08);background:radial-gradient(circle at 80% 0%,rgba(94,231,255,0.15),transparent 40%),#10131b;">
              <span style="font-size:24px;font-weight:900;letter-spacing:-1px;color:#f6f7fb;">LOGAN<span style="color:#8cff5b;">X</span></span>
              <span style="display:inline-block;margin-left:12px;font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#8cff5b;background:rgba(140,255,91,0.1);padding:4px 9px;border-radius:99px;border:1px solid rgba(140,255,91,0.25);">Digital Downloads</span>
            </td>
          </tr>
          <!-- Body -->
          <tr>
            <td style="padding:36px;line-height:1.65;font-size:15px;color:#cbd0db;">
              <p style="margin:0 0 16px;font-size:17px;font-weight:600;color:#ffffff;">Hello,</p>
              <p style="margin:0 0 16px;">You requested a digital download from <strong>LOGANX</strong>:</p>
              
              <div style="background:#151923;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:16px 20px;margin:22px 0;">
                <span style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#8cff5b;font-weight:800;">Requested Item</span>
                <div style="font-size:18px;font-weight:700;color:#ffffff;margin-top:4px;">{$escapedTitle}</div>
              </div>

              <p style="margin:0 0 24px;">Please verify your email address to receive your secure download link.</p>

              <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0;">
                <tr>
                  <td align="center" style="border-radius:12px;background:#8cff5b;">
                    <a href="{$verifyUrl}" target="_blank" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:800;color:#071006;text-decoration:none;border-radius:12px;">Verify Email Address &rarr;</a>
                  </td>
                </tr>
              </table>

              <p style="margin:24px 0 8px;font-size:13px;color:#9da5b5;">
                &bull; This verification link expires in <strong>{$ttlMinutes} minutes</strong>.<br>
                &bull; If you did not request this download, you can safely ignore this email.
              </p>
              
              <div style="margin-top:30px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.08);color:#9da5b5;font-size:13px;">
                Regards,<br>
                <strong style="color:#ffffff;">LOGANX Team</strong>
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * HTML template for ready download delivery
     */
    private static function renderDownloadTemplate(array $data): string
    {
        $escapedTitle = Security::e($data['resourceTitle']);
        $downloadUrl  = Security::e($data['downloadUrl']);
        $expiryHours  = (int)$data['expiryHours'];
        $maxDownloads = (int)$data['maxDownloads'];
        $appUrl       = Security::e($data['appUrl']);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Download Is Ready</title>
</head>
<body style="margin:0;padding:0;background-color:#080a0f;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#f6f7fb;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#080a0f;padding:40px 15px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" max-width="580" cellspacing="0" cellpadding="0" border="0" style="max-width:580px;background:#10131b;border:1px solid rgba(255,255,255,0.12);border-radius:18px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
          <!-- Header Banner -->
          <tr>
            <td style="padding:32px 36px;border-bottom:1px solid rgba(255,255,255,0.08);background:radial-gradient(circle at 80% 0%,rgba(140,255,91,0.18),transparent 45%),#10131b;">
              <span style="font-size:24px;font-weight:900;letter-spacing:-1px;color:#f6f7fb;">LOGAN<span style="color:#8cff5b;">X</span></span>
              <span style="display:inline-block;margin-left:12px;font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#8cff5b;background:rgba(140,255,91,0.1);padding:4px 9px;border-radius:99px;border:1px solid rgba(140,255,91,0.25);">Download Ready</span>
            </td>
          </tr>
          <!-- Body -->
          <tr>
            <td style="padding:36px;line-height:1.65;font-size:15px;color:#cbd0db;">
              <p style="margin:0 0 16px;font-size:17px;font-weight:600;color:#ffffff;">Hello,</p>
              <p style="margin:0 0 16px;">Thank you for verifying your email address.</p>
              <p style="margin:0 0 20px;">Your requested digital product is ready to download:</p>
              
              <div style="background:#151923;border:1px solid rgba(140,255,91,0.25);border-radius:14px;padding:20px 24px;margin:22px 0;">
                <span style="font-size:11px;text-transform:uppercase;letter-spacing:1.2px;color:#8cff5b;font-weight:800;">Product</span>
                <div style="font-size:20px;font-weight:800;color:#ffffff;margin-top:6px;">{$escapedTitle}</div>
                <div style="font-size:12px;color:#9da5b5;margin-top:8px;">Valid for {$expiryHours} hours &bull; Up to {$maxDownloads} downloads</div>
              </div>

              <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0;">
                <tr>
                  <td align="center" style="border-radius:12px;background:#8cff5b;">
                    <a href="{$downloadUrl}" target="_blank" style="display:inline-block;padding:15px 32px;font-size:16px;font-weight:850;color:#071006;text-decoration:none;border-radius:12px;">Download Your File &darr;</a>
                  </td>
                </tr>
              </table>

              <p style="margin:20px 0 8px;font-size:13px;color:#9da5b5;">
                For your security, this download link is personal and time-limited.<br>
                If your link expires, you can request a new one anytime through the <a href="{$appUrl}" style="color:#8cff5b;text-decoration:none;">LOGANX website</a>.
              </p>
              
              <div style="margin-top:30px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.08);color:#9da5b5;font-size:13px;">
                Regards,<br>
                <strong style="color:#ffffff;">LOGANX Team</strong>
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }
}
