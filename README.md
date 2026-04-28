# Bachy TW

Sistema administrativo para gestión de clientes, dispositivos, reparaciones, agenda, stock, ventas, compras y documentos comerciales.

La aplicación principal corre sobre PHP 8.2 + Apache y MySQL 8. Este repositorio está preparado para ejecutarse principalmente con Docker Compose.

## Resumen

- Aplicación web principal en PHP + Apache.
- Base de datos MySQL 8.
- Scripts de migración y carga inicial.
- Servicio MCP opcional para integración con n8n.
- Utilidades opcionales en Node.js para PDF y frontend.

Puertos publicados por defecto:

- Aplicación web: `http://localhost:8185`
- MySQL desde el host: `127.0.0.1:3307`
- MCP: `http://localhost:3101`

## Requisitos

Para el flujo recomendado de instalación:

- Git
- Docker
- Docker Compose v2 (`docker compose`)

Opcionales:

- Node.js 18+ y npm, solo si vas a usar generación de PDF u otras utilidades Node.
- Cliente MySQL, solo si quieres intervenir la base manualmente.

## Instalación desde cero

### 1. Clonar el repositorio

```bash
git clone <URL_DEL_REPOSITORIO> bachy-tw
cd bachy-tw
```

### 2. Crear el archivo `.env`

La configuración base se lee desde el archivo raíz `.env` mediante `src/config.php`.

Crea un archivo `.env` con este contenido mínimo:

```env
DB_HOST=db
DB_PORT=3306
DB_NAME=bachy
DB_USER=bachy
DB_PASS=secret

JWT_SECRET=cambia_esta_clave_por_una_mas_segura

MCP_AUTH_TOKEN=define_un_token_largo_y_seguro
MCP_ENABLE_WRITES=false
MCP_ALLOWED_WRITE_TOOLS=
```

Notas importantes:

- Para Docker, `DB_HOST=db` es correcto porque la app se conecta al servicio MySQL dentro de la red de Compose.
- Si cambias credenciales o nombre de base, deben coincidir con lo configurado en `docker-compose.yml`.
- `JWT_SECRET` conviene cambiarlo incluso en desarrollo compartido.

### 3. Levantar la infraestructura

```bash
docker compose up -d --build
```

Esto levanta los servicios:

- `bachy-app`
- `bachy-db`
- `bachy-mcp`

Si todavía no quieres levantar el MCP:

```bash
docker compose up -d --build app db
```

### 4. Verificar que la stack esté activa

```bash
docker compose ps
```

Prueba rápida de la aplicación:

```bash
curl http://localhost:8185/
```

Respuesta esperada:

```json
{"app":"bachy","status":"ok","message":"Sistema base levantado"}
```

## Inicialización de la base de datos

### 5. Crear la estructura base

En una instalación nueva ejecuta:

```bash
docker exec -it bachy-app php public/migrate.php
```

Este script:

- ejecuta `db/migrations.sql`,
- crea tablas base si no existen,
- agrega columnas e índices incluidos allí,
- inserta algunos datos iniciales, como marcas y modelos base.

### 6. Aplicar migraciones incrementales si corresponde

Importante: `public/migrate.php` ejecuta solo `db/migrations.sql`.

Las migraciones SQL individuales dentro de `db/migrations/` se aplican manualmente con:

```bash
docker exec -it bachy-app php scripts/run_migration.php db/migrations/NOMBRE_DE_LA_MIGRACION.sql
```

Ejemplo:

```bash
docker exec -it bachy-app php scripts/run_migration.php db/migrations/20260412_add_totals_to_repairs.sql
```

Recomendación práctica:

- Instalación nueva: ejecutar primero `public/migrate.php`.
- Luego revisar si hay migraciones recientes en `db/migrations/` que todavía deban aplicarse.
- En una base ya existente: aplicar solo las migraciones nuevas necesarias con `scripts/run_migration.php`.

## Crear el primer usuario administrador

### 7. Alta del usuario inicial

```bash
docker exec -it bachy-app php public/create_user.php admin MiClaveSegura123
```

Formato general:

```bash
docker exec -it bachy-app php public/create_user.php USUARIO CLAVE
```

El usuario queda con rol `admin` y contraseña hasheada.

## Primer acceso al sistema

### 8. URLs principales

- Página base: `http://localhost:8185/`
- Login admin: `http://localhost:8185/admin/login.html`
- Pantalla posterior al login: `http://localhost:8185/admin/agenda.html`

Flujo actual de autenticación:

1. Abrir `admin/login.html`.
2. Ingresar usuario y contraseña.
3. El frontend llama a `POST /auth.php/login`.
4. Si el login es válido, guarda el token JWT en `localStorage`.
5. Redirige a `admin/agenda.html`.

## Carga inicial opcional de datos

### 9. Categorías base

```bash
docker exec -it bachy-app php scripts/seed_categories.php
```

### 10. Provincias y ciudades desde CSV

El repositorio ya incluye el archivo:

- `data/localidades_cp_maestro_clean.csv`

Para importar provincias y ciudades:

```bash
docker exec -it bachy-app php scripts/import_localidades.php
```

## Orden recomendado de puesta en marcha

Para dejar el sistema operativo en un entorno nuevo:

1. Clonar el repositorio.
2. Crear el archivo `.env`.
3. Ejecutar `docker compose up -d --build`.
4. Ejecutar `docker exec -it bachy-app php public/migrate.php`.
5. Aplicar migraciones puntuales de `db/migrations/` si hacen falta.
6. Crear el usuario administrador.
7. Ejecutar `scripts/seed_categories.php`.
8. Ejecutar `scripts/import_localidades.php` si vas a usar provincias/ciudades.
9. Entrar a `http://localhost:8185/admin/login.html`.

## Operación diaria y mantenimiento

### Ver estado de servicios

```bash
docker compose ps
```

### Ver logs generales

```bash
docker compose logs --tail 200
```

### Ver logs de la aplicación

```bash
docker compose logs --tail 200 app
```

### Ver logs de MySQL

```bash
docker compose logs --tail 200 db
```

### Reiniciar servicios

```bash
docker compose restart
```

### Detener la stack

```bash
docker compose down
```

### Reconstruir imágenes

```bash
docker compose up -d --build
```

## Acceso manual a MySQL

Desde el host:

```bash
mysql -h 127.0.0.1 -P 3307 -u bachy -psecret bachy
```

Desde el contenedor:

```bash
docker exec -it bachy-db mysql -u root -prootpass
```

## Reinicio completo desde cero

Si necesitas eliminar contenedores y datos persistidos:

```bash
docker compose down
docker volume rm bachy-tw_mysql_data
docker compose up -d --build
```

Después de eso tendrás que volver a:

- ejecutar migraciones,
- crear el usuario admin,
- recargar datos iniciales si los necesitas.

## Servicio MCP opcional para n8n

El repositorio incluye un servidor MCP en `mcp/bachy-db-mcp`.

Endpoint publicado por Docker:

- `http://localhost:3101/mcp/sse`

Levantar solo el MCP:

```bash
docker compose up -d --build mcp
```

Variables relevantes en `.env`:

```env
MCP_AUTH_TOKEN=define_un_token_seguro
MCP_ENABLE_WRITES=false
MCP_ALLOWED_WRITE_TOOLS=
```

Si luego necesitas escrituras limitadas:

```env
MCP_ENABLE_WRITES=true
MCP_ALLOWED_WRITE_TOOLS=create_customer,create_device,create_repair,upsert_stock,create_sale,create_purchase,create_quote,create_remito,create_invoice
```

Configuración típica en n8n:

- `SSE Endpoint`: `http://127.0.0.1:3101/mcp/sse`
- `Authentication`: `Bearer`
- `Bearer Token`: el valor de `MCP_AUTH_TOKEN`

Si n8n corre en Docker, puede ser necesario usar:

- `http://host.docker.internal:3101/mcp/sse`
- o la IP del host Linux.

### Nuevas herramientas de agenda (MCP)

Se añadieron dos herramientas de escritura al MCP para gestionar reparaciones desde n8n o clientes MCP:

- `update_repair`: permite actualizar campos de una reparación existente (reagendar, reasignar técnico, cambiar estado, contacto, etc.).
- `delete_repair`: elimina una reparación por `repair_id`.

Para exponerlas habilitá escrituras y agrégalas a la allow-list en el `.env` del MCP, por ejemplo:

```env
MCP_ENABLE_WRITES=true
MCP_ALLOWED_WRITE_TOOLS=create_customer,create_repair,update_repair,delete_repair
```

Ejemplo de `update_repair` (payload JSON):

```json
{
	"repair_id": 789,
	"scheduled_at": "2026-05-05 15:00:00",
	"technician_id": 9,
	"status": "scheduled"
}
```

Ejemplo de `delete_repair`:

```json
{ "repair_id": 789 }
```

Después de modificar `.env` reiniciá el servicio MCP:

```bash
docker compose up -d --build mcp
```

En n8n podés usar el nodo `MCP Client Tool` para llamar a estas herramientas (con `Authentication: Bearer` y el token configurado en `MCP_AUTH_TOKEN`).

## Utilidades Node.js opcionales

Instalar dependencias:

```bash
npm install
```

Generar un PDF con Puppeteer:

```bash
node scripts/generate_pdf_puppeteer.js "http://localhost:8185/admin/repairs.html?id=123" ./out/orden-123.pdf
```

Esto no es necesario para levantar el sistema principal, solo para utilidades complementarias.

## Archivos y carpetas importantes

- `docker-compose.yml`: stack principal.
- `docker/php/Dockerfile`: imagen PHP/Apache.
- `src/config.php`: carga del `.env`.
- `public/migrate.php`: inicialización base de la base de datos.
- `public/create_user.php`: creación de usuario admin por CLI.
- `scripts/run_migration.php`: ejecución de migraciones puntuales.
- `scripts/seed_categories.php`: carga de categorías base.
- `scripts/import_localidades.php`: importación de provincias y ciudades.
- `public/admin/`: vistas administrativas.
- `public/api/`: endpoints backend.
- `mcp/bachy-db-mcp/`: servidor MCP.

## Problemas frecuentes

### La aplicación no abre

```bash
docker compose ps
docker compose logs --tail 200 app
```

### El login falla

Revisar:

- que el usuario se haya creado con `public/create_user.php`,
- que la base usada por la app sea la correcta,
- que `JWT_SECRET` esté definido,
- que no haya un token viejo guardado en `localStorage`.

### La app no conecta con MySQL

Verificar:

- que el servicio `db` esté arriba,
- que `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS` coincidan,
- que no haya cambios parciales entre `.env` y `docker-compose.yml`.

### Faltan tablas o columnas

Eso normalmente indica una de estas dos cosas:

- no se ejecutó `public/migrate.php`,
- falta aplicar alguna migración de `db/migrations/`.

### El MCP no responde

```bash
docker compose logs --tail 200 mcp
```

Además, confirma que `MCP_AUTH_TOKEN` tenga un valor real.

## Recomendaciones para producción

- Cambiar `JWT_SECRET` por un valor largo y único.
- Cambiar las credenciales por defecto de MySQL.
- No exponer MySQL públicamente sin protección de red.
- Mantener `MCP_ENABLE_WRITES=false` hasta validar bien el flujo.
- Hacer backup antes de aplicar migraciones nuevas.

## Documentación adicional

- `docs/tablet-alta.md`: flujo tablet para alta y reparación.
- `scripts/README-pdf.md`: uso del generador PDF con Puppeteer.
- `mcp/bachy-db-mcp/README.md`: detalle del servidor MCP.
