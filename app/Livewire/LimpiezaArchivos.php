<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Storage;

class LimpiezaArchivos extends Component
{
    public function mount()
    {
        $limpio = $this->limpiar();
        // Si no había nada para limpiar o ya está limpio, redirigir igual
        return redirect()->route('dashboard');
    }

    public function limpiar()
    {
        $algoEliminado = false;
        // 1. Limpiar storage/app/public/pdfina/temp (archivos temporales de los módulos)
        $algoEliminado = $this->borrarArchivos(Storage::disk('public')->path('pdfina/temp')) || $algoEliminado;
        // 2. Limpiar SOLO archivos en storage/app/public/pdfina (no subcarpetas)
        $algoEliminado = $this->borrarArchivos(Storage::disk('public')->path('pdfina'), true) || $algoEliminado;
        // 3. Limpiar storage/app/livewire-tmp (archivos temporales de Livewire)
        $algoEliminado = $this->borrarArchivos(Storage::disk('local')->path('livewire-tmp')) || $algoEliminado;

        return $algoEliminado;
    }

    private function borrarArchivos($ruta, $soloArchivos = false)
    {
        if (!is_dir($ruta)) return false;
        $archivos = glob($ruta . '/*');
        $eliminado = false;
        foreach ($archivos as $archivo) {
            if (is_file($archivo)) {
                @unlink($archivo);
                $eliminado = true;
            } elseif (!$soloArchivos && is_dir($archivo)) {
                $this->borrarArchivos($archivo);
                @rmdir($archivo);
                $eliminado = true;
            }
        }
        return $eliminado;
    }

    public function render()
    {
        return view('dashboard');
    }
}
