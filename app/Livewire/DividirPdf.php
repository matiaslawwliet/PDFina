<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class DividirPdf extends Component
{
    use WithFileUploads;

    public $pdf;
    public $pagina_inicio;
    public $pagina_fin;
    public ?string $pdfPath = null;
    public $nuevoPdfGenerado = false;
    public $pdfEnviado = false;

    protected $rules = [
        'pdf' => 'required|file|mimes:pdf',
        'pagina_inicio' => 'required|integer|min:1',
        'pagina_fin' => 'required|integer|min:1',
    ];

    public function updatedPdf()
    {
        $this->validateOnly('pdf');
        $this->nuevoPdfGenerado = false;
        $this->pdfPath = null;
        $this->pdfEnviado = false;
    }

    public function dividirPdf()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        if ($this->pagina_inicio > $this->pagina_fin) {
            $this->addError('pagina_inicio', 'La página de inicio debe ser menor o igual a la de fin.');
            return;
        }
        // Guardar PDF temporal en pdfina/temp
        $pdfPath = $this->pdf->store('pdfina/temp', 'public');
        $input = storage_path('app/public/' . $pdfPath);
        $output = storage_path('app/public/pdfina/dividido_' . time() . '.pdf');
        // Ghostscript para extraer rango
        $gs = 'gswin64c.exe';
        $cmd = "$gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -dFirstPage={$this->pagina_inicio} -dLastPage={$this->pagina_fin} -sOutputFile=".escapeshellarg($output)." ".escapeshellarg($input);
        @shell_exec($cmd);
        @unlink($input);
        if (file_exists($output)) {
            $this->pdfPath = '/storage/pdfina/' . basename($output);
            $this->nuevoPdfGenerado = true;
            $this->pdfEnviado = false;
        } else {
            $this->addError('pdf', 'No se pudo dividir el PDF.');
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
        return view('livewire.dividir-pdf');
    }
}
