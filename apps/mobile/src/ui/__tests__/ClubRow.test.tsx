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
    visitors_welcome: false,
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
