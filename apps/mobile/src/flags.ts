/**
 * Mobile feature flags.
 *
 * The build spec marks M2e (personalised Home) as **Future** behind a
 * flag defaulting off, so default-off is the whole point of this file.
 * Flip a flag by setting the matching EXPO_PUBLIC_* var at build time
 * (either in `.env`, `app.config.ts`, or the EAS build profile).
 */

function readBool(value: string | undefined): boolean {
  if (value === undefined) {
    return false;
  }

  const normalised = value.trim().toLowerCase();

  return normalised === '1' || normalised === 'true' || normalised === 'yes' || normalised === 'on';
}

/**
 * Personalised Home (M2e in docs/build-pack/BUILD_SPEC.md).
 *
 * When this returns true the signed-in Home screen calls `/me/feed`
 * and renders Saved + "From what you follow" + "Also nationally"
 * sections with reasons strips and a "Changed" badge. When false
 * every user — signed in or not — sees the generic national
 * calendar (states M2a-M2d).
 *
 * Exposed as a function so tests can `jest.mock('../flags')` and
 * flip it per-test without wrestling with module-load ordering.
 */
export function isHomePersonalisedEnabled(): boolean {
  return readBool(process.env.EXPO_PUBLIC_HOME_PERSONALISED);
}
