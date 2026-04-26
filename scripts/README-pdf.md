Generación de PDF con Puppeteer

Recomendado para recebos complejos (CSS moderno, imágenes y QR).

Instalación (en el root del proyecto):

```bash
# instalar Node 18+ en el sistema si no está
# instalar Puppeteer (descarga Chromium automáticamente)
npm install puppeteer --save
```

Si tu servidor está en Docker o en un entorno restringido, instala Chromium en la imagen o usa la opción `PUPPETEER_SKIP_CHROMIUM_DOWNLOAD` y proporciona un binario de Chrome/Chromium accesible.

Uso:

```bash
# desde la raíz del repo
node scripts/generate_pdf_puppeteer.js "http://localhost:8185/admin/repairs.html?id=123" ./out/orden-123.pdf
```

Notas:
- El script abre la URL y espera a que la página esté inactiva (networkidle0) y agrega un pequeño retardo para asegurar que imágenes/QR se rendericen.
- Ajustá el `waitForTimeout` en el script si tu red o servidor tarda más en servir recursos.
- Para ejecutar en producción en contenedores, añade los flags `--no-sandbox --disable-setuid-sandbox` (ya incluidos en el script) y asegurate de instalar fuentes necesarias (`fonts-dejavu` u otras) para renderizado correcto.

Alternativas:
- `wkhtmltopdf` (CLI), más simple pero soporta menos CSS moderno.
- Librerías PHP (`dompdf`, `mpdf`) si preferís generar PDF directamente desde PHP.
