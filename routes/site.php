<?php

use App\Http\Controllers\Site\ActualiteController;
use App\Http\Controllers\Site\AppManifestController;
use App\Http\Controllers\Site\DemandeController;
use App\Http\Controllers\Site\FormationController;
use App\Http\Controllers\Site\InscriptionController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PaiementNotificationController;
use App\Http\Controllers\Site\SearchController;
use App\Http\Controllers\Site\SeoController;
use App\Http\Controllers\Site\ServiceController;
use App\Http\Controllers\Site\TemoignageController;
use App\Http\Controllers\Site\TestNiveauController;
use App\Http\Controllers\VideoUploadController;
use Illuminate\Support\Facades\Route;

// Site public (cahier §4) : sans authentification, routes nommées `site.*` (test d'architecture). Les formulaires
// sont limités en débit (throttle) et protégés contre le spam (cahier §10).
Route::name('site.')->group(function () {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/a-propos', [PageController::class, 'about'])->name('about');
    Route::get('/services', [PageController::class, 'services'])->name('services');
    // Catalogue des formations par niveau, formules et tarifs (cahier §3.2, §3.3).
    Route::get('/formations', [FormationController::class, 'index'])->name('formations.index');
    Route::get('/formations/{niveau:slug}', [FormationController::class, 'show'])->name('formations.show');
    // Inscription en ligne à une formation (cahier §3.5) : assistant en étapes, tarif figé à l'inscription.
    Route::get('/inscription', [InscriptionController::class, 'create'])->name('inscription.create');
    Route::post('/inscription', [InscriptionController::class, 'store'])->middleware('throttle:demandes')->name('inscription.store');
    // Test de niveau (cahier §3.4) : présentation et demande / réservation (anti-spam, débit limité).
    Route::get('/test-de-niveau', [TestNiveauController::class, 'show'])->name('tests.show');
    Route::post('/test-de-niveau', [TestNiveauController::class, 'store'])->middleware('throttle:demandes')->name('tests.store');
    // Témoignages (vidéo et écrit) ; envoi d'une vidéo par morceaux de 1 Mo (300 Mo au plus).
    Route::get('/temoignages', [TemoignageController::class, 'index'])->name('temoignages.index');
    Route::get('/temoignages/partager', [TemoignageController::class, 'create'])->name('temoignages.create');
    Route::post('/temoignages/partager', [TemoignageController::class, 'store'])->middleware('throttle:demandes')->name('temoignages.store');
    Route::post('/temoignages/video', VideoUploadController::class)->middleware('throttle:400,1')->name('temoignages.video');
    Route::get('/actualites', [ActualiteController::class, 'index'])->name('actualites.index');
    Route::get('/actualites/{article:slug}', [ActualiteController::class, 'show'])->name('actualites.show');

    // Demandes commerciales (cahier §6) : `demande_type` choisit les règles de DemandeRequest ; débit limité
    // (throttle:demandes, AppServiceProvider) et anti-spam (SpamGuard).
    Route::get('/contact', [DemandeController::class, 'contact'])->name('contact');
    Route::get('/devis', [DemandeController::class, 'devis'])->name('devis');
    // Services du centre (cahier §3.7, §3.9, §3.10) : pages dédiées, formulaires envoyés comme des demandes.
    Route::get('/traduction', [ServiceController::class, 'traduction'])->name('traduction');
    Route::get('/formation-personnalisee', [ServiceController::class, 'personnalisee'])->name('personnalisee');
    Route::get('/excursion-ghana', [ServiceController::class, 'excursion'])->name('excursion');
    Route::get('/proposition/{token}', [ServiceController::class, 'proposition'])->middleware('throttle:60,1')->name('proposition.show');
    Route::post('/proposition/{token}/accepter', [ServiceController::class, 'accept'])->middleware('throttle:20,1')->name('proposition.accept');
    Route::middleware('throttle:demandes')->group(function () {
        Route::post('/contact', [DemandeController::class, 'storeContact'])->defaults('demande_type', 'contact')->name('contact.store');
        Route::post('/devis', [DemandeController::class, 'storeDevis'])->defaults('demande_type', 'devis')->name('devis.store');
    });

    Route::get('/mentions-legales', [PageController::class, 'legal'])->name('legal');
    Route::get('/confidentialite', [PageController::class, 'privacy'])->name('privacy');

    // Recherche (page de résultats et suggestions en direct), référencement technique (cahier §11.1).
    Route::get('/recherche', [SearchController::class, 'index'])->name('search');
    Route::get('/recherche/suggestions', [SearchController::class, 'suggest'])->middleware('throttle:120,1')->name('search.suggest');
    Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
    Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
    // Applications installables (PWA) : site public et administration de l'équipe.
    Route::get('/manifest.webmanifest', [AppManifestController::class, 'site'])->name('manifest');
    Route::get('/manifest-equipe.webmanifest', [AppManifestController::class, 'team'])->name('manifest.team');
    // Paiement automatique (cahier §3.8 A) : notification serveur du prestataire (sans CSRF, simple signal) et page
    // « opérateur simulé » (développement seulement, adresses signées).
    Route::get('/recu/{number}/{key}', [PaiementNotificationController::class, 'verify'])->middleware('throttle:60,1')->name('recu.verify');
    Route::match(['get', 'post'], '/paiements/notification/{gateway}', [PaiementNotificationController::class, 'notify'])->middleware('throttle:120,1')->name('paiements.notify');
    Route::get('/paiements/simulation/{transaction}', [PaiementNotificationController::class, 'simulation'])->middleware('signed')->name('paiement-simule');
    Route::post('/paiements/simulation/{transaction}', [PaiementNotificationController::class, 'simulate'])->middleware(['signed', 'throttle:20,1'])->name('paiement-simule.decide');
});

// Adresse inconnue : redirection d'une ancienne adresse de contenu (301), sinon 404 du site.
Route::fallback([SeoController::class, 'fallback'])->name('site.fallback');
