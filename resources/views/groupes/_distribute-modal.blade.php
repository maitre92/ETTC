{{-- « Répartir automatiquement » : les apprenants confirmés sans groupe, par niveau et par mode, d'abord dans les groupes
     qui ont de la place, puis dans de nouveaux groupes équilibrés. $plan (SeanceService::distributionPlan). --}}
<div class="modal fade" id="repartir" tabindex="-1" aria-hidden="true" aria-labelledby="repartir-title">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('groupes.distribute') }}" data-crud-form data-no-guard>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="repartir-title"><i class='bx bx-shuffle me-2'></i>Répartir les apprenants en groupes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-2">Apprenants à placer (inscription confirmée ou validée, sans groupe) :</p>
                    <ul class="list-group mb-3">
                        @foreach ($plan['buckets'] as $bucket)
                            <li class="list-group-item d-flex justify-content-between"><span>{{ $bucket['label'] }}</span><strong>{{ $bucket['count'] }}</strong></li>
                        @endforeach
                    </ul>
                    @if ($plan['waiting_test'] > 0)
                        <p class="small text-muted">{{ $plan['waiting_test'] }} apprenant(s) sans niveau (test à passer) ne sont pas répartis : indiquez d'abord leur niveau.</p>
                    @endif
                    <label for="repartir-capacity" class="form-label fw-semibold">Apprenants par groupe, au plus</label>
                    <input type="number" class="form-control form-control-lg" id="repartir-capacity" name="capacity" min="2" max="200" step="1" value="15" required>
                    <div class="form-text">Les groupes du même niveau et du même mode qui ont encore de la place sont remplis d'abord ; les autres apprenants forment de nouveaux groupes de taille égale (ex. 23 apprenants, 15 au plus → 2 groupes de 12 et 11).</div>
                    <p class="small mt-3 mb-0"><i class='bx bx-info-circle'></i> Ensuite, ouvrez chaque nouveau groupe pour choisir son formateur, son lien Google Meet (en ligne) et programmer ses séances. Vous pouvez toujours déplacer un apprenant à la main.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary btn-lg"><i class='bx bx-check me-1'></i>Répartir {{ $plan['total'] }} apprenant(s)</button>
                </div>
            </form>
        </div>
    </div>
</div>
