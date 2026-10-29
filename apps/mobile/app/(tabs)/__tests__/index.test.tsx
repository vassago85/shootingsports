import { render, screen, waitFor } from '@testing-library/react-native';

import HomeScreen from '../index';
import type { EventCard } from '@/api/events';
import type { FeedEntry } from '@/api/me-feed';

/**
 * Home screen state tests — per BUILD_SPEC §3.2 M2 states + M2e.
 *
 * Signed-out branch (M2a–M2d):
 *   M2a Loading   — HeroBand + centred Spinner
 *   M2b Empty     — HeroBand + EmptyState (calendar-outline)
 *   M2c Error     — HeroBand + ErrorRow + "Browse the full calendar"
 *   M2d Populated — HeroBand + sections + ghost button
 *
 * Signed-in branch (M2e):
 *   · "For you / What's on" eyebrow + H1 when has_follows:true
 *   · Nudge card when has_follows:false (still generic layout)
 *   · needs_province nudge when the user follows a discipline but no province
 *   · Reasons strips appear under personalised cards
 *
 * Expo Router hooks and both APIs are mocked. The real useFocusEffect
 * is a no-op outside a tab navigator — we replace it with a plain
 * useEffect so the data fetch still runs when the component mounts.
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

jest.mock('@/api/me-feed', () => ({
  fetchMeFeed: jest.fn(),
}));

jest.mock('@/auth/store', () => ({
  getAuthToken: jest.fn(),
}));

jest.mock('@/flags', () => ({
  isHomePersonalisedEnabled: jest.fn(),
}));

jest.mock('react-native-safe-area-context', () => ({
  useSafeAreaInsets: () => ({ top: 44, bottom: 34, left: 0, right: 0 }),
  SafeAreaProvider: ({ children }: { children: React.ReactNode }) => children,
}));

import { listEvents } from '@/api/events';
import { fetchMeFeed } from '@/api/me-feed';
import { getAuthToken } from '@/auth/store';
import { isHomePersonalisedEnabled } from '@/flags';

const mockListEvents = jest.mocked(listEvents);
const mockFetchMeFeed = jest.mocked(fetchMeFeed);
const mockGetAuthToken = jest.mocked(getAuthToken);
const mockIsHomePersonalisedEnabled = jest.mocked(isHomePersonalisedEnabled);

beforeEach(() => {
  mockListEvents.mockReset();
  mockFetchMeFeed.mockReset();
  mockGetAuthToken.mockReset();
  mockIsHomePersonalisedEnabled.mockReset();
  // Default off, matching production. Signed-in M2e tests opt in.
  mockIsHomePersonalisedEnabled.mockReturnValue(false);
});

/* ============================================================
 * Signed-out branch (M2a–M2d)
 * ========================================================= */

describe('HomeScreen — signed out', () => {
  beforeEach(() => {
    mockGetAuthToken.mockResolvedValue(null);
  });

  it('M2a: shows the loading spinner while the fetch is in flight', async () => {
    mockListEvents.mockImplementation(() => new Promise(() => {}));

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByLabelText('Loading matches')).toBeTruthy();
    });
  });

  it('M2b: shows the empty state when the API returns zero rows', async () => {
    mockListEvents.mockResolvedValue({ events: [], count: 0, suggestion: null });

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

  it('uses the generic "National calendar / Upcoming matches" hero copy', async () => {
    mockListEvents.mockResolvedValue({ events: [], count: 0, suggestion: null });

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText('National calendar')).toBeTruthy();
      expect(screen.getByText('Upcoming matches')).toBeTruthy();
    });
  });

  it('does NOT show the "Follow a club" nudge when signed out', async () => {
    mockListEvents.mockResolvedValue({ events: [sampleEvent()], count: 1, suggestion: null });

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.queryByText(/Follow a club or discipline/i)).toBeNull();
    });
  });
});

/* ============================================================
 * Signed-in branch with the M2e flag OFF (production default)
 * ========================================================= */

describe('HomeScreen — signed in with M2e flag OFF (default)', () => {
  beforeEach(() => {
    mockGetAuthToken.mockResolvedValue('mock-token');
    // Flag defaults to false in the top-level beforeEach, kept
    // explicit here for the reader.
    mockIsHomePersonalisedEnabled.mockReturnValue(false);
  });

  it('does not call /me/feed when the flag is off', async () => {
    mockListEvents.mockResolvedValue({ events: [sampleEvent()], count: 1, suggestion: null });

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText('WC Provincial')).toBeTruthy();
    });
    expect(mockFetchMeFeed).not.toHaveBeenCalled();
  });

  it('still uses the generic "National calendar" hero when signed in but flag off', async () => {
    mockListEvents.mockResolvedValue({ events: [sampleEvent()], count: 1, suggestion: null });

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText('National calendar')).toBeTruthy();
      expect(screen.queryByText('For you')).toBeNull();
    });
  });
});

/* ============================================================
 * Signed-in branch (M2e)
 * ========================================================= */

describe('HomeScreen — signed in with M2e flag ON', () => {
  beforeEach(() => {
    mockGetAuthToken.mockResolvedValue('mock-token');
    mockIsHomePersonalisedEnabled.mockReturnValue(true);
  });

  it('shows the "For you / What\'s on" hero when has_follows is true', async () => {
    mockFetchMeFeed.mockResolvedValue(
      makeFeed({ next_30_days: [sampleFeedEntry()] }, { has_follows: true, has_home_venue: true }),
    );

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText('For you')).toBeTruthy();
      expect(screen.getByText("What's on")).toBeTruthy();
    });
  });

  it('renders the Saved section (with a Changed badge when flagged)', async () => {
    mockFetchMeFeed.mockResolvedValue(
      makeFeed(
        { saved: [{ ...sampleEvent(), id: 42, title: 'Steel Sunday', changed: true }] },
        { has_follows: true, has_home_venue: true },
      ),
    );

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText('Saved')).toBeTruthy();
      expect(screen.getByText('Steel Sunday')).toBeTruthy();
      expect(screen.getByText('CHANGED')).toBeTruthy();
    });
  });

  it('renders reasons strips under personalised cards', async () => {
    mockFetchMeFeed.mockResolvedValue(
      makeFeed({ next_30_days: [sampleFeedEntry()] }, { has_follows: true, has_home_venue: true }),
    );

    await render(<HomeScreen />);

    await waitFor(() => {
      // Reason text is `${name} · ${province}` — matches "SAPRF · Gauteng".
      expect(screen.getByText(/SAPRF · Gauteng/i)).toBeTruthy();
    });
  });

  it('renders the Near-you section with distance pills when near_you has entries', async () => {
    const nearYouEntry = {
      ...sampleEvent(),
      id: 99,
      title: 'Local Steel Challenge',
      reasons: [
        {
          type: 'near_you' as const,
          name: '42 km from Alberton Range',
          province: null,
          distance_km: 42,
        },
      ],
    };
    mockFetchMeFeed.mockResolvedValue(
      makeFeed({ near_you: [nearYouEntry] }, { has_follows: true, has_home_venue: true }),
    );

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText('Near you')).toBeTruthy();
      expect(screen.getByText('Local Steel Challenge')).toBeTruthy();
      expect(screen.getByText(/42 km from Alberton Range/i)).toBeTruthy();
    });
  });

  it('shows the "Pick a home range" nudge when has_home_venue is false', async () => {
    mockFetchMeFeed.mockResolvedValue(
      makeFeed({}, { has_follows: true, has_home_venue: false }),
    );

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText(/Pick a home range/i)).toBeTruthy();
    });
  });

  it('shows the "Pick your home province" nudge when needs_province is true', async () => {
    mockFetchMeFeed.mockResolvedValue(
      makeFeed({}, { has_follows: true, needs_province: true, has_home_venue: true }),
    );

    await render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText(/Pick your home province/i)).toBeTruthy();
    });
  });

  it('falls back to the generic layout + nudge when has_follows is false', async () => {
    mockFetchMeFeed.mockResolvedValue(
      makeFeed({}, { has_follows: false, has_home_venue: true }),
    );
    mockListEvents.mockResolvedValue({ events: [sampleEvent()], count: 1, suggestion: null });

    await render(<HomeScreen />);

    // has_follows=false lands in the personalised branch's guard
    // (which also renders the nudge). The server returns empty
    // buckets so we just see the nudge + the "Browse" CTA.
    await waitFor(() => {
      expect(screen.getByText(/Follow a club or discipline/i)).toBeTruthy();
    });
  });

  it('falls back to generic events + inline error when /me/feed fails', async () => {
    mockFetchMeFeed.mockRejectedValue(new Error('server glitch'));
    mockListEvents.mockResolvedValue({ events: [sampleEvent()], count: 1, suggestion: null });

    await render(<HomeScreen />);

    await waitFor(() => {
      // The generic layout should appear with our sample event.
      expect(screen.getByText('WC Provincial')).toBeTruthy();
    });
  });
});

/* ============================================================
 * Fixtures
 * ========================================================= */

function sampleEvent(): EventCard {
  return {
    id: 1,
    slug: 'wc-provincial',
    title: 'WC Provincial',
    starts_at: '2026-10-31T00:00:00+00:00',
    ends_at: null,
    all_day: true,
    level: 'provincial',
    status: 'entries_open',
    entry_url: null,
    entry_fee_cents: 80000,
    member_fee_cents: null,
    location_label: 'Atlantis · WC',
    banner_url: null,
    url: 'https://example.test/events/x',
    novice_friendly: false,
    venue: null,
    host: {
      name: 'SAPRF',
      organisation: {
        slug: 'saprf',
        name: 'South African Precision Rifle Federation',
        short_name: 'SAPRF',
        logo_url: null,
      },
    },
    disciplines: [],
    last_material_change_at: null,
  };
}

function sampleFeedEntry(): FeedEntry {
  return {
    ...sampleEvent(),
    reasons: [{ type: 'club', name: 'SAPRF', province: 'Gauteng' }],
  };
}

/**
 * Full `/me/feed` response shape. Each test overrides only the
 * buckets / meta flags it cares about; everything else is set to
 * the "nothing to show" neutral default.
 */
function makeFeed(
  dataOverrides: Partial<{
    saved: EventCard[];
    this_weekend: FeedEntry[];
    near_you: FeedEntry[];
    next_30_days: FeedEntry[];
  }> = {},
  metaOverrides: Partial<{
    has_follows: boolean;
    needs_province: boolean;
    has_home_venue: boolean;
  }> = {},
) {
  return {
    data: {
      saved: [],
      this_weekend: [],
      near_you: [],
      next_30_days: [],
      ...dataOverrides,
    },
    meta: {
      has_follows: false,
      needs_province: false,
      has_home_venue: false,
      ...metaOverrides,
    },
  };
}
