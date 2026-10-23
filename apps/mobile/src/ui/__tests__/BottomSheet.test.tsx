import { fireEvent, render, screen } from '@testing-library/react-native';
import { Text } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import BottomSheet from '../BottomSheet';

/**
 * The sheet reads safe-area insets. In Jest there's no device chrome,
 * so we hand the provider a zero-inset frame + metrics.
 */
const SAFE_FRAME = { x: 0, y: 0, width: 390, height: 844 };
const SAFE_INSETS = { top: 0, right: 0, bottom: 0, left: 0 };

function wrap(ui: React.ReactElement) {
  return (
    <SafeAreaProvider initialMetrics={{ frame: SAFE_FRAME, insets: SAFE_INSETS }}>
      {ui}
    </SafeAreaProvider>
  );
}

/**
 * BottomSheet is the host for the Calendar's new "Filters" sheet.
 * We test the integration points the sheet relies on, not the slide
 * animation (RN's Animated layer is not exercised in jsdom-ish test
 * renderer).
 */
describe('BottomSheet', () => {
  it('renders title, body and the primary action label when visible', async () => {
    await render(
      wrap(
        <BottomSheet
          visible
          title="Filters"
          onClose={jest.fn()}
          primaryAction={{ label: 'Done', onPress: jest.fn() }}
        >
          <Text>Discipline chips</Text>
        </BottomSheet>,
      ),
    );

    expect(screen.getByText('Filters')).toBeTruthy();
    expect(screen.getByText('Discipline chips')).toBeTruthy();
    expect(screen.getByLabelText('Done')).toBeTruthy();
  });

  it('calls onClose when the ✕ button is pressed', async () => {
    const onClose = jest.fn();

    await render(
      wrap(
        <BottomSheet visible title="Filters" onClose={onClose}>
          <Text>body</Text>
        </BottomSheet>,
      ),
    );

    fireEvent.press(screen.getByLabelText('Close'));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('calls primaryAction.onPress when Done is pressed', async () => {
    const onDone = jest.fn();

    await render(
      wrap(
        <BottomSheet
          visible
          title="Filters"
          onClose={jest.fn()}
          primaryAction={{ label: 'Done', onPress: onDone }}
        >
          <Text>body</Text>
        </BottomSheet>,
      ),
    );

    fireEvent.press(screen.getByLabelText('Done'));
    expect(onDone).toHaveBeenCalledTimes(1);
  });

  it('renders secondaryAction and disables it when told', async () => {
    const onClear = jest.fn();

    await render(
      wrap(
        <BottomSheet
          visible
          title="Filters"
          onClose={jest.fn()}
          secondaryAction={{ label: 'Clear all', onPress: onClear, disabled: true }}
        >
          <Text>body</Text>
        </BottomSheet>,
      ),
    );

    const btn = screen.getByLabelText('Clear all');
    // Pressable with disabled still fires in jest unless disabled is honored.
    // We assert the accessibility state instead, which is the stable contract.
    expect(btn.props.accessibilityState).toMatchObject({ disabled: true });
  });

  it('fires secondary onPress when enabled', async () => {
    const onClear = jest.fn();

    await render(
      wrap(
        <BottomSheet
          visible
          title="Filters"
          onClose={jest.fn()}
          secondaryAction={{ label: 'Clear all', onPress: onClear }}
        >
          <Text>body</Text>
        </BottomSheet>,
      ),
    );

    fireEvent.press(screen.getByLabelText('Clear all'));
    expect(onClear).toHaveBeenCalledTimes(1);
  });
});
