@extends('layouts.guest')

@section('title', 'Connexion')

{{-- Écran partagé en deux : l'affiche du centre à gauche, le formulaire à droite (repris du projet CSB, 2026-10-11). --}}
@section('page_class', 'login-page--right')

@section('background')
    <div class="bg-aurora"></div>
    <div class="site-login-scene" aria-hidden="true">
        <span class="site-glow site-glow--a"></span>
        <span class="site-glow site-glow--b"></span>
    </div>
    <div class="site-login-brand">
        {{-- Affiche d'ETTC : logo officiel du centre (intégré à l'image) et couleurs du logo. --}}
        <div class="site-login-poster">
            <img src="{{ asset('assets/site/img/affiche-ettc.svg') }}" alt="English Training and Translation Center — formations d'anglais, test de niveau, traduction, excursions" width="1280" height="853">
        </div>
    </div>
@endsection

@push('styles')
    <style>
        /* Écran partagé en deux (repris du projet CSB) : à gauche l'affiche sur fond blanc, à droite le formulaire sur fond bleu. */
        .login-page--right { justify-content: center; margin-left: 50%; width: 50%; }
        .site-login-scene { position: fixed; top: 0; bottom: 0; right: 0; width: 50%; z-index: 0; overflow: hidden; background: radial-gradient(circle at 60% 40%, #4b90cd 0%, #0c68b2 50%, #084a80 100%); }
        .site-glow { position: absolute; border-radius: 50%; filter: blur(70px); opacity: .55; animation: siteFloat 12s ease-in-out infinite alternate; }
        .site-glow--a { width: 380px; height: 380px; left: 10%; top: 12%; background: #afdef6; }
        .site-glow--b { width: 300px; height: 300px; right: 5%; bottom: 10%; background: #f29677; opacity: .45; animation-delay: -6s; }
        @keyframes siteFloat { from { transform: translate(0, 0) scale(1); } to { transform: translate(30px, -25px) scale(1.1); } }
        .site-login-brand { position: fixed; z-index: 1; left: 0; top: 0; bottom: 0; width: 50%; display: flex; align-items: center; justify-content: center; padding: 2rem; background: #fff; box-shadow: 12px 0 40px rgba(8, 74, 128, .25); pointer-events: none; }
        .site-login-poster { width: 100%; animation: siteLogoIn .9s cubic-bezier(.2, .8, .2, 1) both; }
        .site-login-poster img { display: block; width: 100%; height: auto; max-height: calc(100vh - 4rem); object-fit: contain; }
        .module-wave { left: 50%; }
        @keyframes siteLogoIn { from { opacity: 0; transform: translateY(24px) scale(.9); } to { opacity: 1; transform: none; } }
        @media (max-width: 900px) {
            .site-login-brand { display: none; }
            .site-login-scene { width: 100%; }
            .login-page--right { margin-left: 0; width: 100%; }
            .module-wave { left: 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            .site-glow, .site-login-poster, .bg-aurora { animation: none; }
        }
    </style>
@endpush

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger py-2">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="form-divider"><span>Connexion</span></div>

    <form method="POST" action="{{ route('login.store') }}" id="loginForm">
        @csrf

        <div class="mb-3">
            <label for="phone" class="form-label">Numéro de téléphone</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bx bx-phone"></i></span>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                       class="form-control @error('phone') is-invalid @enderror"
                       inputmode="tel" autocomplete="username" placeholder="70 00 00 00" autofocus required>
            </div>
            <div class="form-text">Indicatif +223 ajouté automatiquement pour un numéro de 8 chiffres.</div>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Mot de passe</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="current-password" required>
                <button type="button" class="btn password-toggle" id="togglePassword" aria-label="Afficher le mot de passe">
                    <i class="bx bx-show"></i>
                </button>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn login-button" id="loginButton">
                <i class="bx bx-log-in-circle"></i> Connexion
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginButton');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Connexion en cours…';
        });

        document.getElementById('togglePassword').addEventListener('click', function () {
            const input = document.getElementById('password');
            const icon = this.querySelector('i');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('bx-show', !isPassword);
            icon.classList.toggle('bx-hide', isPassword);
        });
    </script>
@endpush
