<?php

namespace App\Services\Seances;

use App\Mail\AnnonceCentre;
use App\Models\Annonce;
use App\Models\Inscription;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Annonces du centre (cahier §4) : publiées pour tous les apprenants ou un groupe, prévenus par e-mail si on le
 * souhaite (apprenants en formation ou en attente, avec une adresse). Un échec d'envoi ne bloque jamais la publication.
 */
class AnnonceService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /** @param  array{title: string, body: string, groupe_id?: ?int, expires_on?: ?string}  $data */
    public function publish(array $data, bool $email, User $actor): Annonce
    {
        $annonce = DB::transaction(function () use ($data, $actor) {
            $annonce = Annonce::withoutActivityLog(fn () => Annonce::create($data + ['created_by' => $actor->id]));
            $this->logger->log('seances.annonce_published', 'seances', "Annonce publiée ({$annonce->audience()}) : {$annonce->title}", $annonce, actor: $actor);

            return $annonce;
        });

        if ($email) {
            $sent = 0;
            $recipients = Inscription::whereIn('status', ['validee', 'en_cours'])->whereNotNull('email')
                ->when($annonce->groupe_id, fn ($q) => $q->where('groupe_id', $annonce->groupe_id))
                ->get(['name', 'email'])->unique('email');
            foreach ($recipients as $recipient) {
                try {
                    Mail::to($recipient->email, $recipient->name)->send(new AnnonceCentre($annonce, $recipient->name));
                    $sent++;
                } catch (Throwable $e) {
                    Log::error("Annonce {$annonce->id} non envoyée à {$recipient->email} : ".Str::limit($e->getMessage(), 200));
                }
            }
            $annonce->forceFill(['emailed_count' => $sent])->saveQuietly();
        }

        return $annonce;
    }

    public function delete(Annonce $annonce, User $actor): void
    {
        DB::transaction(function () use ($annonce, $actor) {
            Annonce::withoutActivityLog(fn () => $annonce->delete());
            $this->logger->log('seances.annonce_deleted', 'seances', "Annonce retirée : {$annonce->title}", $annonce, actor: $actor);
        });
    }
}
