<!doctype html>
<html lang="{{ $encomenda->prefersEnglish() ? 'en' : 'pt' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $encomenda->prefersEnglish() ? 'Invoice' : 'Fatura' }} #{{ $encomenda->moloniDocumentId() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0A0F1A] text-slate-100 antialiased">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center px-4 py-10">
        <section class="w-full rounded border border-white/10 bg-[#151E2D] p-6">
            <p class="text-sm font-semibold text-[#22C55E]">Horta da Maria</p>
            <h1 class="mt-3 text-2xl font-semibold text-white">
                {{ $encomenda->prefersEnglish() ? 'Invoice available' : 'Fatura disponivel' }}
            </h1>
            <p class="mt-4 text-sm leading-6 text-slate-300">
                @if($encomenda->prefersEnglish())
                    The invoice #{{ $encomenda->moloniDocumentId() }} exists, but the public PDF access is not configured yet in the delivery app.
                @else
                    A fatura #{{ $encomenda->moloniDocumentId() }} existe, mas o acesso publico ao PDF ainda nao esta configurado na app de entregas.
                @endif
            </p>
            <p class="mt-4 text-sm leading-6 text-slate-400">
                @if($encomenda->prefersEnglish())
                    Please contact Horta da Maria and we will send you the document directly.
                @else
                    Contacte a Horta da Maria e enviamos-lhe o documento diretamente.
                @endif
            </p>
        </section>
    </main>
</body>
</html>
