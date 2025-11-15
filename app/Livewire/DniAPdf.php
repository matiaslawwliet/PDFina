<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class DniAPdf extends Component
{
    use WithFileUploads;

    public $front;
    public $back;
    public ?string $pdfPath = null;
    public $nuevoPdfGenerado = false;
    public $pdfEnviado = false;

    protected $rules = [
        'front' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        'back' => 'required|image|mimes:jpeg,png,jpg|max:5120',
    ];


    public function updatedFront()
    {
        $this->validateOnly('front');
        $this->resetFlags();
    }

    public function updatedBack()
    {
        $this->validateOnly('back');
        $this->resetFlags();
    }

    protected function resetFlags(): void
    {
        $this->nuevoPdfGenerado = false;
        $this->pdfEnviado = false;
        $this->pdfPath = null;
    }

    public function dniAPdf()
    {
        $this->validate();
        if (!$this->front) {
            $this->addError('front', 'Debes subir la imagen del frente.');
            return;
        }
        if (!$this->back) {
            $this->addError('back', 'Debes subir la imagen del dorso.');
            return;
        }

        $folder = 'pdfina';
        $frontFilename = 'dni_front_' . time() . '.jpg';
        $backFilename = 'dni_back_' . time() . '.jpg';

        $frontSaved = $this->saveImageSource(null, $this->front, $folder . '/' . $frontFilename);
        $backSaved = $this->saveImageSource(null, $this->back, $folder . '/' . $backFilename);

        if (!$frontSaved || !$backSaved) {
            $this->addError('front', 'Error al guardar las imágenes.');
            return;
        }

        $fullFrontPath = storage_path('app/public/' . $folder . '/' . $frontFilename);
        $fullBackPath = storage_path('app/public/' . $folder . '/' . $backFilename);

        $dpi = 150.0;
        $pageWidthMm = 210.0;
        $pageMarginMm = 20.0;
        $usableWidthMm = $pageWidthMm - ($pageMarginMm * 2);
        $halfPageHeightMm = (297.0 - ($pageMarginMm * 2)) / 2.0;

        $maxWidth = (int) round($usableWidthMm * $dpi / 25.4);
        $maxHeight = (int) round($halfPageHeightMm * $dpi / 25.4);

        $this->resizeImage($fullFrontPath, $fullFrontPath, $maxWidth, $maxHeight);
        $this->resizeImage($fullBackPath, $fullBackPath, $maxWidth, $maxHeight);

        $html = $this->generateHtmlDoc($frontFilename, $backFilename);

        $pdfFileName = 'dni_' . time() . '.pdf';
        $pdfFullPath = storage_path('app/public/' . $folder . '/' . $pdfFileName);

        try {
            $pdf = Pdf::loadHTML($html)->setPaper('a4');
            $pdf->save($pdfFullPath);
            if (file_exists($pdfFullPath)) {
                $this->pdfPath = '/storage/' . $folder . '/' . $pdfFileName;
                $this->nuevoPdfGenerado = true;
                $this->pdfEnviado = false;
            } else {
                $this->addError('front', 'Error al guardar el PDF generado.');
                $this->pdfPath = null;
            }
        } catch (\Exception $e) {
            $this->addError('front', 'Error al generar el PDF: ' . $e->getMessage());
            $this->pdfPath = null;
        }
    }

    protected function saveImageSource(?string $dataUrl, $uploadedFile, string $relativePath): bool
    {
        if ($dataUrl) {
            if (preg_match('/^data:\w+\/\w+;base64,/', $dataUrl)) {
                $data = substr($dataUrl, strpos($dataUrl, ',') + 1);
                $decoded = base64_decode($data);
                return (bool) file_put_contents(storage_path('app/public/' . $relativePath), $decoded);
            }
            return false;
        }

        if ($uploadedFile) {
            $stream = fopen($uploadedFile->getRealPath(), 'r');
            Storage::disk('public')->put($relativePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            return true;
        }

        return false;
    }

    protected function resizeImage(string $sourcePath, string $destPath, int $maxW, int $maxH): void
    {
        if (!file_exists($sourcePath)) return;

        [$width, $height, $type] = getimagesize($sourcePath);
        $ratio = $width / $height;

        $targetW = $width;
        $targetH = $height;

        if ($width > $maxW || $height > $maxH) {
            if ($maxW / $maxH > $ratio) {
                $targetH = $maxH;
                $targetW = (int) ($maxH * $ratio);
            } else {
                $targetW = $maxW;
                $targetH = (int) ($maxW / $ratio);
            }
        }

        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImg = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $srcImg = imagecreatefrompng($sourcePath);
                break;
            default:
                return;
        }

        $dstImg = imagecreatetruecolor($targetW, $targetH);
        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        imagejpeg($dstImg, $destPath, 90);
        imagedestroy($srcImg);
        imagedestroy($dstImg);
    }

    protected function generateHtmlDoc(string $frontFile, string $backFile): string
    {
        $frontPath = storage_path('app/public/pdfina/' . $frontFile);
        $backPath = storage_path('app/public/pdfina/' . $backFile);

        $frontData = base64_encode(file_get_contents($frontPath));
        $backData = base64_encode(file_get_contents($backPath));

        $frontSrc = 'data:image/jpeg;base64,' . $frontData;
        $backSrc = 'data:image/jpeg;base64,' . $backData;

        $pageWidthMm = 210.0;
        $pageMarginMm = 20.0;
        $usableWidthMm = $pageWidthMm - ($pageMarginMm * 2);
        $maxImageWidthMm = $usableWidthMm / 2.0;
        $halfHeightMm = 128.5;

        $style = "@page { size: A4; margin: {$pageMarginMm}mm; } body { margin:0; padding:0; font-family: sans-serif; } ";
        $style .= ".slot { width: 100%; display:block; text-align:center; margin:0; padding:0; box-sizing:border-box; } ";
        $style .= ".slot .box { width: 100%; max-width: {$maxImageWidthMm}mm; height: {$halfHeightMm}mm; display:flex; align-items:center; justify-content:center; margin:0 auto; overflow:hidden; box-sizing:border-box; } ";
        $style .= ".slot img { max-width: {$maxImageWidthMm}mm; max-height: {$halfHeightMm}mm; width:auto; height:auto; object-fit:contain; display:block; margin:auto; } ";

        $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        $html .= '<style>' . $style . '</style></head><body>';
        $html .= '<div class="slot"><div class="box"><img src="' . $frontSrc . '" alt="DNI Frente" /></div></div>';
        $html .= '<div class="slot"><div class="box"><img src="' . $backSrc . '" alt="DNI Dorso" /></div></div>';
        $html .= '</body></html>';

        return $html;
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
        return view('livewire.dni-a-pdf');
    }
}
