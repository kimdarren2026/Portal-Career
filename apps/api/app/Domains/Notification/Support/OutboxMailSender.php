<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

use App\Domains\Notification\Models\SmtpConfiguration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

/**
 * Sends one already-rendered transactional message (PGC-V1 / PD-B).
 *
 * When an active runtime `smtp_configurations` row exists, a request-scoped
 * Symfony ESMTP transport is built from it exactly as the SMTP test sender
 * does — the decrypted credential lives in a local variable only and is never
 * logged or returned. Otherwise delivery falls back to the deployment
 * `MAIL_*` mailer (in tests the `array` mailer, which captures without
 * touching a network).
 *
 * A transport failure is allowed to throw — the caller (`DeliverEmailOutboxMessage`)
 * turns it into a sanitized `last_error_summary` and the retry/dead-letter
 * state machine. This class never sees or persists business state.
 */
class OutboxMailSender
{
    public function send(string $recipient, string $subject, string $body): void
    {
        $config = SmtpConfiguration::query()->where('is_active', true)->first();

        if ($config === null) {
            Mail::raw($body, function ($message) use ($recipient, $subject): void {
                $message->to($recipient)->subject($subject);
            });

            return;
        }

        $this->sendViaRuntimeSmtp($config, $recipient, $subject, $body);
    }

    private function sendViaRuntimeSmtp(SmtpConfiguration $config, string $recipient, string $subject, string $body): void
    {
        $ciphertext = $config->getAttribute('encrypted_password');
        $password = $ciphertext === null ? null : Crypt::decryptString($ciphertext);

        $implicitTls = $config->encryption_mode === 'TLS';
        $transport = new EsmtpTransport((string) $config->host, (int) $config->port, $implicitTls);

        if ($config->encryption_mode === 'NONE' && method_exists($transport, 'setAutoTls')) {
            $transport->setAutoTls(false);
        }
        if ($config->username !== null && $config->username !== '') {
            $transport->setUsername((string) $config->username);
        }
        if ($password !== null && $password !== '') {
            $transport->setPassword($password);
        }
        if ($config->timeout_seconds !== null && method_exists($transport->getStream(), 'setTimeout')) {
            $transport->getStream()->setTimeout((int) $config->timeout_seconds);
        }

        $email = (new Email())
            ->from($this->addressPair((string) $config->from_address, $config->from_name))
            ->to($recipient)
            ->subject($subject)
            ->text($body);

        if ($config->reply_to_address !== null && $config->reply_to_address !== '') {
            $email->replyTo((string) $config->reply_to_address);
        }

        (new SymfonyMailer($transport))->send($email);

        unset($password);
    }

    private function addressPair(string $address, ?string $name): string
    {
        return $name === null || $name === '' ? $address : sprintf('%s <%s>', $name, $address);
    }
}
