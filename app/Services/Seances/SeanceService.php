<?php

namespace App\Services\Seances;

use App\Enums\FormationMode;
use App\Mail\SeancesCalendrier;
use App\Models\Groupe;
use App\Models\Inscription;
use App\Models\Seance;
use App\Models\Support;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Séances à distance et en présentiel (cahier §3.6, §5) : groupes, apprenants du groupe, séances (une ou une série
 * d'un coup), annulation avec prévenance, supports pédagogiques. Les apprenants reçoivent leur calendrier par e-mail ;
 * un échec d'envoi ne bloque jamais l'enregistrement.
 */
class SeanceService
{
    /** Une série ne dépasse pas ce nombre de séances (garde-fou contre une erreur de dates). */
    public const MAX_SERIES = 120;

    public function __construct(private readonly ActivityLogger $logger) {}

    /** @param  array<string, mixed>  $data */
    public function saveGroupe(?Groupe $groupe, array $data, User $actor): Groupe
    {
        return DB::transaction(function () use ($groupe, $data) {
            $groupe ??= new Groupe;
            $renamed = $groupe->exists && $groupe->name !== $data['name'];
            $groupe->fill($data)->save();
            if ($renamed) {
                // Le libellé « session » des inscriptions suit le nom du groupe.
                Inscription::withoutActivityLog(fn () => Inscription::where('groupe_id', $groupe->id)->update(['session' => $groupe->name]));
            }

            return $groupe;
        });
    }

    /**
     * Inscriptions proposées pour un groupe : même mode (et même niveau s'il est fixé), dossier actif, sans groupe.
     *
     * @return Collection<int, Inscription>
     */
    public function candidates(Groupe $groupe): Collection
    {
        return Inscription::with('niveau')->whereNull('groupe_id')->whereIn('status', ['a_valider', 'validee', 'en_cours'])
            ->where('mode', $groupe->mode->value)
            ->when($groupe->niveau_id, fn ($q) => $q->where(fn ($q) => $q->where('niveau_id', $groupe->niveau_id)->orWhereNull('niveau_id')))
            ->orderBy('name')->get();
    }

    /** @param  list<int>  $inscriptionIds */
    public function addMembers(Groupe $groupe, array $inscriptionIds, User $actor): int
    {
        return DB::transaction(function () use ($groupe, $inscriptionIds, $actor) {
            $inscriptions = Inscription::whereIn('id', $inscriptionIds)->where('status', '!=', 'annulee')->get();
            foreach ($inscriptions as $inscription) {
                Inscription::withoutActivityLog(fn () => $inscription->update(['groupe_id' => $groupe->id, 'session' => $groupe->name]));
            }
            if ($inscriptions->isNotEmpty()) {
                $this->logger->log('seances.members_added', 'seances', "Apprenants ajoutés au groupe {$groupe->name} : ".$inscriptions->pluck('name')->implode(', '), $groupe, actor: $actor);
            }

            return $inscriptions->count();
        });
    }

    public function removeMember(Groupe $groupe, Inscription $inscription, User $actor): void
    {
        abort_unless($inscription->groupe_id === $groupe->id, 404);
        DB::transaction(function () use ($groupe, $inscription, $actor) {
            Inscription::withoutActivityLog(fn () => $inscription->update(['groupe_id' => null, 'session' => null]));
            $this->logger->log('seances.member_removed', 'seances', "{$inscription->name} retiré(e) du groupe {$groupe->name}", $groupe, actor: $actor);
        });
    }

    /**
     * Plusieurs séances d'un coup : jours de la semaine (1 = lundi … 7 = dimanche), heure, durée, du … au ….
     *
     * @param  array{days: list<int|string>, time: string, duration_minutes: int|string, from: string, to: string, title?: ?string}  $data
     * @return Collection<int, Seance>
     */
    public function planSeries(Groupe $groupe, array $data, User $actor): Collection
    {
        [$hour, $minute] = array_map('intval', explode(':', $data['time']));
        $days = array_map('intval', $data['days']);
        $dates = collect();
        for ($day = CarbonImmutable::parse($data['from']); $day->lte(CarbonImmutable::parse($data['to'])); $day = $day->addDay()) {
            if (in_array($day->dayOfWeekIso, $days, true)) {
                $dates->push($day->setTime($hour, $minute));
            }
        }
        if ($dates->isEmpty()) {
            throw ValidationException::withMessages(['days' => 'Aucune date ne correspond à ces jours sur cette période.']);
        }
        if ($dates->count() > self::MAX_SERIES) {
            throw ValidationException::withMessages(['to' => 'Plus de '.self::MAX_SERIES.' séances : réduisez la période.']);
        }

        $seances = DB::transaction(function () use ($groupe, $dates, $data, $actor) {
            $existing = $groupe->seances()->where('is_cancelled', false)->pluck('starts_at')->map(fn ($d) => $d->format('Y-m-d H:i'))->all();
            $created = $dates->reject(fn ($date) => in_array($date->format('Y-m-d H:i'), $existing, true))
                ->map(fn ($date) => Seance::withoutActivityLog(fn () => $groupe->seances()->create([
                    'starts_at' => $date, 'duration_minutes' => (int) $data['duration_minutes'], 'title' => $data['title'] ?? null,
                ])))->values();
            $this->logger->log('seances.planned', 'seances', "{$created->count()} séance(s) programmée(s) pour le groupe {$groupe->name}", $groupe, actor: $actor);

            return $created;
        });

        $this->sendCalendar($groupe, $seances, 'planifiees');

        return $seances;
    }

    /** @param  array<string, mixed>  $data */
    public function saveSeance(Groupe $groupe, ?Seance $seance, array $data, User $actor): Seance
    {
        return DB::transaction(function () use ($groupe, $seance, $data, $actor) {
            $isNew = $seance === null;
            $seance ??= new Seance(['groupe_id' => $groupe->id]);
            Seance::withoutActivityLog(fn () => $seance->fill($data)->save());
            $this->logger->log($isNew ? 'seances.created' : 'seances.updated', 'seances', ($isNew ? 'Séance ajoutée' : 'Séance modifiée')." — {$groupe->name}, {$seance->when()}", $seance, actor: $actor);

            return $seance;
        });
    }

    public function cancelSeance(Seance $seance, string $reason, User $actor): Seance
    {
        if ($seance->is_cancelled) {
            throw ValidationException::withMessages(['reason' => 'Cette séance est déjà annulée.']);
        }
        DB::transaction(function () use ($seance, $reason, $actor) {
            Seance::withoutActivityLog(fn () => $seance->update(['is_cancelled' => true, 'cancel_reason' => $reason]));
            $this->logger->log('seances.cancelled', 'seances', "Séance annulée — {$seance->groupe->name}, {$seance->when()} : {$reason}", $seance, actor: $actor);
        });
        if (! $seance->isPast()) {
            $this->sendCalendar($seance->groupe, collect([$seance]), 'annulee');
        }

        return $seance;
    }

    public function addSupport(Groupe $groupe, string $title, ?UploadedFile $file, ?string $url, User $actor): Support
    {
        $attributes = ['groupe_id' => $groupe->id, 'title' => $title, 'uploaded_by' => $actor->id];
        if ($file) {
            $mime = (string) $file->getMimeType();
            $attributes += [
                'kind' => str_starts_with($mime, 'audio/') ? 'audio' : (str_starts_with($mime, 'video/') ? 'video' : 'document'),
                'path' => $file->storeAs('supports/'.$groupe->id, Str::random(32).'.'.$file->extension(), 'local'),
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime' => $mime,
                'size' => $file->getSize(),
            ];
        } else {
            $attributes += ['kind' => $this->linkKind((string) $url), 'url' => $url];
        }

        return DB::transaction(function () use ($attributes, $groupe, $actor) {
            $support = Support::withoutActivityLog(fn () => Support::create($attributes));
            $this->logger->log('seances.support_added', 'seances', "Support ajouté au groupe {$groupe->name} : {$support->title}", $support, actor: $actor);

            return $support;
        });
    }

    public function deleteSupport(Support $support, User $actor): void
    {
        DB::transaction(function () use ($support, $actor) {
            $support->delete();
            $this->logger->log('seances.support_deleted', 'seances', "Support retiré : {$support->title}", $support, actor: $actor);
        });
        if ($support->path) {
            Storage::disk('local')->delete($support->path);
        }
    }

    private function linkKind(string $url): string
    {
        return match (true) {
            (bool) preg_match('~youtube\.com|youtu\.be|vimeo\.com|tiktok\.com~i', $url) => 'video',
            (bool) preg_match('~\.(mp3|m4a|wav|ogg)(\?|$)~i', $url) => 'audio',
            default => 'lien',
        };
    }

    /** Calendrier (ou annulation) envoyé à chaque apprenant du groupe qui a un e-mail. @param  Collection<int, Seance>  $seances */
    private function sendCalendar(Groupe $groupe, Collection $seances, string $kind): void
    {
        if ($seances->isEmpty()) {
            return;
        }
        foreach ($groupe->members()->whereNotNull('email')->get() as $inscription) {
            try {
                Mail::to($inscription->email, $inscription->name)->send(new SeancesCalendrier($groupe, $inscription, $seances, $kind));
            } catch (Throwable $e) {
                Log::error("Calendrier du groupe {$groupe->name} non envoyé à {$inscription->number} : ".Str::limit($e->getMessage(), 300));
            }
        }
    }

    /** Lien Google Meet valide (https) ; en présentiel, le lien est facultatif. */
    public static function meetRule(): array
    {
        return ['nullable', 'url:https', 'max:300'];
    }

    public static function isOnline(Groupe $groupe): bool
    {
        return $groupe->mode === FormationMode::EnLigne;
    }
}
