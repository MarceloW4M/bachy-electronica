import mysql from 'mysql2/promise';

let pool;

export function createPool(config) {
  if (!pool) {
    pool = mysql.createPool({
      host: config.host,
      port: config.port,
      database: config.name,
      user: config.user,
      password: config.password,
      waitForConnections: true,
      connectionLimit: config.connectionLimit,
      queueLimit: 0,
      namedPlaceholders: false,
    });
  }

  return pool;
}

export async function query(poolInstance, sql, params = []) {
  const [rows] = await poolInstance.execute(sql, params);
  return rows;
}

export async function withTransaction(poolInstance, callback) {
  const connection = await poolInstance.getConnection();

  try {
    await connection.beginTransaction();
    const result = await callback(connection);
    await connection.commit();
    return result;
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
}

export async function closePool() {
  if (pool) {
    await pool.end();
    pool = undefined;
  }
}