<div class="max-w-xl mx-auto p-6 bg-white/80 dark:bg-zinc-900/80 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 backdrop-blur-xl animate-fade-in">
    <h2 class="text-3xl font-extrabold mb-6 text-blue-700 dark:text-blue-300 tracking-tight flex items-center gap-2">
        <img src="{{ asset('images/png/nina.png') }}" alt="PDFina Logo" class="w-10 h-8 aspect-[468/391] dark:drop-shadow-[0_0_2px_white]" />
        Unir archivos PDF
    </h2>
    <form wire:submit.prevent="unirPdfs" class="space-y-6">
        <div class="mb-4 p-3 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-200 rounded shadow text-sm">
            Debes seleccionar <b>al menos 2 archivos PDF</b> para poder unirlos.
        </div>
        <input type="file" wire:model="pdfs" name="pdfs[]" multiple accept="application/pdf" class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 dark:file:bg-blue-900 dark:file:text-blue-200 hover:file:bg-blue-200 dark:hover:file:bg-blue-800 transition" />
        @if($pdfs)
            <div class="mt-4">
                <label class="block text-sm font-medium mb-1">Orden de los archivos seleccionados:</label>
                <ul class="space-y-2">
                    @foreach($pdfs as $i => $pdf)
                        <li class="flex items-center gap-2 bg-blue-50 dark:bg-zinc-800/60 rounded px-3 py-2">
                            <span class="flex-1 truncate">{{ is_object($pdf) ? $pdf->getClientOriginalName() : $pdf }}</span>
                            <button type="button" wire:click="moverArriba({{ $i }})" @if($i === 0) disabled @endif class="px-2 py-1 rounded bg-blue-200 dark:bg-blue-700 text-blue-800 dark:text-blue-100 font-bold disabled:opacity-40">↑</button>
                            <button type="button" wire:click="moverAbajo({{ $i }})" @if($i === count($pdfs)-1) disabled @endif class="px-2 py-1 rounded bg-blue-200 dark:bg-blue-700 text-blue-800 dark:text-blue-100 font-bold disabled:opacity-40">↓</button>
                            <button type="button" wire:click="quitarArchivo({{ $i }})" class="px-2 py-1 rounded bg-red-200 dark:bg-red-700 text-red-800 dark:text-red-100 font-bold">✕</button>
                        </li>
                    @endforeach
                </ul>
                <div class="text-xs text-gray-500 mt-2">Puedes cambiar el orden antes de unir los PDFs.</div>
            </div>
        @endif
        @error('pdfs')
            <div class="text-red-500 text-sm animate-shake">{{ $message }}</div>
        @enderror
        <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-500 to-blue-700 dark:from-blue-800 dark:to-blue-600 text-white font-bold rounded-xl shadow-lg hover:scale-105 hover:from-blue-600 hover:to-blue-800 transition-all duration-300">Unir PDFs</button>
    </form>

    @if($pdfPath)
        <div class="mt-8 text-center">
            @if(!$pdfEnviado)
                <button wire:click="enviarAlEscritorio" class="inline-block px-6 py-3 bg-gradient-to-r from-green-500 to-green-700 dark:from-green-800 dark:to-green-600 text-white font-bold rounded-xl shadow-lg hover:from-green-600 hover:to-green-800 transition-all duration-300">Enviar al escritorio</button>
            @else
                <div class="inline-flex items-center gap-2 px-6 py-3 bg-green-100 dark:bg-green-900/60 text-green-800 dark:text-green-200 font-bold rounded-xl shadow-lg border border-green-300 dark:border-green-700 justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500 dark:text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    ¡PDF enviado al escritorio!
                </div>
            @endif
        </div>
    @endif

    @php if(!isset($error)) { $error = null; } @endphp
</div>
