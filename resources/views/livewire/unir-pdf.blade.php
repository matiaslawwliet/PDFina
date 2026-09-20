<div class="max-w-xl mx-auto p-6 bg-white/80 dark:bg-zinc-900/80 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 backdrop-blur-xl animate-fade-in">
    <h2 class="text-3xl font-extrabold mb-6 text-blue-700 dark:text-blue-300 tracking-tight flex items-center gap-2">
        <img src="{{ asset('images/png/nina.png') }}" alt="PDFina Logo" class="w-10 h-8 aspect-[468/391] dark:drop-shadow-[0_0_2px_white]" />
        Unir archivos PDF
    </h2>
    <form wire:submit.prevent="unirPdfs" class="space-y-6">
        <div class="mb-4 p-3 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-200 rounded shadow text-sm">
            Comprime todos tus PDFs en un <b>.zip</b> y súbelo aquí. El sistema los extraerá para que puedas ordenarlos antes de unirlos.
        </div>

        <div
            x-data="{ progress: 0, uploading: false }"
            x-on:livewire-upload-start="uploading = true; progress = 0"
            x-on:livewire-upload-finish="uploading = false"
            x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
        >
            <input
                type="file"
                wire:model="zip"
                accept=".zip,application/zip"
                class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 dark:file:bg-blue-900 dark:file:text-blue-200 hover:file:bg-blue-200 dark:hover:file:bg-blue-800 transition"
            />

            <div x-show="uploading" x-cloak class="mt-3">
                <div class="h-2 bg-blue-100 dark:bg-blue-900 rounded-full overflow-hidden">
                    <div
                        class="h-2 bg-blue-500 dark:bg-blue-400 rounded-full transition-all duration-300"
                        :style="'width: ' + progress + '%'"
                    ></div>
                </div>
                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                    Subiendo ZIP... <span x-text="progress + '%'"></span>
                </p>
            </div>

            <div wire:loading wire:target="zip" class="mt-2 text-sm text-blue-600 dark:text-blue-400 flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                Extrayendo archivos del ZIP...
            </div>
        </div>

        @error('zip')
            <div class="text-red-500 text-sm animate-shake">{{ $message }}</div>
        @enderror

        @if($extraido)
            @if(count($archivosExtraidos) === 0)
                <div class="p-3 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded text-sm">
                    El ZIP no contiene archivos PDF válidos.
                </div>
            @else
                <div class="mt-4" x-data="{
                    draggingIndex: null,
                    dropTargetIndex: null,
                    orderedFiles: @js(array_map(fn ($archivo) => $archivo['ruta'], $archivosExtraidos)),
                    beginDrag(index) {
                        this.draggingIndex = index;
                        this.dropTargetIndex = index;
                    },
                    endDrag() {
                        this.draggingIndex = null;
                        this.dropTargetIndex = null;
                    },
                    moveItem(from, to) {
                        if (from === null || to === null || from === to) {
                            this.endDrag();
                            return;
                        }

                        const items = [...this.orderedFiles];
                        const [moved] = items.splice(from, 1);
                        items.splice(to, 0, moved);
                        this.orderedFiles = items;
                        this.$wire.reordenarArchivosPorIndices(from, to);
                        this.endDrag();
                    }
                }">
                    <label class="block text-sm font-medium mb-1">
                        Archivos extraídos ({{ count($archivosExtraidos) }}): arrastra desde la manija para cambiar el orden antes de unir.
                    </label>
                    <ul class="space-y-2 max-h-72 overflow-y-auto pr-1">
                        <template x-for="(ruta, index) in orderedFiles" :key="ruta">
                            <li
                                class="flex items-center gap-2 rounded px-3 py-2 transition"
                                :class="dropTargetIndex === index ? 'bg-blue-100 dark:bg-blue-900/40 ring-1 ring-blue-400' : 'bg-blue-50 dark:bg-zinc-800/60'"
                                @dragover.prevent="dropTargetIndex = index"
                                @dragleave.prevent="if (dropTargetIndex === index) dropTargetIndex = null"
                                @drop.prevent="moveItem(draggingIndex, index)"
                            >
                                <span
                                    class="flex h-8 w-8 cursor-grab items-center justify-center rounded bg-zinc-200/80 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300"
                                    draggable="true"
                                    @dragstart="beginDrag(index)"
                                    @dragend="endDrag()"
                                    title="Arrastra para mover"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4v2M8 10v2M8 16v2M16 4v2M16 10v2M16 16v2" />
                                    </svg>
                                </span>
                                <span class="text-xs text-zinc-400 dark:text-zinc-500 w-5 shrink-0" x-text="index + 1"></span>
                                <span class="flex-1 truncate text-sm" x-text="orderedFiles[index] ? orderedFiles[index].split('/').pop() : ''"></span>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="px-2 py-1 rounded bg-blue-200 dark:bg-blue-700 text-blue-800 dark:text-blue-100 font-bold disabled:opacity-40" @click.stop="$wire.moverArriba(index)">↑</button>
                                    <button type="button" class="px-2 py-1 rounded bg-blue-200 dark:bg-blue-700 text-blue-800 dark:text-blue-100 font-bold disabled:opacity-40" @click.stop="$wire.moverAbajo(index)">↓</button>
                                </div>
                                <button type="button" class="px-2 py-1 rounded bg-red-200 dark:bg-red-700 text-red-800 dark:text-red-100 font-bold" @click.stop="$wire.quitarArchivo(index)">✕</button>
                            </li>
                        </template>
                    </ul>
                    <div class="text-xs text-gray-500 dark:text-zinc-400 mt-2">Usa la manija para arrastrar y suelta entre los archivos para cambiar el orden.</div>
                </div>
            @endif
        @endif

        @if($extraido && count($archivosExtraidos) >= 2)
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="unirPdfs"
                class="w-full py-3 flex items-center justify-center gap-2 bg-gradient-to-r from-blue-500 to-blue-700 dark:from-blue-800 dark:to-blue-600 text-white font-bold rounded-xl shadow-lg hover:scale-105 hover:from-blue-600 hover:to-blue-800 transition-all duration-300 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100"
            >
                <svg wire:loading wire:target="unirPdfs" class="animate-spin h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <span wire:loading.remove wire:target="unirPdfs">Unir PDFs</span>
                <span wire:loading wire:target="unirPdfs">Uniendo PDFs...</span>
            </button>
        @endif
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
</div>
