@extends('layouts.site')

{{-- Traduction professionnelle (cahier §3.9) : présentation (Pages et paramètres → Traduction) et demande : langues,
     type de document, délai, coordonnées, document joint (privé). Envoyée comme une demande (site.devis.store). --}}
@section('whatsapp_message', 'Bonjour, je souhaite faire traduire un document.')

@section('content')
    @include('site.partials.page-hero', ['title' => 'Traduction professionnelle', 'crumb' => 'Traduction', 'intro' => 'Envoyez votre document : nous vous adressons une estimation du prix et du délai, sans engagement.'])

    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5">
                    <div class="prose">{!! \App\Support\Markdown::toHtml($site->get('traduction.presentation')) !!}</div>
                </div>
                <div class="col-lg-7">
                    @component('site.demandes._form', ['action' => route('site.devis.store'), 'submit' => 'Demander une estimation', 'multipart' => true])
                        <input type="hidden" name="need_type" value="traduction">
                        @php $languages = ['Français' => 'Français', 'Anglais' => 'Anglais', 'Arabe' => 'Arabe', 'Espagnol' => 'Espagnol', 'Autre' => 'Autre (préciser)']; @endphp
                        <div class="col-md-6">@include('site.demandes._field', ['name' => 'source_language', 'label' => 'Langue du document', 'type' => 'select', 'required' => true, 'options' => $languages, 'value' => 'Français'])</div>
                        <div class="col-md-6">@include('site.demandes._field', ['name' => 'target_language', 'label' => 'Traduire en', 'type' => 'select', 'required' => true, 'options' => $languages, 'value' => 'Anglais'])</div>
                        <div class="col-md-6">@include('site.demandes._field', ['name' => 'document_type', 'label' => 'Type de document', 'max' => 120, 'placeholder' => 'Diplôme, relevé de notes, contrat…'])</div>
                        <div class="col-md-6">@include('site.demandes._field', ['name' => 'deadline', 'label' => 'Délai souhaité', 'max' => 100, 'placeholder' => 'Ex. d\'ici 5 jours'])</div>
                        <div class="col-12">
                            <label for="f-attachment" class="form-label">Document à traduire</label>
                            <input type="file" class="form-control @error('attachment') is-invalid @enderror" id="f-attachment" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.jpg,.jpeg,.png" aria-describedby="f-attachment-help">
                            @error('attachment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text" id="f-attachment-help">PDF, Word, Excel, OpenDocument, photo (JPEG, PNG), {{ \App\Http\Requests\Site\DemandeRequest::ATTACHMENT_MAX_KB / 1024 }} Mo au plus. Conservé de façon privée.</div>
                        </div>
                        @include('site.demandes._identity')
                        <div class="col-12">@include('site.demandes._field', ['name' => 'message', 'label' => 'Précisions (facultatif)', 'type' => 'textarea', 'max' => 5000, 'rows' => 3, 'placeholder' => 'Nombre de pages, usage de la traduction (ambassade, université…), certification…'])</div>
                    @endcomponent
                </div>
            </div>
        </div>
    </section>
@endsection
