import { listEvents } from '../events';

/**
 * Mock the HTTP client so these tests assert the URL shape of the
 * request `listEvents` builds, without ever touching the network.
 * The Pest side (`ApiV1DiscoveryTest`) proves the backend honours
 * each query param; these tests prove the mobile side sends them.
 */
jest.mock('../client', () => ({
  apiFetch: jest.fn().mockResolvedValue({ data: [], meta: { count: 0 } }),
}));

import { apiFetch } from '../client';

const fetchMock = apiFetch as jest.MockedFunction<typeof apiFetch>;

beforeEach(() => {
  fetchMock.mockClear();
});

describe('listEvents query string', () => {
  it('emits an empty path when no filters are set', async () => {
    await listEvents();
    expect(fetchMock).toHaveBeenCalledWith('/events', { auth: false });
  });

  it('passes the venue slug through verbatim', async () => {
    // Regression test for the Find map handoff: tapping a pin should
    // scope the Calendar to that specific range. The map used to
    // only pass `?province=`, so a pin tap in Gauteng returned every
    // Gauteng match instead of the one range the user tapped.
    await listEvents({ venue: 'thabazimbi-range' });

    const [path] = fetchMock.mock.calls[0]!;
    expect(path).toBe('/events?venue=thabazimbi-range');
  });

  it('passes venue + province together so widening the search still works', async () => {
    // Both are set when Find hands off: the venue scopes the list,
    // and the province chip is there so the user can tap "× range"
    // and still have a sensible broader scope to fall back to.
    await listEvents({ venue: 'thabazimbi-range', province: 'gauteng' });

    const [path] = fetchMock.mock.calls[0]!;
    expect(path).toContain('venue=thabazimbi-range');
    expect(path).toContain('province=gauteng');
  });

  it('drops null, undefined, and empty-string filters so the URL stays clean', async () => {
    await listEvents({
      venue: null,
      province: undefined,
      discipline: '',
      family: 'rifle',
    });

    const [path] = fetchMock.mock.calls[0]!;
    expect(path).toBe('/events?family=rifle');
  });

  it('serialises the weekend boolean as weekend=true', async () => {
    await listEvents({ weekend: true });

    const [path] = fetchMock.mock.calls[0]!;
    expect(path).toContain('weekend=true');
  });
});
