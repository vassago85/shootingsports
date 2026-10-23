import { listVenues } from '../venues';

jest.mock('../client', () => ({
  apiFetch: jest.fn().mockResolvedValue({ data: [] }),
}));

import { apiFetch } from '../client';

const fetchMock = apiFetch as jest.MockedFunction<typeof apiFetch>;

beforeEach(() => {
  fetchMock.mockClear();
});

describe('listVenues query string', () => {
  it('emits an empty path when no filters are set', async () => {
    await listVenues();
    expect(fetchMock).toHaveBeenCalledWith('/venues', { auth: false });
  });

  it('passes the province slug through verbatim', async () => {
    await listVenues({ province: 'gauteng' });
    const [path] = fetchMock.mock.calls[0]!;
    expect(path).toBe('/venues?province=gauteng');
  });

  it('passes the free-text search as `q`', async () => {
    await listVenues({ q: 'thabazimbi' });
    const [path] = fetchMock.mock.calls[0]!;
    expect(path).toBe('/venues?q=thabazimbi');
  });

  it('drops null, undefined, and empty-string filters', async () => {
    await listVenues({ province: null, q: '' });
    expect(fetchMock).toHaveBeenCalledWith('/venues', { auth: false });
  });
});
