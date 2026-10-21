import { apiFetch } from './client';

import { setAuthToken, setStoredUser, StoredUser, clearAuthToken } from '@/auth/store';

export type LoginResponse = {
  token: string;
  device_name: string;
  user: { data: StoredUser } | StoredUser;
};

/**
 * Logs the shooter in against `POST /api/v1/auth/login`. On success
 * persists the bearer token to SecureStore so subsequent API calls
 * pick it up automatically.
 */
export async function login(email: string, password: string, deviceName: string): Promise<StoredUser> {
  const response = await apiFetch<LoginResponse>('/auth/login', {
    method: 'POST',
    auth: false,
    body: { email, password, device_name: deviceName },
  });

  const user = 'data' in response.user ? response.user.data : response.user;

  await setAuthToken(response.token);
  await setStoredUser(user);

  return user;
}

/**
 * Revoke the current access token on the server, then wipe the local
 * copy. The server call is best-effort — if the token is already
 * stale (expired, invalidated elsewhere) the API returns 401, which
 * is a success from the user's perspective: the thing they wanted
 * (log out) is already done. We clear the local state either way so
 * the shooter never gets stuck on an "I pressed Log out and nothing
 * happened" screen.
 */
export async function logout(): Promise<void> {
  try {
    await apiFetch('/auth/logout', { method: 'POST', auth: true });
  } catch {
    // Intentionally swallow: local token clears below so the Me tab
    // flips to the signed-out state regardless of what the server said.
  } finally {
    await clearAuthToken();
  }
}
