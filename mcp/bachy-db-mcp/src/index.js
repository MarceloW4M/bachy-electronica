import express from 'express';
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { SSEServerTransport } from '@modelcontextprotocol/sdk/server/sse.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';

import { getConfig } from './config.js';
import { closePool, createPool, query } from './db.js';
import { callTool, getToolDefinitions } from './tools.js';

const config = getConfig();
const pool = createPool(config.db);
const app = express();
const transports = new Map();

app.use(express.json({ limit: '1mb' }));

function isAuthorized(request) {
  const expectedToken = config.server.authToken;
  if (!expectedToken) {
    return true;
  }

  const authHeader = request.headers.authorization;
  if (authHeader && authHeader === `Bearer ${expectedToken}`) {
    return true;
  }

  return request.headers['x-api-key'] === expectedToken;
}

function authMiddleware(request, response, next) {
  if (!isAuthorized(request)) {
    response.status(401).json({ error: 'No autorizado' });
    return;
  }

  next();
}

function createMcpServer() {
  const server = new Server(
    {
      name: 'bachy-db-mcp',
      version: '1.0.0',
    },
    {
      capabilities: {
        tools: {},
      },
    }
  );

  server.setRequestHandler(ListToolsRequestSchema, async () => {
    return {
      tools: getToolDefinitions({
        writesEnabled: config.server.writesEnabled,
        allowedWriteTools: config.server.allowedWriteTools,
      }),
    };
  });

  server.setRequestHandler(CallToolRequestSchema, async (request) => {
    return callTool({
      pool,
      writesEnabled: config.server.writesEnabled,
      allowedWriteTools: config.server.allowedWriteTools,
      name: request.params.name,
      args: request.params.arguments ?? {},
    });
  });

  return server;
}

app.get('/', (_request, response) => {
  response.json({
    name: 'bachy-db-mcp',
    transport: 'sse',
    sse_endpoint: '/mcp/sse',
    health_endpoint: '/health',
    writes_enabled: config.server.writesEnabled,
    allowed_write_tools: config.server.allowedWriteTools,
  });
});

app.get('/health', async (_request, response) => {
  try {
    await query(pool, 'SELECT 1 AS ok');
    response.json({ ok: true });
  } catch (error) {
    response.status(500).json({ ok: false, error: error.message });
  }
});

app.get('/mcp/sse', authMiddleware, async (request, response) => {
  const transport = new SSEServerTransport('/mcp/messages', response);
  const server = createMcpServer();

  transports.set(transport.sessionId, { transport, server });

  response.on('close', async () => {
    transports.delete(transport.sessionId);
    await server.close();
  });

  try {
    await server.connect(transport);
  } catch (error) {
    transports.delete(transport.sessionId);
    await server.close();
    throw error;
  }
});

app.post('/mcp/messages', authMiddleware, async (request, response) => {
  const sessionId = typeof request.query.sessionId === 'string' ? request.query.sessionId : undefined;
  if (!sessionId || !transports.has(sessionId)) {
    response.status(404).json({ error: 'Sesión MCP no encontrada' });
    return;
  }

  const { transport } = transports.get(sessionId);
  await transport.handlePostMessage(request, response, request.body);
});

const serverInstance = app.listen(config.server.port, config.server.host, () => {
  process.stdout.write(
    `bachy-db-mcp escuchando en http://${config.server.host}:${config.server.port}/mcp/sse\n`
  );
});

async function shutdown(signal) {
  process.stdout.write(`Recibido ${signal}, cerrando servidor MCP...\n`);
  serverInstance.close(async () => {
    await closePool();
    process.exit(0);
  });
}

process.on('SIGINT', () => void shutdown('SIGINT'));
process.on('SIGTERM', () => void shutdown('SIGTERM'));