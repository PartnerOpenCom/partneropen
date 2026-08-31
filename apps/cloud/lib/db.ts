import postgres from 'postgres';

let sql: ReturnType<typeof postgres> | null = null;

export function getDb() {
  if (!process.env.DATABASE_URL) {
    throw new Error('DATABASE_URL is not configured');
  }
  if (!sql) {
    sql = postgres(process.env.DATABASE_URL, {
      max: 5,
      idle_timeout: 20,
      connect_timeout: 10,
      ssl: process.env.DATABASE_URL.includes('localhost') ? false : 'require',
    });
  }
  return sql;
}
