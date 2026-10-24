const TOKEN_KEY = 'ss.api.token';
const USER_KEY = 'ss.api.user';

export type StoredUser = {
  id: number;
  name: string;
  email: string;
  calendar_slug: string | null;
  plan: string | null;
  is_pro: boolean;
};

/**
 * Web implementation of the auth token / user store.
 *
 * `expo-secure-store` ships an empty stub on web (its native-only
 * APIs don't exist in the browser), so Metro's web platform
 * resolution has us swap in this file via the `.web.ts` suffix. The
 * app runs in-browser as the dev / screenshot target, where the
 * secure keychain is substituted for `localStorage`.
 *
 * This is intentionally NOT what gets shipped on iOS or Android —
 * on-device, `expo-secure-store` continues to use the Keychain /
 * EncryptedSharedPreferences via the native sibling of this module.
 */

function safeStorage(): Storage | null {
  if (typeof window === 'undefined') {
    return null;
  }
  try {
    return window.localStorage;
  } catch {
    return null;
  }
}

export async function getAuthToken(): Promise<string | null> {
  const storage = safeStorage();
  if (storage === null) {
    return null;
  }
  return storage.getItem(TOKEN_KEY);
}

export async function setAuthToken(token: string): Promise<void> {
  const storage = safeStorage();
  if (storage === null) {
    return;
  }
  storage.setItem(TOKEN_KEY, token);
}

export async function clearAuthToken(): Promise<void> {
  const storage = safeStorage();
  if (storage === null) {
    return;
  }
  storage.removeItem(TOKEN_KEY);
  storage.removeItem(USER_KEY);
}

export async function getStoredUser(): Promise<StoredUser | null> {
  const storage = safeStorage();
  if (storage === null) {
    return null;
  }
  const raw = storage.getItem(USER_KEY);
  if (raw === null) {
    return null;
  }
  try {
    return JSON.parse(raw) as StoredUser;
  } catch {
    return null;
  }
}

export async function setStoredUser(user: StoredUser): Promise<void> {
  const storage = safeStorage();
  if (storage === null) {
    return;
  }
  storage.setItem(USER_KEY, JSON.stringify(user));
}
