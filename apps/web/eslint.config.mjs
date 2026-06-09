import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
    // Service worker es JS plano servido directo desde /public — no participa del bundle
    "public/sw.js",
  ]),
  {
    rules: {
      // Downgrade from error to warn for API response patterns.
      // We use typed interfaces for known shapes; `unknown` for truly dynamic data.
      "@typescript-eslint/no-explicit-any": "warn",
      // Allow unused vars that start with _ (intentional ignores)
      "@typescript-eslint/no-unused-vars": ["error", {
        "argsIgnorePattern": "^_",
        "varsIgnorePattern": "^_",
        "ignoreRestSiblings": true
      }],
      // Downgrade exhaustive-deps from error to warn — complex async patterns
      "react-hooks/exhaustive-deps": "warn",
      // Downgrade set-state-in-effect — fetch-on-mount es el patrón Next.js client legítimo
      // hasta migrar a Server Components con datos via params/loaders.
      "react-hooks/set-state-in-effect": "warn",
      "react-hooks/immutability": "warn",
    }
  }
]);

export default eslintConfig;
