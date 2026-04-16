import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { SSEClientTransport } from '@modelcontextprotocol/sdk/client/sse.js';

const endpoint = process.env.MCP_SSE_URL ?? 'http://127.0.0.1:3101/mcp/sse';
const token = process.env.MCP_AUTH_TOKEN;

if (!token) {
  process.stderr.write('Falta MCP_AUTH_TOKEN para probar el cliente MCP.\n');
  process.exit(1);
}

const client = new Client(
  {
    name: 'bachy-db-mcp-smoke-client',
    version: '1.0.0',
  },
  {
    capabilities: {},
  }
);

const transport = new SSEClientTransport(new URL(endpoint), {
  requestInit: {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  },
});

try {
  await client.connect(transport);

  const tools = await client.listTools();
  const toolNames = tools.tools.map((tool) => tool.name).sort();

  const callResults = {};
  for (const request of [
    { label: 'find_customers', params: { name: 'find_customers', arguments: { limit: 3 } } },
    { label: 'list_sales', params: { name: 'list_sales', arguments: { limit: 3 } } },
  ]) {
    try {
      callResults[request.label] = await client.callTool(request.params);
    } catch (error) {
      callResults[request.label] = {
        error: error instanceof Error ? error.message : String(error),
      };
    }
  }

  process.stdout.write(JSON.stringify({
    endpoint,
    total_tools: toolNames.length,
    sample_tools: toolNames.slice(0, 12),
    call_results: callResults,
  }, null, 2));
  process.stdout.write('\n');
} finally {
  await client.close();
}