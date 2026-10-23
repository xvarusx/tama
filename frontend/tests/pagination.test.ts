import { describe, expect, it } from "vitest";
import { getPaginationItems } from "@/lib/pagination";

describe("getPaginationItems", () => {
  it("returns nothing for a single page", () => {
    expect(getPaginationItems(1, 1)).toEqual([]);
  });

  it("shows every page when there are five or fewer", () => {
    expect(getPaginationItems(4, 4)).toEqual([1, 2, 3, 4]);
    expect(getPaginationItems(3, 5)).toEqual([1, 2, 3, 4, 5]);
  });

  it("keeps the first and last page visible", () => {
    expect(getPaginationItems(5, 10)).toEqual([
      1,
      "ellipsis",
      4,
      5,
      6,
      "ellipsis",
      10,
    ]);
    expect(getPaginationItems(4, 6)).toEqual([
      1,
      "ellipsis",
      3,
      4,
      5,
      6,
    ]);
  });

  it("shows a wider window near either edge", () => {
    expect(getPaginationItems(1, 10)).toEqual([
      1,
      2,
      3,
      4,
      "ellipsis",
      10,
    ]);
    expect(getPaginationItems(10, 10)).toEqual([
      1,
      "ellipsis",
      7,
      8,
      9,
      10,
    ]);
  });

  it("clamps an out-of-range current page", () => {
    expect(getPaginationItems(0, 4)).toEqual([1, 2, 3, 4]);
    expect(getPaginationItems(99, 4)).toEqual([1, 2, 3, 4]);
  });
});
