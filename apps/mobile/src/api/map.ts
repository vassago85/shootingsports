import { apiFetch } from './client';

export type MapPin = {
  slug: string;
  name: string;
  town: string | null;
  province: string | null;
  province_slug: string | null;
  lat: number;
  lng: number;
  count: number;
};

export type MapCentroid = {
  slug: string;
  label: string;
  lat: number;
  lng: number;
};

export type MapPayload = {
  pins: MapPin[];
  centroids: MapCentroid[];
  total_matches: number;
};

export async function fetchMap(): Promise<MapPayload> {
  return apiFetch<MapPayload>('/map', { auth: false });
}
