<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Yönetici hesapları ve çalışan rolünün yetkileri.
 *
 * Yetkiler burada ekrandan değiştirilebilir: "çalışan iade ödemesi yapabilsin"
 * gibi bir karar kod değişikliği gerektirmez.
 */
class StaffController extends Controller
{
    public function index()
    {
        return view('admin.staff.index', [
            'staff' => User::where('role', 'admin')->with('roles')->orderBy('name')->get(),
            'roles' => [RoleSeeder::SUPER_ADMIN => 'Super Admin', RoleSeeder::STAFF => 'Çalışan'],
            'permissionGroups' => Permissions::grouped(),
            'staffPermissions' => Role::findByName(RoleSeeder::STAFF)->permissions->pluck('name')->all(),
        ]);
    }

    public function store(StoreStaffRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'admin',
            'is_active' => true,
        ]);

        $user->syncRoles([$data['role']]);

        return back()->with('success', "{$user->name} yönetici olarak eklendi.");
    }

    public function update(Request $request, User $staff)
    {
        abort_unless($staff->isAdmin(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::in([RoleSeeder::SUPER_ADMIN, RoleSeeder::STAFF])],
            'is_active' => ['required', 'boolean'],
        ], [], ['role' => 'rol', 'is_active' => 'durum']);

        $this->guardLastSuperAdmin($staff, $data);

        $staff->update(['is_active' => (bool) $data['is_active']]);
        $staff->syncRoles([$data['role']]);

        return back()->with('success', "{$staff->name} güncellendi.");
    }

    /** Çalışan rolünün yetkilerini topluca yazar. */
    public function updatePermissions(Request $request)
    {
        $this->authorize('sistem.kullanici');

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ]);

        Role::findByName(RoleSeeder::STAFF)->syncPermissions($data['permissions'] ?? []);

        return back()->with('success', 'Çalışan rolünün yetkileri güncellendi.');
    }

    /**
     * Son super admin'in yetkisi düşürülemez ya da pasife alınamaz; aksi halde
     * panele hiç kimse tam yetkiyle giremez ve kilit dışarıdan açılamaz.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardLastSuperAdmin(User $staff, array $data): void
    {
        if (! $staff->hasRole(RoleSeeder::SUPER_ADMIN)) {
            return;
        }

        $rolunuBirakiyor = $data['role'] !== RoleSeeder::SUPER_ADMIN;
        $pasifeAliniyor = ! (bool) $data['is_active'];

        if (! $rolunuBirakiyor && ! $pasifeAliniyor) {
            return;
        }

        $kalan = User::where('role', 'admin')
            ->where('is_active', true)
            ->where('id', '!=', $staff->id)
            ->whereHas('roles', fn ($q) => $q->where('name', RoleSeeder::SUPER_ADMIN))
            ->count();

        if ($kalan === 0) {
            throw ValidationException::withMessages([
                'role' => 'Sistemdeki son super admin bu şekilde bırakılamaz. Önce başka bir super admin tanımlayın.',
            ]);
        }
    }
}
