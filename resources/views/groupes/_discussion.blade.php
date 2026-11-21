{{-- Discussion d'un groupe (administration et espace formateur) : messages du formateur / de l'équipe et des apprenants,
     du plus ancien au plus récent ; formulaire d'envoi avec « prévenir par e-mail ». $groupe (messages chargés),
     $postUrl (null = lecture seule), $deleteUrl (closure : message → adresse de retrait, ou null). --}}
<div class="card" id="discussion">
    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-conversation me-2'></i>DISCUSSION DU GROUPE</h6></div>
    <div class="card-body">
        <div class="nv-thread mb-3">
            @forelse ($groupe->messages as $msg)
                <div class="nv-thread-item {{ $msg->fromTeam() ? 'is-team' : 'is-learner' }}">
                    <div class="small text-muted d-flex flex-wrap gap-2 align-items-center">
                        <strong class="text-body">{{ $msg->authorName() }}</strong>
                        <span class="badge {{ $msg->fromTeam() ? 'bg-primary-subtle' : 'bg-light text-dark' }}">{{ $msg->fromTeam() ? ($msg->user_id === $groupe->formateur_id ? 'Formateur' : 'Centre') : 'Apprenant' }}</span>
                        <span>{{ $msg->created_at->format('d/m/Y H:i') }}</span>
                        @if ($deleteUrl && ($url = $deleteUrl($msg)))
                            <form method="POST" action="{{ $url }}" class="ms-auto" data-crud-form data-no-guard data-confirm="Retirer ce message ?">@csrf @method('DELETE')<button type="submit" class="btn btn-link btn-sm text-muted p-0" title="Retirer"><i class='bx bx-trash'></i></button></form>
                        @endif
                    </div>
                    <div class="mt-1">{!! nl2br(e($msg->body)) !!}</div>
                </div>
            @empty
                <p class="text-muted mb-0">Aucun message. Écrivez le premier : consignes, devoirs, rappel de la prochaine séance… Les apprenants répondent depuis leur espace.</p>
            @endforelse
        </div>
        @if ($postUrl)
            <form method="POST" action="{{ $postUrl }}" data-crud-form data-no-guard>
                @csrf
                <label for="msg-body" class="form-label fw-semibold">Écrire au groupe</label>
                <textarea class="form-control" id="msg-body" name="body" rows="3" maxlength="3000" required placeholder="Bonjour à tous, pour la prochaine séance…"></textarea>
                <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                    <button type="submit" class="btn btn-primary"><i class='bx bx-send me-1'></i>Envoyer</button>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="msg-email" name="email" value="1" checked>
                        <label class="form-check-label small" for="msg-email">Prévenir aussi les apprenants par e-mail</label>
                    </div>
                </div>
            </form>
        @endif
    </div>
</div>
