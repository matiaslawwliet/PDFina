<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;

class FirmarPdf extends Component
{
    use WithFileUploads;

    public $pdf;
    public $firma;
    public $pdfFirmadoPath;
    public $error;
    public $aclaracion;
    public $dni;
    public $selectedCells = []; // array de ['x'=>int, 'y'=>int]
    public $previewImagePath;
    public $nuevoPdfGenerado = false;
    public $pdfEnviado = false;

    protected $rules = [
        'pdf' => 'required|file|mimes:pdf',
        'firma' => 'required|image|mimes:png,jpg,jpeg',
        'aclaracion' => 'required|string|max:100',
        'dni' => 'required|string|max:20',
    ];

    public function updatedPdf()
    {
        // Generar previsualización de la última página como imagen PNG
        $this->nuevoPdfGenerado = false;
        $this->pdfFirmadoPath = null;
        if ($this->pdf) {
            $pdfPath = $this->pdf->store('pdfina/temp', 'public');
            $gs = 'gswin64c';
            // Normalizar PDF antes de contar páginas
            $normalizedPath = storage_path('app/public/pdfina/temp/preview_normalized_' . uniqid() . '.pdf');
            $cmdNorm = "$gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=".escapeshellarg($normalizedPath)." ".escapeshellarg(storage_path('app/public/' . $pdfPath));
            @shell_exec($cmdNorm);
            $pdfInputPath = file_exists($normalizedPath) ? $normalizedPath : storage_path('app/public/' . $pdfPath);
            // Obtener el número de páginas usando FPDI
            try {
                $pdfTmp = new Fpdi();
                $pageCount = $pdfTmp->setSourceFile($pdfInputPath);
            } catch (\Exception $e) {
                $pageCount = 1;
            }
            $outputImg = storage_path('app/public/pdfina/temp/preview_' . uniqid() . '.png');
            // Extraer solo la última página
            $cmd = "$gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r120 -dFirstPage=$pageCount -dLastPage=$pageCount -sOutputFile=".escapeshellarg($outputImg)." ".escapeshellarg($pdfInputPath);
            @shell_exec($cmd);
            if (file_exists($outputImg)) {
                $base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($outputImg));
                $this->previewImagePath = $base64;
            } else {
                $this->previewImagePath = null;
            }
            if (file_exists($normalizedPath)) {
                @unlink($normalizedPath);
            }
        }
    }

    public function updatedFirma()
    {
        $this->nuevoPdfGenerado = false;
        $this->pdfFirmadoPath = null;
    }

    public function toggleCell($x, $y)
    {
        $key = array_search(["x" => $x, "y" => $y], $this->selectedCells);
        if ($key !== false) {
            unset($this->selectedCells[$key]);
            $this->selectedCells = array_values($this->selectedCells); // reindexar
        } else {
            $this->selectedCells[] = ["x" => $x, "y" => $y];
        }
    }

    public function firmar()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        if (empty($this->selectedCells)) {
            $this->error = 'Debes seleccionar al menos una celda de la cuadrícula.';
            return;
        }
        $pdfPath = $this->pdf->store('pdfina/temp', 'public');
        $firmaPath = $this->firma->store('pdfina/temp', 'public');
        // Ya no reprocesar PNG, dejar la imagen original para mantener transparencia
        $outputFile = 'pdfina/firmado_' . uniqid() . '.pdf';
        $outputPath = storage_path('app/public/' . $outputFile);

        // --- Normalizar PDF con Ghostscript para compatibilidad FPDI ---
        $gs = 'gswin64c'; // Windows
        $normalizedPath = storage_path('app/public/pdfina/temp/normalized_' . uniqid() . '.pdf');
        $cmd = "$gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=".escapeshellarg($normalizedPath)." ".escapeshellarg(storage_path('app/public/' . $pdfPath));
        @shell_exec($cmd);
        $pdfInputPath = file_exists($normalizedPath) ? $normalizedPath : storage_path('app/public/' . $pdfPath);

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($pdfInputPath);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
                // Si es la última página, colocar la firma/aclaración/DNI en el bloque seleccionado
                if ($i === $pageCount) {
                    $pdf->SetFont('Arial', '', 12);
                    $pdf->SetTextColor(40,40,40);
                    $aclaracionText = utf8_decode('Aclaración: ' . $this->aclaracion);
                    $dniText = utf8_decode('DNI: ' . $this->dni);
                    $cols = 5; $rows = 8;
                    $cellW = $size['width'] / $cols;
                    $cellH = $size['height'] / $rows;
                    // Calcular el rectángulo mínimo que abarca todas las celdas seleccionadas
                    $xs = array_column($this->selectedCells, 'x');
                    $ys = array_column($this->selectedCells, 'y');
                    $minX = min($xs); $maxX = max($xs);
                    $minY = min($ys); $maxY = max($ys);
                    $blockX = ($minX - 1) * $cellW;
                    $blockY = ($minY - 1) * $cellH;
                    $blockW = ($maxX - $minX + 1) * $cellW;
                    $blockH = ($maxY - $minY + 1) * $cellH;
                    // Firma centrada en el bloque
                    $firmaWidth = min(90, $blockW * 0.8, $pdf->GetStringWidth($aclaracionText) + 4);
                    $firmaX = $blockX + ($blockW - $firmaWidth) / 2;
                    $firmaY = $blockY + $blockH * 0.1;
                    $pdf->Image(storage_path('app/public/' . $firmaPath), $firmaX, $firmaY, $firmaWidth, 0, 'PNG');
                    $pdf->SetXY($firmaX, $firmaY + $firmaWidth * 0.35 + 6);
                    $pdf->Cell($firmaWidth, 8, $aclaracionText, 0, 2, 'C');
                    $pdf->SetXY($firmaX, $firmaY + $firmaWidth * 0.35 + 18);
                    $pdf->Cell($firmaWidth, 8, $dniText, 0, 2, 'C');
                }
            }
            $pdf->Output('F', $outputPath);
            $this->pdfFirmadoPath = '/storage/' . $outputFile;
            $this->nuevoPdfGenerado = true;
        } catch (\Exception $e) {
            $this->error = 'Error al firmar el PDF: ' . $e->getMessage();
        }
    }

    public function enviarAlEscritorio()
    {
        $this->error = null;
        $this->pdfEnviado = false;
        if (!$this->pdfFirmadoPath) {
            $this->error = 'No hay PDF firmado para exportar.';
            return;
        }
        // Quitar el prefijo '/storage/' para obtener la ruta relativa
        $relativePath = ltrim(str_replace('/storage/', '', $this->pdfFirmadoPath), '/\\');
        $publicDisk = Storage::disk('public');
        $desktopDisk = Storage::disk('desktop');
        if (!$publicDisk->exists($relativePath)) {
            $this->error = 'No se encontró el archivo firmado para exportar.';
            return;
        }
        try {
            $fileName = basename($relativePath);
            $fileContents = $publicDisk->get($relativePath);
            $desktopDisk->put($fileName, $fileContents);
            $this->pdfEnviado = true;
        } catch (\Exception $e) {
            $this->error = 'Error al enviar el PDF al escritorio: ' . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.firmar-pdf');
    }
}
