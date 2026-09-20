"""Microservicio de edicion de PDF para PDFina.

Usa PyMuPDF (fitz) para editar el PDF original en vez de regenerarlo, de forma
que todo lo que el usuario no toca se mantiene exactamente igual que el original.

Uso:
    mservice-epdf.py extract <entrada.pdf> <directorio_salida>
    mservice-epdf.py apply   <entrada.pdf> <ediciones.json> <salida.pdf>
"""

import json
import os
import re
import sys
import tempfile
import traceback

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), os.pardir, "comun"))

from pdfina_proceso import registrar_limpieza, supervisar

try:
    import fitz
except ImportError:
    print("Falta la dependencia PyMuPDF. Instalala con: pip install pymupdf")
    sys.exit(2)


RENDER_ZOOM = 2.0

FLAG_ITALIC = 2
FLAG_SERIF = 4
FLAG_MONO = 8
FLAG_BOLD = 16

EXTENSIONES_FUENTE = ("ttf", "otf", "cff", "ttc", "pfb", "pfa")

ALIAS_FAMILIAS = {
    "timesnewroman": "times",
    "couriernew": "cour",
    "arialnarrow": "arialn",
    "trebuchetms": "trebuc",
    "comicsansms": "comic",
    "bookantiqua": "bkant",
    "centurygothic": "gothic",
}

SUFIJOS_ESTILO = {
    (False, False): ("", "regular"),
    (True, False): ("bd", "b", "bold"),
    (False, True): ("i", "it", "italic", "oblique"),
    (True, True): ("bi", "z", "bolditalic", "boldoblique"),
}

DIRECTORIOS_FUENTES = (
    os.path.join(os.environ.get("WINDIR", "C:\\Windows"), "Fonts"),
    os.path.expanduser("~/AppData/Local/Microsoft/Windows/Fonts"),
    "/usr/share/fonts",
    "/usr/local/share/fonts",
    os.path.expanduser("~/.fonts"),
    "/Library/Fonts",
    "/System/Library/Fonts",
    os.path.expanduser("~/Library/Fonts"),
)

_font_objects = {}
_alias_fuentes = {}
_fuentes_extraidas = {}
_fuentes_sistema = {}
_indice_doc = None
_indice_sistema = None


def base14_from_flags(flags):
    bold = bool(flags & FLAG_BOLD)
    italic = bool(flags & FLAG_ITALIC)
    if flags & FLAG_MONO:
        family = ("cour", "cobo", "coit", "cobi")
    elif flags & FLAG_SERIF:
        family = ("tiro", "tibo", "tiit", "tibi")
    else:
        family = ("helv", "hebo", "heit", "hebi")
    if bold and italic:
        return family[3]
    if bold:
        return family[1]
    if italic:
        return family[2]
    return family[0]


def css_family_from_flags(flags):
    if flags & FLAG_MONO:
        return "monospace"
    if flags & FLAG_SERIF:
        return "serif"
    return "sans-serif"


def int_to_hex(color):
    return "#%06x" % (int(color) & 0xFFFFFF)


def color_to_rgb(color):
    color = int(color) & 0xFFFFFF
    return ((color >> 16 & 255) / 255.0, (color >> 8 & 255) / 255.0, (color & 255) / 255.0)


def sample_bg(pix, rect, zoom):
    """Estima el color de fondo justo alrededor de una linea de texto."""
    x0 = int(rect.x0 * zoom)
    y0 = int(rect.y0 * zoom)
    x1 = int(rect.x1 * zoom)
    y1 = int(rect.y1 * zoom)
    ym = (y0 + y1) // 2
    candidatos = [
        (x0 - 3, y0 - 2), (x1 + 3, y0 - 2),
        (x0 - 3, y1 + 2), (x1 + 3, y1 + 2),
        (x0 - 4, ym), (x1 + 4, ym),
    ]
    conteo = {}
    for x, y in candidatos:
        if 0 <= x < pix.width and 0 <= y < pix.height:
            try:
                pixel = pix.pixel(int(x), int(y))
            except Exception:
                continue
            conteo[pixel[:3]] = conteo.get(pixel[:3], 0) + 1
    if not conteo:
        return "#ffffff"
    mejor = max(conteo.items(), key=lambda kv: kv[1])[0]
    return "#%02x%02x%02x" % mejor


def span_meta(span):
    return {
        "text": span.get("text", ""),
        "bbox": list(span["bbox"]),
        "origin": [span["origin"][0], span["origin"][1]],
        "size": float(span.get("size", 11.0)) or 11.0,
        "font": span.get("font", ""),
        "flags": int(span.get("flags", 0)),
        "color": int(span.get("color", 0)),
    }


def firma_linea(spans):
    """Estilos distintos presentes en la linea; una sola entrada significa tipografia uniforme."""
    return tuple(sorted({
        (s.get("font", ""), round(float(s.get("size", 0)), 1), int(s.get("flags", 0)), int(s.get("color", 0)))
        for s in spans
    }))


def collect_lines(page):
    """Lineas horizontales de la pagina con sus spans y el bloque al que pertenecen."""
    lineas = []
    bloques = []
    for bloque in page.get_text("dict").get("blocks", []):
        if bloque.get("type", 1) != 0:
            continue
        indice_bloque = len(bloques)
        bloques.append(fitz.Rect(bloque["bbox"]))
        for line in bloque.get("lines", []):
            direccion = line.get("dir", (1, 0))
            if abs(direccion[1]) > 0.01 or direccion[0] < 0.99:
                continue
            crudos = line.get("spans", [])
            visibles = [s for s in crudos if s.get("text", "").strip()]
            if not visibles:
                continue
            texto = "".join(s.get("text", "") for s in crudos)
            if not texto.strip():
                continue
            principal = max(visibles, key=lambda s: len(s.get("text", "")))
            lineas.append({
                "bloque": indice_bloque,
                "text": texto,
                "bbox": [
                    min(s["bbox"][0] for s in visibles),
                    min(s["bbox"][1] for s in visibles),
                    max(s["bbox"][2] for s in visibles),
                    max(s["bbox"][3] for s in visibles),
                ],
                "origin": [visibles[0]["origin"][0], visibles[0]["origin"][1]],
                "principal": span_meta(principal),
                "spans": [span_meta(s) for s in crudos],
                "firma": firma_linea(visibles),
            })
    return lineas, bloques


def contenedor_de(rect, bloques, contenido, propio):
    """Ancho util de la linea: el area de contenido recortada por los bloques vecinos de su banda."""
    x0, x1 = contenido.x0, contenido.x1
    alto = max(rect.y1 - rect.y0, 1.0)
    for i, bloque in enumerate(bloques):
        if i == propio:
            continue
        if min(rect.y1, bloque.y1) - max(rect.y0, bloque.y0) <= 0.3 * alto:
            continue
        if bloque.x1 <= rect.x0 + 1:
            x0 = max(x0, bloque.x1)
        elif bloque.x0 >= rect.x1 - 1:
            x1 = min(x1, bloque.x0)
    if x1 - x0 < max(rect.x1 - rect.x0, 10):
        return (contenido.x0, contenido.x1)
    return (x0, x1)


def alineacion_bloque(lineas, contenedor):
    """Deduce la alineacion comparando los margenes izquierdo y derecho de cada linea."""
    ancho = contenedor[1] - contenedor[0]
    if ancho <= 0:
        return "left"
    tolerancia = max(3.0, 0.02 * ancho)
    votos = {"left": 0, "center": 0, "right": 0}
    llenas = 0
    ultima_llena = True
    for indice, linea in enumerate(lineas):
        izquierda = linea["bbox"][0] - contenedor[0]
        derecha = contenedor[1] - linea["bbox"][2]
        if izquierda <= tolerancia and derecha <= tolerancia:
            llenas += 1
            continue
        if indice == len(lineas) - 1:
            ultima_llena = False
        if abs(izquierda - derecha) <= tolerancia:
            votos["center"] += 1
        elif izquierda <= tolerancia:
            votos["left"] += 1
        elif derecha <= tolerancia:
            votos["right"] += 1
        else:
            votos["left"] += 1

    if len(lineas) >= 3 and llenas == len(lineas) - 1 and not ultima_llena and votos["left"] == 1:
        return "justify"
    ganador, cuenta = max(votos.items(), key=lambda kv: kv[1])
    return ganador if cuenta else "left"


def continua_parrafo(grupo, siguiente, bloques, alineaciones):
    """Decide si la linea siguiente es continuacion por ajuste de linea del mismo parrafo."""
    actual = grupo[-1]
    if siguiente["bloque"] != actual["bloque"]:
        return False
    if len(actual["firma"]) != 1 or actual["firma"] != siguiente["firma"]:
        return False

    tamano = actual["principal"]["size"]
    salto = siguiente["origin"][1] - actual["origin"][1]
    if salto < tamano * 0.8 or salto > tamano * 2.5:
        return False
    if len(grupo) > 1:
        salto_previo = grupo[1]["origin"][1] - grupo[0]["origin"][1]
        if abs(salto - salto_previo) > max(1.0, salto_previo * 0.25):
            return False

    alineacion = alineaciones.get(actual["bloque"], "left")
    if alineacion in ("left", "justify") and siguiente["bbox"][0] > actual["bbox"][0] + tamano * 0.6:
        return False

    columna = bloques[actual["bloque"]]
    ancho_columna = columna.x1 - columna.x0
    texto_actual = actual["text"].strip()
    if ancho_columna <= 0 or not texto_actual:
        return False

    # La linea se considera ajustada si la primera palabra de la siguiente no cabia al final.
    ancho_actual = actual["bbox"][2] - actual["bbox"][0]
    medio_caracter = ancho_actual / max(len(texto_actual), 1)
    palabras = siguiente["text"].split()
    if not palabras:
        return False
    necesario = ancho_actual + medio_caracter * (1 + len(palabras[0]))
    return necesario > ancho_columna + medio_caracter * 0.5


def limite_inferior(rect, columna, bloques, propio, page):
    """Hasta donde puede crecer un parrafo sin invadir el bloque siguiente."""
    limite = page.rect.y1 - 10
    for i, bloque in enumerate(bloques):
        if i == propio or bloque.y0 < rect[3] - 1:
            continue
        if min(columna[1], bloque.x1) - max(columna[0], bloque.x0) <= 1:
            continue
        limite = min(limite, bloque.y0 - 1)
    return limite


def collect_units(page, pno):
    """Unidades editables de la pagina: parrafos completos cuando la tipografia es uniforme."""
    lineas, bloques = collect_lines(page)
    if not lineas:
        return []

    contenido = fitz.Rect(bloques[0])
    for bloque in bloques[1:]:
        contenido |= bloque
    if contenido.is_empty:
        contenido = fitz.Rect(page.rect)

    por_bloque = {}
    for linea in lineas:
        por_bloque.setdefault(linea["bloque"], []).append(linea)

    alineaciones = {}
    contenedores = {}
    for indice, grupo in por_bloque.items():
        rect = bloques[indice]
        if len(grupo) > 1:
            contenedor = (rect.x0, rect.x1)
        else:
            contenedor = contenedor_de(rect, bloques, contenido, indice)
        alineaciones[indice] = alineacion_bloque(grupo, contenedor)
        contenedores[indice] = contenedor

    unidades = []
    i = 0
    while i < len(lineas):
        grupo = [lineas[i]]
        while i + 1 < len(lineas) and continua_parrafo(grupo, lineas[i + 1], bloques, alineaciones):
            grupo.append(lineas[i + 1])
            i += 1

        bloque = grupo[0]["bloque"]
        principal = grupo[0]["principal"]
        bbox = [
            min(l["bbox"][0] for l in grupo),
            min(l["bbox"][1] for l in grupo),
            max(l["bbox"][2] for l in grupo),
            max(l["bbox"][3] for l in grupo),
        ]
        if len(grupo) > 1:
            saltos = [grupo[j + 1]["origin"][1] - grupo[j]["origin"][1] for j in range(len(grupo) - 1)]
            interlineado = sum(saltos) / len(saltos)
            columna = (bbox[0], bbox[2])
        else:
            interlineado = principal["size"] * 1.2
            columna = contenedores[bloque]

        unidades.append({
            "id": "p%d-l%d" % (pno, len(unidades)),
            "text": " ".join(l["text"].strip() for l in grupo) if len(grupo) > 1 else grupo[0]["text"],
            "bbox": bbox,
            "align": alineaciones.get(bloque, "left"),
            "multiline": len(grupo) > 1,
            "leading": interlineado,
            "columna": list(columna),
            "limite": limite_inferior(bbox, columna, bloques, bloque, page),
            "lineas": grupo,
            "principal": principal,
            "size": round(principal["size"], 2),
            "font": principal["font"],
            "flags": principal["flags"],
            "color": principal["color"],
        })
        i += 1

    return unidades


def collect_images(page, pno):
    """Devuelve las imagenes visibles de la pagina con ids deterministas."""
    items = []
    vistos = set()
    for info in page.get_images(full=True):
        xref = info[0]
        if xref in vistos:
            continue
        vistos.add(xref)
        try:
            rects = page.get_image_rects(xref)
        except Exception:
            rects = []
        for ri, rect in enumerate(rects):
            if rect.width < 4 or rect.height < 4:
                continue
            items.append({
                "id": "p%d-i%d-%d" % (pno, xref, ri),
                "xref": xref,
                "bbox": [rect.x0, rect.y0, rect.x1, rect.y1],
            })
    return items


def cmd_extract(pdf_path, out_dir):
    os.makedirs(out_dir, exist_ok=True)
    doc = fitz.open(pdf_path)
    if doc.needs_pass:
        doc.close()
        print("El PDF esta protegido con contrasena.")
        return 3

    paginas = []
    for pno in range(1, doc.page_count + 1):
        page = doc[pno - 1]
        pix = page.get_pixmap(matrix=fitz.Matrix(RENDER_ZOOM, RENDER_ZOOM), alpha=False)
        nombre_img = "page-%d.png" % pno
        pix.save(os.path.join(out_dir, nombre_img))

        textos = []
        for item in collect_units(page, pno):
            textos.append({
                "id": item["id"],
                "text": item["text"],
                "bbox": [round(v, 2) for v in item["bbox"]],
                "size": item["size"],
                "color": int_to_hex(item["color"]),
                "bg": sample_bg(pix, fitz.Rect(item["lineas"][0]["bbox"]), RENDER_ZOOM),
                "family": css_family_from_flags(item["flags"]),
                "bold": bool(item["flags"] & FLAG_BOLD),
                "italic": bool(item["flags"] & FLAG_ITALIC),
                "align": item["align"],
                "multiline": item["multiline"],
                "leading": round(item["leading"], 2),
                "column": [round(v, 2) for v in item["columna"]],
            })

        imagenes = [
            {"id": img["id"], "bbox": [round(v, 2) for v in img["bbox"]]}
            for img in collect_images(page, pno)
        ]

        paginas.append({
            "number": pno,
            "width": round(page.rect.width, 2),
            "height": round(page.rect.height, 2),
            "image": nombre_img,
            "texts": textos,
            "images": imagenes,
        })

    with open(os.path.join(out_dir, "manifest.json"), "w", encoding="utf-8") as fh:
        json.dump({"pages": paginas}, fh, ensure_ascii=False)
    doc.close()
    print("OK")
    return 0


def apply_text_redactions(page):
    """Elimina solo el texto marcado, respetando imagenes y graficos."""
    sin_imagen = getattr(fitz, "PDF_REDACT_IMAGE_NONE", 0)
    sin_lineart = getattr(fitz, "PDF_REDACT_LINE_ART_NONE", None)
    intentos = []
    if sin_lineart is not None:
        intentos.append({"images": sin_imagen, "graphics": sin_lineart})
    intentos.append({"images": sin_imagen})
    intentos.append({})
    for kwargs in intentos:
        try:
            page.apply_redactions(**kwargs)
            return True
        except Exception:
            continue
    return False


def delete_image(page, xref):
    try:
        page.delete_image(xref)
        return True
    except Exception:
        pass
    try:
        for rect in page.get_image_rects(xref):
            page.add_redact_annot(rect)
        quitar = getattr(fitz, "PDF_REDACT_IMAGE_REMOVE", 2)
        sin_texto = getattr(fitz, "PDF_REDACT_TEXT_NONE", None)
        if sin_texto is not None:
            page.apply_redactions(images=quitar, text=sin_texto)
        else:
            page.apply_redactions(images=quitar)
        return True
    except Exception:
        return False


def replace_image(page, xref, ruta):
    if not ruta or not os.path.exists(ruta):
        return False
    try:
        page.replace_image(xref, filename=ruta)
        return True
    except Exception:
        pass
    try:
        rects = list(page.get_image_rects(xref))
        delete_image(page, xref)
        for rect in rects:
            page.insert_image(rect, filename=ruta, keep_proportion=True, overlay=True)
        return True
    except Exception:
        return False


def get_font_object(fontfile):
    if fontfile not in _font_objects:
        try:
            _font_objects[fontfile] = fitz.Font(fontfile=fontfile)
        except Exception:
            _font_objects[fontfile] = None
    return _font_objects[fontfile]


def normalizar(nombre):
    return re.sub(r"[^a-z0-9]", "", (nombre or "").lower())


def familia_base(basefont):
    """Convierte 'TimesNewRomanPS-BoldMT' en la familia normalizada 'times'."""
    clave = normalizar(re.split(r"[-,]", basefont or "")[0])
    for sufijo in ("psmt", "ps", "mt"):
        if clave.endswith(sufijo) and len(clave) > len(sufijo) + 2:
            clave = clave[: -len(sufijo)]
            break
    return ALIAS_FAMILIAS.get(clave, clave)


def alias_fuente(ruta):
    if ruta not in _alias_fuentes:
        _alias_fuentes[ruta] = "PDFinaF%d" % (len(_alias_fuentes) + 1)
    return _alias_fuentes[ruta]


def cubre(fuente, texto):
    try:
        for caracter in set(texto or ""):
            if caracter.strip() and not fuente.has_glyph(ord(caracter)):
                return False
    except Exception:
        return False
    return True


def indice_fuentes_doc(doc):
    """Mapa nombre-normalizado -> xref de todas las fuentes incrustadas del documento."""
    global _indice_doc
    if _indice_doc is None:
        indice = {}
        for pno in range(doc.page_count):
            try:
                fuentes = doc[pno].get_fonts(full=True)
            except Exception:
                continue
            for info in fuentes:
                ext = (info[1] or "").lower()
                clave = normalizar((info[3] or "").split("+")[-1])
                if clave and ext in EXTENSIONES_FUENTE:
                    indice.setdefault(clave, info[0])
        _indice_doc = indice
    return _indice_doc


def fuente_incrustada(doc, basefont):
    """Vuelca a disco la fuente incrustada del PDF que coincide con basefont."""
    clave = normalizar((basefont or "").split("+")[-1])
    if not clave:
        return None
    if clave in _fuentes_extraidas:
        return _fuentes_extraidas[clave]

    ruta = None
    xref = indice_fuentes_doc(doc).get(clave)
    if xref:
        try:
            datos = doc.extract_font(xref)
            buffer = datos[3] if datos and len(datos) > 3 else None
            if buffer:
                ext = (datos[1] or "ttf").lower()
                ruta = os.path.join(
                    tempfile.gettempdir(),
                    "pdfina_font_%d_%s.%s" % (os.getpid(), clave[:32], ext),
                )
                with open(ruta, "wb") as fh:
                    fh.write(buffer)
        except Exception:
            ruta = None
    _fuentes_extraidas[clave] = ruta
    return ruta


def indice_fuentes_sistema():
    global _indice_sistema
    if _indice_sistema is None:
        indice = {}
        for carpeta in DIRECTORIOS_FUENTES:
            if not os.path.isdir(carpeta):
                continue
            for raiz, _, archivos in os.walk(carpeta):
                for archivo in archivos:
                    nombre, ext = os.path.splitext(archivo)
                    if ext.lower().lstrip(".") not in ("ttf", "otf", "ttc"):
                        continue
                    indice.setdefault(normalizar(nombre), os.path.join(raiz, archivo))
        _indice_sistema = indice
    return _indice_sistema


def fuente_sistema(basefont, flags):
    """Busca en el sistema una fuente de la misma familia y estilo que la original."""
    familia = familia_base(basefont)
    if not familia:
        return None

    estilo = (bool(flags & FLAG_BOLD), bool(flags & FLAG_ITALIC))
    clave = (familia, estilo)
    if clave in _fuentes_sistema:
        return _fuentes_sistema[clave]

    indice = indice_fuentes_sistema()
    candidatos = [familia + sufijo for sufijo in SUFIJOS_ESTILO[estilo]]
    candidatos.append(normalizar(basefont))
    candidatos.append(familia)

    ruta = None
    for candidato in candidatos:
        if candidato and candidato in indice:
            ruta = indice[candidato]
            break
    _fuentes_sistema[clave] = ruta
    return ruta


def resolve_font(doc, span, texto):
    """Reutiliza la fuente incrustada original; si no sirve, busca la misma familia en el sistema."""
    flags = int(span.get("flags", 0))
    respaldo = (base14_from_flags(flags), None)
    basefont = span.get("font") or ""
    original = span.get("text", "")

    ruta = fuente_incrustada(doc, basefont)
    if ruta:
        fuente = get_font_object(ruta)
        # Si la fuente no reconoce ni su propio texto, su cmap no es fiable (subconjunto CID).
        if fuente is not None and cubre(fuente, original) and cubre(fuente, texto):
            return (alias_fuente(ruta), ruta)

    ruta = fuente_sistema(basefont, flags)
    if ruta:
        fuente = get_font_object(ruta)
        if fuente is not None and cubre(fuente, texto):
            return (alias_fuente(ruta), ruta)

    return respaldo


def text_width(texto, fontname, fontfile, size):
    try:
        if fontfile:
            fuente = get_font_object(fontfile)
            if fuente is not None:
                return fuente.text_length(texto, fontsize=size)
        return fitz.get_text_length(texto, fontname=fontname, fontsize=size)
    except Exception:
        return size * 0.5 * len(texto)


def repartir_texto(spans, original, nuevo):
    """Reparte el texto editado entre los spans de la linea conservando prefijo y sufijo comunes."""
    resultado = [s["text"] for s in spans]
    if nuevo == original or not spans:
        return resultado

    limite = min(len(original), len(nuevo))
    prefijo = 0
    while prefijo < limite and original[prefijo] == nuevo[prefijo]:
        prefijo += 1
    sufijo = 0
    while sufijo < limite - prefijo and original[len(original) - 1 - sufijo] == nuevo[len(nuevo) - 1 - sufijo]:
        sufijo += 1

    fin_original = len(original) - sufijo
    medio = nuevo[prefijo:len(nuevo) - sufijo]

    posicion = 0
    insertado = False
    for i, span in enumerate(spans):
        inicio = posicion
        fin = inicio + len(span["text"])
        posicion = fin
        if fin <= prefijo or inicio >= fin_original:
            continue
        cabeza = span["text"][: max(0, prefijo - inicio)]
        cola = span["text"][max(0, fin_original - inicio):]
        resultado[i] = cabeza + (medio if not insertado else "") + cola
        insertado = True

    if not insertado and medio:
        destino = 0
        posicion = 0
        for i, span in enumerate(spans):
            posicion += len(span["text"])
            if posicion <= prefijo:
                destino = i
        corte = prefijo - sum(len(s["text"]) for s in spans[:destino])
        corte = max(0, min(corte, len(spans[destino]["text"])))
        resultado[destino] = spans[destino]["text"][:corte] + medio + spans[destino]["text"][corte:]

    return resultado


def limpiar_linea(texto):
    return " ".join(texto.replace("\r", " ").replace("\n", " ").split())


def nuevo_dibujo(x, y, texto, fontname, fontfile, size, span, derot):
    punto = fitz.Point(x, y)
    if derot is not None:
        punto = punto * derot
    return {
        "punto": punto,
        "texto": texto,
        "fontname": fontname,
        "fontfile": fontfile,
        "size": size,
        "color": color_to_rgb(span.get("color", 0)),
        "respaldo": base14_from_flags(span.get("flags", 0)),
    }


def ejecutar_dibujo(page, dibujo):
    if not dibujo["texto"].strip():
        return
    try:
        page.insert_text(dibujo["punto"], dibujo["texto"], fontname=dibujo["fontname"],
                         fontfile=dibujo["fontfile"], fontsize=dibujo["size"], color=dibujo["color"])
    except Exception:
        try:
            page.insert_text(dibujo["punto"], dibujo["texto"], fontname=dibujo["respaldo"],
                             fontsize=dibujo["size"], color=dibujo["color"],
                             encoding=fitz.TEXT_ENCODING_LATIN)
        except Exception:
            pass


def rect_redaccion(bbox, derot, relleno_izq=0.5, relleno_der=0.5):
    rect = fitz.Rect(bbox)
    if derot is not None:
        rect = rect * derot
    rect.normalize()
    return fitz.Rect(rect.x0 - relleno_izq, rect.y0 - 0.5, rect.x1 + relleno_der, rect.y1 + 0.5)


def planificar_linea(doc, page, unidad, nuevo, derot):
    """Redibuja solo los spans afectados y desplaza el resto para conservar la alineacion."""
    linea = unidad["lineas"][0]
    spans = linea["spans"]
    nuevos = [limpiar_linea(t) if t.strip() else t for t in repartir_texto(spans, linea["text"], nuevo)]

    infos = []
    for span, texto in zip(spans, nuevos):
        fontname, fontfile = resolve_font(doc, span, texto)
        size = span["size"]
        anterior = text_width(span["text"], fontname, fontfile, size)
        actual = text_width(texto, fontname, fontfile, size)
        infos.append({"texto": texto, "fontname": fontname, "fontfile": fontfile,
                      "size": size, "delta": actual - anterior, "ancho": actual})

    total = sum(info["delta"] for info in infos)
    if unidad["align"] == "center":
        acumulado = -total / 2.0
    elif unidad["align"] == "right":
        acumulado = -total
    else:
        acumulado = 0.0

    dibujos = []
    primero = None
    for i, (span, info) in enumerate(zip(spans, infos)):
        movido = abs(acumulado) > 0.05
        if info["texto"] != span["text"] or movido:
            if primero is None:
                primero = i
            x = span["origin"][0] + acumulado
            disponible = max(unidad["columna"][1] - x, info["ancho"], 10)
            size = info["size"]
            while size > 4 and text_width(info["texto"], info["fontname"], info["fontfile"], size) > disponible:
                size -= 0.25
            dibujos.append(nuevo_dibujo(x, span["origin"][1], info["texto"], info["fontname"],
                                        info["fontfile"], size, span, derot))
        acumulado += info["delta"]

    if primero is None:
        return None

    afectados = spans[primero:]
    bbox = [
        min(s["bbox"][0] for s in afectados),
        min(s["bbox"][1] for s in afectados),
        max(s["bbox"][2] for s in afectados),
        max(s["bbox"][3] for s in afectados),
    ]
    return {
        "rects": [rect_redaccion(bbox, derot, 0.5 if primero == 0 else 0.0, 0.5)],
        "dibujos": dibujos,
    }


def envolver(texto, ancho, fontname, fontfile, size):
    """Reparte el texto en lineas que quepan en el ancho de la columna."""
    lineas = []
    for parrafo in texto.replace("\r", "\n").split("\n"):
        palabras = parrafo.split()
        if not palabras:
            lineas.append("")
            continue
        actual = palabras[0]
        for palabra in palabras[1:]:
            prueba = actual + " " + palabra
            if text_width(prueba, fontname, fontfile, size) <= ancho:
                actual = prueba
            else:
                lineas.append(actual)
                actual = palabra
        lineas.append(actual)
    return lineas


def dibujos_de_linea(texto, x0, ancho, y, info, span, derot, justificar):
    fontname, fontfile, size = info["fontname"], info["fontfile"], info["size"]
    ancho_texto = text_width(texto, fontname, fontfile, size)

    if justificar:
        palabras = texto.split()
        if len(palabras) > 1:
            anchos = [text_width(p, fontname, fontfile, size) for p in palabras]
            hueco = (ancho - sum(anchos)) / (len(palabras) - 1)
            espacio = text_width(" ", fontname, fontfile, size)
            if espacio <= hueco <= espacio * 4:
                dibujos = []
                x = x0
                for palabra, avance in zip(palabras, anchos):
                    dibujos.append(nuevo_dibujo(x, y, palabra, fontname, fontfile, size, span, derot))
                    x += avance + hueco
                return dibujos

    if info["align"] == "center":
        x = (x0 + x0 + ancho) / 2.0 - ancho_texto / 2.0
    elif info["align"] == "right":
        x = x0 + ancho - ancho_texto
    else:
        x = x0
    return [nuevo_dibujo(x, y, texto, fontname, fontfile, size, span, derot)]


def planificar_parrafo(doc, page, unidad, nuevo, derot):
    """Vuelve a componer el parrafo entero respetando columna, interlineado y alineacion."""
    principal = unidad["principal"]
    fontname, fontfile = resolve_font(doc, principal, nuevo)
    x0, x1 = unidad["columna"]
    ancho = x1 - x0
    if ancho <= 10:
        return None

    original = principal["size"]
    size = original
    interlineado = unidad["leading"]
    base_y = unidad["lineas"][0]["origin"][1]

    while True:
        lineas = envolver(nuevo, ancho, fontname, fontfile, size)
        cabidas = int((unidad["limite"] - base_y) / interlineado) + 1
        if len(lineas) <= max(cabidas, len(unidad["lineas"])) or size <= original * 0.6:
            break
        size -= 0.25
        interlineado = unidad["leading"] * size / original

    info = {"fontname": fontname, "fontfile": fontfile, "size": size, "align": unidad["align"]}
    dibujos = []
    for indice, linea in enumerate(lineas):
        if not linea:
            continue
        justificar = unidad["align"] == "justify" and indice < len(lineas) - 1
        dibujos.extend(dibujos_de_linea(linea, x0, ancho, base_y + indice * interlineado,
                                        info, principal, derot, justificar))

    return {
        "rects": [rect_redaccion(l["bbox"], derot) for l in unidad["lineas"]],
        "dibujos": dibujos,
    }


def cmd_apply(pdf_path, edits_path, out_path):
    with open(edits_path, "r", encoding="utf-8") as fh:
        ediciones = json.load(fh)

    textos_editados = ediciones.get("texts") or {}
    imagenes_borradas = set(ediciones.get("deleteImages") or [])
    imagenes_reemplazadas = ediciones.get("replaceImages") or {}

    doc = fitz.open(pdf_path)
    if doc.needs_pass:
        doc.close()
        print("El PDF esta protegido con contrasena.")
        return 3

    for pno in range(1, doc.page_count + 1):
        page = doc[pno - 1]

        for imagen in collect_images(page, pno):
            if imagen["id"] in imagenes_borradas:
                delete_image(page, imagen["xref"])
            elif imagen["id"] in imagenes_reemplazadas:
                replace_image(page, imagen["xref"], imagenes_reemplazadas[imagen["id"]])

        unidades = []
        for unidad in collect_units(page, pno):
            nuevo = textos_editados.get(unidad["id"])
            if nuevo is not None and nuevo != unidad["text"]:
                unidades.append((unidad, nuevo))

        if not unidades:
            continue

        rotacion = page.rotation
        derot = page.derotation_matrix if rotacion else None
        if rotacion:
            page.set_rotation(0)

        planes = []
        for unidad, nuevo in unidades:
            if unidad["multiline"]:
                plan = planificar_parrafo(doc, page, unidad, nuevo, derot)
            else:
                plan = planificar_linea(doc, page, unidad, nuevo, derot)
            if plan:
                planes.append(plan)

        for plan in planes:
            for rect in plan["rects"]:
                page.add_redact_annot(rect, fill=None)
        apply_text_redactions(page)

        for plan in planes:
            for dibujo in plan["dibujos"]:
                ejecutar_dibujo(page, dibujo)

        if rotacion:
            page.set_rotation(rotacion)

    doc.save(out_path, garbage=3, deflate=True, clean=True)
    doc.close()
    print("OK")
    return 0


def limpiar_fuentes_temporales():
    """Borra las fuentes incrustadas que se volcaron al directorio temporal."""
    for ruta in _fuentes_extraidas.values():
        if ruta and os.path.exists(ruta):
            try:
                os.remove(ruta)
            except OSError:
                pass
    _fuentes_extraidas.clear()
    _fuentes_sistema.clear()
    _alias_fuentes.clear()
    _font_objects.clear()


def main():
    supervisar()
    registrar_limpieza(limpiar_fuentes_temporales)

    if len(sys.argv) >= 2 and sys.argv[1] == "extract" and len(sys.argv) == 4:
        return cmd_extract(sys.argv[2], sys.argv[3])
    if len(sys.argv) >= 2 and sys.argv[1] == "apply" and len(sys.argv) == 5:
        return cmd_apply(sys.argv[2], sys.argv[3], sys.argv[4])
    print("Uso: mservice-epdf.py extract <entrada.pdf> <directorio_salida>")
    print("     mservice-epdf.py apply <entrada.pdf> <ediciones.json> <salida.pdf>")
    return 1


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception:
        traceback.print_exc()
        sys.exit(1)
