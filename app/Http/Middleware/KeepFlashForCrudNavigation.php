<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formulaire AJAX de l'administration (crud-ajax.js) qui mène à une AUTRE page (ex. fiche de l'inscription créée) :
 * le navigateur suit la redirection en arrière-plan, puis crud-ajax ouvre réellement la page. Sans cela, le message
 * (« Inscription enregistrée… », reçu à imprimer) serait consommé par le premier chargement et perdu au second.
 * crud-ajax.js envoie l'adresse de départ dans l'en-tête X-Crud-From.
 */
class KeepFlashForCrudNavigation
{
    public function handle(Request $request, Closure $next): Response
    {
        $from = $request->header('X-Crud-From');
        if ($from !== null && $request->isMethod('GET') && $request->hasSession() && '/'.ltrim($request->path(), '/') !== rtrim($from, '/') && '/'.$request->path() !== $from) {
            $request->session()->reflash();
        }

        return $next($request);
    }
}
