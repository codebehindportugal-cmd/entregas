<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class EquipaController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $role = $request->string('role')->toString();
        $ativo = $request->string('ativo')->toString();

        return view('equipa.index', [
            'q' => $q,
            'role' => $role,
            'ativo' => $ativo,
            'users' => User::query()
                ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                }))
                ->when(in_array($role, ['admin', 'colaborador'], true), fn ($query) => $query->where('role', $role))
                ->when($ativo === '1', fn ($query) => $query->where('ativo', true))
                ->when($ativo === '0', fn ($query) => $query->where('ativo', false))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('equipa.create', ['user' => new User(['cor' => '#22C55E', 'role' => 'colaborador', 'ativo' => true])]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            ...Arr::except($request->validated(), ['ativo']),
            'ativo' => $request->boolean('ativo'),
            'password' => Hash::make($request->validated('password')),
        ]);

        return redirect()->route('equipa.index')->with('status', 'Colaborador criado com sucesso.');
    }

    public function edit(User $equipa): View
    {
        return view('equipa.edit', ['user' => $equipa]);
    }

    public function update(UpdateUserRequest $request, User $equipa): RedirectResponse
    {
        $data = Arr::except($request->validated(), ['ativo']);
        $data['ativo'] = $request->boolean('ativo');

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $equipa->update($data);

        return redirect()->route('equipa.index')->with('status', 'Colaborador atualizado.');
    }

    public function destroy(User $equipa): RedirectResponse
    {
        $equipa->update(['ativo' => false]);

        return redirect()->route('equipa.index')->with('status', 'Colaborador desativado.');
    }
}
