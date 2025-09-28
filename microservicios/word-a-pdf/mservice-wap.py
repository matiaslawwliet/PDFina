import sys
import os
import traceback
from docx2pdf import convert
import tempfile
import contextlib
import sys
import io


def convert_doc_to_docx(doc_path):
    """Convert a .doc (binary) to .docx using Word COM on Windows.
    Returns path to the .docx file.
    Raises RuntimeError with helpful message if conversion not possible.
    """
    try:
        import win32com.client
    except Exception as e:
        raise RuntimeError("pywin32 (win32com) is required to convert .doc to .docx on Windows. Install it in the venv: python -m pip install pywin32") from e

    word = None
    tmp_docx = None
    try:
        word = win32com.client.DispatchEx('Word.Application')
        word.Visible = False
        abs_doc = os.path.abspath(doc_path)
        tmp = tempfile.NamedTemporaryFile(delete=False, suffix='.docx')
        tmp.close()
        tmp_docx = tmp.name
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
        except Exception:
            pass

if len(sys.argv) != 3:
    print("Uso: python word2pdf_exec.py <input.docx> <output.pdf>")
    sys.exit(1)

input_path = sys.argv[1]
output_path = sys.argv[2]

if not os.path.isfile(input_path):
    print(f"Archivo de entrada no encontrado: {input_path}")
    sys.exit(2)

output_dir = os.path.dirname(output_path)
# if output_path has no directory part, dirname returns '' on Windows.
# use current directory in that case to avoid FileNotFoundError (WinError 3)
if output_dir == '':
    output_dir = '.'
if not os.path.exists(output_dir):
    os.makedirs(output_dir, exist_ok=True)

# Diagnostics
# keep only minimal logging; diagnostics removed

# If input is a .doc (not .docx), convert to .docx first using Word COM
temp_docx = None
try:
    if input_path.lower().endswith('.doc') and not input_path.lower().endswith('.docx'):
        temp_docx = convert_doc_to_docx(input_path)
        input_for_convert = temp_docx
    else:
        input_for_convert = input_path

    # suppress stdout/stderr from libraries during conversion
    with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
        convert(input_for_convert, output_dir)

    base_name = os.path.splitext(os.path.basename(input_for_convert))[0] + ".pdf"
    generated_pdf = os.path.join(output_dir, base_name)
    if generated_pdf != output_path:
        os.replace(generated_pdf, output_path)
    print(f"OK: {output_path}")
    sys.exit(0)
except Exception as e:
    # concise error output
    print(f"ERROR: {type(e).__name__}: {e}")
    # Helpful hint for common failure mode
    msg = str(e).lower()
    if 'pywin32' in msg or 'win32com' in msg or 'com' in msg:
        print("Hint: Converting .doc requires Microsoft Word via COM. Instala pywin32 en el venv y asegúrate de que MS Word esté instalado: python -m pip install pywin32")
    sys.exit(3)
finally:
    # cleanup temp docx if created
    if 'temp_docx' in locals() and temp_docx:
        try:
            os.remove(temp_docx)
        except Exception:
            pass
