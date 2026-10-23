import { fireEvent, render, screen } from '@testing-library/react-native';

import { Venue } from '@/api/venues';

import RangeRow from '../RangeRow';

function makeVenue(partial: Partial<Venue> & { name: string }): Venue {
  // Start from a complete base, then let the caller override any
  // field — including null fields — via the spread. `??` would
  // swallow an explicit `null` and quietly use the default, which
  // breaks the "no town, no province" branch of the component.
  const base: Venue = {
    id: 1,
    slug: 'thabazimbi-range',
    name: partial.name,
    town: 'Thabazimbi',
    province: 'Gauteng',
    province_slug: 'gauteng',
    lat: null,
    lng: null,
    url: 'https://shootingsports.co.za/ranges/thabazimbi-range',
    logo_url: null,
    summary: null,
    disciplines: [],
    upcoming_count: 0,
  };
  return { ...base, ...partial };
}

describe('RangeRow', () => {
  it('renders name, town+province, and the no-matches footer', async () => {
    await render(
      <RangeRow venue={makeVenue({ name: 'Thabazimbi Range' })} onPress={jest.fn()} />,
    );

    expect(screen.getByText('Thabazimbi Range')).toBeTruthy();
    expect(screen.getByText('Thabazimbi, Gauteng')).toBeTruthy();
    // Empty disciplines + zero upcoming — the footer still reads cleanly.
    expect(screen.getByText('No upcoming matches')).toBeTruthy();
  });

  it('joins the first two disciplines with the upcoming count', async () => {
    await render(
      <RangeRow
        venue={makeVenue({
          name: 'Thabazimbi Range',
          upcoming_count: 3,
          disciplines: [
            { slug: 'prs', name: 'PRS' },
            { slug: 'ipsc', name: 'IPSC' },
          ],
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('PRS · IPSC · 3 upcoming')).toBeTruthy();
  });

  it('shows a "+N" overflow for more than two disciplines', async () => {
    await render(
      <RangeRow
        venue={makeVenue({
          name: 'Thabazimbi Range',
          upcoming_count: 5,
          disciplines: [
            { slug: 'prs', name: 'PRS' },
            { slug: 'ipsc', name: 'IPSC' },
            { slug: 'fclass', name: 'F-Class' },
            { slug: 'skeet', name: 'Skeet' },
          ],
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('PRS · IPSC +2 · 5 upcoming')).toBeTruthy();
  });

  it('builds initials from the first letter of each word', async () => {
    await render(
      <RangeRow
        venue={makeVenue({ name: 'Thabazimbi Shooting Range' })}
        onPress={jest.fn()}
      />,
    );

    // First two words only — TS, not TSR.
    expect(screen.getByText('TS')).toBeTruthy();
  });

  it('falls back to "Location to be confirmed" when both town and province are null', async () => {
    await render(
      <RangeRow
        venue={makeVenue({
          name: 'Hidden Range',
          town: null,
          province: null,
          province_slug: null,
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('Location to be confirmed')).toBeTruthy();
  });

  it('fires onPress when tapped', async () => {
    const onPress = jest.fn();
    await render(
      <RangeRow venue={makeVenue({ name: 'Thabazimbi Range' })} onPress={onPress} />,
    );

    fireEvent.press(
      screen.getByLabelText(/Thabazimbi Range, Thabazimbi, Gauteng/),
    );
    expect(onPress).toHaveBeenCalledTimes(1);
  });
});
