<?php

namespace App\Livewire;

use App\Services\MicroservicioRunner;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class WordAPdf extends Component
{
    use WithFileUploads;

    public $word;

    public ?string $pdfPath = null;

    public $nuevoPdfGenerado = false;

    public $pdfEnviado = false;

    protected $rules = [
        'word' => 'required|file|mimes:doc,docx',
    ];

    public function updatedWord()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        $this->pdfPath = null;
        $this->pdfEnviado = false;
    }

    public function convertir()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        $this->pdfPath = null;
        if (! $this->word) {
            $this->addError('word', 'Debes seleccionar un archivo Word.');

            return;
        }

        $wordPath = $this->word->store('pdfina/temp', 'public');
        $fullWordPath = storage_path('app/public/'.$wordPath);
        $pdfFileName = 'word_'.time().'.pdf';
        $pdfFullPath = storage_path('app/public/pdfina/'.$pdfFileName);
        $exePath = base_path('microservicios/word-a-pdf/dist/mservice-wap.exe');
        if (! file_exists($exePath)) {
            $this->addError('word', 'No se encontró el ejecutable de conversión Word a PDF.');

            return;
        }
        [$returnVar, $output] = app(MicroservicioRunner::class)
            ->ejecutar('word-a-pdf', 'mservice-wap', [$fullWordPath, $pdfFullPath]);
        if ($returnVar === 0 && file_exists($pdfFullPath)) {
            $this->pdfPath = '/storage/pdfina/'.$pdfFileName;
            $this->nuevoPdfGenerado = true;
            $this->pdfEnviado = false;
        } else {
            $this->addError('word', 'Error al convertir el Word a PDF. '.implode("\n", $output));
            $this->pdfPath = null;
        }
    }

    public function enviarAlEscritorio()
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
        return view('livewire.word-a-pdf');
    }
}
