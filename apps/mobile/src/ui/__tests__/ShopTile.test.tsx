import { fireEvent, render, screen } from '@testing-library/react-native';

import { Provider } from '@/api/providers';

import ShopTile from '../ShopTile';

function makeProvider(partial: Partial<Provider> & { name: string }): Provider {
  const base: Provider = {
    id: 1,
    slug: 'pretoria-gunsmith',
    name: partial.name,
    category: 'gunsmith',
    category_label: 'Gunsmith',
    town: 'Pretoria',
    province: 'Gauteng',
    province_slug: 'gauteng',
    tagline: null,
    summary: null,
    logo_url: null,
    tier: null,
    url: 'https://shootingsports.co.za/supplier/pretoria-gunsmith',
  };
  return { ...base, ...partial };
}

describe('ShopTile', () => {
  it('shows the shop name and town+province line', async () => {
    await render(
      <ShopTile
        provider={makeProvider({ name: 'Pretoria Gunsmith' })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('Pretoria Gunsmith')).toBeTruthy();
    expect(screen.getByText('Pretoria, Gauteng')).toBeTruthy();
  });

  it('renders the initials fallback with the category label when no logo', async () => {
    await render(
      <ShopTile
        provider={makeProvider({
          name: 'Pretoria Gunsmith',
          logo_url: null,
          category_label: 'Gunsmith',
        })}
        onPress={jest.fn()}
      />,
    );

    // Two-letter initials from the first two words.
    expect(screen.getByText('PG')).toBeTruthy();
    // Category label stacked under the initials — intentional, so a
    // logo-less tile still looks like what it is.
    expect(screen.getByText('Gunsmith')).toBeTruthy();
  });

  it('falls back to "Nationwide" when town and province are both missing', async () => {
    await render(
      <ShopTile
        provider={makeProvider({
          name: 'Pretoria Gunsmith',
          town: null,
          province: null,
          province_slug: null,
        })}
        onPress={jest.fn()}
      />,
    );

    expect(screen.getByText('Nationwide')).toBeTruthy();
  });

  it('fires onPress when tapped', async () => {
    const onPress = jest.fn();
    await render(
      <ShopTile
        provider={makeProvider({ name: 'Pretoria Gunsmith' })}
        onPress={onPress}
      />,
    );

    fireEvent.press(screen.getByLabelText(/Pretoria Gunsmith, Gunsmith/));
    expect(onPress).toHaveBeenCalledTimes(1);
  });
});
