<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class EliminarPaginasPdf extends Component
{
    use WithFileUploads;

    public $pdf;
    public $paginas = '';
    public ?string $pdfPath = null;
    public $error = null;
    public $totalPaginas = 0;
    public $nuevoPdfGenerado = false;
    public $pdfEnviado = false;

    protected $rules = [
        'pdf' => 'required|file|mimes:pdf',
    ];

    public function updatedPdf()
    {
        $this->validateOnly('pdf');
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        // Guardar el archivo PDF subido y setear $pdfPath
        if ($this->pdf) {
            $path = $this->pdf->store('pdfina/temp', 'public');
            $this->pdfPath = '/storage/' . $path;
            // Contar páginas y asignar
            $fullPath = storage_path('app/public/' . $path);
            $this->totalPaginas = $this->contarPaginas($fullPath);
        } else {
            $this->pdfPath = null;
            $this->totalPaginas = 0;
        }
    }

    public function eliminarPaginas()
    {
        $this->validate();
        $this->error = null;
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        if (!$this->pdfPath) {
            $this->addError('pdf', 'Debes subir un PDF.');
            return;
        }
        $paginas = $this->paginas;
        if (!$paginas) {
            $this->addError('paginas', 'Debes indicar al menos una página a eliminar.');
            return;
        }
        $paginasArray = array_filter(array_map('trim', explode(',', $paginas)), fn($v) => is_numeric($v) && $v > 0);
        if (empty($paginasArray)) {
            $this->addError('paginas', 'Formato de páginas inválido. Usa números separados por coma.');
            return;
        }
        $inputPath = storage_path('app/public/' . str_replace('/storage/', '', $this->pdfPath));
        $outputFile = 'pdf_sin_paginas_' . time() . '.pdf';
        $outputPath = storage_path('app/public/pdfina/' . $outputFile);
        $total = $this->totalPaginas;
        $paginasEliminar = array_map('intval', $paginasArray);
        $paginasMantener = [];
        for ($i = 1; $i <= $total; $i++) {
            if (!in_array($i, $paginasEliminar)) {
                $paginasMantener[] = $i;
            }
        }
        if (empty($paginasMantener)) {
            $this->addError('paginas', 'No puedes eliminar todas las páginas.');
            return;
        }
        // --- Normalizar PDF con Ghostscript para compatibilidad FPDI ---
        $isWindows = stripos(PHP_OS, 'WIN') === 0;
        $gs = $isWindows ? 'gswin64c' : 'gs';
        $normalizedPath = storage_path('app/public/pdfina/temp/normalized_' . uniqid() . '.pdf');
        $cmd = "$gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=".escapeshellarg($normalizedPath)." ".escapeshellarg($inputPath);
        @shell_exec($cmd);
        $pdfInputPath = file_exists($normalizedPath) ? $normalizedPath : $inputPath;
        try {
            $pdf = new \setasign\Fpdi\Fpdi();
            $pdf->setSourceFile($pdfInputPath);
            foreach ($paginasMantener as $pagina) {
                $tpl = $pdf->importPage($pagina);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }
            $pdf->Output($outputPath, 'F');
        } catch (\Exception $e) {
            $this->addError('pdf', 'Error al procesar el PDF: ' . $e->getMessage());
            return;
        }
        if (file_exists($outputPath)) {
            $this->pdfPath = '/storage/pdfina/' . $outputFile;
            $this->nuevoPdfGenerado = true;
            $this->pdfEnviado = false;
        } else {
            $this->addError('pdf', 'No se pudo eliminar las páginas.');
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
        return view('livewire.eliminar-paginas-pdf');
    }

    private function contarPaginas($filePath)
    {
        $content = file_get_contents($filePath);
        if (preg_match_all("/\/Type\s*\/Page[^s]/", $content, $matches)) {
            return count($matches[0]);
        }
        return 0;
    }
}
