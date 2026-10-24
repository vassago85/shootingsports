import { fireEvent, render, screen } from '@testing-library/react-native';

import LinkableText from '../LinkableText';

const mockOpenURL = jest.fn();

jest.mock('expo-linking', () => ({
  openURL: (...args: unknown[]) => mockOpenURL(...args),
}));

beforeEach(() => {
  mockOpenURL.mockClear();
});

describe('LinkableText', () => {
  it('renders a tappable tel: link for a national 0XX phone number', async () => {
    await render(
      <LinkableText>Call Johann on 012 345 6789 to confirm.</LinkableText>,
    );

    const link = screen.getByText('012 345 6789');
    fireEvent.press(link);
    expect(mockOpenURL).toHaveBeenCalledWith('tel:0123456789');
  });

  it('renders a tappable tel: link for a +27 formatted number', async () => {
    await render(
      <LinkableText>Entries via +27 83 123 4567 after 18:00.</LinkableText>,
    );

    const link = screen.getByText('+27 83 123 4567');
    fireEvent.press(link);
    expect(mockOpenURL).toHaveBeenCalledWith('tel:+27831234567');
  });

  it('renders a tappable mailto: link for a standard email address', async () => {
    await render(
      <LinkableText>Email entries@example.co.za to register.</LinkableText>,
    );

    const link = screen.getByText('entries@example.co.za');
    fireEvent.press(link);
    expect(mockOpenURL).toHaveBeenCalledWith('mailto:entries@example.co.za');
  });

  it('leaves non-phone, non-email prose as plain text', async () => {
    await render(
      <LinkableText>No contact info in this paragraph.</LinkableText>,
    );

    expect(screen.getByText('No contact info in this paragraph.')).toBeTruthy();
    // Nothing to tap — the mock should never fire if we don't press.
    expect(mockOpenURL).not.toHaveBeenCalled();
  });

  it('handles a paragraph with both a phone and an email', async () => {
    await render(
      <LinkableText>
        Call 012 345 6789 or email entries@example.co.za.
      </LinkableText>,
    );

    fireEvent.press(screen.getByText('012 345 6789'));
    fireEvent.press(screen.getByText('entries@example.co.za'));

    expect(mockOpenURL).toHaveBeenNthCalledWith(1, 'tel:0123456789');
    expect(mockOpenURL).toHaveBeenNthCalledWith(2, 'mailto:entries@example.co.za');
  });
});
