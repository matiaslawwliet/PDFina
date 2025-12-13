<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class SellarPdf extends Component
{
    use WithFileUploads;

    public $pdf;
    public $sello;
    public $pdfSelladoPath;
    public $error;
    public $selectedCells = [];
    public $previewImagePath;
    public $nuevoPdfGenerado = false;
    public $pdfEnviado = false;
    public $userTimezone = 'UTC';

    protected $rules = [
        'pdf' => 'required|file|mimes:pdf',
        'sello' => 'required|image|mimes:png,jpg,jpeg',
    ];

    public function updatedPdf()
    {
        $this->nuevoPdfGenerado = false;
        $this->pdfSelladoPath = null;
        if ($this->pdf) {
            $pdfPath = $this->pdf->store('pdfina/temp', 'public');
            $gs = 'gswin64c';

            $normalizedPath = storage_path('app/public/pdfina/temp/preview_normalized_' . uniqid() . '.pdf');
            $cmdNorm = "$gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=".escapeshellarg($normalizedPath)." ".escapeshellarg(storage_path('app/public/' . $pdfPath));
            @shell_exec($cmdNorm);
            $pdfInputPath = file_exists($normalizedPath) ? $normalizedPath : storage_path('app/public/' . $pdfPath);

            try {
                $pdfTmp = new Fpdi();
                $pageCount = $pdfTmp->setSourceFile($pdfInputPath);
            } catch (\Exception $e) {
                $pageCount = 1;
            }
            $outputImg = storage_path('app/public/pdfina/temp/preview_' . uniqid() . '.png');

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

    public function updatedSello()
    {
        $this->nuevoPdfGenerado = false;
        $this->pdfSelladoPath = null;
    }

    public function toggleCell($x, $y)
    {
        $key = array_search(["x" => $x, "y" => $y], $this->selectedCells);
        if ($key !== false) {
            unset($this->selectedCells[$key]);
            $this->selectedCells = array_values($this->selectedCells);
        } else {
            $this->selectedCells[] = ["x" => $x, "y" => $y];
        }
    }

    public function sellar()
    {
        $this->validate();
        $this->nuevoPdfGenerado = false;
        if (empty($this->selectedCells)) {
            $this->error = 'Debes seleccionar al menos una celda de la cuadrícula.';
            return;
        }
        $pdfPath = $this->pdf->store('pdfina/temp', 'public');
        $selloPath = $this->sello->store('pdfina/temp', 'public');

        $outputFile = 'pdfina/sellado_' . uniqid() . '.pdf';
        $tempOutputPath = storage_path('app/public/pdfina/temp_sellado_' . uniqid() . '.pdf');
        $finalOutputPath = storage_path('app/public/' . $outputFile);

        $gs = 'gswin64c';
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

                if ($i === $pageCount) {
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->SetTextColor(0, 0, 0);

                    try {
                        $date = now($this->userTimezone);
                    } catch (\Exception $e) {
                        $date = now();
                    }

                    $offsetHours = (int)($date->offset / 3600);
                    $sign = $offsetHours >= 0 ? '+' : '';
                    $fechaHora = $date->format('d/m/Y H:i') . " (UTC{$sign}{$offsetHours})";
                    $textoSello = mb_convert_encoding($fechaHora, 'ISO-8859-1', 'UTF-8');

                    $cols = 5; $rows = 8;
                    $cellW = $size['width'] / $cols;
                    $cellH = $size['height'] / $rows;

                    $xs = array_column($this->selectedCells, 'x');
                    $ys = array_column($this->selectedCells, 'y');
                    $minX = min($xs); $maxX = max($xs);
                    $minY = min($ys); $maxY = max($ys);

                    $blockX = ($minX - 1) * $cellW;
                    $blockY = ($minY - 1) * $cellH;
                    $blockW = ($maxX - $minX + 1) * $cellW;
                    $blockH = ($maxY - $minY + 1) * $cellH;

                    $selloWidth = min(80, $blockW * 0.8);
                    $selloX = $blockX + ($blockW - $selloWidth) / 2;
                    $selloY = $blockY + $blockH * 0.1;

                    $pdf->Image(storage_path('app/public/' . $selloPath), $selloX, $selloY, $selloWidth, 0);

                    list($imgW, $imgH) = getimagesize(storage_path('app/public/' . $selloPath));
                    $ratio = $imgH / $imgW;
                    $selloHeight = $selloWidth * $ratio;

                    $pdf->SetXY($blockX, $selloY + $selloHeight + 2);
                    $pdf->MultiCell($blockW, 5, $textoSello, 0, 'C');
                }
            }
            $pdf->Output('F', $tempOutputPath);

            $ownerPass = uniqid('owner_');

            $cmdSecure = "$gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOwnerPassword=$ownerPass -dEncryptionR=3 -dKeyLength=128 -dPermissions=-60 -sOutputFile=".escapeshellarg($finalOutputPath)." ".escapeshellarg($tempOutputPath);

            @shell_exec($cmdSecure);

            if (!file_exists($finalOutputPath)) {
                copy($tempOutputPath, $finalOutputPath);
            }
            @unlink($tempOutputPath);

            $this->pdfSelladoPath = '/storage/' . $outputFile;
            $this->nuevoPdfGenerado = true;
        } catch (\Exception $e) {
            $this->error = 'Error al sellar el PDF: ' . $e->getMessage();
        }
    }

    public function enviarAlEscritorio()
    {
        $this->error = null;
        $this->pdfEnviado = false;
        if (!$this->pdfSelladoPath) {
            $this->error = 'No hay PDF sellado para exportar.';
            return;
        }
        $relativePath = ltrim(str_replace('/storage/', '', $this->pdfSelladoPath), '/\\');
        $publicDisk = Storage::disk('public');
        $desktopDisk = Storage::disk('desktop');
        if (!$publicDisk->exists($relativePath)) {
            $this->error = 'No se encontró el archivo sellado para exportar.';
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
        return view('livewire.sellar-pdf');
    }
}
