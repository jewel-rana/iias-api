<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function catalog()
    {
        $groups = [];
        foreach (Permissions::groups() as $label => $keys) {
            $groups[] = [
                'label' => $label,
                'permissions' => array_map(fn (string $key) => [
                    'key' => $key,
                    'label' => $key,
                ], $keys),
            ];
        }

        return response()->json(['data' => $groups]);
    }

    public function index(Request $request)
    {
        $query = Role::query()->orderBy('sort_order')->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Role $r) => $this->transform($r)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:80', 'unique:roles,code'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(array_merge(Permissions::keys(), [Permissions::ALL]))],
        ]);

        $code = $data['code'] ?? Str::slug($data['name'], '_');
        if ($code === '' || in_array($code, ['admin', 'collector', 'member'], true)) {
            $code = 'role_'.Str::lower(Str::random(6));
        }

        $role = Role::query()->create([
            'name' => $data['name'],
            'code' => $code,
            'is_system' => false,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => ((int) Role::query()->max('sort_order')) + 1,
            'permissions' => array_values(array_unique($data['permissions'] ?? [])),
        ]);

        return response()->json($this->transform($role), 201);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(array_merge(Permissions::keys(), [Permissions::ALL]))],
        ]);

        $permissions = array_values(array_unique($data['permissions'] ?? $role->permissions ?? []));
        if ($role->code === 'admin') {
            $permissions = Permissions::admin();
        }

        $role->update([
            'name' => $data['name'],
            'is_active' => $role->is_system ? true : $data['is_active'],
            'permissions' => $permissions,
        ]);

        return response()->json($this->transform($role->fresh()));
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }

        if ($role->users()->exists() || $role->members()->exists()) {
            $role->update(['is_active' => false]);

            return response()->json([
                'message' => 'Role is in use, marked inactive.',
                'data' => $this->transform($role->fresh()),
            ]);
        }

        $role->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function transform(Role $r): array
    {
        return [
            'id' => (string) $r->id,
            'name' => $r->name,
            'code' => $r->code,
            'is_system' => $r->is_system,
            'is_active' => $r->is_active,
            'sort_order' => $r->sort_order,
            'permissions' => $r->permissions ?? [],
        ];
    }
}
