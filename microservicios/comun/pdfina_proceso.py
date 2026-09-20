"""Utilidades compartidas de los microservicios de PDFina.

Los microservicios se lanzan desde PHP y bloquean la peticion, pero si Laravel
(o la ventana NativePHP que lo hospeda) se cierra de golpe, el proceso hijo
quedaria huerfano. Este modulo aporta:

- Vigilancia del proceso padre: si el PHP que lanzo el microservicio desaparece,
  el microservicio se cierra solo tras ejecutar sus rutinas de limpieza.
- Manejo de senales (SIGINT/SIGTERM/SIGBREAK) para cerrar de forma ordenada.
- Ayudas para localizar y matar procesos auxiliares en Windows (por ejemplo
  WINWORD.EXE arrancado por la conversion de Word).

PHP debe exportar la variable de entorno PDFINA_PARENT_PID con su propio PID.
"""

import atexit
import os
import signal
import subprocess
import threading

CREATE_NO_WINDOW = 0x08000000

_limpiezas = []
_limpieza_hecha = threading.Event()


def registrar_limpieza(funcion):
    """Registra una rutina que debe ejecutarse antes de cerrar el microservicio."""
    _limpiezas.append(funcion)


def ejecutar_limpiezas():
    """Ejecuta una unica vez todas las rutinas de limpieza registradas."""
    if _limpieza_hecha.is_set():
        return
    _limpieza_hecha.set()
    for funcion in reversed(_limpiezas):
        try:
            funcion()
        except Exception:
            pass


def proceso_vivo(pid):
    """Indica si el proceso indicado sigue en ejecucion."""
    if not pid or pid <= 0:
        return False

    if os.name == "nt":
        import ctypes

        PROCESS_QUERY_LIMITED_INFORMATION = 0x1000
        STILL_ACTIVE = 259

        kernel32 = ctypes.windll.kernel32
        handle = kernel32.OpenProcess(PROCESS_QUERY_LIMITED_INFORMATION, False, int(pid))
        if not handle:
            return False
        codigo = ctypes.c_ulong()
        correcto = kernel32.GetExitCodeProcess(handle, ctypes.byref(codigo))
        kernel32.CloseHandle(handle)
        return bool(correcto) and codigo.value == STILL_ACTIVE

    try:
        os.kill(int(pid), 0)
    except OSError:
        return False
    return True


def pids_de_imagen(nombre_exe):
    """Devuelve los PID de los procesos de Windows que ejecutan ese binario."""
    if os.name != "nt":
        return set()

    try:
        resultado = subprocess.run(
            ["tasklist", "/FI", "IMAGENAME eq " + nombre_exe, "/FO", "CSV", "/NH"],
            capture_output=True,
            text=True,
            timeout=15,
            creationflags=CREATE_NO_WINDOW,
        )
    except Exception:
        return set()

    pids = set()
    for linea in (resultado.stdout or "").splitlines():
        partes = [parte.strip('" ') for parte in linea.split('","')]
        if len(partes) > 1 and partes[1].isdigit():
            pids.add(int(partes[1]))
    return pids


def terminar_pids(pids):
    """Mata los procesos indicados junto con sus hijos."""
    for pid in pids:
        try:
            if os.name == "nt":
                subprocess.run(
                    ["taskkill", "/F", "/T", "/PID", str(pid)],
                    capture_output=True,
                    timeout=15,
                    creationflags=CREATE_NO_WINDOW,
                )
            else:
                os.kill(int(pid), signal.SIGKILL)
        except Exception:
            pass


def _manejar_senal(numero, marco):
    ejecutar_limpiezas()
    os._exit(128 + numero)


def _vigilar_padre(pid, intervalo):
    while not _limpieza_hecha.is_set():
        if not proceso_vivo(pid):
            ejecutar_limpiezas()
            os._exit(9)
        if _limpieza_hecha.wait(intervalo):
            return


def supervisar(intervalo=2.0):
    """Activa el cierre ordenado del microservicio y la vigilancia del padre."""
    atexit.register(ejecutar_limpiezas)

    for nombre in ("SIGINT", "SIGTERM", "SIGBREAK", "SIGHUP"):
        numero = getattr(signal, nombre, None)
        if numero is None:
            continue
        try:
            signal.signal(numero, _manejar_senal)
        except (ValueError, OSError):
            pass

    try:
        pid_padre = int(os.environ.get("PDFINA_PARENT_PID", "0"))
    except (TypeError, ValueError):
        pid_padre = 0

    if pid_padre <= 0:
        return

    threading.Thread(target=_vigilar_padre, args=(pid_padre, intervalo), daemon=True).start()
