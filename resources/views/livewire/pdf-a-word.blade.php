<div class="max-w-xl mx-auto p-6 bg-white/80 dark:bg-zinc-900/80 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 backdrop-blur-xl animate-fade-in">
    <h2 class="text-3xl font-extrabold mb-6 text-blue-700 dark:text-blue-300 tracking-tight flex items-center gap-2">
        <img src="{{ asset('images/png/nina.png') }}" alt="PDFina Logo" class="w-10 h-8 aspect-[468/391] dark:drop-shadow-[0_0_2px_white]" />
        Convertir PDF a Word
    </h2>
    <form wire:submit.prevent="convertir" class="space-y-6">
        <label class="block text-sm font-medium mb-1">Archivo PDF</label>
        <input type="file" wire:model="pdf" accept="application/pdf" class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 dark:file:bg-blue-900 dark:file:text-blue-200 hover:file:bg-blue-200 dark:hover:file:bg-blue-800 transition" />
        @error('pdf')
            <div class="text-red-500 text-sm animate-shake">{{ $message }}</div>
        @enderror
        <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-500 to-blue-700 dark:from-blue-800 dark:to-blue-600 text-white font-bold rounded-xl shadow-lg hover:scale-105 hover:from-blue-600 hover:to-blue-800 transition-all duration-300">Convertir a Word</button>
    </form>

    @php if(!isset($error)) { $error = null; } @endphp

    @if($nuevoPdfGenerado && !$error)
        <div class="mt-8 text-center">
            @if(!$pdfEnviado)
                <button wire:click="enviarAlEscritorio" class="inline-block px-6 py-3 bg-gradient-to-r from-green-500 to-green-700 dark:from-green-800 dark:to-green-600 text-white font-bold rounded-xl shadow-lg hover:from-green-600 hover:to-green-800 transition-all duration-300">Enviar al escritorio</button>
            @else
                <div class="inline-flex items-center gap-2 px-6 py-3 bg-green-100 dark:bg-green-900/60 text-green-800 dark:text-green-200 font-bold rounded-xl shadow-lg border border-green-300 dark:border-green-700 justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500 dark:text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    ¡Archivo enviado al escritorio!
                </div>
            @endif
        </div>
    @endif

    @if($error)
        <div class="mt-4 text-red-500 text-center animate-shake">{{ $error }}</div>
    @endif
</div>
