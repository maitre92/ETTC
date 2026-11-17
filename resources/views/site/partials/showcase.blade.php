{{-- Fond décoratif : visuels des actualités publiées qui défilent sur trois rangées, derrière un voile bleu.
     Caché aux lecteurs d'écran, immobile si le visiteur réduit les animations ; rien sans contenu illustré. À placer
     dans un conteneur `.has-showcase` (voir site.css). --}}
@php
    $showcase = \App\Models\Article::published()->whereNotNull('cover_media_id')->with('cover')->latestFirst()->limit(12)->get()
        ->filter(fn ($a) => $a->cover)->map(fn ($a) => ['image' => $a->cover->thumbUrl(), 'title' => $a->title])->values();
    // Assez de vignettes pour couvrir un grand écran : la liste est répétée ; chaque rangée commence ailleurs.
    $tiles = $showcase->isEmpty() ? collect() : collect(array_fill(0, (int) ceil(8 / $showcase->count()), $showcase->all()))->flatten(1);
    $rows = $tiles->isEmpty() ? [] : array_map(fn ($shift) => $tiles->slice($shift % $showcase->count())->concat($tiles->take($shift % $showcase->count()))->values(), [0, 3, 6]);
@endphp
@if ($rows)
    <div class="auth-showcase" aria-hidden="true">
        @foreach ($rows as $index => $rowTiles)
            <div class="auth-showcase-row {{ $index === 1 ? 'is-reverse' : '' }}" style="--auth-row-duration: {{ 60 + $index * 15 }}s">
                {{-- Deux fois la même suite : la rangée glisse de -50 % puis repart, sans saut visible. --}}
                @foreach ($rowTiles->concat($rowTiles) as $tile)
                    <figure class="auth-tile {{ ! empty($tile['product']) ? 'is-product' : '' }}"><img src="{{ $tile['image'] }}" alt="" width="480" height="300"><figcaption>{{ $tile['title'] }}</figcaption></figure>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
