import sys
import os
import tempfile
import shutil
import subprocess
import traceback
from pdf2docx import Converter
import contextlib
import io
import logging
import sys

# Reduce noisy library logs
logging.getLogger().setLevel(logging.WARNING)
for noisy in ('pdf2docx', 'pikepdf', 'pymupdf', 'fitz'):
    try:
        logging.getLogger(noisy).setLevel(logging.WARNING)
    except Exception:
        pass

if len(sys.argv) != 3:
    print("Uso: pdf2docx_exec.py <archivo.pdf> <salida.docx>")
    sys.exit(1)

pdf_path = sys.argv[1]
docx_path = sys.argv[2]


def repair_with_pikepdf(src_path):
    try:
        import pikepdf
        tmp = tempfile.NamedTemporaryFile(delete=False, suffix='.pdf')
        tmp.close()
        # suppress pikepdf C++ bridge output
        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
            with pikepdf.open(src_path) as pdf:
                pdf.save(tmp.name)
        return tmp.name
    except Exception:
        return None


def repair_with_pypdf2(src_path):
    try:
        from PyPDF2 import PdfReader, PdfWriter

        reader = PdfReader(src_path)
        writer = PdfWriter()
        for p in reader.pages:
            writer.add_page(p)

        tmp = tempfile.NamedTemporaryFile(delete=False, suffix='.pdf')
        tmp.close()
        with open(tmp.name, 'wb') as f:
            writer.write(f)

        # silent
        return tmp.name
    except Exception:
        return None


def repair_with_mutool(src_path):
    try:
        which_mutool = shutil.which('mutool')
        if not which_mutool:
            return None
        tmp = tempfile.NamedTemporaryFile(delete=False, suffix='.pdf')
        tmp.close()
        cmd = [which_mutool, 'clean', src_path, tmp.name]
        subprocess.run(cmd, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        # silent
        return tmp.name
    except Exception:
        return None


def repair_with_ghostscript(src_path):
    try:
        gs = shutil.which('gswin64c') or shutil.which('gs')
        if not gs:
            return None
        tmp = tempfile.NamedTemporaryFile(delete=False, suffix='.pdf')
        tmp.close()
        cmd = [gs, '-dBATCH', '-dNOPAUSE', '-sDEVICE=pdfwrite', f"-sOutputFile={tmp.name}", src_path]
        subprocess.run(cmd, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        # silent
        return tmp.name
    except Exception:
        return None


def main():
    # First, try converting the original PDF directly
    try:
        # suppress library output
        with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
            cv = Converter(pdf_path)
            cv.convert(docx_path, start=0, end=None)
            cv.close()
        print("OK")
        sys.exit(0)
    except Exception as e:
        # concise error
        print(f"ERROR: Initial conversion failed: {e}")

    # Attempt repairs using different strategies, retrying after each
    repairers = [repair_with_pikepdf, repair_with_pypdf2, repair_with_mutool, repair_with_ghostscript]
    temps_to_cleanup = []

    for repair in repairers:
        try:
            repaired = repair(pdf_path)
        except Exception:
            repaired = None

        if not repaired:
            continue

        temps_to_cleanup.append(repaired)

        try:
            # try conversion silently
            out_path = docx_path
            with contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
                cv = Converter(repaired)
                cv.convert(out_path, start=0, end=None)
                cv.close()
            print(f"OK")
            # cleanup temps
            for t in temps_to_cleanup:
                if os.path.exists(t) and t != pdf_path:
                    try:
                        os.remove(t)
                    except Exception:
                        pass
            sys.exit(0)
        except Exception as e:
            # concise note and continue
            print(f"ERROR: Conversion failed after {repair.__name__}: {e}")
            # try next repair method

    # if we reach here, all repairs failed
    print("ERROR: All repair attempts failed")
    for t in temps_to_cleanup:
        if os.path.exists(t) and t != pdf_path:
            try:
                os.remove(t)
            except Exception:
                pass
    sys.exit(2)


if __name__ == '__main__':
    main()
