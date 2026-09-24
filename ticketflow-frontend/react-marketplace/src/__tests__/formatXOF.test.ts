import { describe, expect, it } from "vitest";
import { formatXOF } from "../lib/api";

describe("formatXOF (devise FCFA / XOF uniquement)", () => {
  it("formate les milliers avec espace insécable et suffixe FCFA", () => {
    expect(formatXOF(12500)).toBe("12\u00a0500\u00a0FCFA");
  });
  it("accepte les montants stockés en string (decimal:2 cents)", () => {
    expect(formatXOF("50000")).toBe("50\u00a0000\u00a0FCFA");
  });
  it("gère zéro", () => {
    expect(formatXOF(0)).toBe("0\u00a0FCFA");
  });
});
