import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useState } from 'react';
import {
  ActivityIndicator,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';

import { login } from '@/api/auth';
import { registerForPushNotifications } from '@/push/register';
import { colors } from '@/theme';

/**
 * Device label pattern: `<platform>:<base36-timestamp>`. Shows up on
 * the user's `GET /me/device-tokens` list and on the Laravel
 * `personal_access_tokens` table so a shooter can revoke a specific
 * device if their phone is lost.
 */
function deviceLabel(): string {
  return `${Platform.OS}:${Date.now().toString(36)}`;
}

export default function LoginScreen() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit() {
    setError(null);
    setSubmitting(true);

    try {
      await login(email, password, deviceLabel());
      // Best-effort push registration once we have a token. No-ops in
      // Expo Go — see src/push/register.ts for why.
      void registerForPushNotifications().catch(() => null);
      // Land back on the Me tab — that's where "sign in to see your
      // home" sent the user from, and it has the saved matches list
      // the shooter was most likely trying to reach.
      router.replace('/me');
    } catch (err) {
      // ApiError's message is already server-friendly (see client.ts
      // friendlyErrorMessage). We only fall back to a canned string
      // if the throw was something non-ApiError, like a network crash
      // before the request ever reached Laravel.
      setError(err instanceof Error ? err.message : 'Could not log in.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <View style={styles.container}>
      <Text style={styles.label}>Email</Text>
      <TextInput
        style={styles.input}
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        keyboardType="email-address"
        autoComplete="email"
        placeholder="you@example.com"
        placeholderTextColor={colors.placeholder}
      />

      <Text style={styles.label}>Password</Text>
      <View style={styles.inputWithIcon}>
        <TextInput
          style={styles.inputInner}
          value={password}
          onChangeText={setPassword}
          secureTextEntry={!showPassword}
          autoComplete="password"
          placeholderTextColor={colors.placeholder}
        />
        <Pressable
          onPress={() => setShowPassword((prev) => !prev)}
          hitSlop={10}
          accessibilityRole="button"
          accessibilityLabel={showPassword ? 'Hide password' : 'Show password'}
          style={styles.eyeBtn}
        >
          <Ionicons
            name={showPassword ? 'eye-off-outline' : 'eye-outline'}
            size={22}
            color={colors.muted}
          />
        </Pressable>
      </View>

      {error !== null && <Text style={styles.error}>{error}</Text>}

      <TouchableOpacity onPress={onSubmit} disabled={submitting} style={styles.button}>
        {submitting ? (
          <ActivityIndicator color={colors.ink} />
        ) : (
          <Text style={styles.buttonText}>Log in</Text>
        )}
      </TouchableOpacity>
    </View>
  );
}

// TEMPORARY stylesheet — stays until the full M1a–M1d reskin lands
// with the proper HeroBand ("Welcome back" eyebrow + "Sign in" H1),
// Forgot-password and Register links, and the modal presentation.
// Token references use the new palette's semantic matches.
const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page, padding: 20, gap: 8 },
  label: { color: colors.muted, fontSize: 12, marginTop: 12 },
  input: {
    backgroundColor: colors.card,
    color: colors.ink,
    borderRadius: 6,
    padding: 12,
    borderWidth: 1,
    borderColor: colors.control,
  },
  /* Password field — composite row so the eye-toggle sits inside the
   * same visual box as the input. The underlying TextInput still owns
   * all the keyboard behaviour; the Pressable only toggles visibility. */
  inputWithIcon: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.card,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: colors.control,
  },
  inputInner: {
    flex: 1,
    color: colors.ink,
    padding: 12,
  },
  eyeBtn: {
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  button: {
    backgroundColor: colors.lime,
    padding: 14,
    borderRadius: 6,
    alignItems: 'center',
    marginTop: 20,
  },
  buttonText: { color: colors.ink, fontWeight: '700' },
  error: { color: colors.error, marginTop: 12 },
});
