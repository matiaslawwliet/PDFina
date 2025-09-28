<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl dark:from-zinc-900 dark:via-blue-950 dark:to-zinc-800 transition-colors duration-500">
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 shadow-xl backdrop-blur-xl flex items-center justify-center">
            @php
                $modulo = request()->get('modulo');
            @endphp
            @if($modulo === 'imagen')
                <livewire:imagen-a-pdf />
            @elseif($modulo === 'word')
                <livewire:word-a-pdf />
            @elseif($modulo === 'pdfaword')
                <livewire:pdf-a-word />
            @elseif($modulo === 'unir')
                <livewire:unir-pdf />
            @elseif($modulo === 'dividir')
                <livewire:dividir-pdf />
            @elseif($modulo === 'comprimir')
                <livewire:comprimir-pdf />
            @elseif($modulo === 'limpieza')
                <livewire:limpieza-archivos />
            @elseif($modulo === 'firmar')
                <livewire:firmar-pdf />
            @elseif($modulo === 'eliminarpaginas')
                <livewire:eliminar-paginas-pdf />
            @elseif($modulo === 'eliminarpassword')
                <livewire:eliminar-password-pdf />
            @else
                <div class="absolute inset-0 flex flex-col items-center justify-center text-neutral-400 text-xl animate-fade-in">
                    <img src="{{ asset('images/png/pdfina.png') }}" alt="PDFina Logo" class="w-16 h-24 mb-4 aspect-[468/391] dark:drop-shadow-[0_0_2px_white]" />
                    Selecciona un módulo para comenzar
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
