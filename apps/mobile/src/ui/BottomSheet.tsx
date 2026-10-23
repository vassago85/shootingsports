import { Ionicons } from '@expo/vector-icons';
import { useEffect, useRef } from 'react';
import {
  Animated,
  Easing,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, fonts, radius, size } from '@/theme';

type Props = {
  visible: boolean;
  title: string;
  onClose: () => void;
  /** Rendered inside a scroll view — long filter lists are fine. */
  children: React.ReactNode;
  /**
   * Primary footer action (e.g. "Done"). Rendered on the right.
   * The whole footer is optional; when both are omitted the header
   * close button is the only way out.
   */
  primaryAction?: { label: string; onPress: () => void };
  /** Secondary footer action (e.g. "Clear all"). Rendered on the left. */
  secondaryAction?: { label: string; onPress: () => void; disabled?: boolean };
};

/**
 * BottomSheet — iOS-style sheet rendered via the native Modal.
 *
 * We don't pull in a dependency for this; one slide-up animation
 * driven by Animated covers every case we have (filters, future
 * action sheets). Backdrop press and the ✕ in the header both
 * dismiss. Safe-area aware top + bottom insets so the sheet never
 * runs under the home indicator or notch.
 *
 * Design: forest header strip, card body, sticky footer at bottom.
 */
export default function BottomSheet({
  visible,
  title,
  onClose,
  children,
  primaryAction,
  secondaryAction,
}: Props) {
  const insets = useSafeAreaInsets();
  const slide = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.timing(slide, {
      toValue: visible ? 1 : 0,
      duration: visible ? 220 : 160,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    }).start();
  }, [visible, slide]);

  const translateY = slide.interpolate({
    inputRange: [0, 1],
    outputRange: [600, 0],
  });

  const backdropOpacity = slide.interpolate({
    inputRange: [0, 1],
    outputRange: [0, 0.5],
  });

  return (
    <Modal
      visible={visible}
      transparent
      animationType="none"
      onRequestClose={onClose}
      statusBarTranslucent
    >
      <Animated.View
        pointerEvents={visible ? 'auto' : 'none'}
        style={[styles.backdrop, { opacity: backdropOpacity }]}
      >
        <Pressable style={StyleSheet.absoluteFill} onPress={onClose} />
      </Animated.View>

      <View style={styles.sheetAnchor} pointerEvents="box-none">
        <Animated.View
          style={[
            styles.sheet,
            {
              transform: [{ translateY }],
              paddingBottom: Math.max(insets.bottom, 12),
            },
          ]}
        >
          <View style={styles.handle} />

          <View style={styles.header}>
            <Text style={styles.title}>{title}</Text>
            <Pressable
              onPress={onClose}
              accessibilityRole="button"
              accessibilityLabel="Close"
              hitSlop={8}
              style={({ pressed }) => [styles.closeBtn, pressed && styles.pressed]}
            >
              <Ionicons name="close" size={22} color={colors.ink} />
            </Pressable>
          </View>

          <ScrollView
            style={styles.body}
            contentContainerStyle={styles.bodyContent}
            showsVerticalScrollIndicator={false}
          >
            {children}
          </ScrollView>

          {(primaryAction || secondaryAction) && (
            <View style={styles.footer}>
              {secondaryAction ? (
                <Pressable
                  onPress={secondaryAction.onPress}
                  disabled={secondaryAction.disabled}
                  accessibilityRole="button"
                  accessibilityLabel={secondaryAction.label}
                  accessibilityState={{ disabled: secondaryAction.disabled }}
                  style={({ pressed }) => [
                    styles.secondaryBtn,
                    secondaryAction.disabled && styles.secondaryBtnDisabled,
                    pressed && !secondaryAction.disabled && styles.pressed,
                  ]}
                >
                  <Text
                    style={[
                      styles.secondaryLabel,
                      secondaryAction.disabled && styles.secondaryLabelDisabled,
                    ]}
                  >
                    {secondaryAction.label}
                  </Text>
                </Pressable>
              ) : (
                <View />
              )}
              {primaryAction && (
                <Pressable
                  onPress={primaryAction.onPress}
                  accessibilityRole="button"
                  accessibilityLabel={primaryAction.label}
                  style={({ pressed }) => [
                    styles.primaryBtn,
                    pressed && styles.pressed,
                  ]}
                >
                  <Text style={styles.primaryLabel}>{primaryAction.label}</Text>
                </Pressable>
              )}
            </View>
          )}
        </Animated.View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: '#000',
  },
  sheetAnchor: {
    flex: 1,
    justifyContent: 'flex-end',
  },
  sheet: {
    backgroundColor: colors.page,
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
    maxHeight: '85%',
    minHeight: 320,
  },
  handle: {
    alignSelf: 'center',
    width: 42,
    height: 4,
    borderRadius: 2,
    backgroundColor: colors.control,
    marginTop: 8,
    marginBottom: 2,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 10,
    paddingBottom: 10,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
  },
  title: {
    fontFamily: fonts.display,
    fontSize: 18,
    color: colors.ink,
    textTransform: 'uppercase',
    letterSpacing: 1.1,
  },
  closeBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.tint,
  },
  pressed: {
    opacity: 0.7,
  },
  body: {
    flexGrow: 0,
  },
  bodyContent: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    gap: 18,
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    paddingHorizontal: 16,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.rule,
    backgroundColor: colors.card,
  },
  primaryBtn: {
    minHeight: size.button,
    paddingHorizontal: 22,
    borderRadius: radius.button,
    backgroundColor: colors.lime,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryLabel: {
    fontFamily: fonts.display,
    fontSize: 15,
    color: colors.ink,
    textTransform: 'uppercase',
    letterSpacing: 1.2,
  },
  secondaryBtn: {
    minHeight: size.button,
    paddingHorizontal: 10,
    alignItems: 'flex-start',
    justifyContent: 'center',
  },
  secondaryBtnDisabled: {
    opacity: 0.4,
  },
  secondaryLabel: {
    fontFamily: fonts.bodySemi,
    fontSize: 14,
    color: colors.link,
  },
  secondaryLabelDisabled: {
    color: colors.muted,
  },
});
