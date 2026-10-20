import { apiFetch } from './client';

/**
 * Capability kinds returned by `GET /api/v1/me`. Mirrors the enum of
 * cards built in `App\Support\DeskCapabilities::kindsFor()`.
 */
export type CapabilityKind = 'club' | 'shop' | 'range' | 'media';

export type MeProfile = {
  id: number;
  name: string;
  email: string;
  calendar_slug: string | null;
  home_province: string | null;
  home_province_slug: string | null;
  travel_radius_km: number | null;
  plan: string | null;
  is_pro: boolean;
  on_trial: boolean;
  trial_days_remaining: number;
  capabilities: CapabilityKind[];
  has_desk_access: boolean;
  limits: {
    follows: number | null;
    saved_searches: number | null;
    attended_events_slots: number | null;
    history_months: number | null;
    export: boolean;
    household_profiles: number | null;
  };
  usage: {
    follows: number;
    saved_searches: number;
    attended_events_slots: number;
  };
  remaining: {
    follows: number | null;
    saved_searches: number | null;
    attended_events_slots: number | null;
  };
  email_prefs: {
    marketing: boolean;
    match_alerts: boolean;
    weekly_digest: boolean;
    product_updates: boolean;
  };
};

export type CapabilityCard = {
  kind: CapabilityKind;
  title: string;
  summary: string;
  items: Array<{ name: string; url: string }>;
  primary_action: { label: string; url: string };
};

/**
 * Fresh identity + plan + capability summary. The app calls this on
 * launch (and on reconnect) so the Me tab and the Manage hub stay in
 * sync with what the website says.
 */
export async function fetchMe(): Promise<MeProfile> {
  const response = await apiFetch<{ data: MeProfile }>('/me');
  return response.data;
}

/**
 * Full capability-card payload for the Manage hub. The `primary_action`
 * URL is a public website URL — the app opens it with the system
 * browser rather than trying to render the heavy editor inline.
 */
export async function fetchCapabilities(): Promise<CapabilityCard[]> {
  const response = await apiFetch<{ data: CapabilityCard[] }>('/me/capabilities');
  return response.data;
}
