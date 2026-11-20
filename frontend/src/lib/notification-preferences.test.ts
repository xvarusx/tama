import { describe, expect, it } from "vitest"
import {
  changedPreferences,
  flattenPreferences,
} from "./notification-preferences"
import type { NotificationPreference, PreferenceEntry } from "@/services/notifications-api"

const matrix: NotificationPreference[] = [
  {
    type: "invoice.issued",
    channels: { in_app: true, email: false },
  },
  {
    type: "payment.installment.overdue",
    channels: { in_app: true, email: true },
  },
]

describe("preference matrix helpers", () => {
  it("flattens the matrix into preference entries", () => {
    expect(flattenPreferences(matrix)).toEqual([
      { type: "invoice.issued", channel: "in_app", enabled: true },
      { type: "invoice.issued", channel: "email", enabled: false },
      {
        type: "payment.installment.overdue",
        channel: "in_app",
        enabled: true,
      },
      {
        type: "payment.installment.overdue",
        channel: "email",
        enabled: true,
      },
    ])
  })

  it("returns only changed rows for saving", () => {
    const original: PreferenceEntry[] = flattenPreferences(matrix)
    const current: PreferenceEntry[] = original.map((entry) =>
      entry.type === "invoice.issued" && entry.channel === "email"
        ? { ...entry, enabled: true }
        : entry,
    )

    expect(changedPreferences(original, current)).toEqual([
      { type: "invoice.issued", channel: "email", enabled: true },
    ])
    expect(changedPreferences(original, original)).toEqual([])
  })
})
