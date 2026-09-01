<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

use App\Domains\Notification\Models\SmtpConfiguration;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Default {@see SmtpTestSender}: builds a request-scoped Symfony ESMTP
 * transport from the stored configuration and sends one plain diagnostic
 * message. Nothing global is mutated (no `config()` write, no `.env`), so a
 * concurrent request keeps its own mail configuration.
 *
 * The credential is decrypted in a local variable only, is never logged, and
 * is never included in the returned outcome. Any failure is mapped to a fixed
 * sanitized phrase — the raw provider exception (which can echo host,
 * username, or more) never leaves this method.
 */
final class SymfonyMailerSmtpTestSender implements SmtpTestSender
{
    public function send(SmtpConfiguration $config, string $recipient): SmtpTestOutcome
    {
        try {
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
                ->subject('Portal Karir Kampus — uji konfigurasi SMTP')
                ->text("Ini adalah email uji dari Portal Karir Kampus.\nJika Anda menerimanya, konfigurasi SMTP berhasil.");

            if ($config->reply_to_address !== null && $config->reply_to_address !== '') {
                $email->replyTo((string) $config->reply_to_address);
            }

            (new Mailer($transport))->send($email);

            unset($password);

            return SmtpTestOutcome::success();
        } catch (Throwable) {
            // The raw exception can contain the host, username, DSN or provider
            // banner — none of it is surfaced. One fixed, safe phrase only.
            return SmtpTestOutcome::failure(
                'Pengiriman uji gagal. Periksa host, port, mode enkripsi, dan kredensial SMTP.',
            );
        }
    }

    private function addressPair(string $address, ?string $name): string
    {
        return $name === null || $name === '' ? $address : sprintf('%s <%s>', $name, $address);
    }
}
