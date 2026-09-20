<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use setasign\Fpdi\Fpdi;
use ZipArchive;

class UnirPdf extends Component
{
    use WithFileUploads;

    public $zip = null;

    public array $archivosExtraidos = [];

    public bool $extraido = false;

    public ?string $tempDir = null;

    public ?string $pdfPath = null;

    public bool $pdfEnviado = false;

    public function updatedZip(): void
    {
        $this->validate(['zip' => 'required|file|mimes:zip']);
        $this->extraerZip();
    }

    private function extraerZip(): void
    {
        $this->limpiarTempDir();

        $za = new ZipArchive;
        if ($za->open($this->zip->getRealPath()) !== true) {
            $this->addError('zip', 'No se pudo abrir el archivo ZIP.');

            return;
        }

        $tempDir = storage_path('app/public/pdfina/temp/unir_'.uniqid('', true));
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $archivos = [];
        for ($i = 0; $i < $za->numFiles; $i++) {
            $stat = $za->statIndex($i);
            $nombre = $stat['name'];

            if (! str_ends_with(strtolower($nombre), '.pdf') || str_ends_with($nombre, '/')) {
                continue;
            }

            $baseName = basename($nombre);
            if (empty($baseName)) {
                continue;
            }

            $contenido = $za->getFromIndex($i);
            if ($contenido === false) {
                continue;
            }

            $destPath = $tempDir.'/'.$baseName;
            file_put_contents($destPath, $contenido);
            $archivos[] = ['nombre' => $baseName, 'ruta' => $destPath];
        }
        $za->close();

        usort($archivos, fn ($a, $b) => strnatcasecmp($a['nombre'], $b['nombre']));

        $this->tempDir = $tempDir;
        $this->archivosExtraidos = $archivos;
        $this->extraido = true;
        $this->pdfPath = null;
        $this->pdfEnviado = false;
        $this->zip = null;
    }

    public function unirPdfs(): void
    {
        if (count($this->archivosExtraidos) < 2) {
            $this->addError('zip', 'Debes tener al menos dos archivos PDF en el ZIP.');

            return;
        }

        $this->pdfPath = null;
        $this->pdfEnviado = false;
        $this->resetErrorBag();

        ini_set('memory_limit', '2048M');

        $maxWidth = 0;
        $maxHeight = 0;
        $paginas = [];
        $pdfsValidos = 0;

        foreach ($this->archivosExtraidos as $archivo) {
            if (! file_exists($archivo['ruta'])) {
                continue;
            }

            $pdfParaLeer = $this->normalizarPdfParaUnir($archivo['ruta']);

            try {
                $reader = new Fpdi;
                $pageCount = $reader->setSourceFile($pdfParaLeer);

                if ($pageCount < 1) {
                    continue;
                }

                $pdfsValidos++;

                for ($i = 1; $i <= $pageCount; $i++) {
                    $tpl = $reader->importPage($i);
                    $size = $reader->getTemplateSize($tpl);
                    $maxWidth = max($maxWidth, $size['width']);
                    $maxHeight = max($maxHeight, $size['height']);
                    $paginas[] = [
                        'file' => $pdfParaLeer,
                        'page' => $i,
                        'width' => $size['width'],
                        'height' => $size['height'],
                    ];
                }

                unset($reader);
            } catch (\Throwable $e) {
                $this->addError(
                    'zip',
                    'No se pudo procesar el archivo "'.$archivo['nombre'].'". Asegúrate de que sea un PDF válido o que use una compresión compatible con FPDI.'
                );
            }
        }

        if ($pdfsValidos < 2) {
            $this->addError('zip', 'Debes tener al menos dos archivos PDF válidos para unir.');

            return;
        }

        if (count($paginas) < 1) {
            $this->addError('zip', 'No se encontraron páginas válidas para unir.');

            return;
        }

        gc_collect_cycles();

        try {
            $pdfOut = new Fpdi;
            $orientation = ($maxWidth > $maxHeight) ? 'L' : 'P';
            $currentFile = null;

            foreach ($paginas as $p) {
                if ($currentFile !== $p['file']) {
                    $pdfOut->setSourceFile($p['file']);
                    $currentFile = $p['file'];
                }
                $tpl = $pdfOut->importPage($p['page']);
                $pdfOut->AddPage($orientation, [$maxWidth, $maxHeight]);
                $x = ($maxWidth - $p['width']) / 2;
                $y = ($maxHeight - $p['height']) / 2;
                $pdfOut->useTemplate($tpl, $x, $y, $p['width'], $p['height']);
            }

            $output = storage_path('app/public/pdfina/unido_'.time().'.pdf');
            $pdfOut->Output('F', $output);
            unset($pdfOut);

            $this->limpiarTempDir();
            $this->archivosExtraidos = [];
            $this->extraido = false;

            if (file_exists($output)) {
                $this->pdfPath = '/storage/pdfina/'.basename($output);
            } else {
                $this->addError('zip', 'No se pudo unir los archivos PDF.');
            }
        } catch (\Throwable $e) {
            $this->addError('zip', 'No se pudo unir los archivos PDF. Verifica que todos los archivos sean compatibles.');
        }
    }

    private function normalizarPdfParaUnir(string $pdfPath): string
    {
        $normalizedPath = $this->tempDir ? $this->tempDir.'/normalized_'.basename($pdfPath) : storage_path('app/public/pdfina/temp/normalized_'.uniqid('', true).'.pdf');

        $isWindows = str_contains(strtolower(PHP_OS_FAMILY), 'win');
        $gs = $isWindows ? 'gswin64c' : 'gs';

        $cmd = $gs.' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile='.escapeshellarg($normalizedPath).' '.escapeshellarg($pdfPath);
        @shell_exec($cmd);

        if (file_exists($normalizedPath) && filesize($normalizedPath) > 0) {
            return $normalizedPath;
        }

        return $pdfPath;
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

    public function moverArriba(int $index): void
    {
        if ($index > 0) {
            $this->reordenarArchivosPorIndices($index, $index - 1);
        }
    }

    public function moverAbajo(int $index): void
    {
        if ($index < count($this->archivosExtraidos) - 1) {
            $this->reordenarArchivosPorIndices($index, $index + 1);
        }
    }

    public function reordenarArchivosPorIndices(int $fromIndex, int $toIndex): void
    {
        if ($fromIndex < 0 || $toIndex < 0) {
            return;
        }

        $total = count($this->archivosExtraidos);
        if ($fromIndex >= $total || $toIndex >= $total || $fromIndex === $toIndex) {
            return;
        }

        $archivo = $this->archivosExtraidos[$fromIndex];
        unset($this->archivosExtraidos[$fromIndex]);
        $this->archivosExtraidos = array_values($this->archivosExtraidos);

        array_splice($this->archivosExtraidos, $toIndex, 0, [$archivo]);
    }

    public function reordenarArchivos(array $nuevosOrdenes): void
    {
        if (empty($nuevosOrdenes)) {
            return;
        }

        $archivosPorRuta = [];
        foreach ($this->archivosExtraidos as $archivo) {
            $archivosPorRuta[$archivo['ruta']] = $archivo;
        }

        $archivosReordenados = [];
        foreach ($nuevosOrdenes as $ruta) {
            if (isset($archivosPorRuta[$ruta])) {
                $archivosReordenados[] = $archivosPorRuta[$ruta];
            }
        }

        foreach ($this->archivosExtraidos as $archivo) {
            if (! in_array($archivo['ruta'], $nuevosOrdenes, true)) {
                $archivosReordenados[] = $archivo;
            }
        }

        $this->archivosExtraidos = $archivosReordenados;
    }

    public function quitarArchivo(int $index): void
    {
        if (isset($this->archivosExtraidos[$index])) {
            @unlink($this->archivosExtraidos[$index]['ruta']);
        }
        array_splice($this->archivosExtraidos, $index, 1);

        if (count($this->archivosExtraidos) === 0) {
            $this->limpiarTempDir();
            $this->extraido = false;
        }
    }

    private function limpiarTempDir(): void
    {
        if ($this->tempDir && is_dir($this->tempDir)) {
            foreach (glob($this->tempDir.'/*.pdf') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tempDir);
        }
        $this->tempDir = null;
    }

    public function render()
    {
        return view('livewire.unir-pdf');
    }
}
