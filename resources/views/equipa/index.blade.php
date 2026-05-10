<x-layouts.app title="Equipa">
    <x-page-title title="Equipa" subtitle="Admins e colaboradores">
        <a href="{{ route('equipa.create') }}" class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Novo colaborador</a>
    </x-page-title>
    <form method="get" class="mb-6 grid gap-3 rounded border border-white/10 bg-[#151E2D] p-4 lg:grid-cols-[2fr_1fr_1fr_auto]">
        <label class="text-sm text-slate-300">Pesquisar
            <input name="q" value="{{ $q }}" placeholder="Nome ou email..." class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
        </label>
        <label class="text-sm text-slate-300">Role
            <select name="role" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="">Todos</option>
                <option value="admin" @selected($role === 'admin')>Admin</option>
                <option value="colaborador" @selected($role === 'colaborador')>Colaborador</option>
            </select>
        </label>
        <label class="text-sm text-slate-300">Estado
            <select name="ativo" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                <option value="">Todos</option>
                <option value="1" @selected($ativo === '1')>Ativos</option>
                <option value="0" @selected($ativo === '0')>Inativos</option>
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Filtrar</button>
            <a href="{{ route('equipa.index') }}" class="rounded bg-white/10 px-4 py-2 text-sm text-slate-200">Limpar</a>
        </div>
    </form>
    <div class="overflow-x-auto rounded border border-white/10 bg-[#151E2D]">
        <table class="w-full text-left text-sm">
            <thead class="bg-white/5 text-slate-400"><tr><th class="p-3">Nome</th><th class="p-3">Role</th><th class="p-3">Estado</th><th class="p-3"></th></tr></thead>
            <tbody>
                @forelse($users as $user)
                    <tr class="border-t border-white/10">
                        <td class="p-3 text-white"><span class="mr-2 inline-block h-3 w-3 rounded-full" style="background: {{ $user->cor }}"></span>{{ $user->name }} <span class="text-slate-400">{{ $user->email }}</span></td>
                        <td class="p-3">{{ $user->role }}</td>
                        <td class="p-3">{{ $user->ativo ? 'Ativo' : 'Inativo' }}</td>
                        <td class="p-3 text-right"><a class="text-[#3B82F6]" href="{{ route('equipa.edit', $user) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-slate-400">Sem utilizadores para os filtros escolhidos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
