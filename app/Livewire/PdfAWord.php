<?php

namespace App\Livewire;

use App\Services\MicroservicioRunner;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class PdfAWord extends Component
{
    use WithFileUploads;

    public $pdf;

    public ?string $wordPath = null;

    public $error = null;

    public $nuevoPdfGenerado = false;

    public $pdfEnviado = false;

    protected $rules = [
        'pdf' => 'required|file|mimes:pdf',
    ];

    public function updatedPdf()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        $this->wordPath = null;
        $this->pdfEnviado = false;
    }

    public function convertir()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        $this->error = null;
        $this->pdfEnviado = false;
        if (! $this->pdf) {
            $this->addError('pdf', 'Debes seleccionar un archivo PDF.');

            return;
        }
        $pdfPath = $this->pdf->store('pdfina/temp', 'public');
        $fullPdfPath = storage_path('app/public/'.$pdfPath);
        $wordFileName = 'pdf_'.time().'.docx';
        $wordFullPath = storage_path('app/public/pdfina/'.$wordFileName);
        $pyScript = base_path('microservicios/pdf-a-word/dist/mservice-paw.exe');
        if (! file_exists($pyScript)) {
            $this->addError('pdf', 'No se encontró el ejecutable de conversión PDF a Word.');

            return;
        }
        [$returnVar, $output] = app(MicroservicioRunner::class)
            ->ejecutar('pdf-a-word', 'mservice-paw', [$fullPdfPath, $wordFullPath]);
        if ($returnVar === 0 && file_exists($wordFullPath)) {
            $this->wordPath = '/storage/pdfina/'.$wordFileName;
            $this->nuevoPdfGenerado = true;
            $this->pdfEnviado = false;
        } else {
            $this->addError('pdf', 'Error al convertir el PDF a Word. '.implode("\n", $output));
            $this->wordPath = null;
        }
    }

    public function enviarAlEscritorio()
    {
        if (! $this->wordPath) {
            return;
        }
        $nombre = basename($this->wordPath);
        $contenido = Storage::disk('public')->get('pdfina/'.$nombre);
        Storage::disk('desktop')->put($nombre, $contenido);
        $this->pdfEnviado = true;
    }

    public function render()
    {
        return view('livewire.pdf-a-word');
    }
}
