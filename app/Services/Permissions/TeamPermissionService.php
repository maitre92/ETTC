<?php

namespace App\Services\Permissions;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\ModuleRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permissions données par l'administrateur à ses subalternes (demande du centre, 2026-11-23). Garde-fous :
 *  - cible : un compte d'un rôle qu'il gère (config/role_hierarchy.php), jamais lui-même, un pair ou le superadmin ;
 *  - droits : seulement ceux qu'il a LUI-MÊME, jamais un droit réservé au superadmin, ni un droit déjà donné par le
 *    rôle de la personne (inutile) ;
 *  - audit de chaque changement (avant / après). Le superadmin garde ses écrans (Rôles, Permissions, Attribution).
 */
class TeamPermissionService
{
    public function __construct(private readonly ModuleRegistry $registry, private readonly ActivityLogger $logger) {}

    /** Comptes que l'acteur peut régler. @return Collection<int, User> */
    public function team(User $actor): Collection
    {
        $roles = $actor->isSuperadmin()
            ? array_map(fn (RoleName $r) => $r->value, array_filter(RoleName::cases(), fn (RoleName $r) => $r !== RoleName::Superadmin))
            : array_map(fn (RoleName $r) => $r->value, $actor->roleName()?->manages() ?? []);

        return User::with(['roles', 'permissions'])->whereKeyNot($actor->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))->orderBy('name')->get();
    }

    public function canManage(User $actor, User $target): bool
    {
        return $this->team($actor)->contains('id', $target->id);
    }

    /**
     * Droits que l'acteur peut donner à la cible, groupés par module : [module => [ [name, label, inherited, granted] ]].
     *
     * @return array<string, list<array{name: string, label: string, inherited: bool, granted: bool}>>
     */
    public function grid(User $actor, User $target): array
    {
        $catalog = $this->registry->permissions();
        $modules = $this->registry->modules();
        $inherited = $target->roleName() ? $this->registry->roleDefaults($target->roleName()) : [];
        $direct = $target->permissions->pluck('name')->all();

        $grid = [];
        foreach ($catalog as $name => $definition) {
            if ($definition['reserved'] || ! $actor->can($name) || $name === 'equipe.permissions') {
                continue;
            }
            $grid[$modules[$definition['module']]['label'] ?? $definition['module']][] = [
                'name' => $name, 'label' => $definition['label'],
                'inherited' => in_array($name, $inherited, true), 'granted' => in_array($name, $direct, true),
            ];
        }
        ksort($grid);

        return $grid;
    }

    /** @param  list<string>  $names  droits cochés (en plus de ceux du rôle) */
    public function sync(User $actor, User $target, array $names): array
    {
        if (! $this->canManage($actor, $target)) {
            throw ValidationException::withMessages(['permissions' => 'Vous ne pouvez régler que les comptes de votre équipe.']);
        }
        $allowed = collect($this->grid($actor, $target))->flatten(1)->reject(fn ($p) => $p['inherited'])->pluck('name')->all();
        $invalid = array_values(array_diff($names, $allowed));
        if ($invalid !== []) {
            throw ValidationException::withMessages(['permissions' => 'Permission(s) que vous ne pouvez pas donner : '.implode(', ', $invalid).'.']);
        }
        // Les droits que l'acteur ne voit pas (donnés par le superadmin) sont conservés.
        $kept = $target->permissions->pluck('name')->reject(fn ($n) => in_array($n, $allowed, true))->values()->all();
        $final = array_values(array_unique([...$kept, ...$names]));

        return DB::transaction(function () use ($actor, $target, $final) {
            $before = $target->permissions()->pluck('name')->all();
            $target->syncPermissions(Permission::whereIn('name', $final)->get());
            $after = $target->permissions()->pluck('name')->all();
            $added = array_values(array_diff($after, $before));
            $removed = array_values(array_diff($before, $after));
            if ($added !== [] || $removed !== []) {
                $this->logger->log('equipe.permissions_updated', 'equipe', "Permissions de {$target->name} : ".($added ? '+ '.implode(', ', $added) : '').($added && $removed ? ' ; ' : '').($removed ? '− '.implode(', ', $removed) : ''), $target,
                    ['permissions' => $before], ['permissions' => $after], $actor);
            }

            return ['added' => $added, 'removed' => $removed];
        });
    }
}
