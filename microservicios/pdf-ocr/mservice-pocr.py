"""Microservicio de OCR para PDFina.

Convierte un PDF escaneado (paginas que solo contienen la imagen del escaner)
en un PDF con texto real, manteniendo la apariencia lo mas fiel posible.

Modos:
    fiel  -> se conserva la pagina original tal cual (bit a bit) y se le anade
             encima una capa de texto invisible con el resultado del OCR. El PDF
             se ve identico al escaneo pero se puede buscar, seleccionar y copiar.
    texto -> se reconstruye la pagina: el texto se escribe con fuentes reales y
             las zonas graficas detectadas (fotos, firmas, sellos, tablas) se
             recortan del escaneo y se colocan en su posicion original.

Dos cuidados importantes:

- Las fotos, sellos y logotipos se tratan como un bloque rectangular cerrado.
  Todo lo que hay dentro de ese rectangulo (incluidas las letras impresas en la
  propia imagen) forma parte de la imagen: no se transcribe ni se puede
  seleccionar, y el recorte se pega intacto.
- Antes de pasar el OCR se mide la inclinacion del escaneo y la pagina se
  endereza. Un escaneo torcido descuadra las cajas de las palabras y hace que
  Tesseract falle o devuelva cuerpos de letra disparatados.

Las paginas que ya tienen texto digital se copian sin tocar, salvo que se pase
--forzar, de forma que nunca se pierde calidad del original.

Uso:
    mservice-pocr.py <entrada.pdf> <salida.pdf> [--idioma spa] [--modo fiel|texto]
                     [--dpi 300] [--forzar] [--sin-enderezar]
"""

import argparse
import html
import json
import os
import re
import shutil
import statistics
import subprocess
import sys
import tempfile
from collections import Counter

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), os.pardir, "comun"))

from pdfina_proceso import CREATE_NO_WINDOW, registrar_limpieza, supervisar

try:
    import fitz
except ImportError:
    print("Falta la dependencia PyMuPDF. Instalala con: pip install pymupdf")
    sys.exit(2)


DPI_MASCARA = 150
DPI_INCLINACION = 150
UMBRAL_TINTA = 205
UMBRAL_MEDIO_BAJO = 60
UMBRAL_MEDIO_ALTO = 210
CONFIANZA_MINIMA = 40
MINIMO_TEXTO_DIGITAL = 40
FUENTE = "helv"
ALTURA_FUENTE = 0.925
MINIMO_REGION_PT = 11.0
MINIMO_FOTO_PT = 24.0
DILATACION_TEXTO_PX = 3
MARGEN_FOTO_PT = 2.0
SOLAPE_FOTO = 0.55
AREA_MAXIMA_FOTO = 0.65
COBERTURA_TEXTO = 0.35
UMBRAL_TONO = 0.45
ANGULO_MAXIMO = 8.0
ANGULO_MINIMO = 0.12
PASO_GRUESO = 0.5
PASO_FINO = 0.05
MUESTRAS_INCLINACION = 12000
TOLERANCIA_TAMANO = 0.08
HOLGURA_ANCHO = 1.02
ENCOGIDO_MAXIMO = 0.85

TABLA_TINTA = bytes(1 if valor < UMBRAL_TINTA else 0 for valor in range(256))
TABLA_MEDIOS = bytes(1 if UMBRAL_MEDIO_BAJO <= valor <= UMBRAL_MEDIO_ALTO else 0 for valor in range(256))

CLASES_LINEA = ("ocr_line", "ocr_header", "ocr_textfloat", "ocr_caption")

PATRON_ELEMENTO = re.compile(
    r"<(?:div|p|span)\s+class='(?P<clase>[a-z_]+)'[^>]*?title=(?P<comilla>[\"'])(?P<titulo>.*?)(?P=comilla)[^>]*>(?P<texto>[^<]*)",
    re.S,
)

RUTAS_TESSERACT = (
    r"C:\Program Files\Tesseract-OCR\tesseract.exe",
    r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
    "/usr/bin/tesseract",
    "/usr/local/bin/tesseract",
    "/opt/homebrew/bin/tesseract",
)

_temporales = []


def limpiar_temporales():
    for ruta in _temporales:
        try:
            if ruta and os.path.exists(ruta):
                os.remove(ruta)
        except Exception:
            pass
    _temporales.clear()


def localizar_tesseract():
    """Devuelve la ruta del ejecutable de Tesseract o None si no esta instalado."""
    ruta = shutil.which("tesseract")
    if ruta:
        return ruta

    for candidata in RUTAS_TESSERACT:
        if os.path.isfile(candidata):
            return candidata

    return None


def ejecutar_tesseract(exe, argumentos, timeout=300):
    return subprocess.run(
        [exe] + argumentos,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        timeout=timeout,
        creationflags=CREATE_NO_WINDOW if os.name == "nt" else 0,
    )


def idiomas_instalados(exe):
    try:
        resultado = ejecutar_tesseract(exe, ["--list-langs"], timeout=60)
    except Exception:
        return set()
    salida = (resultado.stdout or "") + (resultado.stderr or "")
    idiomas = set()
    for linea in salida.splitlines()[1:]:
        linea = linea.strip()
        if linea and " " not in linea:
            idiomas.add(linea)
    return idiomas


def idioma_utilizable(exe, idioma):
    """Descarta los idiomas que no estan instalados y deja siempre uno valido."""
    disponibles = idiomas_instalados(exe)
    if not disponibles:
        return idioma
    partes = [p for p in idioma.split("+") if p in disponibles]
    if partes:
        return "+".join(partes)
    if "eng" in disponibles:
        return "eng"
    return sorted(disponibles)[0]


def ocr_hocr(exe, imagen, idioma, timeout):
    """Lanza Tesseract y devuelve el hOCR.

    Frente al TSV, el hOCR aporta la estructura de la pagina: la linea base y la
    altura real de cada renglon y, sobre todo, que bloques son imagen.
    """
    base = os.path.splitext(imagen)[0]
    destino = base + ".hocr"
    _temporales.append(destino)

    resultado = ejecutar_tesseract(
        exe,
        [imagen, base, "-l", idioma, "--psm", "3", "hocr"],
        timeout=timeout,
    )
    if resultado.returncode != 0 or not os.path.isfile(destino):
        raise RuntimeError((resultado.stderr or "Tesseract devolvio un error").strip())

    with open(destino, "r", encoding="utf-8", errors="replace") as fichero:
        return fichero.read()


def campos_titulo(titulo):
    """Convierte el atributo title del hOCR en un diccionario de campos."""
    campos = {}
    for parte in titulo.replace("\n", " ").split(";"):
        trozos = parte.split()
        if trozos:
            campos[trozos[0]] = trozos[1:]
    return campos


def numeros(valores, cantidad):
    if len(valores) < cantidad:
        return None
    try:
        return [float(valor) for valor in valores[:cantidad]]
    except ValueError:
        return None


def parsear_hocr(hocr, escala):
    """Devuelve (renglones, fotos) con las cajas en puntos del render."""
    lineas = []
    fotos = []
    actual = None

    for elemento in PATRON_ELEMENTO.finditer(hocr):
        clase = elemento.group("clase")
        campos = campos_titulo(elemento.group("titulo"))
        caja = numeros(campos.get("bbox", []), 4)
        if caja is None:
            continue

        if clase == "ocr_photo":
            fotos.append(fitz.Rect(caja) * escala)
            continue

        if clase in CLASES_LINEA:
            base = numeros(campos.get("baseline", []), 2) or [0.0, 0.0]
            altura = numeros(campos.get("x_size", []), 1)
            actual = {
                "px": caja,
                "pendiente": base[0],
                "desfase": base[1],
                "x_size": altura[0] if altura else 0.0,
                "palabras": [],
            }
            lineas.append(actual)
            continue

        if clase != "ocrx_word" or actual is None:
            continue

        texto = html.unescape(elemento.group("texto")).strip()
        if not texto or caja[2] <= caja[0] or caja[3] <= caja[1]:
            continue
        confianza = numeros(campos.get("x_wconf", []), 1)
        if confianza and confianza[0] < CONFIANZA_MINIMA:
            continue

        actual["palabras"].append({
            "texto": texto,
            "px": tuple(caja),
            "rect": fitz.Rect(caja) * escala,
        })

    return [linea for linea in lineas if linea["palabras"]], fotos


def base_de_linea(linea, x_px):
    """Altura de la linea base del renglon en la x indicada, en pixeles del render."""
    x0, _, _, y1 = linea["px"]
    return y1 + linea["desfase"] + linea["pendiente"] * (x_px - x0)


def puntos_de_tinta(pix):
    """Muestrea los arranques de tinta de cada fila.

    Solo se guardan las transiciones papel -> tinta: un parrafo aporta un punto
    por letra mientras que una foto solo aporta uno por fila, asi que las
    manchas grandes no falsean la medida de la inclinacion.
    """
    datos = pix.samples
    canales = pix.n
    desplazamiento = 1 if canales >= 3 else 0
    # Se recorren todas las filas: saltarse filas concentraria los puntos en unas
    # pocas alturas y el angulo cero saldria siempre ganador.
    paso = max(1, pix.height // 2000)

    xs = []
    ys = []
    for y in range(0, pix.height, paso):
        base = y * pix.stride
        fila = datos[base + desplazamiento:base + pix.width * canales:canales]
        marcas = fila.translate(TABLA_TINTA)
        posicion = marcas.find(b"\x00\x01")
        while posicion >= 0:
            xs.append(posicion + 1)
            ys.append(y)
            posicion = marcas.find(b"\x00\x01", posicion + 2)

    if len(xs) > MUESTRAS_INCLINACION:
        salto = len(xs) // MUESTRAS_INCLINACION + 1
        xs = xs[::salto]
        ys = ys[::salto]
    return xs, ys


def nitidez(xs, ys, angulo, desplazamiento):
    """Cuanto se concentran los renglones al girar la pagina ese angulo."""
    matriz = fitz.Matrix(angulo)
    b = matriz.b
    d = matriz.d
    cuentas = Counter([int(x * b + y * d) + desplazamiento for x, y in zip(xs, ys)])
    return sum(valor * valor for valor in cuentas.values())


def estimar_inclinacion(pagina):
    """Angulo, en grados de fitz.Matrix, que hay que aplicar para enderezar."""
    pix = pagina.get_pixmap(dpi=DPI_INCLINACION, colorspace=fitz.csGRAY, alpha=False)
    xs, ys = puntos_de_tinta(pix)
    if len(xs) < 200:
        return 0.0

    desplazamiento = pix.width + pix.height
    mejor = 0.0
    mejor_puntuacion = None

    pasos = int(ANGULO_MAXIMO / PASO_GRUESO)
    for indice in range(-pasos, pasos + 1):
        angulo = indice * PASO_GRUESO
        puntuacion = nitidez(xs, ys, angulo, desplazamiento)
        if mejor_puntuacion is None or puntuacion > mejor_puntuacion:
            mejor_puntuacion = puntuacion
            mejor = angulo

    grueso = mejor
    finos = int(PASO_GRUESO / PASO_FINO)
    for indice in range(-finos, finos + 1):
        angulo = round(grueso + indice * PASO_FINO, 2)
        if abs(angulo) > ANGULO_MAXIMO:
            continue
        puntuacion = nitidez(xs, ys, angulo, desplazamiento)
        if puntuacion > mejor_puntuacion:
            mejor_puntuacion = puntuacion
            mejor = angulo

    return 0.0 if abs(mejor) < ANGULO_MINIMO else round(mejor, 2)


def matriz_de_render(dpi, angulo):
    zoom = dpi / 72.0
    return fitz.Matrix(zoom, zoom) * fitz.Matrix(angulo)


def texto_a_latin1(texto):
    """Las fuentes base14 usan latin-1; se sustituye lo que no encaja."""
    return texto.encode("latin-1", "replace").decode("latin-1")


def tamano_ajustado(texto, rect):
    """Calcula el cuerpo de letra para que la palabra ocupe el ancho detectado."""
    referencia = fitz.get_text_length(texto, fontname=FUENTE, fontsize=10.0)
    if referencia <= 0:
        return max(4.0, min(rect.height * 0.8, 72.0))
    tamano = 10.0 * rect.width / referencia
    return max(2.0, min(tamano, 300.0))


def pagina_tiene_texto(pagina):
    return len(pagina.get_text("text").strip()) >= MINIMO_TEXTO_DIGITAL


def mediana(valores):
    return statistics.median(valores) if valores else 0.0


def calcular_tamanos(lineas, escala):
    """Fija un cuerpo de letra por renglon y lo unifica en toda la pagina.

    El ancho medido palabra a palabra es el que mejor reproduce el original,
    pero un escaneo sucio puede devolver cajas absurdas. Por eso se acota con la
    altura de renglon que mide Tesseract (x_size) y despues los renglones con
    tamanos casi iguales se unifican para que el documento no baile.
    """
    for linea in lineas:
        anchos = [
            tamano_ajustado(texto_a_latin1(palabra["texto"]), palabra["rect"])
            for palabra in linea["palabras"]
        ]
        por_ancho = mediana(anchos)
        por_altura = linea["x_size"] * escala / ALTURA_FUENTE

        if por_altura <= 0:
            tamano = por_ancho
        elif por_ancho <= 0:
            tamano = por_altura
        else:
            tamano = min(max(por_ancho, por_altura * 0.8), por_altura * 1.25)

        linea["tamano"] = max(2.0, tamano)

    pesos = Counter()
    for linea in lineas:
        pesos[round(linea["tamano"] * 4) / 4] += len(linea["palabras"])

    dominantes = [tamano for tamano, peso in pesos.most_common() if peso >= 3]
    for linea in lineas:
        for dominante in dominantes:
            if abs(linea["tamano"] - dominante) <= dominante * TOLERANCIA_TAMANO:
                linea["tamano"] = dominante
                break


def depurar_fotos(fotos, limites):
    """Deja solo bloques de imagen con entidad y descarta el falso positivo de
    dar la hoja entera por fotografia."""
    area_pagina = max(1.0, limites.get_area())
    validas = []
    for foto in fotos:
        recorte = fitz.Rect(foto) & limites
        if recorte.is_empty:
            continue
        if recorte.width < MINIMO_FOTO_PT or recorte.height < MINIMO_FOTO_PT:
            continue
        if recorte.get_area() > area_pagina * AREA_MAXIMA_FOTO:
            continue
        validas.append(recorte)

    return [foto & limites for foto in fusionar(validas, MARGEN_FOTO_PT)]


def dentro_de_foto(rect, fotos):
    area = rect.get_area()
    if area <= 0:
        return False
    for foto in fotos:
        comun = fitz.Rect(rect) & foto
        if not comun.is_empty and comun.get_area() >= area * SOLAPE_FOTO:
            return True
    return False


def descartar_en_fotos(lineas, fotos):
    """Lo que cae dentro de una imagen es parte de la imagen: ni se transcribe
    ni queda seleccionable."""
    if not fotos:
        return lineas

    limpias = []
    for linea in lineas:
        palabras = [p for p in linea["palabras"] if not dentro_de_foto(p["rect"], fotos)]
        if palabras:
            linea["palabras"] = palabras
            limpias.append(linea)
    return limpias


def caja_de_borrado(pix, linea, palabra, margen):
    """Caja a tapar: el ancho de la palabra por el alto completo del renglon.

    Usar solo la caja de la palabra dejaba restos de tinta (tildes, remates,
    bordes suavizados) que luego se pegaban como si fueran dibujos.
    """
    x0, _, x1, _ = palabra["px"]
    _, y0, _, y1 = linea["px"]
    caja = fitz.IRect(int(x0) - margen, int(y0) - margen, int(x1) + margen + 1, int(y1) + margen + 1)
    return caja & pix.irect


def color_fondo(pix, caja, margen=3):
    """Color del papel alrededor de una palabra, para taparla sin dejar parches blancos."""
    paso_x = max(1, (caja.x1 - caja.x0) // 8)
    paso_y = max(1, (caja.y1 - caja.y0) // 4)
    puntos = []
    for x in range(caja.x0, caja.x1, paso_x):
        puntos.append((x, caja.y0 - margen))
        puntos.append((x, caja.y1 + margen))
    for y in range(caja.y0, caja.y1, paso_y):
        puntos.append((caja.x0 - margen, y))
        puntos.append((caja.x1 + margen, y))

    muestras = []
    for x, y in puntos:
        if 0 <= x < pix.width and 0 <= y < pix.height:
            try:
                pixel = pix.pixel(x, y)
            except Exception:
                continue
            muestras.append(tuple(pixel[:3]) if len(pixel) >= 3 else (pixel[0], pixel[0], pixel[0]))

    if not muestras:
        return (255, 255, 255)

    muestras.sort(key=lambda c: -(c[0] + c[1] + c[2]))
    claras = muestras[: max(1, len(muestras) // 2)]
    return tuple(int(sum(c[i] for c in claras) / len(claras)) for i in range(3))


def tapar(pix, caja):
    """Cubre una zona del render con el color del papel que la rodea."""
    if caja.is_empty:
        return
    color = color_fondo(pix, caja)
    if pix.n < 3:
        color = (sum(color) // 3,) * pix.n
    try:
        pix.set_rect(caja, color[:pix.n])
    except Exception:
        pass


def borrar_texto_del_escaneo(pix, lineas):
    """Tapa en el render el texto reconocido para que no invada las imagenes recortadas."""
    for linea in lineas:
        for palabra in linea["palabras"]:
            tapar(pix, caja_de_borrado(pix, linea, palabra, DILATACION_TEXTO_PX))


def recortar(pix, caja):
    """Copia un trozo del render. Se usa copy() porque Pixmap(pix, clip) falla en PyMuPDF 1.28."""
    trozo = fitz.Pixmap(pix.colorspace, caja, pix.alpha)
    trozo.copy(pix, caja)
    return trozo


def mascara_reducida(pix, dpi):
    """Version reducida del render para segmentar mas rapido. Devuelve (pixmap, factor)."""
    reducciones = 0
    factor = 1
    while dpi / factor > DPI_MASCARA * 1.5 and reducciones < 3:
        reducciones += 1
        factor *= 2

    if not reducciones:
        return pix, 1

    copia = fitz.Pixmap(pix, 0)
    copia.shrink(reducciones)
    return copia, factor


def rejilla_tinta(pix, celda):
    """Marca que celdas de la pagina contienen tinta (pixeles oscuros)."""
    columnas = (pix.width + celda - 1) // celda
    filas = (pix.height + celda - 1) // celda
    rejilla = [bytearray(columnas) for _ in range(filas)]
    datos = pix.samples
    canales = pix.n
    desplazamiento = 1 if canales >= 3 else 0

    for y in range(pix.height):
        inicio = y * pix.stride
        linea = datos[inicio:inicio + pix.width * canales]
        fila = rejilla[y // celda]
        for cx in range(columnas):
            if fila[cx]:
                continue
            desde = cx * celda * canales + desplazamiento
            hasta = min((cx + 1) * celda, pix.width) * canales
            trozo = linea[desde:hasta:canales]
            if trozo and min(trozo) < UMBRAL_TINTA:
                fila[cx] = 1
    return rejilla, columnas, filas


def rejilla_medios_tonos(pix, celda):
    """Marca las celdas de tono continuo: las que se llenan de gris intermedio.

    El texto escaneado es casi blanco y negro, mientras que una fotografia, un
    logotipo o un sello dejan mucho gris. Sirve para cazar las imagenes que
    Tesseract no llega a marcar como tales.
    """
    columnas = (pix.width + celda - 1) // celda
    filas = (pix.height + celda - 1) // celda
    acumulado = [[0] * columnas for _ in range(filas)]
    datos = pix.samples
    canales = pix.n
    desplazamiento = 1 if canales >= 3 else 0

    for y in range(pix.height):
        inicio = y * pix.stride
        gris = datos[inicio + desplazamiento:inicio + pix.width * canales:canales]
        medios = gris.translate(TABLA_MEDIOS)
        fila = acumulado[y // celda]
        for cx in range(columnas):
            fila[cx] += sum(medios[cx * celda:(cx + 1) * celda])

    rejilla = [bytearray(columnas) for _ in range(filas)]
    for cy in range(filas):
        alto = min((cy + 1) * celda, pix.height) - cy * celda
        for cx in range(columnas):
            ancho = min((cx + 1) * celda, pix.width) - cx * celda
            if acumulado[cy][cx] >= max(1, ancho * alto) * UMBRAL_TONO:
                rejilla[cy][cx] = 1
    return rejilla, columnas, filas


def componentes(rejilla, columnas, filas):
    """Agrupa celdas contiguas (8 vecinos) en regiones."""
    vistos = [bytearray(columnas) for _ in range(filas)]
    regiones = []
    for y in range(filas):
        for x in range(columnas):
            if not rejilla[y][x] or vistos[y][x]:
                continue
            vistos[y][x] = 1
            pila = [(x, y)]
            minx = maxx = x
            miny = maxy = y
            celdas = 0
            while pila:
                cx, cy = pila.pop()
                celdas += 1
                minx = min(minx, cx)
                maxx = max(maxx, cx)
                miny = min(miny, cy)
                maxy = max(maxy, cy)
                for dy in (-1, 0, 1):
                    for dx in (-1, 0, 1):
                        nx, ny = cx + dx, cy + dy
                        if 0 <= nx < columnas and 0 <= ny < filas and rejilla[ny][nx] and not vistos[ny][nx]:
                            vistos[ny][nx] = 1
                            pila.append((nx, ny))
            regiones.append({"caja": (minx, miny, maxx, maxy), "celdas": celdas})
    return regiones


def fusionar(rects, margen):
    """Une los rectangulos que se tocan para no trocear una misma figura."""
    resultado = []
    for rect in rects:
        actual = fitz.Rect(rect)
        fusion = True
        while fusion:
            fusion = False
            restantes = []
            for otro in resultado:
                if (fitz.Rect(actual) + (-margen, -margen, margen, margen)).intersects(otro):
                    actual = fitz.Rect(actual) | otro
                    fusion = True
                else:
                    restantes.append(otro)
            resultado = restantes
        resultado.append(actual)
    return resultado


def ajustar_a_tinta(pix, rect):
    """Encoge un rectangulo hasta el contenido real para no arrastrar margenes en blanco."""
    x0 = max(0, int(rect.x0))
    y0 = max(0, int(rect.y0))
    x1 = min(pix.width, int(rect.x1))
    y1 = min(pix.height, int(rect.y1))
    if x1 <= x0 or y1 <= y0:
        return None

    datos = pix.samples
    canales = pix.n
    desplazamiento = 1 if canales >= 3 else 0

    arriba = abajo = None
    izquierda = x1
    derecha = x0
    for y in range(y0, y1):
        base = y * pix.stride
        linea = datos[base + x0 * canales + desplazamiento:base + x1 * canales:canales]
        if not linea or min(linea) >= UMBRAL_TINTA:
            continue
        if arriba is None:
            arriba = y
        abajo = y
        for i, valor in enumerate(linea):
            if valor < UMBRAL_TINTA:
                izquierda = min(izquierda, x0 + i)
                break
        for i in range(len(linea) - 1, -1, -1):
            if linea[i] < UMBRAL_TINTA:
                derecha = max(derecha, x0 + i)
                break

    if arriba is None:
        return None
    return fitz.Rect(izquierda, arriba, derecha + 1, abajo + 1)


def regiones_graficas(mascara):
    """Devuelve, en pixeles de la mascara, las zonas con contenido grafico."""
    celda = max(3, mascara.width // 160)
    rejilla, columnas, filas = rejilla_tinta(mascara, celda)
    area_pagina = max(1, columnas * filas)

    rects = []
    for region in componentes(rejilla, columnas, filas):
        minx, miny, maxx, maxy = region["caja"]
        area = (maxx - minx + 1) * (maxy - miny + 1)

        # Un contorno enorme y casi vacio suele ser el borde o la sombra del escaner.
        if area >= 0.9 * area_pagina and region["celdas"] < 0.08 * area:
            continue

        rects.append(fitz.Rect(
            minx * celda,
            miny * celda,
            min((maxx + 1) * celda, mascara.width),
            min((maxy + 1) * celda, mascara.height),
        ))

    ajustados = []
    for rect in fusionar(rects, celda):
        ajustado = ajustar_a_tinta(mascara, rect)
        if ajustado is not None:
            ajustados.append(ajustado)
    return ajustados


def regiones_de_tono(mascara, escala):
    """Zonas de tono continuo que se consideran imagen, en puntos del render."""
    celda = max(4, mascara.width // 110)
    rejilla, columnas, filas = rejilla_medios_tonos(mascara, celda)

    rects = []
    for region in componentes(rejilla, columnas, filas):
        minx, miny, maxx, maxy = region["caja"]
        celdas_caja = (maxx - minx + 1) * (maxy - miny + 1)
        if region["celdas"] < 8 or region["celdas"] < celdas_caja * 0.5:
            continue
        rects.append(fitz.Rect(
            minx * celda * escala,
            miny * celda * escala,
            min((maxx + 1) * celda, mascara.width) * escala,
            min((maxy + 1) * celda, mascara.height) * escala,
        ))
    return rects


def color_dominante(pix, rect_px):
    """Aproxima el color del texto con el pixel mas oscuro de su caja."""
    x0, y0, x1, y1 = [int(v) for v in rect_px]
    x0 = max(0, min(x0, pix.width - 1))
    x1 = max(x0 + 1, min(x1, pix.width))
    y0 = max(0, min(y0, pix.height - 1))
    y1 = max(y0 + 1, min(y1, pix.height))

    paso_x = max(1, (x1 - x0) // 40)
    paso_y = max(1, (y1 - y0) // 12)

    mejor = None
    luminancia = 256
    for y in range(y0, y1, paso_y):
        for x in range(x0, x1, paso_x):
            try:
                pixel = pix.pixel(x, y)
            except Exception:
                continue
            if len(pixel) < 3:
                pixel = (pixel[0], pixel[0], pixel[0])
            valor = (pixel[0] * 299 + pixel[1] * 587 + pixel[2] * 114) // 1000
            if valor < luminancia:
                luminancia = valor
                mejor = pixel[:3]
    if not mejor or luminancia > 190:
        return (0.0, 0.0, 0.0)
    return (mejor[0] / 255.0, mejor[1] / 255.0, mejor[2] / 255.0)


def colores_por_linea(pix, lineas):
    """Muestrea un color por renglon antes de tapar el texto en el render."""
    colores = {}
    for indice, linea in enumerate(lineas):
        referencia = max(linea["palabras"], key=lambda p: p["rect"].width)
        colores[indice] = color_dominante(pix, referencia["px"])
    return colores


def escribir_lineas(pagina, lineas, colores, escala, origen):
    """Escribe el texto con un cuerpo de letra unico por renglon, apoyado en la
    linea base que calculo Tesseract.

    Al compartir base y tamano, el renglon queda recto y homogeneo aunque el
    escaneo estuviera ondulado.
    """
    for indice, linea in enumerate(lineas):
        tamano_linea = linea["tamano"]
        color = colores.get(indice, (0.0, 0.0, 0.0))
        palabras = linea["palabras"]

        for posicion, palabra in enumerate(palabras):
            texto = texto_a_latin1(palabra["texto"])
            rect = palabra["rect"]
            if posicion + 1 < len(palabras):
                limite = palabras[posicion + 1]["rect"].x0
            else:
                limite = rect.x1 + tamano_linea
            disponible = max(1.0, limite - rect.x0)
            ancho = fitz.get_text_length(texto, fontname=FUENTE, fontsize=tamano_linea)

            tamano = tamano_linea
            if ancho > disponible * HOLGURA_ANCHO:
                tamano = max(tamano_linea * ENCOGIDO_MAXIMO, tamano_linea * disponible / ancho)

            # El espacio final no se ve, pero hace que al copiar el texto las
            # palabras no salgan pegadas.
            if posicion + 1 < len(palabras):
                texto += " "

            base = base_de_linea(linea, palabra["px"][0]) * escala
            try:
                pagina.insert_text(
                    (rect.x0 + origen[0], base + origen[1]),
                    texto,
                    fontname=FUENTE,
                    fontsize=tamano,
                    color=color,
                )
            except Exception:
                continue


def escribir_capa_invisible(pagina, lineas, mapear, giro):
    """Coloca el texto del OCR sin pintarlo, siguiendo exactamente el escaneo."""
    for linea in lineas:
        for palabra in linea["palabras"]:
            texto = texto_a_latin1(palabra["texto"])
            tamano = tamano_ajustado(texto, palabra["rect"])
            punto = mapear(palabra["px"][0], base_de_linea(linea, palabra["px"][0]))
            try:
                pagina.insert_text(
                    punto,
                    texto,
                    fontname=FUENTE,
                    fontsize=tamano,
                    render_mode=3,
                    morph=(punto, giro),
                )
            except Exception:
                continue


def constructor_de_mapa(origen, matriz, pagina):
    """Convierte pixeles del render enderezado en coordenadas de la pagina."""
    inversa = ~matriz
    dx, dy = origen
    derotacion = pagina.derotation_matrix if pagina.rotation else None

    def mapear(px, py):
        punto = fitz.Point(px + dx, py + dy) * inversa
        return punto * derotacion if derotacion is not None else punto

    return mapear


def matriz_de_giro(angulo, pagina):
    """Giro que devuelve el texto enderezado a la inclinacion real del escaneo."""
    giro = fitz.Matrix(-angulo)
    if pagina.rotation:
        derotacion = pagina.derotation_matrix
        giro = giro * fitz.Matrix(derotacion.a, derotacion.b, derotacion.c, derotacion.d, 0, 0)
    return giro


def procesar_pagina_fiel(salida, documento, indice, lineas, origen, matriz, angulo):
    pagina = documento[indice]
    mapear = constructor_de_mapa(origen, matriz, pagina)
    giro = matriz_de_giro(angulo, pagina)
    salida.insert_pdf(documento, from_page=indice, to_page=indice)
    escribir_capa_invisible(salida[-1], lineas, mapear, giro)


def metricas_de_lineas(lineas, escala):
    """Base y altura de cada renglon, para enderezar los fragmentos graficos que los acompanan."""
    metricas = []
    for linea in lineas:
        x0, y0, x1, y1 = linea["px"]
        metricas.append({
            "x0": x0 * escala,
            "x1": x1 * escala,
            "y0": y0 * escala,
            "y1": y1 * escala,
            "base": base_de_linea(linea, (x0 + x1) / 2.0) * escala,
            "alto": max(1.0, (y1 - y0) * escala),
        })
    return metricas


def resto_de_texto(destino, metricas):
    """Motas sueltas dentro de un renglon ya transcrito: bordes de letra que el
    borrado no alcanzo. Pegarlas ensuciaria la pagina reconstruida."""
    for linea in metricas:
        if destino.width >= linea["alto"] * 0.7 or destino.height >= linea["alto"] * 0.7:
            continue
        banda = fitz.Rect(linea["x0"], linea["y0"], linea["x1"], linea["y1"]) + (-2, -2, 2, 2)
        if banda.intersects(destino):
            return True
    return False


def enderezar(destino, metricas):
    """Apoya en la linea base del texto los recortes pequenos (palabras que el OCR no leyo)."""
    mejor = None
    distancia_minima = None

    for linea in metricas:
        if destino.height > linea["alto"] * 2.2:
            continue
        margen = linea["alto"] * 6
        if destino.x1 <= linea["x0"] - margen or destino.x0 >= linea["x1"] + margen:
            continue
        distancia = abs(linea["base"] - destino.y1)
        if distancia > linea["alto"] * 1.2:
            continue
        if distancia_minima is None or distancia < distancia_minima:
            distancia_minima = distancia
            mejor = linea

    if mejor is None:
        return destino
    desplazamiento = mejor["base"] - destino.y1
    return destino + (0, desplazamiento, 0, desplazamiento)


def caja_de_puntos(rect, escala, limites):
    """Pasa un rectangulo en puntos del render a pixeles del propio render."""
    return fitz.IRect(
        int(rect.x0 / escala),
        int(rect.y0 / escala),
        int(rect.x1 / escala) + 1,
        int(rect.y1 / escala) + 1,
    ) & limites


def procesar_pagina_texto(salida, pagina, lineas, fotos, pix, escala):
    nueva = salida.new_page(width=pagina.rect.width, height=pagina.rect.height)
    limites = fitz.Rect(0, 0, pagina.rect.width, pagina.rect.height)

    # Al enderezar, el render es algo mayor que la hoja: se centra para repartir
    # la diferencia entre los margenes en vez de perderla por un solo lado.
    origen = (
        (pagina.rect.width - pix.width * escala) / 2.0,
        (pagina.rect.height - pix.height * escala) / 2.0,
    )
    traslado = (origen[0], origen[1], origen[0], origen[1])

    colores = colores_por_linea(pix, lineas)

    # Las fotos se pegan enteras desde el render intacto, con lo que lleven
    # dentro; en la copia de trabajo se tapan para no volver a trocearlas.
    # fitz.Pixmap(pix) anadiria un canal alfa: se pide copia sin transparencia.
    trabajo = fitz.Pixmap(pix, 0)
    for foto in fotos:
        caja = caja_de_puntos(foto, escala, pix.irect)
        if caja.is_empty:
            continue
        destino = (fitz.Rect(foto) + traslado) & limites
        if destino.is_empty:
            continue
        try:
            nueva.insert_image(destino, pixmap=recortar(pix, caja))
        except Exception:
            pass
        tapar(trabajo, caja)

    borrar_texto_del_escaneo(trabajo, lineas)

    metricas = metricas_de_lineas(lineas, escala)
    mascara, factor = mascara_reducida(trabajo, round(72.0 / escala))
    for region in regiones_graficas(mascara):
        caja = fitz.IRect(
            int(region.x0 * factor),
            int(region.y0 * factor),
            int(region.x1 * factor),
            int(region.y1 * factor),
        ) & trabajo.irect
        if caja.is_empty:
            continue

        destino = fitz.Rect(
            caja.x0 * escala, caja.y0 * escala, caja.x1 * escala, caja.y1 * escala
        )
        # Las lineas de tablas o subrayados son muy finas: solo se descarta lo diminuto.
        if destino.width < MINIMO_REGION_PT and destino.height < MINIMO_REGION_PT:
            continue

        if resto_de_texto(destino, metricas):
            continue

        destino = (enderezar(destino, metricas) + traslado) & limites
        if destino.is_empty:
            continue

        try:
            nueva.insert_image(destino, pixmap=recortar(trabajo, caja))
        except Exception:
            continue

    escribir_lineas(nueva, lineas, colores, escala, origen)


def renderizar(pagina, matriz, gris=False):
    """Renderiza la pagina ya enderezada.

    Al girar, el pixmap nace con un origen negativo. Se lleva a (0, 0) para que
    sus pixeles coincidan con los que ve Tesseract, y se devuelve el origen
    original porque es lo que permite volver a las coordenadas de la pagina.
    """
    if gris:
        pix = pagina.get_pixmap(matrix=matriz, colorspace=fitz.csGRAY, alpha=False)
    else:
        pix = pagina.get_pixmap(matrix=matriz, alpha=False)
    origen = (pix.x, pix.y)
    pix.set_origin(0, 0)
    return pix, origen


def renderizar_para_ocr(pagina, matriz):
    pix, origen = renderizar(pagina, matriz, gris=True)
    temporal = tempfile.NamedTemporaryFile(delete=False, suffix=".png")
    temporal.close()
    _temporales.append(temporal.name)
    pix.save(temporal.name)
    return temporal.name, pix, origen


def cubierta_por_texto(rect, lineas, escala):
    """Proporcion del rectangulo ocupada por renglones ya reconocidos."""
    area = rect.get_area()
    if area <= 0:
        return 1.0

    ocupado = 0.0
    for linea in lineas:
        x0, y0, x1, y1 = linea["px"]
        comun = fitz.Rect(x0 * escala, y0 * escala, x1 * escala, y1 * escala) & rect
        if not comun.is_empty:
            ocupado += comun.get_area()
    return ocupado / area


def detectar_fotos(hocr_fotos, gris, escala, lineas):
    """Une los bloques de imagen del OCR con las zonas de tono continuo.

    Las zonas de tono se descartan si estan cubiertas de renglones: seria un
    parrafo de un escaneo sucio, no una fotografia.
    """
    mascara, factor = mascara_reducida(gris, round(72.0 / escala))
    candidatas = [
        rect for rect in regiones_de_tono(mascara, escala * factor)
        if cubierta_por_texto(rect, lineas, escala) < COBERTURA_TEXTO
    ]
    limites = fitz.Rect(0, 0, gris.width * escala, gris.height * escala)
    return depurar_fotos(hocr_fotos + candidatas, limites)


def analizar(args, exe):
    documento = fitz.open(args.entrada)
    if documento.needs_pass:
        documento.close()
        print("El PDF esta protegido con contrasena.")
        return 3

    idioma = idioma_utilizable(exe, args.idioma)
    salida = fitz.open()
    escala = 72.0 / args.dpi
    resumen = {
        "paginas": documento.page_count,
        "ocr": 0,
        "copiadas": 0,
        "enderezadas": 0,
        "imagenes": 0,
        "idioma": idioma,
        "modo": args.modo,
    }

    try:
        for indice in range(documento.page_count):
            pagina = documento[indice]

            if not args.forzar and pagina_tiene_texto(pagina):
                salida.insert_pdf(documento, from_page=indice, to_page=indice)
                resumen["copiadas"] += 1
                continue

            angulo = 0.0 if args.sin_enderezar else estimar_inclinacion(pagina)
            matriz = matriz_de_render(args.dpi, angulo)
            imagen, gris, origen = renderizar_para_ocr(pagina, matriz)

            lineas, fotos = parsear_hocr(ocr_hocr(exe, imagen, idioma, args.timeout), escala)
            fotos = detectar_fotos(fotos, gris, escala, lineas)
            lineas = descartar_en_fotos(lineas, fotos)

            if not lineas:
                salida.insert_pdf(documento, from_page=indice, to_page=indice)
                resumen["copiadas"] += 1
                continue

            calcular_tamanos(lineas, escala)

            if args.modo == "texto":
                color, _ = renderizar(pagina, matriz)
                procesar_pagina_texto(salida, pagina, lineas, fotos, color, escala)
            else:
                procesar_pagina_fiel(salida, documento, indice, lineas, origen, matriz, angulo)

            resumen["ocr"] += 1
            resumen["imagenes"] += len(fotos)
            if angulo:
                resumen["enderezadas"] += 1

        if salida.page_count == 0:
            print("No se pudo generar ninguna pagina.")
            return 5

        salida.save(args.salida, garbage=3, deflate=True)
    finally:
        salida.close()
        documento.close()
        limpiar_temporales()

    print("OK " + json.dumps(resumen, ensure_ascii=False))
    return 0


def main():
    supervisar()
    registrar_limpieza(limpiar_temporales)

    parser = argparse.ArgumentParser(description="OCR de PDF escaneado para PDFina")
    parser.add_argument("entrada")
    parser.add_argument("salida")
    parser.add_argument("--idioma", default="spa")
    parser.add_argument("--modo", default="fiel", choices=["fiel", "texto"])
    parser.add_argument("--dpi", type=int, default=300)
    parser.add_argument("--timeout", type=int, default=300)
    parser.add_argument("--forzar", action="store_true")
    parser.add_argument("--sin-enderezar", dest="sin_enderezar", action="store_true")
    args = parser.parse_args()

    if not os.path.isfile(args.entrada):
        print("No se encontro el PDF de entrada.")
        return 1

    args.dpi = max(150, min(args.dpi, 600))

    exe = localizar_tesseract()
    if not exe:
        print("No se encontro Tesseract OCR. Instalalo y anadelo al PATH del sistema.")
        return 4

    try:
        return analizar(args, exe)
    except subprocess.TimeoutExpired:
        print("El OCR ha tardado demasiado y se ha cancelado.")
        return 6
    except Exception as error:
        print("ERROR: %s" % error)
        return 1


if __name__ == "__main__":
    sys.exit(main())
