<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permissions::KULLANICI_YONET);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:190'],
            'role' => ['required', Rule::in([RoleSeeder::SUPER_ADMIN, RoleSeeder::STAFF])],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'ad soyad', 'email' => 'e-posta', 'password' => 'şifre', 'role' => 'rol'];
    }
}
