<div class="max-w-xl mx-auto p-6 bg-white/80 dark:bg-zinc-900/80 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 backdrop-blur-xl animate-fade-in">
    <h2 class="text-3xl font-extrabold mb-6 text-blue-700 dark:text-blue-300 tracking-tight flex items-center gap-2">
        <img src="{{ asset('images/png/nina.png') }}" alt="PDFina Logo" class="w-10 h-8 aspect-[468/391] dark:drop-shadow-[0_0_2px_white]" />
        PDF escaneado a PDF seleccionable
    </h2>

    <div class="mb-4 p-3 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-200 rounded shadow text-sm">
        <b>Requisito:</b> Debes tener instalado <b>Tesseract OCR</b> en tu equipo y añadido al <b>PATH</b> del sistema para convertir PDFs escaneados en PDFs seleccionables.<br>
        Descárgalo desde <a href="https://github.com/UB-Mannheim/tesseract/wiki" class="underline text-blue-700 dark:text-blue-300" target="_blank">github.com/UB-Mannheim/tesseract/wiki</a><br>
        Durante la instalación marca los idiomas que vayas a usar (por ejemplo <b>Spanish</b>) en <i>Additional language data</i>.
    </div>

    <form wire:submit.prevent="reconocerTexto" class="space-y-6">
        <div>
            <label class="block text-sm font-medium mb-1">Archivo PDF escaneado</label>
            <input type="file" wire:model="pdf" accept="application/pdf" class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 dark:file:bg-blue-900 dark:file:text-blue-200 hover:file:bg-blue-200 dark:hover:file:bg-blue-800 transition" />
            @error('pdf')
                <div class="text-red-500 text-sm animate-shake">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Idioma del documento</label>
            <select wire:model.live="idioma" class="w-full rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                @foreach($this->idiomasDisponibles() as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
            @error('idioma')
                <div class="text-red-500 text-sm animate-shake">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Modo de conversión</label>
            <select wire:model.live="modo" class="w-full rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                <option value="fiel">Fiel al original (imagen + texto buscable)</option>
                <option value="texto">Reconstruido (letras reales e imágenes)</option>
            </select>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                @if($modo === 'fiel')
                    Mantiene el escaneo intacto y añade una capa de texto invisible: el PDF se ve igual que el original y además se puede buscar, seleccionar y copiar.
                @else
                    Reescribe cada página con texto real y recorta del escaneo las fotos, firmas y sellos para colocarlos en su sitio. Ideal si necesitas un PDF ligero y editable.
                @endif
            </p>
            @error('modo')
                <div class="text-red-500 text-sm animate-shake">{{ $message }}</div>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <input type="checkbox" wire:model.live="forzar" class="rounded border-zinc-300 dark:border-zinc-700" />
            Aplicar OCR también a las páginas que ya tienen texto
        </label>

        <button type="submit" wire:loading.attr="disabled" class="w-full py-3 bg-gradient-to-r from-blue-500 to-blue-700 dark:from-blue-800 dark:to-blue-600 text-white font-bold rounded-xl shadow-lg hover:scale-105 hover:from-blue-600 hover:to-blue-800 transition-all duration-300 disabled:opacity-60 disabled:hover:scale-100">
            <span wire:loading.remove wire:target="reconocerTexto">Aplicar OCR</span>
            <span wire:loading wire:target="reconocerTexto">Aplicando OCR...</span>
        </button>
    </form>

    <div wire:loading wire:target="reconocerTexto" class="mt-4 text-center text-sm text-zinc-500 dark:text-zinc-400">
        El OCR puede tardar varios minutos según el número de páginas. El resultado será un PDF seleccionable.
    </div>

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
