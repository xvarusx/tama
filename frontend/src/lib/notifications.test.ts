import { describe, expect, it } from "vitest"
import {
  buildInboxQuery,
  severityBadgeVariant,
  severityClass,
  tabToStatus,
} from "./notifications"

describe("severity presentation", () => {
  it("returns the severity badge class for critical", () => {
    expect(severityClass("critical")).toBe("text-destructive")
    expect(severityBadgeVariant("critical")).toBe("destructive")
    expect(severityClass("info")).toBe("text-sky-600 dark:text-sky-400")
    expect(severityBadgeVariant("info")).toBe("secondary")
  })
})

describe("inbox filters", () => {
  it("maps tab values to status query params", () => {
    expect(tabToStatus("all")).toBeUndefined()
    expect(tabToStatus("unread")).toBe("unread")
    expect(tabToStatus("archived")).toBe("archived")
  })

  it("builds the inbox query string state from filters", () => {
    expect(
      buildInboxQuery({
        tab: "unread",
        severity: "warning",
        search: "overdue",
        page: 2,
        pageSize: 10,
      }),
    ).toEqual({
      page: 2,
      pageSize: 10,
      status: "unread",
      severity: "warning",
      search: "overdue",
    })

    expect(
      buildInboxQuery({
        tab: "all",
        severity: "",
        search: "",
        page: 1,
        pageSize: 20,
      }),
    ).toEqual({ page: 1, pageSize: 20 })
  })
})
