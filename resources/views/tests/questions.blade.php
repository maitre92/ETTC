@extends('layouts.admin')

@section('title', 'Questions du test en ligne')

{{-- Banque de questions du test de niveau en ligne : QCM (corrigés automatiquement) et sujets d'expression orale
     (enregistrés par le candidat, écoutés par un formateur). Saisie une par une, import d'un fichier (Excel, CSV,
     Word), réglages du test. --}}
@section('content')
    @include('admin._crud', [
        'title' => 'Questions du test en ligne',
        'section' => 'Formations',
        'crumbs' => [['Tests de niveau', route('tests.index')]],
        'heading' => 'QUESTIONS DU TEST EN LIGNE',
        'icon' => 'bx bx-list-check',
        'intro' => 'Le candidat reçoit des questions tirées au hasard (QCM corrigés aussitôt) et, s\'il y en a, un sujet d\'expression orale qu\'il enregistre au micro. Son score donne un niveau provisoire (score minimal réglé sur chaque niveau) ; un formateur écoute l\'oral et confirme. Importez un questionnaire : « Saisie en lot / import » (Excel ou CSV), ou un document Word ci-dessous.',
        'items' => $questions,
        'model' => \App\Models\TestQuestion::class,
        'routePrefix' => 'questions',
        'idField' => 'question_id',
        'deleteTitle' => 'Supprimer cette question ?',
        'newLabel' => 'Nouvelle question',
        'labelOf' => fn ($q) => \Illuminate\Support\Str::limit($q->question, 50),
        'statusField' => 'is_active',
        'statusLabels' => ['Active', 'Inactive'],
        'fields' => [
            ['name' => 'section', 'label' => 'Partie', 'type' => 'select', 'required' => true, 'options' => \App\Models\TestQuestion::SECTIONS, 'default' => 'grammaire', 'col' => 'col-md-6'],
            ['name' => 'difficulty', 'label' => 'Difficulté', 'type' => 'select', 'required' => true, 'options' => \App\Models\TestQuestion::DIFFICULTIES, 'default' => 1, 'col' => 'col-md-6'],
            ['name' => 'question', 'label' => 'Question (ou sujet oral)', 'type' => 'textarea', 'required' => true, 'max' => 2000, 'placeholder' => 'She ___ to school every day.'],
            ['name' => 'choices_text', 'label' => 'Réponses (QCM)', 'type' => 'textarea', 'max' => 2000, 'placeholder' => "go\n*goes\ngoing\ngone", 'help' => 'Une réponse par ligne ; mettez une étoile * devant la bonne. Laissez vide pour un sujet d\'expression orale.'],
            ['name' => 'is_active', 'label' => 'Active (peut être tirée au sort)', 'type' => 'switch', 'default' => true],
        ],
        'columns' => [
            ['label' => 'QUESTION', 'value' => fn ($q) => '<div class="fw-semibold">'.e(\Illuminate\Support\Str::limit($q->question, 90)).'</div><div class="small text-muted">'.e(collect($q->choices)->map(fn ($c, $i) => ($i === $q->answer ? '✔ ' : '').$c)->implode(' · ')).'</div>'],
            ['label' => 'PARTIE', 'value' => fn ($q) => '<span class="badge '.($q->isSpeaking() ? 'bg-warning-subtle' : 'bg-light text-dark').'">'.e(\App\Models\TestQuestion::SECTIONS[$q->section] ?? $q->section).'</span>'],
            ['label' => 'DIFFICULTÉ', 'value' => fn ($q) => e(\App\Models\TestQuestion::DIFFICULTIES[$q->difficulty] ?? '')],
        ],
    ])

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-cog me-2'></i>RÉGLAGES DU TEST EN LIGNE</h6></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('questions.settings') }}" class="row g-2 align-items-end">
                        @csrf @method('PUT')
                        <div class="col-4"><label class="form-label small fw-semibold" for="o-count">Questions QCM</label><input type="number" class="form-control" id="o-count" name="count" min="5" max="60" value="{{ $settings->get('test.online_count') }}" required></div>
                        <div class="col-4"><label class="form-label small fw-semibold" for="o-speak">Sujets oraux</label><input type="number" class="form-control" id="o-speak" name="speaking" min="0" max="5" value="{{ $settings->get('test.online_speaking') }}" required></div>
                        <div class="col-4"><label class="form-label small fw-semibold" for="o-min">Durée (min)</label><input type="number" class="form-control" id="o-min" name="minutes" min="5" max="120" value="{{ $settings->get('test.online_minutes') }}" required></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button> <span class="small text-muted">Score minimal de chaque niveau : Formations → Niveaux.</span></div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bxs-file-doc me-2'></i>IMPORTER UN QUESTIONNAIRE WORD</h6></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('questions.word') }}" enctype="multipart/form-data" class="row g-2">
                        @csrf
                        <div class="col-12"><input type="file" class="form-control" name="document" accept=".docx" required aria-label="Questionnaire Word (.docx)"></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-import me-1'></i>Importer</button></div>
                    </form>
                    <p class="small text-muted mt-2 mb-1">Présentation attendue (une question par bloc) :</p>
                    <pre class="small bg-light p-2 rounded mb-1">1. She ___ to school every day.
A) go
*B) goes
C) going

Speaking: Introduce yourself in one minute.</pre>
                    <p class="small text-muted mb-0">« * » devant la bonne réponse ; « Speaking: » pour un sujet oral. Un PDF ne s'importe pas : déposez-le comme questionnaire à télécharger (fiche du test).</p>
                </div>
            </div>
        </div>
    </div>
@endsection
