{{-- « Comment payer par code marchand » : étapes une à une (diaporama, site.js [data-steps-slider]) ; sans JavaScript,
     toutes les étapes sont listées. $label (moyen), $ussd (PaiementService::merchantUssd()[moyen]). --}}
<div class="ussd-steps" data-steps-slider role="group" aria-label="Comment payer par code marchand {{ $label }}">
    <p class="ussd-steps-head"><i class='bx bx-dialpad-alt' aria-hidden="true"></i> Payer par code marchand {{ $label }}@if ($ussd['merchant']) <span>· code marchand <strong>{{ $ussd['merchant'] }}</strong></span>@endif</p>
    <ol class="ussd-steps-list">
        @foreach ($ussd['steps'] as $step)
            <li class="ussd-step" data-step-slide>
                <span class="ussd-step-num" aria-hidden="true">{{ $loop->iteration }}</span>
                <div>
                    <strong>{{ $step['title'] }}</strong>
                    <p class="mb-1">{{ $step['text'] }}</p>
                    @if ($step['code'])
                        <div class="ussd-code">
                            <code>{{ $step['code'] }}</code>
                            @if ($loop->first)<button type="button" class="btn btn-sm btn-outline-primary" data-copy="{{ $step['code'] }}"><i class='bx bx-copy' aria-hidden="true"></i> Copier</button>@endif
                        </div>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
