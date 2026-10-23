import { listProviders, PROVIDER_CATEGORIES } from '../providers';

jest.mock('../client', () => ({
  apiFetch: jest.fn().mockResolvedValue({ data: [] }),
}));

import { apiFetch } from '../client';

const fetchMock = apiFetch as jest.MockedFunction<typeof apiFetch>;

beforeEach(() => {
  fetchMock.mockClear();
});

describe('listProviders query string', () => {
  it('emits an empty path when no filters are set', async () => {
    await listProviders();
    expect(fetchMock).toHaveBeenCalledWith('/providers', { auth: false });
  });

  it('passes the province slug through verbatim', async () => {
    await listProviders({ province: 'gauteng' });
    expect(fetchMock.mock.calls[0]![0]).toBe('/providers?province=gauteng');
  });

  it('passes the category slug through verbatim', async () => {
    await listProviders({ category: 'gunsmith' });
    expect(fetchMock.mock.calls[0]![0]).toBe('/providers?category=gunsmith');
  });

  it('drops null, undefined, and empty-string filters', async () => {
    await listProviders({ province: null, category: '', q: undefined });
    expect(fetchMock).toHaveBeenCalledWith('/providers', { auth: false });
  });
});

describe('PROVIDER_CATEGORIES constant', () => {
  it('exposes the kebab-case slugs the backend ProviderCategory enum uses', () => {
    const slugs = PROVIDER_CATEGORIES.map((c) => c.slug);
    // Spot-check that we're using the URL-slug form (dashes) not the
    // enum underscore form. The backend normalises both via
    // ProviderCategory::fromUrlSlug/tryFrom but kebab-case is the
    // canonical shape on the API.
    expect(slugs).toContain('reloading-components');
    expect(slugs).toContain('chassis-stocks');
    expect(slugs).toContain('ballistics-electronics');
    expect(slugs).toContain('safes-storage');
    // Distributors are not a shopping category — they must not appear.
    expect(slugs).not.toContain('distributor');
  });
});
