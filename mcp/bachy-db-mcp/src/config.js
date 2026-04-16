import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import dotenv from 'dotenv';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function loadEnv(pathname) {
  if (fs.existsSync(pathname)) {
    dotenv.config({ path: pathname, override: false });
  }
}

loadEnv(path.resolve(process.cwd(), '.env'));
loadEnv(path.resolve(__dirname, '../.env'));
loadEnv(path.resolve(__dirname, '../../.env'));
loadEnv(path.resolve(__dirname, '../../../.env'));

function env(keys, fallback = undefined) {
  for (const key of keys) {
    const value = process.env[key];
    if (value !== undefined && value !== '') {
      return value;
    }
  }
  return fallback;
}

function envInt(keys, fallback) {
  const raw = env(keys);
  if (raw === undefined) {
    return fallback;
  }

  const parsed = Number.parseInt(raw, 10);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function envBool(keys, fallback = false) {
  const raw = env(keys);
  if (raw === undefined) {
    return fallback;
  }

  return ['1', 'true', 'yes', 'on'].includes(String(raw).trim().toLowerCase());
}

function envCsv(keys) {
  const raw = env(keys, '');
  return String(raw)
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
}

export function getConfig() {
  const authToken = env(['MCP_AUTH_TOKEN'], '');
  if (!authToken || authToken === 'define_un_token_largo_y_seguro' || authToken === 'cambiar_este_token_mcp') {
    throw new Error('Debes definir MCP_AUTH_TOKEN con un valor seguro antes de iniciar el servidor MCP.');
  }

  const writesEnabled = envBool(['MCP_ENABLE_WRITES'], false);
  const allowedWriteTools = envCsv(['MCP_ALLOWED_WRITE_TOOLS']);

  return {
    server: {
      host: env(['MCP_HOST'], '0.0.0.0'),
      port: envInt(['MCP_PORT'], 3101),
      authToken,
      writesEnabled,
      allowedWriteTools,
    },
    db: {
      host: env(['BACHY_MCP_DB_HOST', 'DB_HOST'], '127.0.0.1'),
      port: envInt(['BACHY_MCP_DB_PORT', 'DB_PORT'], 3307),
      name: env(['BACHY_MCP_DB_NAME', 'DB_NAME'], 'bachy'),
      user: env(['BACHY_MCP_DB_USER', 'DB_USER'], 'bachy'),
      password: env(['BACHY_MCP_DB_PASS', 'DB_PASS'], 'secret'),
      connectionLimit: envInt(['BACHY_MCP_DB_POOL_SIZE'], 10),
    },
  };
}