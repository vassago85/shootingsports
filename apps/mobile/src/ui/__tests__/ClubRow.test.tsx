import { fireEvent, render, screen } from '@testing-library/react-native';

import { Organisation } from '@/api/organisations';

import ClubRow from '../ClubRow';

function makeOrganisation(
  partial: Partial<Organisation> & { name: string },
): Organisation {
  const base: Organisation = {
    id: 1,
    slug: 'gauteng-rifle-club',
    name: partial.name,
    short_name: null,
    type: 'club',
    town: 'Pretoria',
    province: 'Gauteng',
    province_slug: 'gauteng',
    logo_url: null,
    url: 'https://shootingsports.co.za/clubs/gauteng-rifle-club',
    group: 'regional',
    visitors_welcome: null,
    disciplines: [],
  };
  return { ...base, ...partial };
}

describe('ClubRow', () => {
  it('renders name, town+province, and omits the discipline line when empty', async () => {
    await render(
      <ClubRow
        organisation={makeOrganisation({ name: 'Gauteng Rifle Club' })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('Gauteng Rifle Club')).toBeTruthy();
    expect(screen.getByText('Pretoria, Gauteng')).toBeTruthy();
    // No disciplines → no third line (nothing to show, nothing to clip).
    expect(screen.queryByText('·')).toBeNull();
  });

  it('surfaces a VISITORS pill when the club welcomes drop-ins', async () => {
    await render(
      <ClubRow
        organisation={makeOrganisation({
          name: 'Gauteng Rifle Club',
          visitors_welcome: true,
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('VISITORS')).toBeTruthy();
  });

  it('joins up to three disciplines with "·" separators', async () => {
    await render(
      <ClubRow
        organisation={makeOrganisation({
          name: 'Gauteng Rifle Club',
          disciplines: [
            { slug: 'prs', name: 'PRS' },
            { slug: 'ipsc', name: 'IPSC' },
            { slug: 'fclass', name: 'F-Class' },
          ],
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('PRS · IPSC · F-Class')).toBeTruthy();
  });

  it('shows "+N" overflow for more than three disciplines', async () => {
    await render(
      <ClubRow
        organisation={makeOrganisation({
          name: 'Gauteng Rifle Club',
          disciplines: [
            { slug: 'prs', name: 'PRS' },
            { slug: 'ipsc', name: 'IPSC' },
            { slug: 'fclass', name: 'F-Class' },
            { slug: 'skeet', name: 'Skeet' },
            { slug: 'trap', name: 'Trap' },
          ],
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('PRS · IPSC · F-Class +2')).toBeTruthy();
  });

  it('falls back to initials when there is no logo', async () => {
    await render(
      <ClubRow
        organisation={makeOrganisation({ name: 'Gauteng Rifle Club' })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('GR')).toBeTruthy();
  });

  it('shows "National body" for a national row with no HQ town', async () => {
    await render(
      <ClubRow
        organisation={makeOrganisation({
          name: 'SA Precision Rifle Federation',
          group: 'national',
          town: null,
          province: null,
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('National body')).toBeTruthy();
    expect(screen.queryByText('Location to be confirmed')).toBeNull();
  });

  it('keeps the HQ town for a national body that has one (CTSASA)', async () => {
    await render(
      <ClubRow
        organisation={makeOrganisation({
          name: 'CTSASA',
          group: 'national',
          town: 'Mossel Bay',
          province: 'Western Cape',
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('Mossel Bay, Western Cape')).toBeTruthy();
    expect(screen.queryByText('National body')).toBeNull();
  });

  it('hides the Visitors pill on national bodies even if visitors_welcome=true', async () => {
    await render(
      <ClubRow
        organisation={makeOrganisation({
          name: 'SA Precision Rifle Federation',
          group: 'national',
          visitors_welcome: true,
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.queryByText('VISITORS')).toBeNull();
  });

  it('keeps "Location to be confirmed" for regional rows with no town', async () => {
    await render(
      <ClubRow
        organisation={makeOrganisation({
          name: 'New Local Club',
          group: 'regional',
          town: null,
          province: null,
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('Location to be confirmed')).toBeTruthy();
  });

  it('fires onPress when tapped', async () => {
    const onPress = jest.fn();
    await render(
      <ClubRow
        organisation={makeOrganisation({ name: 'Gauteng Rifle Club' })}
        onPress={onPress}
      />,
    );

    fireEvent.press(screen.getByLabelText(/Gauteng Rifle Club/));
    expect(onPress).toHaveBeenCalledTimes(1);
  });
});
