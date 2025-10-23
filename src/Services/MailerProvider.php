<?php

namespace App\Services;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerProvider
{
  private $mailer;
  private $mailFrom;

  public function __construct(MailerInterface $mailer, string $mailFrom)
  {
    $this->mailer = $mailer;
    $this->mailFrom = $mailFrom;
  }

  public function sendEmail($to, $subject, $body)
  {
    try {
      $email = (new Email())
        ->from($this->mailFrom)
        ->to($to)
        ->subject($subject)
        ->html($body);
      $this->mailer->send($email);
    } catch (\Exception $e) {
      throw new \RuntimeException('Erreur lors de l\'envoi de l\'email', 0, $e);
    }
  }
}


