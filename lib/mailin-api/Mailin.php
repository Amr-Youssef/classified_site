<?php
// Minimal Mailin-compatible wrapper to work with this project
// Usage example:
//   include 'lib/mailin-api/Mailin.php';
//   $mailin = new Mailin('username','password');
//   $mailin->addTo('to@example.com','Name')->setFrom('from@example.com','From')->setSubject('Hello')->setText('Hi')->send();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';

class Mailin {
    private $username;
    private $password;
    private $to = [];
    private $from = [];
    private $reply = [];
    private $subject = '';
    private $text = '';
    private $html = '';

    public function __construct($username, $password) {
        $this->username = (string)$username;
        $this->password = (string)$password;
    }
    public function addTo($email, $name = '') { $this->to[] = [$email, $name]; return $this; }
    public function setFrom($email, $name = '') { $this->from = [$email, $name]; return $this; }
    public function setReplyTo($email, $name = '') { $this->reply = [$email, $name]; return $this; }
    public function setSubject($subject) { $this->subject = (string)$subject; return $this; }
    public function setText($text) { $this->text = (string)$text; return $this; }
    public function setHtml($html) { $this->html = (string)$html; return $this; }

    public function send() {
        // Prefer PHPMailer when available for full control over From/Reply-To
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                if (EMAIL_PROVIDER === 'smtp') {
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->Port = SMTP_PORT ?: 587;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USERNAME ?: $this->username;
                    $mail->Password = SMTP_PASSWORD ?: $this->password;
                    $enc = strtolower(SMTP_ENCRYPTION ?: 'tls');
                    $mail->SMTPSecure = ($enc === 'ssl') ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }
                $fromEmail = $this->from[0] ?? EMAIL_FROM;
                $fromName  = $this->from[1] ?? EMAIL_FROM_NAME;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom($fromEmail, $fromName);
                foreach ($this->to as [$e,$n]) { $mail->addAddress($e, $n); }
                if (!empty($this->reply)) { $mail->addReplyTo($this->reply[0], $this->reply[1] ?? ''); }
                $mail->isHTML(true);
                $mail->Subject = $this->subject;
                $mail->Body    = ($this->html !== '') ? $this->html : nl2br($this->text);
                $mail->AltBody = ($this->text !== '') ? $this->text : strip_tags($this->html);
                $mail->send();
                return ['result' => true, 'message' => 'Email sent'];
            } catch (\Throwable $e) {
                return ['result' => false, 'message' => $e->getMessage()];
            }
        }
        // Fallback to project helper for first recipient
        $toEmail = $this->to[0][0] ?? EMAIL_FROM;
        $ok = send_email($toEmail, $this->subject, ($this->html !== '' ? $this->html : nl2br($this->text)), $this->text);
        return ['result' => (bool)$ok, 'message' => $ok ? 'Email sent' : 'Failed to send'];
    }
}
