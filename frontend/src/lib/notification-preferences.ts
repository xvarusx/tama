import type {
  NotificationChannel,
  NotificationPreference,
  PreferenceEntry,
} from "@/services/notifications-api"

const CHANNELS: NotificationChannel[] = ["in_app", "email"]

export function flattenPreferences(
  matrix: NotificationPreference[],
): PreferenceEntry[] {
  return matrix.flatMap((row) =>
    CHANNELS.map((channel) => ({
      type: row.type,
      channel,
      enabled: row.channels[channel] ?? false,
    })),
  )
}

export function changedPreferences(
  original: PreferenceEntry[],
  current: PreferenceEntry[],
): PreferenceEntry[] {
  const originalByKey = new Map(
    original.map((entry) => [`${entry.type}:${entry.channel}`, entry.enabled]),
  )

  return current.filter(
    (entry) =>
      originalByKey.get(`${entry.type}:${entry.channel}`) !== entry.enabled,
  )
}
