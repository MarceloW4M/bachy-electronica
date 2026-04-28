# Bachy DB MCP

Servidor MCP orientado a n8n para consultar y operar la base de datos de Bachy TW.

Qué expone
- Clientes
- Dispositivos
- Técnicos
- Reparaciones
- Agenda de reparaciones
- Artículos
- Rubros
- Marcas
- Modelos
- Provincias
- Ciudades
- Ventas
- Compras
- Presupuestos
- Remitos
- Facturas
- Stock y movimientos de stock
- Depósitos
- Proveedores

Transporte
- HTTP + SSE, pensado para usarlo desde el nodo MCP Client Tool de n8n.

Herramientas disponibles
- `database_summary`
- `find_customers`
- `get_customer`
- `find_devices`
- `find_technicians`
- `find_repairs`
- `get_agenda`
- `list_categories`
- `list_brands`
- `list_models`
- `list_provinces`
- `list_cities`
- `list_sales`
- `list_purchases`
- `list_quotes`
- `list_remitos`
- `list_invoices`
- `find_items`
- `list_stock`
- `get_stock_movements`
- `list_warehouses`
- `find_suppliers`

Herramientas opcionales de escritura
- `create_customer`
- `create_repair`
- `create_device`
- `create_sale`
- `create_purchase`
- `create_quote`
- `create_remito`
- `create_invoice`
- `upsert_stock`

Herramientas de escritura nuevas
- `update_repair`: actualiza campos de una reparación existente (reagendar, reasignar técnico, cambiar estado).
- `delete_repair`: elimina una reparación existente.

Ejemplo `update_repair` (para reagendar):
```json
{
	"repair_id": 789,
	"scheduled_at": "2026-05-05 15:00:00",
	"technician_id": 9,
	"status": "scheduled"
}
```

Ejemplo `delete_repair`:
```json
{ "repair_id": 789 }
```

Estas herramientas de escritura solo aparecen si defines `MCP_ENABLE_WRITES=true`.
Además, solo se exponen las que pongas explícitamente en `MCP_ALLOWED_WRITE_TOOLS`.

Instalación

1. Ir a la carpeta del MCP:

```bash
cd /root/bachy-tw/mcp/bachy-db-mcp
```

2. Crear archivo de entorno:
MCP_ALLOWED_WRITE_TOOLS=create_customer,create_device
```bash
cp .env.example .env
```

3. Instalar dependencias:

```bash
npm install
```

4. Arrancar el servidor:

```bash
npm start
```

Por defecto queda escuchando en:
- `http://127.0.0.1:3101/mcp/sse`

Configuración recomendada para este repo

Si tu app Bachy usa el `docker-compose.yml` actual, MySQL queda expuesto en `127.0.0.1:3307`. Por eso el `.env.example` del MCP ya viene preparado con:

```env
MCP_AUTH_TOKEN=define_un_token_largo_y_seguro
MCP_ENABLE_WRITES=false
MCP_ALLOWED_WRITE_TOOLS=
BACHY_MCP_DB_HOST=127.0.0.1
BACHY_MCP_DB_PORT=3307
BACHY_MCP_DB_NAME=bachy
BACHY_MCP_DB_USER=bachy
BACHY_MCP_DB_PASS=secret
```

Autenticación obligatoria

El servidor no arranca si `MCP_AUTH_TOKEN` está vacío o con el placeholder de ejemplo.

```env
MCP_AUTH_TOKEN=tu_token_seguro
```

Con eso el servidor aceptará:
- `Authorization: Bearer tu_token_seguro`
- o header `x-api-key: tu_token_seguro`

Configuración en n8n

En n8n, usa el nodo `MCP Client Tool`.

Valores a cargar:
- `SSE Endpoint`: `http://127.0.0.1:3101/mcp/sse`
- `Authentication`: `Bearer`
- `Tool Selection`: `All` para exponer todo, o `All Except` para excluir herramientas de escritura

Recomendación inicial
- Arranca primero con `MCP_ENABLE_WRITES=false`
- En n8n usa solo herramientas de lectura hasta validar respuestas
- Si necesitas escrituras, habilítalas de forma acotada, por ejemplo:

```env
MCP_ENABLE_WRITES=true
MCP_ALLOWED_WRITE_TOOLS=create_customer,create_device,create_repair,upsert_stock,create_sale,create_purchase,create_quote,create_remito,create_invoice
```

Eso expone solo las herramientas listadas, no todas las escrituras.

En este repo quedó configurado justamente ese modo: escrituras habilitadas pero acotadas por allowlist en el archivo raíz `.env`.

Uso con Docker Compose del proyecto principal

El repo ya quedó preparado para levantar el MCP como servicio `mcp` dentro del `docker-compose.yml` principal.

Pasos:

```bash
cd /root/bachy-tw
docker compose up -d --build mcp
```

Con eso el endpoint queda publicado en:
- `http://127.0.0.1:3101/mcp/sse`

Si n8n corre en Docker

Usa una de estas opciones para el `SSE Endpoint`:
- `http://host.docker.internal:3101/mcp/sse` si tu Docker lo soporta
- `http://IP_DE_TU_HOST:3101/mcp/sse` en Linux si `host.docker.internal` no resuelve
- `http://bachy-mcp:3101/mcp/sse` si luego decides meter este MCP dentro del mismo `docker-compose` con ese nombre de servicio

Pruebas rápidas

Health check:

```bash
curl http://127.0.0.1:3101/health
```

Tests básicos:

```bash
npm test
```

Notas
- Este servidor accede directo a MySQL; no pasa por los endpoints PHP.
- Si cambias el esquema SQL, revisa las consultas del MCP.
- Si el MCP corre fuera de Docker, no reutilices ciegamente `DB_HOST=db`; usa `BACHY_MCP_DB_HOST=127.0.0.1` o la IP correcta.