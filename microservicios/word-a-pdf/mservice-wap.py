import sys
import os
import traceback
from docx2pdf import convert
import tempfile
import contextlib
import sys
import io

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), os.pardir, "comun"))

from pdfina_proceso import pids_de_imagen, registrar_limpieza, supervisar, terminar_pids

_word_apps = []
_winword_previos = set()
_temporales = []


def cerrar_word():
    while _word_apps:
        app = _word_apps.pop()
        try:
            app.Quit(SaveChanges=0)
        except Exception:
            pass

    nuevos = pids_de_imagen("WINWORD.EXE") - _winword_previos
    if nuevos:
        terminar_pids(nuevos)

    while _temporales:
        ruta = _temporales.pop()
        try:
            os.remove(ruta)
        except Exception:
            pass


def convert_doc_to_docx(doc_path):
    try:
        import win32com.client
    except Exception as e:
        raise RuntimeError("pywin32 (win32com) is required to convert .doc to .docx on Windows. Install it in the venv: python -m pip install pywin32") from e

    word = None
    tmp_docx = None
    try:
        word = win32com.client.DispatchEx('Word.Application')
        _word_apps.append(word)
        word.Visible = False
        abs_doc = os.path.abspath(doc_path)
        tmp = tempfile.NamedTemporaryFile(delete=False, suffix='.docx')
        tmp.close()
        tmp_docx = tmp.name
        _temporales.append(tmp_docx)
        doc = word.Documents.Open(abs_doc)
        # FileFormat=16 -> wdFormatXMLDocument (.docx)
        doc.SaveAs(tmp_docx, FileFormat=16)
        doc.Close()
        return tmp_docx
    except Exception as e:
        raise RuntimeError(f"Fallo al convertir .doc a .docx: {e}") from e
    finally:
        try:
            if word is not None:
                word.Quit()
                if word in _word_apps:
                    _word_apps.remove(word)
        except Exception:
            pass


def main():
    global _winword_previos

    supervisar()
    registrar_limpieza(cerrar_word)
    _winword_previos = pids_de_imagen("WINWORD.EXE")

    if len(sys.argv) != 3:
        print("Uso: python word2pdf_exec.py <input.docx> <output.pdf>")
        sys.exit(1)

    input_path = sys.argv[1]
    output_path = sys.argv[2]

    if not os.path.isfile(input_path):
        print(f"Archivo de entrada no encontrado: {input_path}")
        sys.exit(2)

    output_dir = os.path.dirname(output_path)
    if output_dir == '':
        output_dir = '.'
    if not os.path.exists(output_dir):
        os.makedirs(output_dir, exist_ok=True)

    try:
        if input_path.lower().endswith('.doc') and not input_path.lower().endswith('.docx'):
            input_for_convert = convert_doc_to_docx(input_path)
        else:
            input_for_convert = input_path

        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
            convert(input_for_convert, output_dir)

        base_name = os.path.splitext(os.path.basename(input_for_convert))[0] + ".pdf"
        generated_pdf = os.path.join(output_dir, base_name)
        if generated_pdf != output_path:
            os.replace(generated_pdf, output_path)
        print(f"OK: {output_path}")
        sys.exit(0)
    except SystemExit:
        raise
    except Exception as e:
        print(f"ERROR: {type(e).__name__}: {e}")
        msg = str(e).lower()
        if 'pywin32' in msg or 'win32com' in msg or 'com' in msg:
            print("Hint: Converting .doc requires Microsoft Word via COM. Instala pywin32 en el venv y asegúrate de que MS Word esté instalado: python -m pip install pywin32")
        sys.exit(3)


if __name__ == '__main__':
    main()
