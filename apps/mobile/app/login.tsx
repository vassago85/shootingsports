import { Ionicons } from '@expo/vector-icons';
import { Stack, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useRef, useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { login } from '@/api/auth';
import { ApiError } from '@/api/client';
import { registerForPushNotifications } from '@/push/register';
import { colors, fonts, radius, size } from '@/theme';
import { ErrorRow, GhostButton, HeroBand, PrimaryButton } from '@/ui';

/**
 * M1 Sign in.
 *
 * `POST /api/v1/auth/login` with email + password. Token stored in
 * SecureStore (handled inside `login()`).
 *
 * States per BUILD_SPEC §3.2:
 *   M1a Resting            — button enabled once both fields are non-empty
 *   M1b Submitting         — inputs + button disabled, "Signing in" label
 *   M1c Invalid credentials — ErrorRow above the fields, password cleared
 *                             and refocused, password border error-coloured
 *   M1d Offline / network   — ErrorRow, both fields kept untouched
 *
 * Also required:
 *   - Copy: "Signed-in shooters save matches, follow sports, and manage
 *           their clubs or business."
 *   - "Forgot password?" link opens the website reset in in-app browser (R4)
 *   - Footer: "New here? Register on the website" also in in-app browser
 *
 * `expo-web-browser` presents SFSafariViewController on iOS and Custom
 * Tabs on Android. Password managers work; cookies carry back.
 *
 * Full modal presentation of this screen per spec §3.1 is part of the
 * nav restructure (p1_nav); for now it's a stack screen with the
 * system header hidden so HeroBand owns the top.
 */

const PASSWORD_RESET_URL = 'https://shootingsports.co.za/password/reset';
const REGISTER_URL = 'https://shootingsports.co.za/register';

type ErrorKind = 'invalid' | 'network' | null;

function classify(err: unknown): ErrorKind {
  if (err instanceof ApiError) {
    // status 0 is set by apiFetch when fetch() itself threw —
    // treat as network / offline (M1d).
    if (err.status === 0) {
      return 'network';
    }
    // Anything else from Laravel (401, 422, 500) is credential-ish
    // from the user's perspective — M1c.
    return 'invalid';
  }
  return 'invalid';
}

function errorCopy(kind: ErrorKind, serverMessage: string | null): string {
  if (kind === 'network') {
    return "Can't reach the server. Check your connection, then tap Sign in to try again.";
  }

  // Default M1c copy — spec-mandated.
  if (serverMessage === null || serverMessage.length === 0) {
    return "That email and password don't match. Check them and try again.";
  }

  return serverMessage;
}

function deviceLabel(): string {
  return `${Platform.OS}:${Date.now().toString(36)}`;
}

export default function LoginScreen() {
  const router = useRouter();
  const passwordRef = useRef<TextInput>(null);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errorKind, setErrorKind] = useState<ErrorKind>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const canSubmit = email.trim().length > 0 && password.length > 0 && !submitting;

  async function onSubmit() {
    if (!canSubmit) {
      return;
    }

    setErrorKind(null);
    setErrorMessage(null);
    setSubmitting(true);

    try {
      await login(email.trim(), password, deviceLabel());
      // Best-effort push registration once we have a token. No-ops in
      // Expo Go — see src/push/register.ts for why.
      void registerForPushNotifications().catch(() => null);
      // Land back on Me — that's where "Sign in to see your home" sent
      // the user from, and it has the saved matches list the shooter
      // was most likely trying to reach.
      router.replace('/me');
    } catch (err) {
      const kind = classify(err);
      setErrorKind(kind);
      setErrorMessage(err instanceof Error ? err.message : null);

      // M1c: clear + focus password. Email stays.
      if (kind === 'invalid') {
        setPassword('');
        requestAnimationFrame(() => passwordRef.current?.focus());
      }
    } finally {
      setSubmitting(false);
    }
  }

  function openResetLink() {
    void WebBrowser.openBrowserAsync(PASSWORD_RESET_URL);
  }

  function openRegisterLink() {
    void WebBrowser.openBrowserAsync(REGISTER_URL);
  }

  return (
    <>
      <Stack.Screen options={{ headerShown: false }} />
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.container}
      >
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
        >
          <HeroBand eyebrow="Welcome" title="Sign in" />

          <View style={styles.body}>
            <Text style={styles.lead}>
              Signed-in shooters save matches, follow sports, and manage their clubs or business.
            </Text>

            {errorKind !== null && (
              <ErrorRow>{errorCopy(errorKind, errorMessage)}</ErrorRow>
            )}

            <View style={styles.fieldGroup}>
              <Text style={styles.label}>Email</Text>
              <TextInput
                value={email}
                onChangeText={setEmail}
                autoCapitalize="none"
                keyboardType="email-address"
                autoComplete="email"
                textContentType="username"
                returnKeyType="next"
                onSubmitEditing={() => passwordRef.current?.focus()}
                editable={!submitting}
                placeholder="you@example.com"
                placeholderTextColor={colors.placeholder}
                style={styles.input}
              />
            </View>

            <View style={styles.fieldGroup}>
              <Text style={styles.label}>Password</Text>
              <View style={[styles.inputWithIcon, errorKind === 'invalid' && styles.inputError]}>
                <TextInput
                  ref={passwordRef}
                  value={password}
                  onChangeText={setPassword}
                  secureTextEntry={!showPassword}
                  autoComplete="password"
                  textContentType="password"
                  returnKeyType="go"
                  onSubmitEditing={() => void onSubmit()}
                  editable={!submitting}
                  placeholder="Your password"
                  placeholderTextColor={colors.placeholder}
                  style={styles.inputInner}
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
            </View>

            <PrimaryButton
              label={submitting ? 'Signing in' : 'Sign in'}
              onPress={() => void onSubmit()}
              loading={submitting}
              disabled={!canSubmit}
              style={styles.submit}
            />

            <GhostButton
              label="Forgot password?"
              onPress={openResetLink}
              style={styles.forgotBtn}
            />

            <View style={styles.registerRow}>
              <Text style={styles.registerLead}>New here?</Text>
              <Pressable onPress={openRegisterLink} hitSlop={8}>
                <Text style={styles.registerLink}>Register on the website ↗</Text>
              </Pressable>
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },
  scroll: { flex: 1 },
  scrollContent: { flexGrow: 1, paddingBottom: 40 },
  body: { padding: 20, gap: 16 },

  lead: {
    fontFamily: fonts.body,
    fontSize: 15,
    lineHeight: 22,
    color: colors.muted,
  },

  fieldGroup: { gap: 6 },
  label: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    letterSpacing: 1.4,
    textTransform: 'uppercase',
    color: colors.muted,
  },
  input: {
    minHeight: size.input,
    backgroundColor: colors.card,
    color: colors.ink,
    borderRadius: radius.input,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderWidth: 1,
    borderColor: colors.control,
    fontFamily: fonts.body,
    fontSize: 16,
  },

  /* Password field — composite row so the eye toggle sits inside
   * the same visual box as the input. */
  inputWithIcon: {
    flexDirection: 'row',
    alignItems: 'center',
    minHeight: size.input,
    backgroundColor: colors.card,
    borderRadius: radius.input,
    borderWidth: 1,
    borderColor: colors.control,
  },
  inputInner: {
    flex: 1,
    color: colors.ink,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontFamily: fonts.body,
    fontSize: 16,
  },
  inputError: {
    borderColor: colors.error,
  },
  eyeBtn: {
    paddingHorizontal: 14,
    paddingVertical: 10,
  },

  submit: { marginTop: 8 },
  forgotBtn: { alignSelf: 'stretch' },

  registerRow: {
    marginTop: 24,
    alignItems: 'center',
    gap: 6,
  },
  registerLead: {
    fontFamily: fonts.body,
    fontSize: 14,
    color: colors.muted,
  },
  registerLink: {
    fontFamily: fonts.bodySemi,
    fontSize: 15,
    color: colors.link,
  },
});
