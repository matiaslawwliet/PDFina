<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class UnirPdf extends Component
{
    use WithFileUploads;

    public array $pdfs = [];
    public ?string $pdfPath = null;
    public $nuevoPdfGenerado = false;
    public $pdfEnviado = false;

    protected $rules = [
        'pdfs' => 'required|array|min:2',
        'pdfs.*' => 'file|mimes:pdf',
    ];

    public function updatedPdfs()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        $this->pdfPath = null;
        $this->pdfEnviado = false;
    }

    public function unirPdfs()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        if (count($this->pdfs) < 2) {
            $this->addError('pdfs', 'Debes seleccionar al menos dos archivos PDF.');
            return;
        }
        $rutas = [];
        foreach ($this->pdfs as $pdf) {
            $path = $pdf->store('pdfina/temp', 'public');
            $rutas[] = storage_path('app/public/' . $path);
        }
        // --- Detectar el ancho máximo de todas las páginas ---
        $maxWidth = 0;
        $maxHeight = 0;
        $paginas = [];
        foreach ($rutas as $ruta) {
            $pdf = new \setasign\Fpdi\Fpdi();
            $pageCount = $pdf->setSourceFile($ruta);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $maxWidth = max($maxWidth, $size['width']);
                $maxHeight = max($maxHeight, $size['height']);
                $paginas[] = [
                    'file' => $ruta,
                    'page' => $i,
                    'width' => $size['width'],
                    'height' => $size['height'],
                    'orientation' => $size['orientation'],
                ];
            }
        }
        // --- Crear PDF unificado con todas las páginas centradas en el ancho máximo ---
        $pdfOut = new \setasign\Fpdi\Fpdi();
        foreach ($paginas as $p) {
            $tplIdx = $pdfOut->setSourceFile($p['file']);
            $tpl = $pdfOut->importPage($p['page']);
            $orientation = ($maxWidth > $maxHeight) ? 'L' : 'P';
            $pdfOut->AddPage($orientation, [$maxWidth, $maxHeight]);
            // Centrar la página original en la nueva página
            $x = ($maxWidth - $p['width']) / 2;
            $y = ($maxHeight - $p['height']) / 2;
            $pdfOut->useTemplate($tpl, $x, $y, $p['width'], $p['height']);
        }
        $output = storage_path('app/public/pdfina/unido_' . time() . '.pdf');
        $pdfOut->Output('F', $output);
        foreach ($rutas as $ruta) { @unlink($ruta); }
        if (file_exists($output)) {
            $this->pdfPath = '/storage/pdfina/' . basename($output);
            $this->nuevoPdfGenerado = true;
            $this->pdfEnviado = false;
        } else {
            $this->addError('pdfs', 'No se pudo unir los archivos PDF.');
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

    public function moverArriba($index)
    {
        if ($index > 0) {
            $tmp = $this->pdfs[$index-1];
            $this->pdfs[$index-1] = $this->pdfs[$index];
            $this->pdfs[$index] = $tmp;
        }
    }

    public function moverAbajo($index)
    {
        if ($index < count($this->pdfs) - 1) {
            $tmp = $this->pdfs[$index+1];
            $this->pdfs[$index+1] = $this->pdfs[$index];
            $this->pdfs[$index] = $tmp;
        }
    }

    public function quitarArchivo($index)
    {
        array_splice($this->pdfs, $index, 1);
    }

    public function render()
    {
        return view('livewire.unir-pdf');
    }
}
