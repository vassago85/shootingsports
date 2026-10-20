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

export async function logout(): Promise<void> {
  try {
    await apiFetch('/auth/logout', { method: 'POST' });
  } finally {
    await clearAuthToken();
  }
}
