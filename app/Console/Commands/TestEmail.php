<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Vérifie l'envoi des e-mails (SMTP du .env) : envoie un message simple à l'adresse donnée. */
class TestEmail extends Command
{
    protected $signature = 'site:test-email {adresse : Adresse qui doit recevoir le message de test}';

    protected $description = 'Envoie un e-mail de test pour vérifier la configuration SMTP (MAIL_* du .env)';

    public function handle(): int
    {
        $to = (string) $this->argument('adresse');
        $this->line('Envoi par '.config('mail.default').' ('.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port').') depuis '.config('mail.from.address').'…');
        try {
            Mail::raw("Bonjour,\n\nCe message confirme que le site ".config('app.name')." envoie bien ses e-mails.\n\nComputer Service BARRY", function ($message) use ($to) {
                $message->to($to)->subject('['.config('app.name').'] E-mail de test');
            });
        } catch (Throwable $e) {
            $this->error('Échec : '.$e->getMessage());
            $this->line('Vérifiez MAIL_USERNAME, MAIL_PASSWORD (mot de passe d\'application Gmail) et MAIL_FROM_ADDRESS dans le .env, puis : php artisan config:clear');

            return self::FAILURE;
        }
        $this->info("E-mail envoyé à {$to} : regardez la boîte de réception (et les indésirables).");

        return self::SUCCESS;
    }
}
