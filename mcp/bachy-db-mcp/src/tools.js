import { query, withTransaction } from './db.js';

function clampLimit(value, fallback = 20, max = 200) {
  const parsed = Number.parseInt(value ?? fallback, 10);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    return fallback;
  }

  return Math.min(parsed, max);
}

function likeTerm(value) {
  return `%${String(value).trim()}%`;
}

function asTextResult(payload) {
  return {
    content: [
      {
        type: 'text',
        text: JSON.stringify(payload, null, 2),
      },
    ],
  };
}

function ensureWriteAllowed(writeToolNames, toolName) {
  if (!writeToolNames.has(toolName)) {
    throw new Error(`La herramienta de escritura ${toolName} no está permitida. Revisa MCP_ALLOWED_WRITE_TOOLS.`);
  }
}

async function getCityInfo(pool, cityId) {
  const rows = await query(
    pool,
    'SELECT id, province_id, postal_code, name FROM cities WHERE id = ? LIMIT 1',
    [cityId]
  );
  return rows[0] ?? null;
}

async function ensureExists(pool, tableName, id) {
  const rows = await query(pool, `SELECT id FROM ${tableName} WHERE id = ? LIMIT 1`, [id]);
  return Boolean(rows[0]);
}

const READ_TOOLS = [
  {
    name: 'database_summary',
    description: 'Devuelve un resumen general de clientes, dispositivos, reparaciones, técnicos, artículos, depósitos y stock.',
    inputSchema: {
      type: 'object',
      properties: {},
      additionalProperties: false,
    },
  },
  {
    name: 'find_customers',
    description: 'Busca clientes por nombre, teléfono, email o DNI/CUIT.',
    inputSchema: {
      type: 'object',
      properties: {
        query: { type: 'string' },
        active_only: { type: 'boolean', default: true },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'get_customer',
    description: 'Obtiene un cliente puntual con resumen de dispositivos y reparaciones asociadas.',
    inputSchema: {
      type: 'object',
      properties: {
        customer_id: { type: 'integer' },
      },
      required: ['customer_id'],
      additionalProperties: false,
    },
  },
  {
    name: 'find_devices',
    description: 'Busca dispositivos por cliente, marca, modelo o serie.',
    inputSchema: {
      type: 'object',
      properties: {
        query: { type: 'string' },
        customer_id: { type: 'integer' },
        active_only: { type: 'boolean', default: true },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'find_technicians',
    description: 'Busca técnicos por nombre, teléfono o email.',
    inputSchema: {
      type: 'object',
      properties: {
        query: { type: 'string' },
        active_only: { type: 'boolean', default: true },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'find_repairs',
    description: 'Busca reparaciones por estado, cliente, técnico, texto libre o rango de fechas.',
    inputSchema: {
      type: 'object',
      properties: {
        query: { type: 'string' },
        status: { type: 'string' },
        technician_id: { type: 'integer' },
        customer_id: { type: 'integer' },
        from_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        to_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'get_agenda',
    description: 'Devuelve agenda de reparaciones programadas por rango de fecha y técnico.',
    inputSchema: {
      type: 'object',
      properties: {
        from_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        to_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        technician_id: { type: 'integer' },
        limit: { type: 'integer', minimum: 1, maximum: 200 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_categories',
    description: 'Lista rubros o categorías de artículos.',
    inputSchema: {
      type: 'object',
      properties: {
        active_only: { type: 'boolean', default: true },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_brands',
    description: 'Lista marcas activas o todas.',
    inputSchema: {
      type: 'object',
      properties: {
        active_only: { type: 'boolean', default: true },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_models',
    description: 'Lista modelos por marca o estado.',
    inputSchema: {
      type: 'object',
      properties: {
        brand_id: { type: 'integer' },
        active_only: { type: 'boolean', default: true },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_provinces',
    description: 'Lista provincias disponibles.',
    inputSchema: {
      type: 'object',
      properties: {},
      additionalProperties: false,
    },
  },
  {
    name: 'list_cities',
    description: 'Lista ciudades por provincia o por texto.',
    inputSchema: {
      type: 'object',
      properties: {
        province_id: { type: 'integer' },
        query: { type: 'string' },
        limit: { type: 'integer', minimum: 1, maximum: 200 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'find_items',
    description: 'Busca artículos por nombre, SKU, código o rubro.',
    inputSchema: {
      type: 'object',
      properties: {
        query: { type: 'string' },
        category_id: { type: 'integer' },
        active_only: { type: 'boolean', default: true },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_stock',
    description: 'Lista stock por artículo, depósito o búsqueda textual. También permite detectar bajo stock.',
    inputSchema: {
      type: 'object',
      properties: {
        query: { type: 'string' },
        item_id: { type: 'integer' },
        warehouse_id: { type: 'integer' },
        low_stock_threshold: { type: 'integer' },
        limit: { type: 'integer', minimum: 1, maximum: 200 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'get_stock_movements',
    description: 'Consulta movimientos de stock por artículo, depósito o fecha.',
    inputSchema: {
      type: 'object',
      properties: {
        item_id: { type: 'integer' },
        warehouse_id: { type: 'integer' },
        since: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        limit: { type: 'integer', minimum: 1, maximum: 200 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_sales',
    description: 'Lista ventas o devuelve una venta puntual con sus items.',
    inputSchema: {
      type: 'object',
      properties: {
        sale_id: { type: 'integer' },
        customer_id: { type: 'integer' },
        from_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        to_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_purchases',
    description: 'Lista compras o devuelve una compra puntual con sus items.',
    inputSchema: {
      type: 'object',
      properties: {
        purchase_id: { type: 'integer' },
        supplier_id: { type: 'integer' },
        from_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        to_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_quotes',
    description: 'Lista presupuestos o devuelve uno puntual con sus items.',
    inputSchema: {
      type: 'object',
      properties: {
        quote_id: { type: 'integer' },
        customer_id: { type: 'integer' },
        from_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        to_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_remitos',
    description: 'Lista remitos o devuelve uno puntual con sus items.',
    inputSchema: {
      type: 'object',
      properties: {
        remito_id: { type: 'integer' },
        recipient: { type: 'string' },
        from_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        to_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_invoices',
    description: 'Lista facturas o devuelve una puntual con sus items.',
    inputSchema: {
      type: 'object',
      properties: {
        invoice_id: { type: 'integer' },
        customer_id: { type: 'integer' },
        from_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        to_date: { type: 'string', description: 'Formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS' },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'list_warehouses',
    description: 'Lista depósitos activos o todos.',
    inputSchema: {
      type: 'object',
      properties: {
        active_only: { type: 'boolean', default: true },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'find_suppliers',
    description: 'Busca proveedores por nombre, contacto, teléfono o email.',
    inputSchema: {
      type: 'object',
      properties: {
        query: { type: 'string' },
        active_only: { type: 'boolean', default: true },
        limit: { type: 'integer', minimum: 1, maximum: 100 },
      },
      additionalProperties: false,
    },
  },
];

const WRITE_TOOLS = [
  {
    name: 'create_customer',
    description: 'Crea un cliente nuevo.',
    inputSchema: {
      type: 'object',
      properties: {
        name: { type: 'string' },
        dni_cuit: { type: 'string' },
        phone: { type: 'string' },
        email: { type: 'string' },
        address: { type: 'string' },
        province_id: { type: 'integer' },
        city_id: { type: 'integer' },
        postal_code: { type: 'string' },
      },
      required: ['name'],
      additionalProperties: false,
    },
  },
  {
    name: 'create_repair',
    description: 'Crea una reparación nueva y opcionalmente la agenda para una fecha.',
    inputSchema: {
      type: 'object',
      properties: {
        customer_id: { type: 'integer' },
        device_id: { type: 'integer' },
        device_model: { type: 'string' },
        problem: { type: 'string' },
        status: { type: 'string' },
        technician_id: { type: 'integer' },
        scheduled_at: { type: 'string', description: 'Formato YYYY-MM-DD HH:MM:SS' },
        contact: { type: 'string' },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'create_device',
    description: 'Crea un dispositivo para un cliente.',
    inputSchema: {
      type: 'object',
      properties: {
        customer_id: { type: 'integer' },
        serial: { type: 'string' },
        brand: { type: 'string' },
        model: { type: 'string' },
        notes: { type: 'string' },
      },
      additionalProperties: false,
    },
  },
  {
    name: 'create_sale',
    description: 'Crea una venta con items y opcionalmente descuenta stock de un depósito.',
    inputSchema: {
      type: 'object',
      properties: {
        customer_id: { type: 'integer' },
        warehouse_id: { type: 'integer' },
        items: {
          type: 'array',
          minItems: 1,
          items: {
            type: 'object',
            properties: {
              item_id: { type: 'integer' },
              quantity: { type: 'integer' },
              unit_price: { type: 'number' }
            },
            required: ['item_id', 'quantity', 'unit_price'],
            additionalProperties: false
          }
        }
      },
      required: ['items'],
      additionalProperties: false,
    },
  },
  {
    name: 'create_purchase',
    description: 'Crea una compra con items y opcionalmente incrementa stock en un depósito.',
    inputSchema: {
      type: 'object',
      properties: {
        supplier_id: { type: 'integer' },
        warehouse_id: { type: 'integer' },
        items: {
          type: 'array',
          minItems: 1,
          items: {
            type: 'object',
            properties: {
              item_id: { type: 'integer' },
              quantity: { type: 'integer' },
              unit_price: { type: 'number' }
            },
            required: ['item_id', 'quantity', 'unit_price'],
            additionalProperties: false
          }
        }
      },
      required: ['items'],
      additionalProperties: false,
    },
  },
  {
    name: 'create_quote',
    description: 'Crea un presupuesto con items.',
    inputSchema: {
      type: 'object',
      properties: {
        customer_id: { type: 'integer' },
        items: {
          type: 'array',
          minItems: 1,
          items: {
            type: 'object',
            properties: {
              item_id: { type: 'integer' },
              quantity: { type: 'integer' },
              unit_price: { type: 'number' }
            },
            required: ['item_id', 'quantity', 'unit_price'],
            additionalProperties: false
          }
        }
      },
      required: ['items'],
      additionalProperties: false,
    },
  },
  {
    name: 'create_remito',
    description: 'Crea un remito con items.',
    inputSchema: {
      type: 'object',
      properties: {
        recipient: { type: 'string' },
        items: {
          type: 'array',
          minItems: 1,
          items: {
            type: 'object',
            properties: {
              item_id: { type: 'integer' },
              quantity: { type: 'integer' }
            },
            required: ['item_id', 'quantity'],
            additionalProperties: false
          }
        }
      },
      required: ['items'],
      additionalProperties: false,
    },
  },
  {
    name: 'create_invoice',
    description: 'Crea una factura con items.',
    inputSchema: {
      type: 'object',
      properties: {
        customer_id: { type: 'integer' },
        items: {
          type: 'array',
          minItems: 1,
          items: {
            type: 'object',
            properties: {
              item_id: { type: 'integer' },
              quantity: { type: 'integer' },
              unit_price: { type: 'number' }
            },
            required: ['item_id', 'quantity', 'unit_price'],
            additionalProperties: false
          }
        }
      },
      required: ['items'],
      additionalProperties: false,
    },
  },
  {
    name: 'upsert_stock',
    description: 'Crea o actualiza el stock de un artículo en un depósito y registra el movimiento.',
    inputSchema: {
      type: 'object',
      properties: {
        item_id: { type: 'integer' },
        warehouse_id: { type: 'integer' },
        quantity: { type: 'integer' },
        note: { type: 'string' },
      },
      required: ['item_id', 'warehouse_id', 'quantity'],
      additionalProperties: false,
    },
  },
];

export function getToolDefinitions({ writesEnabled, allowedWriteTools = [] }) {
  if (!writesEnabled) {
    return READ_TOOLS;
  }

  const allowed = new Set(allowedWriteTools);
  return [...READ_TOOLS, ...WRITE_TOOLS.filter((tool) => allowed.has(tool.name))];
}

export async function callTool({ pool, writesEnabled, allowedWriteTools = [], name, args = {} }) {
  const writeToolNames = writesEnabled ? new Set(allowedWriteTools) : new Set();
  switch (name) {
    case 'database_summary': {
      const counts = await Promise.all([
        query(pool, 'SELECT COUNT(*) AS total FROM customers'),
        query(pool, 'SELECT COUNT(*) AS total FROM devices'),
        query(pool, 'SELECT COUNT(*) AS total FROM repairs'),
        query(pool, 'SELECT COUNT(*) AS total FROM technicians WHERE active = 1'),
        query(pool, 'SELECT COUNT(*) AS total FROM items WHERE active = 1'),
        query(pool, 'SELECT COUNT(*) AS total FROM warehouses WHERE active = 1'),
        query(pool, 'SELECT COUNT(*) AS total FROM suppliers WHERE active = 1'),
        query(pool, 'SELECT COUNT(*) AS total FROM stocks'),
      ]);

      const upcomingRepairs = await query(
        pool,
        `SELECT r.id, r.status, r.device_model, r.scheduled_at, c.name AS customer_name, t.name AS technician_name
         FROM repairs r
         LEFT JOIN customers c ON c.id = r.customer_id
         LEFT JOIN technicians t ON t.id = r.technician_id
         WHERE r.scheduled_at IS NOT NULL
         ORDER BY r.scheduled_at ASC
         LIMIT 10`
      );

      return asTextResult({
        customers: counts[0][0].total,
        devices: counts[1][0].total,
        repairs: counts[2][0].total,
        active_technicians: counts[3][0].total,
        active_items: counts[4][0].total,
        active_warehouses: counts[5][0].total,
        active_suppliers: counts[6][0].total,
        stock_rows: counts[7][0].total,
        upcoming_repairs: upcomingRepairs,
      });
    }

    case 'find_customers': {
      const params = [];
      const limit = clampLimit(args.limit, 20, 100);
      let sql = `SELECT c.id, c.name, c.dni_cuit, c.phone, c.email, c.address, c.postal_code, c.active,
                        p.name AS province_name, ci.name AS city_name, c.created_at
                 FROM customers c
                 LEFT JOIN provinces p ON p.id = c.province_id
                 LEFT JOIN cities ci ON ci.id = c.city_id
                 WHERE 1 = 1`;

      if (args.active_only !== false) {
        sql += ' AND c.active = 1';
      }

      if (args.query) {
        sql += ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.dni_cuit LIKE ?)';
        const term = likeTerm(args.query);
        params.push(term, term, term, term);
      }

      sql += ` ORDER BY c.name ASC LIMIT ${limit}`;

      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'get_customer': {
      const customerId = Number(args.customer_id);
      const rows = await query(
        pool,
        `SELECT c.*, p.name AS province_name, ci.name AS city_name
         FROM customers c
         LEFT JOIN provinces p ON p.id = c.province_id
         LEFT JOIN cities ci ON ci.id = c.city_id
         WHERE c.id = ?
         LIMIT 1`,
        [customerId]
      );

      if (!rows[0]) {
        throw new Error(`Cliente ${customerId} no encontrado.`);
      }

      const [devices, repairs] = await Promise.all([
        query(
          pool,
          'SELECT id, serial, brand, model, active, created_at FROM devices WHERE customer_id = ? ORDER BY created_at DESC',
          [customerId]
        ),
        query(
          pool,
          `SELECT r.id, r.device_model, r.problem, r.status, r.scheduled_at, t.name AS technician_name
           FROM repairs r
           LEFT JOIN technicians t ON t.id = r.technician_id
           WHERE r.customer_id = ?
           ORDER BY COALESCE(r.scheduled_at, r.created_at) DESC`,
          [customerId]
        ),
      ]);

      return asTextResult({ customer: rows[0], devices, repairs });
    }

    case 'find_devices': {
      const params = [];
      const limit = clampLimit(args.limit, 20, 100);
      let sql = `SELECT d.id, d.customer_id, c.name AS customer_name, d.serial, d.brand, d.model, d.notes, d.active, d.created_at
                 FROM devices d
                 LEFT JOIN customers c ON c.id = d.customer_id
                 WHERE 1 = 1`;

      if (args.active_only !== false) {
        sql += ' AND d.active = 1';
      }

      if (args.customer_id) {
        sql += ' AND d.customer_id = ?';
        params.push(Number(args.customer_id));
      }

      if (args.query) {
        sql += ' AND (d.serial LIKE ? OR d.brand LIKE ? OR d.model LIKE ? OR c.name LIKE ?)';
        const term = likeTerm(args.query);
        params.push(term, term, term, term);
      }

      sql += ` ORDER BY d.created_at DESC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'find_technicians': {
      const params = [];
      const limit = clampLimit(args.limit, 20, 100);
      let sql = 'SELECT id, name, phone, email, active, created_at FROM technicians WHERE 1 = 1';

      if (args.active_only !== false) {
        sql += ' AND active = 1';
      }

      if (args.query) {
        sql += ' AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)';
        const term = likeTerm(args.query);
        params.push(term, term, term);
      }

      sql += ` ORDER BY name ASC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'find_repairs': {
      const params = [];
      const limit = clampLimit(args.limit, 30, 100);
      let sql = `SELECT r.id, r.customer_id, r.device_id, r.device_model, r.problem, r.status, r.technician_id,
                        r.scheduled_at, r.contact, r.created_at,
                        c.name AS customer_name, t.name AS technician_name
                 FROM repairs r
                 LEFT JOIN customers c ON c.id = r.customer_id
                 LEFT JOIN technicians t ON t.id = r.technician_id
                 WHERE 1 = 1`;

      if (args.customer_id) {
        sql += ' AND r.customer_id = ?';
        params.push(Number(args.customer_id));
      }

      if (args.technician_id) {
        sql += ' AND r.technician_id = ?';
        params.push(Number(args.technician_id));
      }

      if (args.status) {
        sql += ' AND r.status = ?';
        params.push(String(args.status));
      }

      if (args.from_date) {
        sql += ' AND COALESCE(r.scheduled_at, r.created_at) >= ?';
        params.push(String(args.from_date));
      }

      if (args.to_date) {
        sql += ' AND COALESCE(r.scheduled_at, r.created_at) <= ?';
        params.push(String(args.to_date));
      }

      if (args.query) {
        sql += ' AND (c.name LIKE ? OR r.device_model LIKE ? OR r.problem LIKE ? OR r.contact LIKE ?)';
        const term = likeTerm(args.query);
        params.push(term, term, term, term);
      }

      sql += ` ORDER BY COALESCE(r.scheduled_at, r.created_at) DESC, r.id DESC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'get_agenda': {
      const params = [];
      const limit = clampLimit(args.limit, 50, 200);
      let sql = `SELECT r.id, r.status, r.device_model, r.problem, r.scheduled_at, r.contact,
                        c.name AS customer_name, c.phone AS customer_phone,
                        t.name AS technician_name
                 FROM repairs r
                 LEFT JOIN customers c ON c.id = r.customer_id
                 LEFT JOIN technicians t ON t.id = r.technician_id
                 WHERE r.scheduled_at IS NOT NULL`;

      if (args.from_date) {
        sql += ' AND r.scheduled_at >= ?';
        params.push(String(args.from_date));
      }

      if (args.to_date) {
        sql += ' AND r.scheduled_at <= ?';
        params.push(String(args.to_date));
      }

      if (args.technician_id) {
        sql += ' AND r.technician_id = ?';
        params.push(Number(args.technician_id));
      }

      sql += ` ORDER BY r.scheduled_at ASC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'find_items': {
      const params = [];
      const limit = clampLimit(args.limit, 20, 100);
      let sql = `SELECT i.id, i.name, i.sku, i.barcode, i.qr_code, i.unit_price, i.active,
                        c.name AS category_name, b.name AS brand_name, m.name AS model_name
                 FROM items i
                 LEFT JOIN categories c ON c.id = i.category_id
                 LEFT JOIN brands b ON b.id = i.brand_id
                 LEFT JOIN models m ON m.id = i.model_id
                 WHERE 1 = 1`;

      if (args.active_only !== false) {
        sql += ' AND i.active = 1';
      }

      if (args.category_id) {
        sql += ' AND i.category_id = ?';
        params.push(Number(args.category_id));
      }

      if (args.query) {
        sql += ' AND (i.name LIKE ? OR i.sku LIKE ? OR i.barcode LIKE ? OR i.qr_code LIKE ? OR c.name LIKE ?)';
        const term = likeTerm(args.query);
        params.push(term, term, term, term, term);
      }

      sql += ` ORDER BY i.name ASC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_categories': {
      const rows = args.active_only === false
        ? await query(pool, 'SELECT id, name, active, created_at FROM categories ORDER BY name ASC')
        : await query(pool, 'SELECT id, name, active, created_at FROM categories WHERE active = 1 ORDER BY name ASC');
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_brands': {
      const rows = args.active_only === false
        ? await query(pool, 'SELECT id, name, active FROM brands ORDER BY name ASC')
        : await query(pool, 'SELECT id, name, active FROM brands WHERE active = 1 ORDER BY name ASC');
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_models': {
      const params = [];
      let sql = `SELECT m.id, m.brand_id, m.name, m.active, b.name AS brand_name
                 FROM models m
                 LEFT JOIN brands b ON b.id = m.brand_id
                 WHERE 1 = 1`;
      if (args.brand_id) {
        sql += ' AND m.brand_id = ?';
        params.push(Number(args.brand_id));
      }
      if (args.active_only !== false) {
        sql += ' AND m.active = 1';
      }
      sql += ' ORDER BY b.name ASC, m.name ASC';
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_provinces': {
      const rows = await query(pool, 'SELECT id, name, created_at FROM provinces ORDER BY name ASC');
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_cities': {
      const params = [];
      const limit = clampLimit(args.limit, 100, 200);
      let sql = `SELECT ci.id, ci.province_id, ci.name, ci.postal_code, p.name AS province_name
                 FROM cities ci
                 JOIN provinces p ON p.id = ci.province_id
                 WHERE 1 = 1`;
      if (args.province_id) {
        sql += ' AND ci.province_id = ?';
        params.push(Number(args.province_id));
      }
      if (args.query) {
        sql += ' AND (ci.name LIKE ? OR ci.postal_code LIKE ? OR p.name LIKE ?)';
        const term = likeTerm(args.query);
        params.push(term, term, term);
      }
      sql += ` ORDER BY p.name ASC, ci.name ASC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_stock': {
      const params = [];
      const limit = clampLimit(args.limit, 50, 200);
      let sql = `SELECT s.id, s.item_id, s.warehouse_id, s.quantity, s.created_at, s.updated_at,
                        i.name AS item_name, i.sku, i.unit_price,
                        c.name AS category_name,
                        w.name AS warehouse_name, w.location AS warehouse_location
                 FROM stocks s
                 JOIN items i ON i.id = s.item_id
                 JOIN warehouses w ON w.id = s.warehouse_id
                 LEFT JOIN categories c ON c.id = i.category_id
                 WHERE 1 = 1`;

      if (args.item_id) {
        sql += ' AND s.item_id = ?';
        params.push(Number(args.item_id));
      }

      if (args.warehouse_id) {
        sql += ' AND s.warehouse_id = ?';
        params.push(Number(args.warehouse_id));
      }

      if (args.low_stock_threshold !== undefined) {
        sql += ' AND s.quantity <= ?';
        params.push(Number(args.low_stock_threshold));
      }

      if (args.query) {
        sql += ' AND (i.name LIKE ? OR i.sku LIKE ? OR c.name LIKE ? OR w.name LIKE ?)';
        const term = likeTerm(args.query);
        params.push(term, term, term, term);
      }

      sql += ` ORDER BY w.name ASC, i.name ASC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'get_stock_movements': {
      const params = [];
      const limit = clampLimit(args.limit, 50, 200);
      let sql = `SELECT sm.id, sm.item_id, sm.warehouse_id, sm.quantity_before, sm.quantity_after, sm.delta, sm.note, sm.created_at,
                        i.name AS item_name, w.name AS warehouse_name
                 FROM stock_movements sm
                 JOIN items i ON i.id = sm.item_id
                 JOIN warehouses w ON w.id = sm.warehouse_id
                 WHERE 1 = 1`;

      if (args.item_id) {
        sql += ' AND sm.item_id = ?';
        params.push(Number(args.item_id));
      }

      if (args.warehouse_id) {
        sql += ' AND sm.warehouse_id = ?';
        params.push(Number(args.warehouse_id));
      }

      if (args.since) {
        sql += ' AND sm.created_at >= ?';
        params.push(String(args.since));
      }

      sql += ` ORDER BY sm.created_at DESC, sm.id DESC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_warehouses': {
      const rows = args.active_only === false
        ? await query(pool, 'SELECT id, name, location, active, created_at FROM warehouses ORDER BY name ASC')
        : await query(pool, 'SELECT id, name, location, active, created_at FROM warehouses WHERE active = 1 ORDER BY name ASC');
      return asTextResult({ total: rows.length, rows });
    }

    case 'find_suppliers': {
      const params = [];
      const limit = clampLimit(args.limit, 20, 100);
      let sql = 'SELECT id, name, contact, phone, email, active, created_at FROM suppliers WHERE 1 = 1';

      if (args.active_only !== false) {
        sql += ' AND active = 1';
      }

      if (args.query) {
        sql += ' AND (name LIKE ? OR contact LIKE ? OR phone LIKE ? OR email LIKE ?)';
        const term = likeTerm(args.query);
        params.push(term, term, term, term);
      }

      sql += ` ORDER BY name ASC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_sales': {
      if (args.sale_id) {
        const sales = await query(pool, 'SELECT * FROM sales WHERE id = ? LIMIT 1', [Number(args.sale_id)]);
        if (!sales[0]) {
          throw new Error(`Venta ${args.sale_id} no encontrada.`);
        }
        const items = await query(
          pool,
          `SELECT si.*, i.name AS item_name
           FROM sale_items si
           LEFT JOIN items i ON i.id = si.item_id
           WHERE si.sale_id = ?`,
          [Number(args.sale_id)]
        );
        return asTextResult({ sale: sales[0], items });
      }

      const params = [];
      const limit = clampLimit(args.limit, 50, 100);
      let sql = 'SELECT * FROM sales WHERE 1 = 1';
      if (args.customer_id) {
        sql += ' AND customer_id = ?';
        params.push(Number(args.customer_id));
      }
      if (args.from_date) {
        sql += ' AND created_at >= ?';
        params.push(String(args.from_date));
      }
      if (args.to_date) {
        sql += ' AND created_at <= ?';
        params.push(String(args.to_date));
      }
      sql += ` ORDER BY created_at DESC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_purchases': {
      if (args.purchase_id) {
        const purchases = await query(pool, 'SELECT * FROM purchases WHERE id = ? LIMIT 1', [Number(args.purchase_id)]);
        if (!purchases[0]) {
          throw new Error(`Compra ${args.purchase_id} no encontrada.`);
        }
        const items = await query(
          pool,
          `SELECT pi.*, i.name AS item_name
           FROM purchase_items pi
           LEFT JOIN items i ON i.id = pi.item_id
           WHERE pi.purchase_id = ?`,
          [Number(args.purchase_id)]
        );
        return asTextResult({ purchase: purchases[0], items });
      }

      const params = [];
      const limit = clampLimit(args.limit, 50, 100);
      let sql = 'SELECT * FROM purchases WHERE 1 = 1';
      if (args.supplier_id) {
        sql += ' AND supplier_id = ?';
        params.push(Number(args.supplier_id));
      }
      if (args.from_date) {
        sql += ' AND created_at >= ?';
        params.push(String(args.from_date));
      }
      if (args.to_date) {
        sql += ' AND created_at <= ?';
        params.push(String(args.to_date));
      }
      sql += ` ORDER BY created_at DESC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_quotes': {
      if (args.quote_id) {
        const quotes = await query(pool, 'SELECT * FROM quotes WHERE id = ? LIMIT 1', [Number(args.quote_id)]);
        if (!quotes[0]) {
          throw new Error(`Presupuesto ${args.quote_id} no encontrado.`);
        }
        const items = await query(
          pool,
          `SELECT qi.*, i.name AS item_name
           FROM quote_items qi
           LEFT JOIN items i ON i.id = qi.item_id
           WHERE qi.quote_id = ?`,
          [Number(args.quote_id)]
        );
        return asTextResult({ quote: quotes[0], items });
      }

      const params = [];
      const limit = clampLimit(args.limit, 50, 100);
      let sql = 'SELECT * FROM quotes WHERE 1 = 1';
      if (args.customer_id) {
        sql += ' AND customer_id = ?';
        params.push(Number(args.customer_id));
      }
      if (args.from_date) {
        sql += ' AND created_at >= ?';
        params.push(String(args.from_date));
      }
      if (args.to_date) {
        sql += ' AND created_at <= ?';
        params.push(String(args.to_date));
      }
      sql += ` ORDER BY created_at DESC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_remitos': {
      if (args.remito_id) {
        const remitos = await query(pool, 'SELECT * FROM remitos WHERE id = ? LIMIT 1', [Number(args.remito_id)]);
        if (!remitos[0]) {
          throw new Error(`Remito ${args.remito_id} no encontrado.`);
        }
        const items = await query(
          pool,
          `SELECT ri.*, i.name AS item_name
           FROM remito_items ri
           LEFT JOIN items i ON i.id = ri.item_id
           WHERE ri.remito_id = ?`,
          [Number(args.remito_id)]
        );
        return asTextResult({ remito: remitos[0], items });
      }

      const params = [];
      const limit = clampLimit(args.limit, 50, 100);
      let sql = 'SELECT * FROM remitos WHERE 1 = 1';
      if (args.recipient) {
        sql += ' AND recipient LIKE ?';
        params.push(likeTerm(args.recipient));
      }
      if (args.from_date) {
        sql += ' AND created_at >= ?';
        params.push(String(args.from_date));
      }
      if (args.to_date) {
        sql += ' AND created_at <= ?';
        params.push(String(args.to_date));
      }
      sql += ` ORDER BY created_at DESC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'list_invoices': {
      if (args.invoice_id) {
        const invoices = await query(pool, 'SELECT * FROM invoices WHERE id = ? LIMIT 1', [Number(args.invoice_id)]);
        if (!invoices[0]) {
          throw new Error(`Factura ${args.invoice_id} no encontrada.`);
        }
        const items = await query(
          pool,
          `SELECT ii.*, i.name AS item_name
           FROM invoice_items ii
           LEFT JOIN items i ON i.id = ii.item_id
           WHERE ii.invoice_id = ?`,
          [Number(args.invoice_id)]
        );
        return asTextResult({ invoice: invoices[0], items });
      }

      const params = [];
      const limit = clampLimit(args.limit, 50, 100);
      let sql = 'SELECT * FROM invoices WHERE 1 = 1';
      if (args.customer_id) {
        sql += ' AND customer_id = ?';
        params.push(Number(args.customer_id));
      }
      if (args.from_date) {
        sql += ' AND created_at >= ?';
        params.push(String(args.from_date));
      }
      if (args.to_date) {
        sql += ' AND created_at <= ?';
        params.push(String(args.to_date));
      }
      sql += ` ORDER BY created_at DESC LIMIT ${limit}`;
      const rows = await query(pool, sql, params);
      return asTextResult({ total: rows.length, rows });
    }

    case 'create_customer': {
      ensureWriteAllowed(writeToolNames, 'create_customer');

      if (!args.name || !String(args.name).trim()) {
        throw new Error('El campo name es obligatorio.');
      }

      const dni = args.dni_cuit ? String(args.dni_cuit).replace(/\D/g, '') : null;
      if (dni && dni.length > 32) {
        throw new Error('dni_cuit inválido.');
      }

      let provinceId = args.province_id ? Number(args.province_id) : null;
      let cityId = args.city_id ? Number(args.city_id) : null;
      let postalCode = args.postal_code ? String(args.postal_code) : null;

      if (cityId) {
        const city = await getCityInfo(pool, cityId);
        if (!city) {
          throw new Error(`Ciudad ${cityId} no encontrada.`);
        }
        if (provinceId && city.province_id !== provinceId) {
          throw new Error('La ciudad no pertenece a la provincia indicada.');
        }
        provinceId = city.province_id;
        postalCode = city.postal_code ?? postalCode;
      }

      const result = await query(
        pool,
        `INSERT INTO customers (name, dni_cuit, phone, email, address, province_id, city_id, postal_code)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          String(args.name).trim(),
          dni,
          args.phone ? String(args.phone) : null,
          args.email ? String(args.email) : null,
          args.address ? String(args.address) : null,
          provinceId,
          cityId,
          postalCode,
        ]
      );

      return asTextResult({ ok: true, id: result.insertId });
    }

    case 'create_repair': {
      ensureWriteAllowed(writeToolNames, 'create_repair');

      if (args.customer_id && !(await ensureExists(pool, 'customers', Number(args.customer_id)))) {
        throw new Error(`Cliente ${args.customer_id} no encontrado.`);
      }

      if (args.device_id && !(await ensureExists(pool, 'devices', Number(args.device_id)))) {
        throw new Error(`Dispositivo ${args.device_id} no encontrado.`);
      }

      if (args.technician_id && !(await ensureExists(pool, 'technicians', Number(args.technician_id)))) {
        throw new Error(`Técnico ${args.technician_id} no encontrado.`);
      }

      const result = await query(
        pool,
        `INSERT INTO repairs (customer_id, device_id, device_model, problem, status, technician_id, scheduled_at, contact)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          args.customer_id ? Number(args.customer_id) : null,
          args.device_id ? Number(args.device_id) : null,
          args.device_model ? String(args.device_model) : null,
          args.problem ? String(args.problem) : null,
          args.status ? String(args.status) : 'en reparacion',
          args.technician_id ? Number(args.technician_id) : null,
          args.scheduled_at ? String(args.scheduled_at) : null,
          args.contact ? String(args.contact) : null,
        ]
      );

      return asTextResult({ ok: true, id: result.insertId });
    }

    case 'create_device': {
      ensureWriteAllowed(writeToolNames, 'create_device');

      if (args.customer_id && !(await ensureExists(pool, 'customers', Number(args.customer_id)))) {
        throw new Error(`Cliente ${args.customer_id} no encontrado.`);
      }

      const result = await query(
        pool,
        `INSERT INTO devices (customer_id, serial, brand, model, notes)
         VALUES (?, ?, ?, ?, ?)`,
        [
          args.customer_id ? Number(args.customer_id) : null,
          args.serial ? String(args.serial) : null,
          args.brand ? String(args.brand) : null,
          args.model ? String(args.model) : null,
          args.notes ? String(args.notes) : null,
        ]
      );

      return asTextResult({ ok: true, id: result.insertId });
    }

    case 'create_sale': {
      ensureWriteAllowed(writeToolNames, 'create_sale');
      if (!Array.isArray(args.items) || args.items.length === 0) {
        throw new Error('items es obligatorio y debe contener al menos un item.');
      }
      if (args.customer_id && !(await ensureExists(pool, 'customers', Number(args.customer_id)))) {
        throw new Error(`Cliente ${args.customer_id} no encontrado.`);
      }
      if (args.warehouse_id && !(await ensureExists(pool, 'warehouses', Number(args.warehouse_id)))) {
        throw new Error(`Depósito ${args.warehouse_id} no encontrado.`);
      }

      const payload = await withTransaction(pool, async (connection) => {
        let total = 0;
        for (const item of args.items) {
          total += Number(item.quantity) * Number(item.unit_price);
        }

        const [saleResult] = await connection.execute('INSERT INTO sales (customer_id, total) VALUES (?, ?)', [args.customer_id ? Number(args.customer_id) : null, total]);
        const saleId = saleResult.insertId;

        for (const item of args.items) {
          const itemId = Number(item.item_id);
          const quantity = Number(item.quantity);
          const unitPrice = Number(item.unit_price);
          if (!(await ensureExists(pool, 'items', itemId))) {
            throw new Error(`Artículo ${itemId} no encontrado.`);
          }

          await connection.execute(
            'INSERT INTO sale_items (sale_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)',
            [saleId, itemId, quantity, unitPrice]
          );

          if (args.warehouse_id) {
            const warehouseId = Number(args.warehouse_id);
            const [stockRows] = await connection.execute(
              'SELECT id, quantity FROM stocks WHERE item_id = ? AND warehouse_id = ? LIMIT 1',
              [itemId, warehouseId]
            );
            const previous = stockRows[0] ?? null;
            const previousQuantity = previous ? Number(previous.quantity) : 0;
            const newQuantity = previousQuantity - quantity;
            if (previous) {
              await connection.execute('UPDATE stocks SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [newQuantity, previous.id]);
            } else {
              await connection.execute('INSERT INTO stocks (item_id, warehouse_id, quantity) VALUES (?, ?, ?)', [itemId, warehouseId, newQuantity]);
            }
            await connection.execute(
              'INSERT INTO stock_movements (item_id, warehouse_id, quantity_before, quantity_after, delta, note) VALUES (?, ?, ?, ?, ?, ?)',
              [itemId, warehouseId, previousQuantity, newQuantity, newQuantity - previousQuantity, `Venta #${saleId}`]
            );
          }
        }

        return { id: saleId, total };
      });

      return asTextResult({ ok: true, ...payload });
    }

    case 'create_purchase': {
      ensureWriteAllowed(writeToolNames, 'create_purchase');
      if (!Array.isArray(args.items) || args.items.length === 0) {
        throw new Error('items es obligatorio y debe contener al menos un item.');
      }
      if (args.supplier_id && !(await ensureExists(pool, 'suppliers', Number(args.supplier_id)))) {
        throw new Error(`Proveedor ${args.supplier_id} no encontrado.`);
      }
      if (args.warehouse_id && !(await ensureExists(pool, 'warehouses', Number(args.warehouse_id)))) {
        throw new Error(`Depósito ${args.warehouse_id} no encontrado.`);
      }

      const payload = await withTransaction(pool, async (connection) => {
        let total = 0;
        for (const item of args.items) {
          total += Number(item.quantity) * Number(item.unit_price);
        }

        const [purchaseResult] = await connection.execute('INSERT INTO purchases (supplier_id, total) VALUES (?, ?)', [args.supplier_id ? Number(args.supplier_id) : null, total]);
        const purchaseId = purchaseResult.insertId;

        for (const item of args.items) {
          const itemId = Number(item.item_id);
          const quantity = Number(item.quantity);
          const unitPrice = Number(item.unit_price);
          if (!(await ensureExists(pool, 'items', itemId))) {
            throw new Error(`Artículo ${itemId} no encontrado.`);
          }

          await connection.execute(
            'INSERT INTO purchase_items (purchase_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)',
            [purchaseId, itemId, quantity, unitPrice]
          );

          if (args.warehouse_id) {
            const warehouseId = Number(args.warehouse_id);
            const [stockRows] = await connection.execute(
              'SELECT id, quantity FROM stocks WHERE item_id = ? AND warehouse_id = ? LIMIT 1',
              [itemId, warehouseId]
            );
            const previous = stockRows[0] ?? null;
            const previousQuantity = previous ? Number(previous.quantity) : 0;
            const newQuantity = previousQuantity + quantity;
            if (previous) {
              await connection.execute('UPDATE stocks SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [newQuantity, previous.id]);
            } else {
              await connection.execute('INSERT INTO stocks (item_id, warehouse_id, quantity) VALUES (?, ?, ?)', [itemId, warehouseId, newQuantity]);
            }
            await connection.execute(
              'INSERT INTO stock_movements (item_id, warehouse_id, quantity_before, quantity_after, delta, note) VALUES (?, ?, ?, ?, ?, ?)',
              [itemId, warehouseId, previousQuantity, newQuantity, newQuantity - previousQuantity, `Compra #${purchaseId}`]
            );
          }
        }

        return { id: purchaseId, total };
      });

      return asTextResult({ ok: true, ...payload });
    }

    case 'create_quote': {
      ensureWriteAllowed(writeToolNames, 'create_quote');
      if (!Array.isArray(args.items) || args.items.length === 0) {
        throw new Error('items es obligatorio y debe contener al menos un item.');
      }
      if (args.customer_id && !(await ensureExists(pool, 'customers', Number(args.customer_id)))) {
        throw new Error(`Cliente ${args.customer_id} no encontrado.`);
      }
      const payload = await withTransaction(pool, async (connection) => {
        let total = 0;
        for (const item of args.items) {
          total += Number(item.quantity) * Number(item.unit_price);
        }
        const [quoteResult] = await connection.execute('INSERT INTO quotes (customer_id, total) VALUES (?, ?)', [args.customer_id ? Number(args.customer_id) : null, total]);
        const quoteId = quoteResult.insertId;
        for (const item of args.items) {
          const itemId = Number(item.item_id);
          if (!(await ensureExists(pool, 'items', itemId))) {
            throw new Error(`Artículo ${itemId} no encontrado.`);
          }
          await connection.execute(
            'INSERT INTO quote_items (quote_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)',
            [quoteId, itemId, Number(item.quantity), Number(item.unit_price)]
          );
        }
        return { id: quoteId, total };
      });
      return asTextResult({ ok: true, ...payload });
    }

    case 'create_remito': {
      ensureWriteAllowed(writeToolNames, 'create_remito');
      if (!Array.isArray(args.items) || args.items.length === 0) {
        throw new Error('items es obligatorio y debe contener al menos un item.');
      }
      const payload = await withTransaction(pool, async (connection) => {
        let total = 0;
        for (const item of args.items) {
          total += Number(item.quantity) * Number(item.unit_price ?? 0);
        }
        const [remitoResult] = await connection.execute('INSERT INTO remitos (recipient, total) VALUES (?, ?)', [args.recipient ? String(args.recipient) : null, total]);
        const remitoId = remitoResult.insertId;
        for (const item of args.items) {
          const itemId = Number(item.item_id);
          if (!(await ensureExists(pool, 'items', itemId))) {
            throw new Error(`Artículo ${itemId} no encontrado.`);
          }
          await connection.execute(
            'INSERT INTO remito_items (remito_id, item_id, quantity) VALUES (?, ?, ?)',
            [remitoId, itemId, Number(item.quantity)]
          );
        }
        return { id: remitoId, total };
      });
      return asTextResult({ ok: true, ...payload });
    }

    case 'create_invoice': {
      ensureWriteAllowed(writeToolNames, 'create_invoice');
      if (!Array.isArray(args.items) || args.items.length === 0) {
        throw new Error('items es obligatorio y debe contener al menos un item.');
      }
      if (args.customer_id && !(await ensureExists(pool, 'customers', Number(args.customer_id)))) {
        throw new Error(`Cliente ${args.customer_id} no encontrado.`);
      }
      const payload = await withTransaction(pool, async (connection) => {
        let total = 0;
        for (const item of args.items) {
          total += Number(item.quantity) * Number(item.unit_price);
        }
        const [invoiceResult] = await connection.execute('INSERT INTO invoices (customer_id, total) VALUES (?, ?)', [args.customer_id ? Number(args.customer_id) : null, total]);
        const invoiceId = invoiceResult.insertId;
        for (const item of args.items) {
          const itemId = Number(item.item_id);
          if (!(await ensureExists(pool, 'items', itemId))) {
            throw new Error(`Artículo ${itemId} no encontrado.`);
          }
          await connection.execute(
            'INSERT INTO invoice_items (invoice_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)',
            [invoiceId, itemId, Number(item.quantity), Number(item.unit_price)]
          );
        }
        return { id: invoiceId, total };
      });
      return asTextResult({ ok: true, ...payload });
    }

    case 'upsert_stock': {
      ensureWriteAllowed(writeToolNames, 'upsert_stock');

      const itemId = Number(args.item_id);
      const warehouseId = Number(args.warehouse_id);
      const quantity = Number(args.quantity);

      if (!(await ensureExists(pool, 'items', itemId))) {
        throw new Error(`Artículo ${itemId} no encontrado.`);
      }

      if (!(await ensureExists(pool, 'warehouses', warehouseId))) {
        throw new Error(`Depósito ${warehouseId} no encontrado.`);
      }

      const payload = await withTransaction(pool, async (connection) => {
        const [existingRows] = await connection.execute(
          'SELECT id, quantity FROM stocks WHERE item_id = ? AND warehouse_id = ? LIMIT 1',
          [itemId, warehouseId]
        );
        const previous = existingRows[0] ?? null;
        const previousQuantity = previous ? Number(previous.quantity) : 0;

        const [upsertResult] = await connection.execute(
          `INSERT INTO stocks (item_id, warehouse_id, quantity)
           VALUES (?, ?, ?)
           ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = CURRENT_TIMESTAMP`,
          [itemId, warehouseId, quantity]
        );

        await connection.execute(
          `INSERT INTO stock_movements (item_id, warehouse_id, quantity_before, quantity_after, delta, note)
           VALUES (?, ?, ?, ?, ?, ?)`,
          [itemId, warehouseId, previousQuantity, quantity, quantity - previousQuantity, args.note ? String(args.note) : null]
        );

        return {
          stock_id: previous ? previous.id : upsertResult.insertId,
          quantity_before: previousQuantity,
          quantity_after: quantity,
          delta: quantity - previousQuantity,
        };
      });

      return asTextResult({ ok: true, ...payload });
    }

    default:
      throw new Error(`Herramienta no soportada: ${name}`);
  }
}