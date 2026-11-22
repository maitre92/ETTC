<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Middleware\TrackLastSeen;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Qui est en ligne (demande du centre, 2026-11-22) : équipe et apprenants, en ligne (activité depuis moins de 5 min, pas
 * déconnecté) ou non, date et heure de connexion, dernière activité. L'administrateur voit ses subalternes et lui-même ;
 * le superadmin voit toute l'équipe. Lecture seule ; la page se met à jour toute seule chaque minute.
 */
class ConnexionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $team = User::with('roles')->active()
            ->when(! $user->isSuperadmin(), fn ($q) => $q->where(fn ($q) => $q->whereKey($user->id)
                ->orWhereHas('roles', fn ($r) => $r->whereIn('name', array_map(fn (RoleName $role) => $role->value, $user->roleName()?->manages() ?? [])))))
            ->get()->sortByDesc(fn (User $u) => [self::isOnline($u), $u->last_seen_at?->timestamp ?? 0])->values();

        $search = trim((string) $request->query('q'));
        $clients = Client::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('phone', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('email', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_seen_at')->orderByDesc('last_login_at')->limit(100)->get();

        return view('connexions.index', [
            'team' => $team,
            'clients' => $clients,
            'search' => $search,
            'onlineTeam' => $team->filter(fn ($u) => self::isOnline($u))->count(),
            'onlineClients' => Client::where('last_seen_at', '>=', now()->subMinutes(TrackLastSeen::ONLINE_MINUTES))
                ->where(fn ($q) => $q->whereNull('last_logout_at')->orWhereColumn('last_logout_at', '<', 'last_seen_at'))->count(),
        ]);
    }

    /** En ligne : activité depuis moins de 5 minutes et pas déconnecté depuis. */
    public static function isOnline(User|Client $person): bool
    {
        return $person->last_seen_at !== null
            && $person->last_seen_at->gte(now()->subMinutes(TrackLastSeen::ONLINE_MINUTES))
            && ($person->last_logout_at === null || $person->last_logout_at->lt($person->last_seen_at));
    }
}
