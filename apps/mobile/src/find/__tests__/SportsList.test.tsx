import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { Discipline } from '@/api/disciplines';

import SportsList from '../SportsList';

/**
 * SportsList is the first live mode on Find after Events. Its job
 * is to turn `/api/v1/disciplines` into a 2-column grid grouped
 * under firearm family headers, with a working tap handoff to
 * Calendar.
 *
 * We mock the API client so tests don't need Expo config or the
 * network, and mock expo-router so the push can be asserted without
 * a real router context.
 */

// Jest hoists jest.mock() above every import, so the factory can only
// reference identifiers prefixed with `mock*` (Jest treats these as
// hoistable). We alias to local names below for readability.
const mockPush = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush, replace: jest.fn() }),
}));

jest.mock('@/api/disciplines', () => {
  const actual = jest.requireActual('@/api/disciplines');
  return {
    ...actual,
    listDisciplines: jest.fn(),
  };
});

import { listDisciplines } from '@/api/disciplines';

const listMock = listDisciplines as jest.MockedFunction<typeof listDisciplines>;
const pushMock = mockPush;

function makeDiscipline(partial: Partial<Discipline> & { slug: string; name: string }): Discipline {
  return {
    id: 1,
    slug: partial.slug,
    name: partial.name,
    family: partial.family ?? null,
    family_label: partial.family_label ?? null,
    short_blurb: null,
    parent_id: null,
    upcoming_count: partial.upcoming_count ?? 0,
    url: 'https://example.com',
  };
}

beforeEach(() => {
  pushMock.mockClear();
  listMock.mockClear();
});

describe('SportsList', () => {
  it('groups disciplines under the Rifle / Handgun / Shotgun headers', async () => {
    listMock.mockResolvedValueOnce([
      makeDiscipline({ slug: 'prs', name: 'PRS', family: 'rifle', family_label: 'Rifle' }),
      makeDiscipline({
        slug: 'ipsc',
        name: 'IPSC Practical',
        family: 'handgun',
        family_label: 'Handgun',
      }),
      makeDiscipline({
        slug: 'clay',
        name: 'Clay Target',
        family: 'shotgun',
        family_label: 'Shotgun',
      }),
    ]);

    await render(<SportsList />);

    await waitFor(() => {
      expect(screen.getByText('PRS')).toBeTruthy();
    });

    // Each family label appears twice when both the header and the
    // tile's family pill render — one match for the header, one for
    // the pill. The important thing is that we see both at least once.
    expect(screen.getAllByText('Rifle').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Handgun').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Shotgun').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('IPSC Practical')).toBeTruthy();
    expect(screen.getByText('Clay Target')).toBeTruthy();
  });

  it('hides a family header when it has no disciplines', async () => {
    listMock.mockResolvedValueOnce([
      makeDiscipline({ slug: 'prs', name: 'PRS', family: 'rifle', family_label: 'Rifle' }),
    ]);

    await render(<SportsList />);

    await waitFor(() => expect(screen.getByText('PRS')).toBeTruthy());

    // Only Rifle has a discipline — the Handgun/Shotgun/Airgun/Multi
    // headers must not render.
    expect(screen.queryByText('Handgun')).toBeNull();
    expect(screen.queryByText('Shotgun')).toBeNull();
    expect(screen.queryByText('Airgun')).toBeNull();
    expect(screen.queryByText('Multi')).toBeNull();
  });

  it('still renders a discipline with a zero upcoming count (browse surface)', async () => {
    // Unlike Calendar's chip row, Sports is a browse surface — we
    // keep the tile even when it would return no matches today,
    // but render it muted with a "NO UPCOMING" footer so active
    // tiles rise visually to the top of the page.
    listMock.mockResolvedValueOnce([
      makeDiscipline({
        slug: 'prs',
        name: 'PRS',
        family: 'rifle',
        family_label: 'Rifle',
        upcoming_count: 0,
      }),
    ]);

    await render(<SportsList />);

    await waitFor(() => expect(screen.getByText('PRS')).toBeTruthy());
    expect(screen.getByText('NO UPCOMING')).toBeTruthy();
  });

  it('pushes to Calendar with the discipline slug when a tile is tapped', async () => {
    listMock.mockResolvedValueOnce([
      makeDiscipline({ slug: 'prs', name: 'PRS', family: 'rifle', family_label: 'Rifle' }),
    ]);

    await render(<SportsList />);

    await waitFor(() => expect(screen.getByText('PRS')).toBeTruthy());

    fireEvent.press(screen.getByLabelText('PRS, no upcoming matches'));

    expect(pushMock).toHaveBeenCalledWith({
      pathname: '/calendar',
      params: { discipline: 'prs' },
    });
  });

  it('shows a friendly empty state when the API returns no disciplines', async () => {
    listMock.mockResolvedValueOnce([]);

    await render(<SportsList />);

    await waitFor(() => {
      expect(screen.getByText(/No sports listed yet/i)).toBeTruthy();
    });
  });
});
