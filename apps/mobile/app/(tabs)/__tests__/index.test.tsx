import { render, screen, waitFor } from '@testing-library/react-native';

import HomeScreen from '../index';

/**
 * Home screen state tests — per BUILD_SPEC §3.2 M2 states:
 *
 *   M2a Loading   — HeroBand + centred Spinner
 *   M2b Empty     — HeroBand + EmptyState (calendar-outline)
 *   M2c Error     — HeroBand + ErrorRow + "Browse the full calendar"
 *   M2d Populated — HeroBand + sections + ghost button
 *
 * Expo Router hooks and the events API are mocked so Home renders
 * in isolation. The real `useFocusEffect` is a no-op outside a tab
 * navigator — we replace it with a plain `useEffect` so the data
 * fetch still runs when the component mounts in a test.
 */

jest.mock('expo-router', () => {
  const React = require('react');
  return {
    useRouter: () => ({ push: jest.fn(), replace: jest.fn() }),
    useFocusEffect: (cb: () => void) => React.useEffect(cb, []),
  };
});

jest.mock('@/api/events', () => ({
  listEvents: jest.fn(),
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 44, bottom: 34, left: 0, right: 0 }),
  SafeAreaProvider: ({ children }: { children: React.ReactNode }) => children,
}));

// Reach into the mock after jest.mock has hoisted — `.mocked` keeps
// the TS types correct.
import { listEvents } from '@/api/events';
const mockListEvents = jest.mocked(listEvents);

describe('HomeScreen states', () => {
  beforeEach(() => {
    mockListEvents.mockReset();
  });

  it('M2a: shows the loading spinner while the fetch is in flight', async () => {
    // Return an unresolved promise so loading stays true.
    mockListEvents.mockImplementation(() => new Promise(() => {}));

    await render(<HomeScreen />);

    expect(screen.getByLabelText('Loading matches')).toBeTruthy();
  });

  it('M2b: shows the empty state when the API returns zero rows', async () => {
    mockListEvents.mockResolvedValue({ events: [], count: 0 });

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText(/No upcoming matches yet/i)).toBeTruthy();
    });
  });

  it('M2c: shows the error row + Browse ghost when the fetch rejects', async () => {
    mockListEvents.mockRejectedValue(new Error('Network down'));

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText(/Couldn't load matches/i)).toBeTruthy();
    });
    expect(screen.getByText(/Browse the full calendar/i)).toBeTruthy();
  });
});
