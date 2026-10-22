import { render, screen } from '@testing-library/react-native';

import Spinner from '../Spinner';

/**
 * Smoke test for the Jest + RNTL harness.
 *
 * RNTL v14 made `render` async (React 19 test renderer). Every test
 * that renders must `await render(...)` or the `screen` queries fail
 * with "render function has not been called".
 */
describe('Spinner', () => {
  it('exposes a default screen-reader label', async () => {
    await render(<Spinner />);
    // The View has accessibilityLabel="Loading" by default.
    expect(screen.getByLabelText('Loading')).toBeTruthy();
  });

  it('surfaces a custom label as both text and a11y label', async () => {
    await render(<Spinner label="Loading matches" />);
    expect(screen.getByText('Loading matches')).toBeTruthy();
    expect(screen.getByLabelText('Loading matches')).toBeTruthy();
  });
});
