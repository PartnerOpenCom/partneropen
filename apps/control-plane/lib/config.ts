export type ControlPlaneNodeEnv = 'development' | 'test' | 'production';

export interface ControlPlaneConfig {
  nodeEnv: ControlPlaneNodeEnv;
  appBaseUrl: string;
  databaseUrl?: string;
  authTokenPepper?: string;
  encryptionKeyHex?: string;
  encryptionKeyVersion: number;
  sessionCookieName: string;
  sessionTtlSeconds: number;
  connectorRequestTimeoutMs: number;
  email: {
    provider: 'resend';
    apiKey?: string;
    from?: string;
  };
}

export interface ConfigOptions {
  requireDatabase?: boolean;
  requireEmail?: boolean;
}

function nodeEnv(value: string | undefined): ControlPlaneNodeEnv {
  if (value === 'production') return 'production';
  if (value === 'test') return 'test';
  return 'development';
}

function positiveInteger(value: string | undefined, fallback: number, name: string): number {
  if (value === undefined || value.trim() === '') return fallback;
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed <= 0) {
    throw new Error(`${name} must be a positive integer`);
  }
  return parsed;
}

function required(value: string | undefined, name: string): string {
  if (!value || value.trim() === '') throw new Error(`${name} is required in production`);
  return value.trim();
}

function validateBaseUrl(value: string, environment: ControlPlaneNodeEnv): string {
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new Error('APP_BASE_URL must be an absolute URL');
  }
  if (environment === 'production' && url.protocol !== 'https:') {
    throw new Error('APP_BASE_URL must use HTTPS in production');
  }
  return url.toString().replace(/\/$/, '');
}

function validateEncryptionKey(value: string | undefined): string | undefined {
  if (value === undefined || value.trim() === '') return undefined;
  const key = value.trim();
  if (!/^[0-9a-fA-F]{64}$/.test(key)) {
    throw new Error('ENCRYPTION_KEY must be 32-byte hex');
  }
  return key.toLowerCase();
}

function validateProductionSecret(value: string | undefined, name: string): string {
  const secret = required(value, name);
  if (secret.length < 32) throw new Error(`${name} must be at least 32 characters`);
  return secret;
}

export function loadConfig(
  env: NodeJS.ProcessEnv,
  options: ConfigOptions = {},
): ControlPlaneConfig {
  const environment = nodeEnv(env.NODE_ENV);
  const databaseUrl = env.DATABASE_URL?.trim() || undefined;
  const requireDatabase = options.requireDatabase === true || environment === 'production';
  if (requireDatabase && !databaseUrl) {
    throw new Error(`${'DATABASE_URL'} is required in ${environment}`);
  }

  const appBaseUrl = validateBaseUrl(
    env.APP_BASE_URL?.trim() || (environment === 'production' ? required(undefined, 'APP_BASE_URL') : 'http://localhost:3001'),
    environment,
  );

  const encryptionKeyHex = validateEncryptionKey(env.ENCRYPTION_KEY);
  if (environment === 'production' && !encryptionKeyHex) {
    throw new Error('ENCRYPTION_KEY is required in production');
  }

  const authTokenPepper = env.AUTH_TOKEN_PEPPER?.trim() || undefined;
  if (environment === 'production' && !authTokenPepper) {
    throw new Error('AUTH_TOKEN_PEPPER is required in production');
  }
  if (environment === 'production' && authTokenPepper && authTokenPepper.length < 32) {
    throw new Error('AUTH_TOKEN_PEPPER must be at least 32 characters');
  }

  const emailApiKey = env.RESEND_API_KEY?.trim() || undefined;
  const emailFrom = env.EMAIL_FROM?.trim() || undefined;
  const requireEmail = options.requireEmail === true || environment === 'production';
  if (requireEmail && !emailApiKey) {
    throw new Error(`RESEND_API_KEY is required in ${environment}`);
  }
  if (requireEmail && !emailFrom) {
    throw new Error(`EMAIL_FROM is required in ${environment}`);
  }

  return {
    nodeEnv: environment,
    appBaseUrl,
    databaseUrl,
    authTokenPepper,
    encryptionKeyHex,
    encryptionKeyVersion: positiveInteger(env.ENCRYPTION_KEY_VERSION, 1, 'ENCRYPTION_KEY_VERSION'),
    sessionCookieName: env.SESSION_COOKIE_NAME?.trim() || 'partneropen_session',
    sessionTtlSeconds: positiveInteger(env.SESSION_TTL_SECONDS, 60 * 60 * 24 * 30, 'SESSION_TTL_SECONDS'),
    connectorRequestTimeoutMs: positiveInteger(env.CONNECTOR_REQUEST_TIMEOUT_MS, 10_000, 'CONNECTOR_REQUEST_TIMEOUT_MS'),
    email: {
      provider: 'resend',
      apiKey: emailApiKey,
      from: emailFrom,
    },
  };
}
