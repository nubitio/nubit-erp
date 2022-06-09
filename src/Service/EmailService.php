<?php

namespace App\Service;

use App\Domain\Common\EmailDto;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class EmailService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->SMTPAuth = true;
        $this->mailer->SMTPSecure = 'tls';
        $this->mailer->Host = $_ENV['SMTP_HOST'];
        $this->mailer->Port = $_ENV['SMTP_PORT'];
        $this->mailer->Username = $_ENV['SMTP_USER'];
        $this->mailer->Password = $_ENV['SMTP_PASSWORD'];
        if ($_ENV['APP_ENV']) {
            $this->mailer->SMTPDebug = 2;
        }
    }

    public function sendEmail(EmailDto $email): array
    {
        try {
            $this->mailer->setFrom($email->getFrom(), $email->getFromName());
            $this->mailer->addAddress($email->getTo());
            foreach ($email->getCc() as $cc) {
                $this->mailer->addCC($cc);
            }
            foreach ($email->getBcc() as $bcc) {
                $this->mailer->addBCC($bcc);
            }

            $this->mailer->isHTML();
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Subject = $email->getSubject();

            foreach ($email->getAttachments() as $attachment) {
                $this->mailer->addAttachment($attachment);
            }

            $this->mailer->Body = $email->getBody();
            $this->mailer->AltBody = $email->getBody();

            $this->mailer->send();
            return ["success" => true, "msg" => "Mensaje enviado", "data" => null];
        } catch (Exception $e) {
            return ["success" => false, "msg" => $this->mailer->ErrorInfo, "data" => null];
        }
    }
}