import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Platform, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';

import { login } from '@/api/auth';
import { registerForPushNotifications } from '@/push/register';

function deviceLabel(): string {
  return `${Platform.OS}:${Date.now().toString(36)}`;
}

export default function LoginScreen() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function onSubmit() {
    setError(null);
    setSubmitting(true);

    try {
      await login(email, password, deviceLabel());
      // Best-effort push registration once we have a token.
      void registerForPushNotifications().catch(() => null);
      router.replace('/saved');
    } catch (err) {
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
        placeholderTextColor="#5a6360"
      />

      <Text style={styles.label}>Password</Text>
      <TextInput
        style={styles.input}
        value={password}
        onChangeText={setPassword}
        secureTextEntry
        autoComplete="password"
        placeholderTextColor="#5a6360"
      />

      {error !== null && <Text style={styles.error}>{error}</Text>}

      <TouchableOpacity onPress={onSubmit} disabled={submitting} style={styles.button}>
        {submitting ? <ActivityIndicator color="#232a2c" /> : <Text style={styles.buttonText}>Log in</Text>}
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#101516', padding: 20, gap: 8 },
  label: { color: '#a3adaa', fontSize: 12, marginTop: 12 },
  input: {
    backgroundColor: '#1c2426',
    color: '#f4f0e6',
    borderRadius: 6,
    padding: 12,
    borderWidth: 1,
    borderColor: '#3a4548',
  },
  button: {
    backgroundColor: '#d9ae52',
    padding: 14,
    borderRadius: 6,
    alignItems: 'center',
    marginTop: 20,
  },
  buttonText: { color: '#232a2c', fontWeight: '700' },
  error: { color: '#f28c8c', marginTop: 12 },
});
