<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class LimpiezaArchivos extends Component
{
    public function mount()
    {
        $limpio = $this->limpiar();

        return redirect()->route('dashboard');
    }

    public function limpiar()
    {
        $algoEliminado = false;
        $algoEliminado = $this->borrarArchivos(Storage::disk('public')->path('pdfina/temp')) || $algoEliminado;
        $algoEliminado = $this->borrarArchivos(Storage::disk('public')->path('pdfina'), true) || $algoEliminado;
        $algoEliminado = $this->borrarArchivos(Storage::disk('local')->path('livewire-tmp')) || $algoEliminado;

        return $algoEliminado;
    }

    private function borrarArchivos($ruta, $soloArchivos = false)
    {
        if (! is_dir($ruta)) {
            return false;
        }
        $archivos = glob($ruta.'/*');
        $eliminado = false;
        foreach ($archivos as $archivo) {
            if (is_file($archivo)) {
                @unlink($archivo);
                $eliminado = true;
            } elseif (! $soloArchivos && is_dir($archivo)) {
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
