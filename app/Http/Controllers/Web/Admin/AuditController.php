<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\ActivityChannel;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AuditFilterRequest;
use App\Models\ActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Journal d'audit en lecture seule (permission `audit.view` réservée au superadmin) et journal d'activité de l'équipe
 * (`activite.view`, administrateur : ses subalternes et lui-même). La consultation elle-même n'est
 * pas journalisée (les lectures ne le sont pas, CLAUDE.md §4). Le détail d'une ligne s'ouvre en modale.
 */
class AuditController extends Controller
{
    private const PER_PAGE = 25;

    public function index(AuditFilterRequest $request): View
    {
        return $this->render($request, ActivityLog::query(), 'audit.index', false);
    }

    /**
     * Journal d'activité de l'équipe (demande du centre, 2026-11-21) : l'administrateur voit les actions des rôles qu'il
     * gère (secrétaire, gestionnaire, formateur) et les siennes ; jamais celles du superadmin ni d'un autre
     * administrateur. Le superadmin y voit tout.
     */
    public function team(AuditFilterRequest $request): View
    {
        $user = $request->user();
        $query = ActivityLog::query();
        if (! $user->isSuperadmin()) {
            $roles = array_map(fn (RoleName $r) => $r->value, $user->roleName()?->manages() ?? []);
            $query->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('user_role', $roles));
        }

        return $this->render($request, $query, 'activite.index', true);
    }

    private function render(AuditFilterRequest $request, $base, string $route, bool $team): View
    {
        $filters = $request->validated();

        $logs = (clone $base)
            ->when($filters['author'] ?? null, fn ($query, $value) => $query->where('user_name', 'like', '%'.$this->escapeLike($value).'%'))
            ->when($filters['module'] ?? null, fn ($query, $value) => $query->where('module', $value))
            ->when($filters['action'] ?? null, fn ($query, $value) => $query->where('action', 'like', '%'.$this->escapeLike($value).'%'))
            ->when($filters['channel'] ?? null, fn ($query, $value) => $query->where('channel', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()))
            ->when(! $team && $request->boolean('superadmin'), fn ($query) => $query->where('user_role', RoleName::Superadmin->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'details' => $logs->getCollection()->mapWithKeys(fn (ActivityLog $log) => [$log->id => $this->detail($log)]),
            'modules' => (clone $base)->distinct()->orderBy('module')->pluck('module'),
            'channels' => collect(ActivityChannel::cases())->mapWithKeys(fn (ActivityChannel $channel) => [$channel->value => $this->channelLabel($channel)]),
            'filters' => $filters + ['superadmin' => $request->boolean('superadmin')],
            'route' => $route,
            'team' => $team,
        ]);
    }

    /**
     * Données de la modale de détail d'une ligne (affichées côté navigateur avec textContent : elles peuvent contenir
     * du texte saisi par un tiers, comme un numéro tenté à la connexion).
     *
     * @return array<string, mixed>
     */
    private function detail(ActivityLog $log): array
    {
        return [
            'date' => $log->created_at?->format('d/m/Y H:i:s'),
            'author' => $log->user_name ?? 'Non connecté',
            'role' => $this->roleLabel($log->user_role),
            'superadmin' => $log->isBySuperadmin(),
            'module' => $log->module,
            'action' => $log->action,
            'description' => $log->description,
            'subject' => $log->subject_label ? $log->subject_label.' ('.$log->subject_type.' #'.$log->subject_id.')' : null,
            'channel' => $this->channelLabel($log->channel),
            'ip' => $log->ip_address,
            'agent' => $log->user_agent,
            'old' => $log->old_values ?? [],
            'new' => $log->new_values ?? [],
        ];
    }

    private function roleLabel(?string $role): ?string
    {
        return $role === null ? null : (RoleName::tryFrom($role)?->label() ?? $role);
    }

    private function channelLabel(?ActivityChannel $channel): string
    {
        return match ($channel) {
            ActivityChannel::Web => 'Web',
            ActivityChannel::Console => 'Console',
            ActivityChannel::Api => 'API (logiciel de stock)',
            null => '—',
        };
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
