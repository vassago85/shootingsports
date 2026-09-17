import { useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Linking, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';

import { EventDetail, fetchEvent } from '@/api/events';
import { saveEvent, unsaveEvent, listSavedEvents } from '@/api/saved-events';
import { getAuthToken } from '@/auth/store';

export default function MatchDetailScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const [event, setEvent] = useState<EventDetail | null>(null);
  const [saved, setSaved] = useState<boolean | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (typeof slug !== 'string') {
      setError('Missing match slug.');
      setLoading(false);
      return;
    }

    setError(null);

    try {
      const detail = await fetchEvent(slug);
      setEvent(detail);

      const token = await getAuthToken();
      if (token !== null) {
        const savedEvents = await listSavedEvents();
        setSaved(savedEvents.some((row) => row.id === detail.id));
      } else {
        setSaved(false);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not load this match.');
    } finally {
      setLoading(false);
    }
  }, [slug]);

  useEffect(() => {
    void load();
  }, [load]);

  async function toggleSaved() {
    if (event === null) {
      return;
    }

    const token = await getAuthToken();
    if (token === null) {
      // Screens that need auth push to /login. Detail page keeps the
      // saved toggle disabled until then.
      return;
    }

    if (saved === true) {
      await unsaveEvent(event.id);
      setSaved(false);
    } else {
      await saveEvent(event.id);
      setSaved(true);
    }
  }

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator />
      </View>
    );
  }

  if (error !== null || event === null) {
    return (
      <View style={styles.center}>
        <Text style={styles.error}>{error ?? 'Match not found.'}</Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ padding: 20, gap: 12 }}>
      <Text style={styles.title}>{event.title}</Text>
      <Text style={styles.meta}>{event.host.name}</Text>
      <Text style={styles.meta}>
        {event.starts_at !== null ? new Date(event.starts_at).toDateString() : 'Date TBC'}
      </Text>
      <Text style={styles.meta}>{event.location_label}</Text>

      {event.description !== null && event.description.length > 0 && (
        <Text style={styles.body}>{event.description}</Text>
      )}

      <View style={styles.row}>
        {event.entry_url !== null && event.entry_url.length > 0 && (
          <TouchableOpacity
            onPress={() => Linking.openURL(event.entry_url as string)}
            style={styles.primary}
          >
            <Text style={styles.primaryText}>Open entry link</Text>
          </TouchableOpacity>
        )}

        <TouchableOpacity onPress={toggleSaved} style={styles.secondary}>
          <Text style={styles.secondaryText}>
            {saved === true ? 'Remove from calendar' : 'Add to calendar'}
          </Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#101516' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#101516' },
  title: { color: '#f4f0e6', fontSize: 22, fontWeight: '700' },
  meta: { color: '#a3adaa', fontSize: 13 },
  body: { color: '#d0d5d3', fontSize: 14, lineHeight: 20, marginTop: 12 },
  row: { flexDirection: 'row', gap: 12, marginTop: 16, flexWrap: 'wrap' },
  primary: { backgroundColor: '#d9ae52', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 6 },
  primaryText: { color: '#232a2c', fontWeight: '700' },
  secondary: {
    borderWidth: 1,
    borderColor: '#3a4548',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 6,
  },
  secondaryText: { color: '#f4f0e6' },
  error: { color: '#f28c8c', textAlign: 'center', margin: 24 },
});
