// Mock expo-font so @expo/vector-icons' componentDidMount loadAsync() does
// not try to resolve TTF assets through react-native's AssetRegistry (which
// Jest does not populate). Returning a resolved promise matches the behavior
// of a successful font load.
jest.mock('expo-font', () => ({
  ...jest.requireActual('expo-font'),
  isLoaded: jest.fn().mockReturnValue(true),
  loadAsync: jest.fn().mockResolvedValue(undefined),
}));
