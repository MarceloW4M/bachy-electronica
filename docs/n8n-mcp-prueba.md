# Prueba rápida de n8n con el MCP de Bachy

Objetivo
- Conectar n8n al MCP de Bachy
- Ver herramientas disponibles
- Probar consultas como clientes y ventas

Precondiciones
- El servicio MCP debe estar arriba en `http://127.0.0.1:3101/mcp/sse`
- Debes tener el token `MCP_AUTH_TOKEN` del archivo raíz `.env`

Configuración en n8n

1. Crear un workflow nuevo.
2. Añadir un nodo `Manual Trigger`.
3. Añadir un nodo `AI Agent`.
4. Dentro del `AI Agent`, añadir un `MCP Client Tool`.
5. Configurar el `MCP Client Tool` así:

```text
SSE Endpoint: http://127.0.0.1:3101/mcp/sse
Authentication: Bearer
Bearer Token: el valor de MCP_AUTH_TOKEN
Tool Selection: All
```

6. En el `AI Agent`, conectar el modelo que ya uses en n8n.
7. En el prompt del `AI Agent`, prueba con alguno de estos mensajes:

```text
Listame hasta 5 clientes del sistema.
```

```text
Mostrame las ultimas 3 ventas.
```

```text
Decime que herramientas MCP tengo disponibles para clientes, stock y ventas.
```

Prueba recomendada inicial
- Primero usa solo consultas:
  - `find_customers`
  - `list_sales`
  - `list_stock`
  - `find_repairs`

Prueba de escrituras controladas
Como este MCP ya tiene allowlist activa, puedes pedir también:

```text
Crea un cliente llamado Juan Perez con telefono 2804123456.
```

```text
Crea un presupuesto para el cliente 1 con un item 2, cantidad 1, precio 15000.
```

Si n8n corre en Docker
- Usa esta URL en vez de `127.0.0.1`:

```text
http://host.docker.internal:3101/mcp/sse
```

Si no resuelve, usa la IP del host.

Validación fuera de n8n
También puedes validar el MCP con el cliente de prueba del repo:

```bash
cd /root/bachy-tw/mcp/bachy-db-mcp
MCP_AUTH_TOKEN=tu_token npm run smoke:mcp
```

Eso lista herramientas y ejecuta una consulta de clientes y otra de ventas.