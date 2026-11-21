<?php

namespace App\Services\Seances;

use App\Mail\MessageGroupe;
use App\Models\Client;
use App\Models\Groupe;
use App\Models\GroupeMessage;
use App\Models\Inscription;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Discussion d'un groupe (demande du centre, 2026-11-21) : le formateur (ou l'équipe) écrit à ses apprenants, qui
 * répondent depuis leur espace. Les apprenants peuvent être prévenus par e-mail d'un message du formateur ; le formateur
 * voit les nouveaux messages dans son espace (pastille du menu, cloche). Un échec d'envoi ne bloque jamais le message.
 */
class DiscussionService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /** Message du formateur ou de l'équipe. Retourne le nombre d'apprenants prévenus par e-mail. */
    public function postFromTeam(Groupe $groupe, string $body, User $author, bool $email): int
    {
        $message = DB::transaction(function () use ($groupe, $body, $author) {
            $message = GroupeMessage::withoutActivityLog(fn () => GroupeMessage::create(['groupe_id' => $groupe->id, 'user_id' => $author->id, 'body' => $body]));
            $this->logger->log('seances.message_posted', 'seances', "Message au groupe {$groupe->name} (".Str::limit($body, 60).')', $groupe, actor: $author);

            return $message;
        });
        GroupeMessage::markSeen('user:'.$author->id, $groupe->id);

        return $email ? $this->mailLearners($groupe, $message) : 0;
    }

    /** Message d'un apprenant du groupe (vérifié par l'appelant). */
    public function postFromLearner(Groupe $groupe, string $body, Client $client): GroupeMessage
    {
        $message = GroupeMessage::create(['groupe_id' => $groupe->id, 'client_id' => $client->id, 'body' => $body]);
        GroupeMessage::markSeen('client:'.$client->id, $groupe->id);

        return $message;
    }

    /** Message retiré (par son auteur de l'équipe, le formateur du groupe ou un responsable). */
    public function delete(GroupeMessage $message, User $actor): void
    {
        DB::transaction(function () use ($message, $actor) {
            GroupeMessage::withoutActivityLog(fn () => $message->delete());
            $this->logger->log('seances.message_deleted', 'seances', "Message retiré du groupe {$message->groupe->name} (".$message->authorName().')', $message->groupe, actor: $actor);
        });
    }

    private function mailLearners(Groupe $groupe, GroupeMessage $message): int
    {
        $sent = 0;
        $recipients = Inscription::where('groupe_id', $groupe->id)->whereIn('status', ['validee', 'en_cours'])->whereNotNull('email')
            ->get(['name', 'email'])->unique('email');
        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->email, $recipient->name)->send(new MessageGroupe($message, $recipient->name));
                $sent++;
            } catch (Throwable $e) {
                Log::error("Message de groupe {$message->id} non envoyé à {$recipient->email} : ".Str::limit($e->getMessage(), 200));
            }
        }

        return $sent;
    }
}
