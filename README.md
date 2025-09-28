![Logo PDFina](https://raw.githubusercontent.com/matiaslawwliet/PDFina/refs/heads/main/public/icon.png)

# PDFina
**PDFina** es una herramienta de escritorio gratuita, desarrollada con [Laravel 12](https://laravel.com/) y [NativePHP](https://nativephp.com/), que permite trabajar con archivos PDF de forma local, sin depender de servicios en la nube ni exponer tus archivos a terceros.

---

## Características principales
- Convertir imágenes a PDF
- Convertir Docx a PDF
- Convertir PDF a Docx
- Unir archivos PDF
- Dividir archivo PDF
- Comprimir PDF
- Firmar PDF
- Eliminar páginas de PDF
- Eliminar contraseña de PDF
- 100% offline, sin límites de tamaño

## Objetivo del proyecto
Brindar una herramienta gratuita, local y confiable para manipular PDFs sin comprometer la privacidad del usuario ni depender de servicios comerciales o en línea.

## Tecnologías utilizadas
- Laravel 12
- SQLite
- NativePHP 1.3
- PHP 8.3
- Tailwind CSS 4
- Livewire 3
- [Ghostscript](https://ghostscript.com/releases/gsdnld.html): unir, dividir y comprimir PDFs
- Dompdf: convertir imágenes a PDF
- pdf2docx (Python, empaquetado como microservicio ejecutable): conversión local de PDF a Docx sin requerir instalación de Python por parte del usuario

##  Licencia
PDFina está licenciado bajo **GNU GPLv3 con una cláusula adicional** que restringe su uso **únicamente a fines personales y no comerciales**.

> Cualquier uso comercial está prohibido sin autorización expresa.
> El nombre **“PDFina”** está protegido y **no puede ser reutilizado en forks o derivados**.

Consulta el archivo [`LICENSE`](LICENSE) para más información.

---

## Capturas de pantalla
Una vista rápida de la interfaz y los flujos principales de PDFina.

![Vista inicio](https://raw.githubusercontent.com/matiaslawwliet/PDFina/refs/heads/main/public/images/demo/demo1.png)
*Vista Inicio*

![Interfaz principal](https://raw.githubusercontent.com/matiaslawwliet/PDFina/refs/heads/main/public/images/demo/demo2.png)
*Interfaz principal*

![Firmar PDF](https://raw.githubusercontent.com/matiaslawwliet/PDFina/refs/heads/main/public/images/demo/demo3.png)
*Proceso para firmar PDF*

![Resultado firma](https://raw.githubusercontent.com/matiaslawwliet/PDFina/refs/heads/main/public/images/demo/demo4.png)
*Resultado firma PDF*

![Unir PDF](https://raw.githubusercontent.com/matiaslawwliet/PDFina/refs/heads/main/public/images/demo/demo5.png)
*Unir varios archivos PDF*

---

## Repositorio de releases
Para mantener el repositorio principal limpio y centrado en el código fuente, los artefactos de distribución (binarios, instaladores y assets de las versiones) se publican en un repositorio separado de releases. Puedes acceder al repositorio de releases aquí: [PDFina releases](https://github.com/matiaslawwliet/PDFina-releases)
