# Bachy TW

Aplicación administrativa mínima (PHP + Apache) con MySQL, preparada para ejecutarse con Docker Compose.

Resumen rápido
- Servicio web disponible en el puerto `8185` (mappeado a `80` del contenedor).
- Base de datos MySQL en el contenedor `bachy-db` (puerto externo `3307` → interno `3306`).

Requisitos
- Docker
- Docker Compose (v2 / comando `docker compose`)
- Git (para clonar el repositorio)

Instalación desde cero (pasos mínimos)

1. Clonar el repositorio:

```bash
git clone <repo-url> bachy-tw
cd bachy-tw
```

2. Crear el archivo de configuración `.env` (opcional, se usan valores por defecto si no existe). Ejemplo mínimo (`.env`):

```env
# Conexión a la base de datos (valores por defecto usados por el código)
DB_HOST=db
DB_PORT=3306
DB_NAME=bachy
DB_USER=bachy
DB_PASS=secret

# Clave para JWT (cámbiala en producción)
JWT_SECRET=una_clave_segura_aqui
```

3. Levantar contenedores:

```bash
docker compose up -d --build
```

4. Ejecutar migraciones para crear las tablas en la base de datos:

```bash
docker exec -it bachy-app php public/migrate.php
```

5. Crear un usuario administrador (ejemplo):

```bash
docker exec -it bachy-app php public/create_user.php admin MiPass123
```

6. (Opcional) Cargar datos de ejemplo/seed:

```bash
docker exec -it bachy-app php scripts/seed_categories.php
# Si tienes el CSV de localidades:
docker exec -it bachy-app php scripts/import_localidades.php
```

Acceso a la aplicación
- Login: http://localhost:8185/admin/login.html
- Dashboard: http://localhost:8185/admin/dashboard.html
- Página pública (index): http://localhost:8185/

Servicio MCP para n8n
- El proyecto incluye un servidor MCP en `mcp/bachy-db-mcp`
- Expone consultas de clientes, dispositivos, técnicos, reparaciones, agenda, stock, depósitos, proveedores, marcas, modelos, provincias, ciudades, ventas, compras, presupuestos, remitos y facturas
- Se publica por Docker Compose en `http://localhost:3101/mcp/sse`

Levantar solo el MCP:

```bash
docker compose up -d --build mcp
```

Variables MCP en `.env`

```env
MCP_AUTH_TOKEN=define_un_token_seguro
MCP_ENABLE_WRITES=true
MCP_ALLOWED_WRITE_TOOLS=create_customer,create_device,create_repair,upsert_stock,create_sale,create_purchase,create_quote,create_remito,create_invoice
```

Configuración en n8n
- Nodo: `MCP Client Tool`
- `SSE Endpoint`: `http://127.0.0.1:3101/mcp/sse`
- `Authentication`: `Bearer`
- `Bearer Token`: el valor de `MCP_AUTH_TOKEN`

Herramientas de escritura actualmente habilitadas por allowlist
- `create_customer`
- `create_device`
- `create_repair`
- `upsert_stock`
- `create_sale`
- `create_purchase`
- `create_quote`
- `create_remito`
- `create_invoice`

Si n8n corre en Docker, usa `http://host.docker.internal:3101/mcp/sse` o la IP del host.

Conexión a la base de datos desde el host
- MySQL está expuesto en el puerto `3307` del host (mapeado a `3306` del contenedor). Por ejemplo:

```bash
mysql -h 127.0.0.1 -P 3307 -u bachy -psecret bachy
```

Archivos importantes
- `docker-compose.yml` — definición de servicios (`app` y `db`).
- `docker/php/Dockerfile` — Dockerfile para el contenedor PHP/Apache.
- `public/migrate.php` — ejecuta las migraciones SQL en `db/migrations.sql`.
- `public/create_user.php` — script CLI para crear un usuario admin.
- `scripts/seed_categories.php`, `scripts/import_localidades.php` — utilidades para poblar datos.

Solución de problemas comunes
- Si no arrancan los contenedores: revisa los logs con `docker compose logs --tail 200`.
- Si la aplicación no alcanza la base de datos, verifica que `bachy-db` esté saludable y que las credenciales en `.env` coincidan con las definidas en `docker-compose.yml`.
- Para reiniciar desde cero (elimina datos persistentes):

```bash
docker compose down
docker volume rm bachy-tw_mysql_data
docker compose up -d --build
```

Buenas prácticas
- Cambia `JWT_SECRET` por una cadena segura en producción.
- No expongas la base de datos MySQL directamente en producción sin firewall/restore de seguridad.

¿Necesitas que haga el commit de estos cambios en `README.md` o que añada un `CONTRIBUTING.md` con pasos más detallados? Si quieres, hago el commit ahora.

---

## Nota: comportamiento al guardar Informes

Resumen: al guardar un informe desde la UI se registran los repuestos, la mano de obra y los subtotales en la orden de reparación asociada; la orden pasa a estado "Completada" y queda protegida contra ediciones desde el popup de reparación.

- Migración incluida: `db/migrations/20260412_add_totals_to_repairs.sql` — añade columnas `parts_total`, `labour_price`, `total_amount` en `repairs`.
- Para aplicar la migración manualmente (ejemplo con MySQL expuesto por Docker):

```bash
mysql -h 127.0.0.1 -P 3307 -u bachy -psecret bachy < db/migrations/20260412_add_totals_to_repairs.sql
```

- Cambios clave:
	- `public/api/repair_reports.php`: al insertar el informe, calcula totales, crea las líneas y actualiza la orden (`repairs`) con `parts_total`, `labour_price`, `total_amount` y establece `status = 'Completada'`.
	 - `public/api/repairs.php`: rechaza `PUT` cuando la orden tiene estado `done` o `completada` (case-insensitive).
	- `public/admin/repairs.html`: el modal de edición se deshabilita si la orden está concluida; al guardar informe la lista se refresca; se muestra un enlace "Editar Orden" solo para administradores (`localStorage.role === 'admin'`).

- Pruebas recomendadas:
	1. Ejecutar la migración.
	2. Crear una orden de reparación desde la UI o via API.
	3. Abrir el modal Informe, agregar repuestos y mano de obra, y pulsar "Guardar informe".
	4. Verificar en BD (`SELECT parts_total, labour_price, total_amount, status FROM repairs WHERE id = <ID>;`) que la orden tiene los totales y `status = 'Completada'`.
	5. Intentar editar la orden (popup o PUT a la API); la edición debe rechazarse (403).

Si quieres, puedo crear un `CONTRIBUTING.md` con estos pasos y comandos listos para ejecutarse.
