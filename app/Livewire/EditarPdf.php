<?php

namespace App\Livewire;

use App\Services\MicroservicioRunner;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditarPdf extends Component
{
    use WithFileUploads;

    public $pdf;

    public ?string $sesion = null;

    public array $paginas = [];

    public array $ediciones = [];

    public array $imagenesEliminadas = [];

    public array $imagenesNuevas = [];

    public int $paginaActual = 1;

    public int $zoom = 100;

    public ?string $pdfPath = null;

    public bool $nuevoPdfGenerado = false;

    public bool $pdfEnviado = false;

    public ?string $error = null;

    protected $rules = [
        'pdf' => 'required|file|mimes:pdf|max:51200',
    ];

    public function updatedPdf(): void
    {
        $this->validateOnly('pdf');
        $this->reiniciarEstado();

        $sesion = bin2hex(random_bytes(8));
        $relativo = 'pdfina/temp/editar_'.$sesion;
        $this->pdf->storeAs($relativo, 'original.pdf', 'public');
        $directorio = storage_path('app/public/'.$relativo);

        [$codigo, $salida] = $this->ejecutarMicroservicio([
            'extract',
            $directorio.'/original.pdf',
            $directorio,
        ]);

        $manifiesto = $directorio.'/manifest.json';
        if ($codigo !== 0 || ! file_exists($manifiesto)) {
            $this->addError('pdf', 'No se pudo analizar el PDF. '.implode(' ', $salida));

            return;
        }

        $datos = json_decode((string) file_get_contents($manifiesto), true);
        if (! is_array($datos) || empty($datos['pages'])) {
            $this->addError('pdf', 'El PDF no contiene páginas que se puedan editar.');

            return;
        }

        $this->sesion = $sesion;
        $this->paginas = $datos['pages'];
        $this->paginaActual = 1;

        foreach ($this->paginas as $pagina) {
            foreach ($pagina['texts'] as $texto) {
                $this->ediciones[$texto['id']] = $texto['text'];
            }
        }
    }

    public function irAPagina(int $pagina): void
    {
        $this->paginaActual = max(1, min($pagina, count($this->paginas)));
    }

    public function toggleEliminarImagen(string $id): void
    {
        if (! $this->esIdValido($id)) {
            return;
        }

        if (! empty($this->imagenesEliminadas[$id])) {
            unset($this->imagenesEliminadas[$id]);

            return;
        }

        $this->imagenesEliminadas[$id] = true;
        unset($this->imagenesNuevas[$id]);
    }

    public function restaurarTexto(string $id): void
    {
        foreach ($this->paginas as $pagina) {
            foreach ($pagina['texts'] as $texto) {
                if ($texto['id'] === $id) {
                    $this->ediciones[$id] = $texto['text'];

                    return;
                }
            }
        }
    }

    public function generarPdf(): void
    {
        $this->error = null;
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        $this->pdfPath = null;

        $directorio = $this->rutaSesion();
        if (! $directorio) {
            $this->error = 'La sesión de edición no es válida. Vuelve a subir el PDF.';

            return;
        }

        $this->validate([
            'imagenesNuevas.*' => 'nullable|image|mimes:png,jpg,jpeg|max:10240',
        ]);

        $reemplazos = [];
        foreach ($this->imagenesNuevas as $id => $archivo) {
            if (! $archivo || ! $this->esIdValido($id) || ! empty($this->imagenesEliminadas[$id])) {
                continue;
            }
            $extension = $archivo->getClientOriginalExtension() ?: 'png';
            $destino = $directorio.'/reemplazo_'.$id.'.'.strtolower(preg_replace('/[^a-zA-Z]/', '', $extension));
            copy($archivo->getRealPath(), $destino);
            $reemplazos[$id] = $destino;
        }

        $textos = [];
        foreach ($this->ediciones as $id => $valor) {
            if ($this->esIdValido((string) $id)) {
                $textos[$id] = (string) $valor;
            }
        }

        $eliminadas = [];
        foreach ($this->imagenesEliminadas as $id => $activo) {
            if ($activo && $this->esIdValido((string) $id)) {
                $eliminadas[] = $id;
            }
        }

        $rutaEdiciones = $directorio.'/ediciones.json';
        file_put_contents($rutaEdiciones, json_encode([
            'texts' => (object) $textos,
            'deleteImages' => $eliminadas,
            'replaceImages' => (object) $reemplazos,
        ], JSON_UNESCAPED_UNICODE));

        $nombreSalida = 'editado_'.time().'.pdf';
        $rutaSalida = storage_path('app/public/pdfina/'.$nombreSalida);

        [$codigo, $salida] = $this->ejecutarMicroservicio([
            'apply',
            $directorio.'/original.pdf',
            $rutaEdiciones,
            $rutaSalida,
        ]);

        if ($codigo !== 0 || ! file_exists($rutaSalida)) {
            $this->error = 'No se pudo generar el PDF editado. '.implode(' ', $salida);

            return;
        }

        $this->pdfPath = '/storage/pdfina/'.$nombreSalida;
        $this->nuevoPdfGenerado = true;
    }

    public function enviarAlEscritorio(): void
    {
        if (! $this->pdfPath) {
            return;
        }
        $nombre = basename($this->pdfPath);
        $contenido = Storage::disk('public')->get('pdfina/'.$nombre);
        Storage::disk('desktop')->put($nombre, $contenido);
        $this->pdfEnviado = true;
    }

    public function cargarOtroPdf(): void
    {
        $this->reset(['pdf', 'sesion', 'paginas', 'ediciones', 'imagenesEliminadas', 'imagenesNuevas', 'paginaActual', 'pdfPath', 'nuevoPdfGenerado', 'pdfEnviado', 'error']);
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.editar-pdf');
    }

    protected function reiniciarEstado(): void
    {
        $this->sesion = null;
        $this->paginas = [];
        $this->ediciones = [];
        $this->imagenesEliminadas = [];
        $this->imagenesNuevas = [];
        $this->paginaActual = 1;
        $this->pdfPath = null;
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        $this->error = null;
    }

    protected function esIdValido(string $id): bool
    {
        return (bool) preg_match('/^p\d+-(l\d+|i\d+-\d+)$/', $id);
    }

    protected function rutaSesion(): ?string
    {
        if (! $this->sesion || ! preg_match('/^[a-f0-9]{16}$/', $this->sesion)) {
            return null;
        }

        $directorio = storage_path('app/public/pdfina/temp/editar_'.$this->sesion);

        return is_dir($directorio) && file_exists($directorio.'/original.pdf') ? $directorio : null;
    }

    /**
     * @param  array<int, string>  $argumentos
     * @return array{0: int, 1: array<int, string>}
     */
    protected function ejecutarMicroservicio(array $argumentos): array
    {
        return app(MicroservicioRunner::class)->ejecutar('editar-pdf', 'mservice-epdf', $argumentos);
    }
}
