import postgres from 'postgres';

const MIGRATIONS = [
  {
    name: '0001_auth_tables',
    sql: `
      CREATE TABLE IF NOT EXISTS users (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        email TEXT NOT NULL UNIQUE,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
        last_login_at TIMESTAMPTZ
      );

      CREATE TABLE IF NOT EXISTS magic_tokens (
        token_hash TEXT PRIMARY KEY,
        user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
        expires_at TIMESTAMPTZ NOT NULL,
        used_at TIMESTAMPTZ
      );
      CREATE INDEX IF NOT EXISTS magic_tokens_user_idx ON magic_tokens (user_id);

      CREATE TABLE IF NOT EXISTS sessions (
        session_id TEXT PRIMARY KEY,
        user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
        expires_at TIMESTAMPTZ NOT NULL
      );
      CREATE INDEX IF NOT EXISTS sessions_user_idx ON sessions (user_id);
    `,
  },
];

if (!process.env.DATABASE_URL) {
  console.error('DATABASE_URL is required');
  process.exit(1);
}

const db = postgres(process.env.DATABASE_URL, { ssl: 'require' });

await db`CREATE TABLE IF NOT EXISTS schema_migrations (
  name TEXT PRIMARY KEY,
  applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
)`;
for (const migration of MIGRATIONS) {
  const [applied] = await db`SELECT name FROM schema_migrations WHERE name = ${migration.name}`;
  if (applied) {
    console.log(`skip  ${migration.name}`);
    continue;
  }
  await db.unsafe(migration.sql);
  await db`INSERT INTO schema_migrations (name) VALUES (${migration.name})`;
  console.log(`apply ${migration.name}`);
}
console.log('done');
await db.end();
