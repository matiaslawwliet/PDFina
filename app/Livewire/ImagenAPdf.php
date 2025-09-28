<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ImagenAPdf extends Component
{
    use WithFileUploads;

    public array $imagenes = [];
    public ?string $pdfPath = null;
    public $nuevoPdfGenerado = false;
    public $pdfEnviado = false;

    protected $rules = [
        'imagenes' => 'required|array|min:1',
        'imagenes.*' => 'image',
    ];

    public function updatedImagenes()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        $this->pdfPath = null;
        $this->pdfEnviado = false;
    }

    public function generarPdf()
    {
        $this->validate();
        if (empty($this->imagenes)) {
            $this->addError('imagenes', 'Debes seleccionar al menos una imagen.');
            return;
        }
        $dataUris = [];
        foreach ($this->imagenes as $imagen) {
            $contenido = file_get_contents($imagen->getRealPath());
            $mime = $imagen->getMimeType();
            $base64 = base64_encode($contenido);
            $dataUris[] = "data:{$mime};base64,{$base64}";
        }
        $html = '<html><head><meta charset="utf-8"><style>.img-page { page-break-after: always; text-align: center; } img { max-width: 100%; max-height: 100vh; display: block; margin: auto; }</style></head><body>';
        foreach ($dataUris as $i => $uri) {
            $last = $i === count($dataUris) - 1;
            $html .= '<div class="img-page"' . ($last ? ' style="page-break-after: auto;"' : '') . '>' . '<img src="' . $uri . '" alt="Imagen"/>' . '</div>';
        }
        $html .= '</body></html>';
        $pdf = Pdf::loadHTML($html);
        $filename = 'pdfina/imagenes_' . time() . '.pdf';
        Storage::disk('public')->put($filename, $pdf->output());
        $this->pdfPath = '/storage/' . $filename;
        $this->nuevoPdfGenerado = true;
        $this->pdfEnviado = false;
    }

    public function enviarAlEscritorio()
    {
        if (!$this->pdfPath) return;
        $nombre = basename($this->pdfPath);
        $contenido = Storage::disk('public')->get(str_replace('/storage/', '', $this->pdfPath));
        Storage::disk('desktop')->put($nombre, $contenido);
        $this->pdfEnviado = true;
    }

    public function render()
    {
        return view('livewire.imagen-a-pdf');
    }
}
