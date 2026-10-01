<x-layouts.app title="O meu perfil">
    <x-page-title title="O meu perfil" :subtitle="$user->email" />

    @if(session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">{{ session('status') }}</div>
    @endif

    <x-card title="Chave da API" subtitle="Serve para o chat registar faturas e encomendas (/api/v1) e ler os mapas (/api/claude). Gerar uma nova desliga a anterior.">
        @if(! $podeTerChave)
            <p class="text-sm text-slate-600">Só os administradores têm chave da API.</p>
        @else
            <p class="text-sm text-slate-700">
                @if($chave)
                    Chave activa desde {{ $chave->created_at->format('d/m/Y H:i') }} · último uso:
                    {{ $chave->ultimo_uso_em ? $chave->ultimo_uso_em->format('d/m/Y H:i') : 'nunca usada' }}
                @else
                    Ainda não tens chave.
                @endif
            </p>

            @if($chaveNova)
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-900">Copia-a agora — não volta a ser mostrada.</p>
                    <code id="chave-api-nova" class="mt-2 block break-all rounded bg-white p-2 text-sm" style="user-select:all">{{ $chaveNova }}</code>
                    <button type="button" class="mt-2 text-sm font-semibold text-amber-900 underline"
                            onclick="navigator.clipboard.writeText(document.getElementById('chave-api-nova').textContent.trim()).then(() => { this.textContent = 'Copiada'; })">
                        Copiar
                    </button>
                </div>
            @endif

            <form method="post" action="{{ route('perfil.chave-api.store') }}" class="mt-5 space-y-3">
                @csrf
                <label class="block text-sm text-slate-700">Password actual
                    <input type="password" name="password" autocomplete="current-password" required
                           class="mt-1 w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2">
                </label>
                @error('password')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                <x-btn type="submit">{{ $chave ? 'Gerar nova chave da API' : 'Gerar chave da API' }}</x-btn>
            </form>

            @if($chave)
                <form method="post" action="{{ route('perfil.chave-api.destroy') }}" class="mt-3"
                      onsubmit="return confirm('Revogar a chave? O chat deixa de conseguir usar a API até gerares outra.');">
                    @csrf
                    @method('DELETE')
                    <x-btn type="submit" variant="danger">Revogar chave</x-btn>
                </form>
            @endif
        @endif
    </x-card>
</x-layouts.app>
