import Constants from 'expo-constants';

import { getAuthToken } from '@/auth/store';

const DEFAULT_BASE_URL = 'http://127.0.0.1:8000';

/**
 * Resolves the API base URL from EXPO_PUBLIC_API_URL. Falls back to
 * 127.0.0.1:8000 so the app boots against a stock Laragon Laravel
 * install without extra config.
 */
function resolveBaseUrl(): string {
  const configured =
    process.env.EXPO_PUBLIC_API_URL ??
    (Constants.expoConfig?.extra as { apiUrl?: string } | undefined)?.apiUrl;

  return (configured ?? DEFAULT_BASE_URL).replace(/\/+$/, '');
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public body?: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export type RequestInitJson = Omit<RequestInit, 'body' | 'headers'> & {
  body?: unknown;
  headers?: Record<string, string>;
  auth?: boolean;
};

/**
 * Thin JSON fetch wrapper. Bearer token is injected when `auth: true`
 * (or when omitted on `/me/*` calls). Throws ApiError on non-2xx so
 * screens can `.catch()` in one place.
 */
export async function apiFetch<T>(path: string, init: RequestInitJson = {}): Promise<T> {
  const base = resolveBaseUrl();
  const url = `${base}/api/v1${path.startsWith('/') ? path : `/${path}`}`;
  const needsAuth = init.auth ?? path.startsWith('/me');

  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(init.headers ?? {}),
  };

  if (init.body !== undefined && !(init.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }

  if (needsAuth) {
    const token = await getAuthToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
  }

  const response = await fetch(url, {
    ...init,
    headers,
    body:
      init.body === undefined
        ? undefined
        : init.body instanceof FormData
          ? init.body
          : JSON.stringify(init.body),
  });

  const raw = await response.text();
  const parsed: unknown = raw.length > 0 ? safeJsonParse(raw) : undefined;

  if (!response.ok) {
    throw new ApiError(response.status, friendlyErrorMessage(response.status, path, parsed), parsed);
  }

  return parsed as T;
}

/**
 * Preferred error source order:
 *   1. The first validation error on any field (`errors.<field>[0]`) —
 *      Laravel's 422 shape, best-fit copy for a form submission.
 *   2. The top-level `message` string — Laravel's default shape for
 *      ValidationException and most authentication exceptions.
 *   3. A hand-written fallback keyed on the HTTP status so the user
 *      never sees `API 500` or `undefined` on a 500/401/403.
 */
function friendlyErrorMessage(status: number, path: string, body: unknown): string {
  if (body !== null && typeof body === 'object') {
    const bag = body as { message?: unknown; errors?: unknown };

    if (bag.errors !== null && typeof bag.errors === 'object') {
      for (const value of Object.values(bag.errors as Record<string, unknown>)) {
        if (Array.isArray(value) && value.length > 0 && typeof value[0] === 'string') {
          return value[0];
        }
      }
    }

    if (typeof bag.message === 'string' && bag.message.length > 0) {
      return bag.message;
    }
  }

  switch (status) {
    case 401:
      return 'You need to log in again.';
    case 403:
      return 'You are not allowed to do that.';
    case 404:
      return 'That page or record is gone.';
    case 429:
      return 'Too many attempts. Wait a moment and try again.';
    case 500:
    case 502:
    case 503:
      return 'The server is having a bad moment. Try again shortly.';
    default:
      return `Something went wrong on ${path}. Try again shortly.`;
  }
}

function safeJsonParse(text: string): unknown {
  try {
    return JSON.parse(text) as unknown;
  } catch {
    return text;
  }
}
