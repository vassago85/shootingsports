/**
 * Shared UI primitives — spec §2.3 "Shared components".
 *
 * These are the only pieces that compose Shooting Sports screens.
 * No screen should define its own stylesheet for a card, chip,
 * button, error row or empty state when a primitive here fits —
 * add a prop to the primitive instead.
 */
export { default as BottomSheet } from './BottomSheet';
export { default as CapabilityCard } from './CapabilityCard';
export { default as ClubRow } from './ClubRow';
export { default as EmptyState } from './EmptyState';
export { default as ErrorRow } from './ErrorRow';
export { default as FilterChip } from './FilterChip';
export { default as GhostButton } from './GhostButton';
export { default as HeroBand } from './HeroBand';
export { default as MatchCard } from './MatchCard';
export { default as MatchRow } from './MatchRow';
export { default as PlanPill } from './PlanPill';
export { default as PrimaryButton } from './PrimaryButton';
export { default as RangeRow } from './RangeRow';
export { default as SectionTitle } from './SectionTitle';
export { default as ShopTile } from './ShopTile';
export { default as SportTile } from './SportTile';
export { default as Spinner } from './Spinner';
