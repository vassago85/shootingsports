import { apiFetch } from './client';

export type Platform = 'ios' | 'android' | 'web';

export async function registerDeviceToken(token: string, platform: Platform): Promise<void> {
  await apiFetch('/me/device-tokens', {
    method: 'POST',
    body: { token, platform },
  });
}

export async function unregisterDeviceToken(token: string): Promise<void> {
  await apiFetch('/me/device-tokens', {
    method: 'DELETE',
    body: { token },
  });
}
