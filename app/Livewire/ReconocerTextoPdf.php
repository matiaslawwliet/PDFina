<?php

namespace App\Livewire;

use App\Services\MicroservicioRunner;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReconocerTextoPdf extends Component
{
    use WithFileUploads;

    public const TIMEOUT_OCR = 1800;

    public $pdf;

    public string $idioma = 'spa';

    public string $modo = 'fiel';

    public bool $forzar = false;

    public ?string $pdfPath = null;

    public bool $nuevoPdfGenerado = false;

    public bool $pdfEnviado = false;

    public ?string $error = null;

    protected $rules = [
        'pdf' => 'required|file|mimes:pdf',
        'idioma' => 'required|in:spa,eng,spa+eng,cat,glg,eus,fra,por,ita,deu',
        'modo' => 'required|in:fiel,texto',
        'forzar' => 'boolean',
    ];

    /**
     * @return array<string, string>
     */
    public function idiomasDisponibles(): array
    {
        return [
            'spa' => 'Español',
            'spa+eng' => 'Español + Inglés',
            'eng' => 'Inglés',
            'cat' => 'Catalán',
            'glg' => 'Gallego',
            'eus' => 'Euskera',
            'fra' => 'Francés',
            'por' => 'Portugués',
            'ita' => 'Italiano',
            'deu' => 'Alemán',
        ];
    }

    public function updatedPdf(): void
    {
        $this->validateOnly('pdf');
        $this->reiniciarEstado();
    }

    public function updatedModo(): void
    {
        $this->reiniciarEstado();
    }

    public function updatedIdioma(): void
    {
        $this->reiniciarEstado();
    }

    public function reconocerTexto(): void
    {
        $this->validate();
        $this->reiniciarEstado();

        $rutaSubida = $this->pdf->store('pdfina/temp', 'public');
        $entrada = storage_path('app/public/'.$rutaSubida);
        $nombreSalida = 'ocr_'.time().'.pdf';
        $salida = storage_path('app/public/pdfina/'.$nombreSalida);

        $argumentos = [
            $entrada,
            $salida,
            '--idioma', $this->idioma,
            '--modo', $this->modo,
        ];

        if ($this->forzar) {
            $argumentos[] = '--forzar';
        }

        [$codigo, $lineas] = app(MicroservicioRunner::class)
            ->ejecutar('pdf-ocr', 'mservice-pocr', $argumentos, self::TIMEOUT_OCR);

        @unlink($entrada);

        if ($codigo !== 0 || ! file_exists($salida)) {
            @unlink($salida);
            $this->error = $this->mensajeDeError($codigo, $lineas);

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

    public function render()
    {
        return view('livewire.reconocer-texto-pdf');
    }

    protected function reiniciarEstado(): void
    {
        $this->pdfPath = null;
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        $this->error = null;
    }

    /**
     * @param  array<int, string>  $lineas
     */
    protected function mensajeDeError(int $codigo, array $lineas): string
    {
        return match ($codigo) {
            2 => 'Falta la dependencia PyMuPDF del microservicio de OCR.',
            3 => 'El PDF está protegido con contraseña. Quítasela antes de aplicar el OCR.',
            4 => 'No se encontró Tesseract OCR. Instálalo y añádelo al PATH del sistema.',
            6 => 'El OCR ha tardado demasiado. Prueba con un PDF con menos páginas.',
            default => 'No se pudo convertir el PDF escaneado en un PDF seleccionable. '.implode(' ', $lineas),
        };
    }
}
