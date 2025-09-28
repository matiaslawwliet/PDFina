<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class ComprimirPdf extends Component
{
    use WithFileUploads;

    public $pdf;
    public ?string $pdfPath = null;
    public $calidad = '80'; // valor por defecto
    public $nuevoPdfGenerado = false;
    public $pdfEnviado = false;

    protected $rules = [
        'pdf' => 'required|file|mimes:pdf',
        'calidad' => 'nullable|in:60,80',
    ];

    public function updatedPdf()
    {
        $this->validateOnly('pdf');
        $this->nuevoPdfGenerado = false;
        $this->pdfPath = null;
        $this->pdfEnviado = false;
    }

    public function comprimirPdf()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        // Guardar PDF temporal en pdfina/temp
        $pdfPath = $this->pdf->store('pdfina/temp', 'public');
        $input = storage_path('app/public/' . $pdfPath);
        $output = storage_path('app/public/pdfina/comprimido_' . time() . '.pdf');
        // Ghostscript multiplataforma para comprimir
        $isWindows = stripos(PHP_OS, 'WIN') === 0;
        $gs = $isWindows ? 'gswin64c' : 'gs';
        // Determinar parámetros de Ghostscript según calidad seleccionada
        $cal = (string) $this->calidad;
        $common = "-sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH";
        $extra = '';
        if ($cal === '60') {
            // Modo screen: baja resolución, alta compresión
            $extra = "-dPDFSETTINGS=/screen -dDownsampleColorImages=true -dDownsampleGrayImages=true -dDownsampleMonoImages=true -dColorImageResolution=72 -dGrayImageResolution=72 -dMonoImageResolution=72 -dAutoFilterColorImages=true -dAutoFilterGrayImages=true -dAutoFilterMonoImages=true -dJPEGQ=40";
        } elseif ($cal === '80') {
            // Modo ebook: equilibrio entre calidad y tamaño
            $extra = "-dPDFSETTINGS=/ebook -dDownsampleColorImages=true -dDownsampleGrayImages=true -dDownsampleMonoImages=true -dColorImageResolution=150 -dGrayImageResolution=150 -dMonoImageResolution=150 -dAutoFilterColorImages=true -dAutoFilterGrayImages=true -dAutoFilterMonoImages=true -dJPEGQ=75";
    }

        $cmd = "$gs $common $extra -sOutputFile=".escapeshellarg($output)." ".escapeshellarg($input);
        @shell_exec($cmd);
        @unlink($input);
        if (file_exists($output)) {
            $this->pdfPath = '/storage/pdfina/' . basename($output);
            $this->nuevoPdfGenerado = true;
            $this->pdfEnviado = false;
        } else {
            $this->addError('pdf', 'No se pudo comprimir el PDF.');
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
        return view('livewire.comprimir-pdf');
    }
}
