import { fetchMeFeed } from '../me-feed';

/**
 * Mock the HTTP client so these tests assert the URL + shape without
 * ever touching the network. The Pest side (ApiV1MeFeedTest) proves
 * the backend returns the right data for every follow combination;
 * these tests prove the mobile client calls the right endpoint and
 * passes the auth header through (via apiFetch's default behaviour
 * for /me/* paths).
 */
jest.mock('../client', () => ({
  apiFetch: jest.fn(),
}));

import { apiFetch } from '../client';

const fetchMock = apiFetch as jest.MockedFunction<typeof apiFetch>;

beforeEach(() => {
  fetchMock.mockClear();
});

describe('fetchMeFeed', () => {
  it('calls GET /me/feed', async () => {
    fetchMock.mockResolvedValue({
      data: { saved: [], this_weekend: [], next_30_days: [] },
      meta: { has_follows: false, needs_province: false },
    });

    await fetchMeFeed();

    expect(fetchMock).toHaveBeenCalledWith('/me/feed');
  });

  it('returns the three buckets and meta flags unchanged', async () => {
    const sample = {
      data: {
        saved: [],
        this_weekend: [],
        next_30_days: [
          {
            id: 16,
            slug: 'saprf-pr22-wc-provincial-champs-2026',
            title: 'WC Provincial Rimfire Champs',
            starts_at: '2026-10-31T00:00:00+00:00',
            ends_at: null,
            all_day: true,
            level: 'provincial',
            status: 'entries_open',
            entry_url: null,
            entry_fee_cents: 80000,
            member_fee_cents: 60000,
            location_label: 'Atlantis · WC',
            banner_url: null,
            url: 'https://example.test/events/x',
            novice_friendly: false,
            venue: null,
            host: { name: 'SAPRF', organisation: null },
            disciplines: [],
            last_material_change_at: null,
            reasons: [{ type: 'club', name: 'SAPRF', province: 'Gauteng' }],
          },
        ],
      },
      meta: { has_follows: true, needs_province: false },
    };
    fetchMock.mockResolvedValue(sample);

    const response = await fetchMeFeed();

    expect(response.meta.has_follows).toBe(true);
    expect(response.meta.needs_province).toBe(false);
    expect(response.data.next_30_days).toHaveLength(1);
    expect(response.data.next_30_days[0]!.reasons[0]!.type).toBe('club');
  });
});
