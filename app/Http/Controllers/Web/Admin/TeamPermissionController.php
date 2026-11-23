<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Permissions\TeamPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Permissions de l'équipe : l'administrateur coche les droits supplémentaires de chaque subalterne. */
class TeamPermissionController extends Controller
{
    public function __construct(private readonly TeamPermissionService $service) {}

    public function index(Request $request): View
    {
        $team = $this->service->team($request->user());
        $selected = $request->filled('user') ? $team->firstWhere('id', (int) $request->query('user')) : $team->first();

        return view('equipe.permissions', [
            'team' => $team,
            'selected' => $selected,
            'grid' => $selected ? $this->service->grid($request->user(), $selected) : [],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->service->canManage($request->user(), $user), 403);
        $names = $request->validate(['permissions' => ['array'], 'permissions.*' => ['string']])['permissions'] ?? [];
        $result = $this->service->sync($request->user(), $user, $names);
        $message = $result['added'] || $result['removed'] ? "Permissions de {$user->name} mises à jour." : 'Aucun changement.';

        return redirect()->route('equipe.permissions', ['user' => $user->id])->with('status', $message);
    }
}
