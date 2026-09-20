<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class EliminarPasswordPdf extends Component
{
    use WithFileUploads;

    public $pdf;
    public ?string $pdfPath = null;
    public $nuevoPdfGenerado = false;
    public $error = null;
    public $pdfEnviado = false;

    protected $rules = [
        'pdf' => 'required|file|mimes:pdf',
    ];

    public function updatedPdf()
    {
        $this->validateOnly('pdf');
        $path = $this->pdf->store('pdfina/temp', 'public');
        $this->pdfPath = '/storage/' . $path;
        $this->nuevoPdfGenerado = false;
        $this->error = null;
        $this->pdfEnviado = false;
    }

    public function eliminarPassword()
    {
        $this->validateOnly('pdf');
        $this->error = null;
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        if (!$this->pdfPath) {
            $this->addError('pdf', 'Debes subir un PDF.');
            return;
        }
        $inputPath = storage_path('app/public/' . str_replace('/storage/', '', $this->pdfPath));
        $outputFile = 'pdf_sin_restricciones_' . time() . '.pdf';
        $outputPath = storage_path('app/public/pdfina/' . $outputFile);
        $isWindows = stripos(PHP_OS, 'WIN') === 0;
        $gs = $isWindows ? 'gswin64c' : 'gs';
        $cmd = "$gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=".escapeshellarg($outputPath)." ".escapeshellarg($inputPath);
        @shell_exec($cmd);
        if (file_exists($outputPath)) {
            $this->pdfPath = '/storage/pdfina/' . $outputFile;
            $this->nuevoPdfGenerado = true;
            $this->pdfEnviado = false;
        } else {
            $this->addError('pdf', 'No se pudo eliminar las restricciones. Si el PDF requiere contraseña para abrirse, no es posible quitarla.');
        }
    }

    public function enviarAlEscritorio()
    {
        if (!$this->pdfPath) return;
        $nombre = basename($this->pdfPath);
        $contenido = Storage::disk('public')->get('pdfina/' . $nombre);
        Storage::disk('desktop')->put($nombre, $contenido);
        $this->pdfEnviado = true;
    }

    public function render()
    {
        return view('livewire.eliminar-password-pdf');
    }
}
