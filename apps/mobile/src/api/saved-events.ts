import { apiFetch } from './client';
import { EventCard } from './events';

export async function listSavedEvents(): Promise<EventCard[]> {
  const response = await apiFetch<{ data: EventCard[] }>('/me/saved-events');
  return response.data;
}

export async function saveEvent(eventId: number): Promise<void> {
  await apiFetch('/me/saved-events', {
    method: 'POST',
    body: { event_id: eventId },
  });
}

export async function unsaveEvent(eventId: number): Promise<void> {
  await apiFetch(`/me/saved-events/${eventId}`, {
    method: 'DELETE',
  });
}
