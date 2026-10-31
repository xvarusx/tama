import { describe, expect, it } from "vitest"
import { clampPage, getPageAfterDelete, getPageItems } from "./pagination"

describe("getPageItems", () => {
  it("lists every page when there are seven or fewer", () => {
    expect(getPageItems(1, 5)).toEqual([1, 2, 3, 4, 5])
  })

  it("adds a trailing ellipsis near the start", () => {
    expect(getPageItems(2, 10)).toEqual([1, 2, 3, 4, 5, "ellipsis-end", 10])
  })

  it("adds both ellipses around a middle page", () => {
    expect(getPageItems(5, 10)).toEqual([
      1, "ellipsis-start", 4, 5, 6, "ellipsis-end", 10,
    ])
  })

  it("adds a leading ellipsis near the end", () => {
    expect(getPageItems(9, 10)).toEqual([1, "ellipsis-start", 6, 7, 8, 9, 10])
  })
})

describe("clampPage", () => {
  it("keeps a valid page", () => expect(clampPage(3, 10)).toBe(3))
  it("clamps below one", () => expect(clampPage(0, 10)).toBe(1))
  it("clamps above the last page", () => expect(clampPage(12, 10)).toBe(10))
  it("never returns less than one when there are no pages", () => {
    expect(clampPage(5, 0)).toBe(1)
  })
})

describe("getPageAfterDelete", () => {
  it("stays put when other rows remain", () => {
    expect(getPageAfterDelete(2, 4)).toBe(2)
  })
  it("goes back when the last row on a later page is deleted", () => {
    expect(getPageAfterDelete(3, 1)).toBe(2)
  })
  it("stays on page one", () => expect(getPageAfterDelete(1, 1)).toBe(1))
})
