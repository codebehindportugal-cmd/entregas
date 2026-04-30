<x-layouts.app title="Login">
    <div class="mx-auto mt-10 max-w-md rounded border border-white/10 bg-[#111B17]/95 p-6 shadow-2xl shadow-black/30">
        <div class="mb-6 flex items-center gap-4">
            <span class="flex h-16 w-16 items-center justify-center rounded bg-white p-2">
                <img src="{{ asset('images/horta-da-maria-logo.png') }}" alt="Horta da Maria" class="h-full w-full object-contain">
            </span>
            <div>
                <h1 class="text-2xl font-semibold text-white">Entrar</h1>
                <p class="text-sm text-emerald-200/80">Horta da Maria - entregas</p>
            </div>
        </div>
        <form method="post" action="{{ route('login.store') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block text-sm text-slate-300">Email
                <input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded border border-white/10 bg-[#07110D] px-3 py-2 text-white outline-none focus:border-[#22C55E]">
            </label>
            <label class="block text-sm text-slate-300">Password
                <input name="password" type="password" required class="mt-1 w-full rounded border border-white/10 bg-[#07110D] px-3 py-2 text-white outline-none focus:border-[#22C55E]">
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input name="remember" type="checkbox" class="rounded border-white/10 bg-[#0A0F1A]"> Lembrar
            </label>
            <button class="w-full rounded bg-[#22C55E] px-4 py-2.5 font-semibold text-[#07110D] shadow-sm shadow-emerald-950/30 hover:bg-emerald-300" type="submit">Entrar</button>
        </form>
    </div>
</x-layouts.app>
