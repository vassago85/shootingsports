import { listOrganisations } from '../organisations';

jest.mock('../client', () => ({
  apiFetch: jest.fn().mockResolvedValue({ data: [] }),
}));

import { apiFetch } from '../client';

const fetchMock = apiFetch as jest.MockedFunction<typeof apiFetch>;

beforeEach(() => {
  fetchMock.mockClear();
});

describe('listOrganisations query string', () => {
  it('emits an empty path when no filters are set', async () => {
    await listOrganisations();
    expect(fetchMock).toHaveBeenCalledWith('/organisations', { auth: false });
  });

  it('passes the province slug through verbatim', async () => {
    await listOrganisations({ province: 'gauteng' });
    const [path] = fetchMock.mock.calls[0]!;
    expect(path).toBe('/organisations?province=gauteng');
  });

  it('serialises visitors=true but drops visitors=false (null-equivalent)', async () => {
    // The server treats missing + "false" the same (show everyone),
    // so sending visitors=false is wasted bytes. The client drops it
    // and only sends `visitors=true` when the toggle is actually on.
    await listOrganisations({ visitors: true });
    expect(fetchMock.mock.calls[0]![0]).toBe('/organisations?visitors=true');

    fetchMock.mockClear();
    await listOrganisations({ visitors: false });
    expect(fetchMock.mock.calls[0]![0]).toBe('/organisations');
  });

  it('passes the type override through', async () => {
    await listOrganisations({ type: 'federation' });
    expect(fetchMock.mock.calls[0]![0]).toBe('/organisations?type=federation');
  });
});
