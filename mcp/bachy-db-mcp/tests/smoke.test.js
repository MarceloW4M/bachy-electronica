import test from 'node:test';
import assert from 'node:assert/strict';

import { getToolDefinitions } from '../src/tools.js';

test('expone herramientas de lectura por defecto', () => {
  const tools = getToolDefinitions({ writesEnabled: false, allowedWriteTools: [] });
  const names = tools.map((tool) => tool.name);

  assert.ok(names.includes('find_customers'));
  assert.ok(names.includes('list_stock'));
  assert.ok(names.includes('list_brands'));
  assert.ok(names.includes('list_cities'));
  assert.ok(!names.includes('create_customer'));
});

test('expone solo herramientas de escritura permitidas', () => {
  const tools = getToolDefinitions({
    writesEnabled: true,
    allowedWriteTools: ['create_customer', 'create_device'],
  });
  const names = tools.map((tool) => tool.name);

  assert.ok(names.includes('create_customer'));
  assert.ok(names.includes('create_device'));
  assert.ok(!names.includes('create_repair'));
  assert.ok(!names.includes('upsert_stock'));
});