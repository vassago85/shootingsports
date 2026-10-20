import { StyleSheet, Text, View } from 'react-native';

/**
 * Web override for the Find tab. The native screen in `find.tsx`
 * uses `react-native-maps`, which is iOS/Android only — importing it
 * on web crashes Metro at module-eval because `codegenNativeComponent`
 * is not implemented in `react-native-web`.
 *
 * Expo Router picks `find.web.tsx` on web and `find.tsx` on native,
 * so the native screen never gets evaluated here. If we ever want
 * clubs/ranges/shops discovery on the web app, drop the API-backed
 * directory lists in here — the map centroids are not useful on a
 * desktop browser anyway.
 */
export default function FindScreenWeb() {
  return (
    <View style={styles.center}>
      <Text style={styles.eyebrow}>Find</Text>
      <Text style={styles.title}>Open the mobile app for the map</Text>
      <Text style={styles.copy}>
        The province cluster map and range pins live on the iOS and
        Android apps. On the web, use the full calendar at
        shootingsports.co.za/map instead.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#101516',
    padding: 32,
    gap: 12,
  },
  eyebrow: {
    color: '#d9ae52',
    fontSize: 12,
    letterSpacing: 1.1,
    textTransform: 'uppercase',
  },
  title: {
    color: '#f4f0e6',
    fontSize: 20,
    fontWeight: '700',
    textAlign: 'center',
  },
  copy: {
    color: '#a3adaa',
    fontSize: 14,
    textAlign: 'center',
    maxWidth: 420,
  },
});
