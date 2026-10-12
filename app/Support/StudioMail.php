<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the studio its own e-mail — the new-enquiry alerts.
 *
 * Where they go and which mailbox sends them are set in the admin (Manage →
 * Enquiry alerts), not in .env: the host has no shell, and the deploy caches
 * the configuration, so a .env edit would only take effect on the next
 * deploy. With no mailbox set, the server's own mail settings are used.
 *
 * Sending is never allowed to fail the thing that triggered it: an enquiry is
 * saved and shown in the admin whether or not its alert gets out.
 */
class StudioMail
{
    public const TO = 'alerts_to';

    public const ENABLED = 'alerts_enabled';

    public const HOST = 'alerts_smtp_host';

    public const PORT = 'alerts_smtp_port';

    public const USERNAME = 'alerts_smtp_username';

    public const PASSWORD = 'alerts_smtp_password';   // stored encrypted

    public const FROM = 'alerts_from_address';

    /** Alerts are on unless switched off in the admin. */
    public static function enabled(): bool
    {
        return setting(self::ENABLED, '1') !== '0';
    }

    /**
     * Who gets the alerts: the addresses set in the admin, or else the studio
     * e-mail shown on the site.
     *
     * @return array<int, string>
     */
    public static function recipients(): array
    {
        $raw = setting(self::TO) ?: setting('contact_email_new', 'Info@arammizzuri.com');

        return collect(preg_split('/[\s,;]+/', $raw))
            ->map(fn ($a) => trim($a))
            ->filter(fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))
            ->unique()->values()->all();
    }

    /** True once a mailbox has been set up in the admin. */
    public static function hasMailbox(): bool
    {
        return filled(setting(self::HOST)) && filled(setting(self::USERNAME));
    }

    /** Store the mailbox password — encrypted with the app key, never in clear. */
    public static function storePassword(string $password): void
    {
        SiteSetting::put(self::PASSWORD, Crypt::encryptString($password), 'alerts');
    }

    private static function password(): ?string
    {
        $stored = setting(self::PASSWORD);
        if ($stored === '') {
            return null;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;   // encrypted under another app key — ask for it again
        }
    }

    /**
     * The mailer to send with: the admin's mailbox when one is set, else the
     * default from .env. Port 465 is SMTP over TLS from the first byte
     * ("smtps"); 587 and the rest upgrade with STARTTLS.
     */
    public static function mailer(): Mailer
    {
        if (! self::hasMailbox()) {
            return Mail::mailer();
        }

        $port = (int) (setting(self::PORT) ?: 465);
        config(['mail.mailers.studio' => [
            'transport' => 'smtp',
            'scheme' => $port === 465 ? 'smtps' : 'smtp',
            'host' => setting(self::HOST),
            'port' => $port,
            'username' => setting(self::USERNAME),
            'password' => self::password(),
            'timeout' => 15,
        ]]);
        // drop a mailer built from earlier settings (a faked Mail has none)
        $manager = Mail::getFacadeRoot();
        if ($manager instanceof MailManager) {
            $manager->purge('studio');
        }

        return Mail::mailer('studio');
    }

    /** The address alerts are sent from — the mailbox itself, unless set otherwise. */
    public static function fromAddress(): string
    {
        return setting(self::FROM) ?: (self::hasMailbox() ? setting(self::USERNAME) : (string) config('mail.from.address'));
    }

    /**
     * Send one message to the studio. Returns null on success, or the reason
     * it failed (also written to the log).
     */
    public static function send(Mailable $mail): ?string
    {
        $to = self::recipients();
        if ($to === []) {
            return 'No valid address to send to.';
        }

        try {
            $mail->from(self::fromAddress(), 'Aram Mizuri Architecture — website');
            self::mailer()->to($to)->send($mail);

            return null;
        } catch (Throwable $e) {
            Log::warning('Studio e-mail could not be sent: '.$e->getMessage());

            return $e->getMessage();
        }
    }
}
