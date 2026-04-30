<div class="grid gap-4 lg:grid-cols-2">
    <label class="text-sm text-slate-300">Nome
        <input name="name" required value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Email
        <input name="email" type="email" required value="{{ old('email', $user->email) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Password
        <input name="password" type="password" {{ $user->exists ? '' : 'required' }} class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
    </label>
    <label class="text-sm text-slate-300">Role
        <select name="role" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            <option value="colaborador" @selected(old('role', $user->role) === 'colaborador')>Colaborador</option>
            <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
        </select>
    </label>
    <label class="text-sm text-slate-300">Cor
        <input name="cor" type="color" value="{{ old('cor', $user->cor ?? '#22C55E') }}" class="mt-1 h-11 w-full rounded border border-white/10 bg-[#0A0F1A] px-2 py-1">
    </label>
    <label class="flex items-end gap-2 text-sm text-slate-300">
        <input name="ativo" value="1" type="checkbox" @checked(old('ativo', $user->ativo ?? true))> Ativo
    </label>
</div>
