import { describe, expect, it } from "vitest"
import { getApiErrorMessage } from "./api"

const axiosError = (data: unknown) =>
  ({ isAxiosError: true, response: { data }, message: "Request failed" }) as never

describe("getApiErrorMessage", () => {
  it("reads the API error envelope", () => {
    expect(
      getApiErrorMessage(
        axiosError({
          status: "error",
          error: { code: "SKU_ALREADY_EXISTS", message: "SKU taken." },
        }),
      ),
    ).toBe("SKU taken.")
  })

  it("reads a plain error string", () => {
    expect(getApiErrorMessage(axiosError({ error: "Nope" }))).toBe("Nope")
  })

  it("reads Symfony's Problem Details detail field", () => {
    expect(getApiErrorMessage(axiosError({ detail: "Invalid payload." }))).toBe(
      "Invalid payload.",
    )
  })

  it("falls back to the axios message", () => {
    expect(getApiErrorMessage(axiosError({}))).toBe("Request failed")
  })

  it("reads a plain Error", () => {
    expect(getApiErrorMessage(new Error("Boom"))).toBe("Boom")
  })

  it("returns the fallback for unknown values", () => {
    expect(getApiErrorMessage(42, "Try again.")).toBe("Try again.")
  })
})
