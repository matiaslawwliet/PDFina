<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Lanza los microservicios de Python controlando su ciclo de vida.
 * Si Laravel muere de golpe, el propio microservicio se cierra al detectar que su proceso padre ya no existe.
 */
class MicroservicioRunner
{
    public const TIMEOUT_POR_DEFECTO = 300;

    /**
     * @param  array<int, string>  $argumentos
     * @return array{0: int, 1: array<int, string>}
     */
    public function ejecutar(string $carpeta, string $script, array $argumentos = [], int $timeout = self::TIMEOUT_POR_DEFECTO): array
    {
        $comando = $this->resolverComando($carpeta, $script);

        if ($comando === null) {
            return [1, ['No se encontró el microservicio '.$script.'.']];
        }

        foreach ($argumentos as $argumento) {
            $comando[] = $argumento;
        }

        try {
            $resultado = Process::path(base_path())
                ->env(['PDFINA_PARENT_PID' => (string) getmypid()])
                ->timeout($timeout)
                ->run($comando);

            return [$resultado->exitCode() ?? 1, $this->lineas($resultado->output().$resultado->errorOutput())];
        } catch (Throwable $e) {
            return [1, [$e->getMessage()]];
        }
    }

    public function disponible(string $carpeta, string $script): bool
    {
        return $this->resolverComando($carpeta, $script) !== null;
    }

    /**
     * @return array<int, string>|null
     */
    protected function resolverComando(string $carpeta, string $script): ?array
    {
        $ejecutable = base_path("microservicios/{$carpeta}/dist/{$script}.exe");

        if (file_exists($ejecutable)) {
            return [$ejecutable];
        }

        $fuente = base_path("microservicios/{$carpeta}/{$script}.py");

        if (! file_exists($fuente)) {
            return null;
        }

        return $this->esWindows()
            ? ['py', '-3', $fuente]
            : ['python3', $fuente];
    }

    protected function esWindows(): bool
    {
        return stripos(PHP_OS_FAMILY, 'Windows') === 0;
    }

    /**
     * @return array<int, string>
     */
    protected function lineas(string $salida): array
    {
        $salida = trim($salida);

        if ($salida === '') {
            return [];
        }

        return preg_split('/\r\n|\r|\n/', $salida) ?: [];
    }
}
