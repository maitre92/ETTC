{{-- Bandeau d'appel à l'action (cahier ETTC §3.1 « boutons d'action » : faire une demande, nous contacter ; « S'inscrire » viendra avec le module des inscriptions). --}}
<section class="section-sm">
    <div class="container">
        <div class="cta-band">
            <div class="row align-items-center g-4">
                <div class="col-lg-7 position-relative" style="z-index: 1">
                    <h2>{{ $title ?? 'Prêt à progresser en anglais ?' }}</h2>
                    <p>{{ $text ?? 'Formation, test de niveau, traduction : parlez-nous de votre besoin, nous vous répondons rapidement.' }}</p>
                </div>
                <div class="col-lg-5">
                    <div class="cta-actions justify-content-lg-end">
                        @if (Route::has('site.devis'))
                            <a href="{{ route('site.devis') }}" class="btn btn-accent btn-lg">Faire une demande</a>
                        @endif
                        @if (Route::has('site.contact'))
                            <a href="{{ route('site.contact') }}" class="btn btn-outline-light btn-lg">Nous contacter</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
