<div class="{{ $sesion ? 'w-full h-full max-w-6xl overflow-y-auto' : 'max-w-xl' }} mx-auto p-6 bg-white/80 dark:bg-zinc-900/80 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 backdrop-blur-xl animate-fade-in transition-all duration-300">
    <h2 class="text-3xl font-extrabold mb-6 text-blue-700 dark:text-blue-300 tracking-tight flex items-center gap-2">
        <img src="{{ asset('images/png/nina.png') }}" alt="PDFina Logo" class="w-10 h-8 aspect-[468/391] dark:drop-shadow-[0_0_2px_white]" />
        Editar PDF
    </h2>

    @if(!$sesion)
        <input type="file" wire:model="pdf" accept="application/pdf" class="block w-full min-h-11 py-1 leading-7 cursor-pointer text-sm text-gray-700 dark:text-gray-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:cursor-pointer file:bg-blue-100 file:text-blue-700 dark:file:bg-blue-900 dark:file:text-blue-200 hover:file:bg-blue-200 dark:hover:file:bg-blue-800 transition" />
        @error('pdf')
            <div class="mt-3 text-red-500 text-sm animate-shake">{{ $message }}</div>
        @enderror
        <div wire:loading wire:target="pdf" class="mt-4 text-blue-600 dark:text-blue-300 text-sm font-semibold">Analizando el PDF...</div>
    @else
        @php
            $pagina = $paginas[$paginaActual - 1] ?? null;
            $factor = $zoom / 100;
        @endphp

        <div x-data="{ resaltar: true }">
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <div class="flex items-center gap-2">
                <button type="button" wire:click="irAPagina({{ $paginaActual - 1 }})" @disabled($paginaActual <= 1) class="px-3 py-1.5 rounded-lg bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-100 font-semibold disabled:opacity-40 transition">←</button>
                <span class="text-sm text-zinc-700 dark:text-zinc-200 font-semibold">Página {{ $paginaActual }} / {{ count($paginas) }}</span>
                <button type="button" wire:click="irAPagina({{ $paginaActual + 1 }})" @disabled($paginaActual >= count($paginas)) class="px-3 py-1.5 rounded-lg bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-100 font-semibold disabled:opacity-40 transition">→</button>
            </div>

            <select wire:model.live="zoom" class="rounded-lg border-2 border-blue-200 dark:border-blue-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-gray-100 px-3 py-1.5 text-sm">
                <option value="75">75%</option>
                <option value="100">100%</option>
                <option value="125">125%</option>
                <option value="150">150%</option>
                <option value="200">200%</option>
            </select>

            <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200 select-none cursor-pointer">
                <input type="checkbox" x-model="resaltar" class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500" />
                Resaltar zonas editables
            </label>

            <div class="flex-1"></div>

            <button type="button" wire:click="cargarOtroPdf" class="px-4 py-2 rounded-xl bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-100 font-semibold transition">Cargar otro PDF</button>
            <button type="button" wire:click="generarPdf" wire:loading.attr="disabled" wire:target="generarPdf" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-700 dark:from-blue-800 dark:to-blue-600 text-white font-bold rounded-xl shadow-lg hover:from-blue-600 hover:to-blue-800 transition-all duration-300 disabled:opacity-60">
                <span wire:loading.remove wire:target="generarPdf">Generar PDF</span>
                <span wire:loading wire:target="generarPdf">Generando...</span>
            </button>
        </div>

        <div class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">
            Haz clic sobre cualquier texto para editarlo. Pasa el ratón sobre una imagen para eliminarla o reemplazarla.
        </div>

        @if($pagina)
            <div class="overflow-auto border border-zinc-200 dark:border-zinc-700 rounded-xl bg-zinc-100 dark:bg-zinc-800 p-4">
                <div class="relative mx-auto shadow-lg bg-white" wire:key="pagina-{{ $paginaActual }}" style="width: {{ $pagina['width'] * $factor }}px; height: {{ $pagina['height'] * $factor }}px;">
                    <img src="{{ route('editar.pdf.pagina', ['sesion' => $sesion, 'pagina' => $paginaActual]) }}" alt="Página {{ $paginaActual }}" class="absolute inset-0 w-full h-full select-none pointer-events-none" draggable="false" />

                    @foreach($pagina['images'] as $imagen)
                        @php
                            $idImagen = $imagen['id'];
                            [$ix0, $iy0, $ix1, $iy1] = $imagen['bbox'];
                            $eliminada = ! empty($imagenesEliminadas[$idImagen]);
                            $reemplazada = ! empty($imagenesNuevas[$idImagen]);
                        @endphp
                        <div class="absolute group" wire:key="img-{{ $idImagen }}"
                             style="left: {{ $ix0 * $factor }}px; top: {{ $iy0 * $factor }}px; width: {{ ($ix1 - $ix0) * $factor }}px; height: {{ ($iy1 - $iy0) * $factor }}px;">
                            <div class="w-full h-full rounded-sm ring-1 ring-transparent group-hover:ring-2 group-hover:ring-fuchsia-500 transition
                                        {{ $eliminada ? 'bg-white ring-2 ring-red-500' : '' }}
                                        {{ $reemplazada && ! $eliminada ? 'ring-2 ring-emerald-500' : '' }}"
                                 :class="resaltar && !@js($eliminada) && !@js($reemplazada) ? 'ring-1 ring-fuchsia-400/50' : ''"></div>

                            <div class="absolute -top-3 right-0 z-20 hidden group-hover:flex gap-1">
                                <button type="button" wire:click="toggleEliminarImagen('{{ $idImagen }}')" class="px-2 py-0.5 text-[11px] font-bold rounded-md shadow {{ $eliminada ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' }}">
                                    {{ $eliminada ? 'Restaurar' : 'Eliminar' }}
                                </button>
                                @if(! $eliminada)
                                    <label class="px-2 py-0.5 text-[11px] font-bold rounded-md shadow bg-fuchsia-600 text-white cursor-pointer">
                                        Reemplazar
                                        <input type="file" class="hidden" accept="image/png,image/jpeg" wire:model="imagenesNuevas.{{ $idImagen }}" />
                                    </label>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @foreach($pagina['texts'] as $texto)
                        @php
                            $idTexto = $texto['id'];
                            $valor = $ediciones[$idTexto] ?? $texto['text'];
                            $modificado = $valor !== $texto['text'];
                            [$tx0, $ty0, $tx1, $ty1] = $texto['bbox'];
                            $alineacion = $texto['align'] ?? 'left';
                            $multilinea = $texto['multiline'] ?? false;
                            [$cx0, $cx1] = $texto['column'] ?? [$tx0, $tx1];
                            $interlineado = $texto['leading'] ?? ($texto['size'] * 1.2);
                            $usaColumna = $multilinea || in_array($alineacion, ['center', 'right'], true);
                            $izquierda = ($usaColumna ? $cx0 : $tx0) * $factor;
                            $ancho = max(($usaColumna ? $cx1 - $cx0 : $tx1 - $tx0) * $factor, 12);
                            $desfase = max(($interlineado - $texto['size']) / 2, 0);
                            $arriba = ($multilinea ? $ty0 - $desfase : $ty0) * $factor;
                            $alto = max(($ty1 - $ty0 + ($multilinea ? $desfase * 2 : 0)) * $factor, 10);
                            $clases = 'absolute border-0 p-0 m-0 rounded-[2px] outline-none caret-blue-600 cursor-text
                                       hover:ring-2 hover:ring-blue-400/60 focus:ring-2 focus:ring-blue-500
                                       focus:[color:var(--pdfina-color)] focus:[background:var(--pdfina-bg)] '
                                       . ($modificado ? '[color:var(--pdfina-color)] [background:var(--pdfina-bg)]' : 'text-transparent bg-transparent');
                            $estilo = "--pdfina-color: {$texto['color']}; --pdfina-bg: {$texto['bg']};
                                       left: {$izquierda}px; top: {$arriba}px; width: {$ancho}px; height: {$alto}px;
                                       font-size: " . ($texto['size'] * $factor) . "px; font-family: {$texto['family']};
                                       font-weight: " . ($texto['bold'] ? '700' : '400') . "; font-style: " . ($texto['italic'] ? 'italic' : 'normal') . ";
                                       text-align: {$alineacion};";
                        @endphp
                        @if($multilinea)
                            <textarea wire:key="txt-{{ $idTexto }}" wire:model.blur="ediciones.{{ $idTexto }}"
                                      title="{{ $texto['text'] }}" rows="1"
                                      :class="resaltar ? 'ring-1 ring-blue-400/40 bg-blue-500/5' : ''"
                                      class="{{ $clases }} resize-none overflow-hidden"
                                      style="{{ $estilo }} line-height: {{ $interlineado * $factor }}px;">{{ $valor }}</textarea>
                        @else
                            <input type="text" wire:key="txt-{{ $idTexto }}" wire:model.blur="ediciones.{{ $idTexto }}"
                                   title="{{ $texto['text'] }}"
                                   :class="resaltar ? 'ring-1 ring-blue-400/40 bg-blue-500/5' : ''"
                                   class="{{ $clases }} leading-none"
                                   style="{{ $estilo }}" />
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if($nuevoPdfGenerado && !$error)
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

        @error('imagenesNuevas.*')
            <div class="mt-4 text-red-500 text-center animate-shake">{{ $message }}</div>
        @enderror

        @if($error)
            <div class="mt-4 text-red-500 text-center animate-shake">{{ $error }}</div>
        @endif
        </div>
    @endif
</div>
