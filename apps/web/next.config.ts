import type { NextConfig } from "next";
import { withSentryConfig } from "@sentry/nextjs";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";
const API_ORIGIN = (() => {
  try {
    return new URL(API_URL).origin;
  } catch {
    return "http://localhost:8000";
  }
})();

const nextConfig: NextConfig = {
  output: "standalone",

  images: {
    remotePatterns: [
      { protocol: "https", hostname: "picsum.photos" },
      { protocol: "https", hostname: "images.comecyt.gob.mx" },
      { protocol: "https", hostname: "**.edomex.gob.mx" },
      { protocol: "http", hostname: "localhost", port: "8000" },
    ],
  },

  async headers() {
    return [
      {
        source: "/(.*)",
        headers: [
          { key: "X-Frame-Options", value: "DENY" },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          {
            key: "Permissions-Policy",
            value: "camera=(), microphone=(), geolocation=(), payment=(), usb=()",
          },
          {
            key: "Strict-Transport-Security",
            value: "max-age=31536000; includeSubDomains",
          },
          {
            key: "Content-Security-Policy",
            value:
              `default-src 'self'; ` +
              `script-src 'self' 'unsafe-inline'; ` +
              `style-src 'self' 'unsafe-inline'; ` +
              `font-src 'self' data:; ` +
              `img-src 'self' data: blob: ${API_ORIGIN} https://picsum.photos https://*.edomex.gob.mx; ` +
              `connect-src 'self' ${API_ORIGIN} https://*.sentry.io wss: ws:; ` +
              `frame-ancestors 'none'; ` +
              `base-uri 'self'; ` +
              `form-action 'self'; ` +
              `object-src 'none'`,
          },
        ],
      },
    ];
  },
};

export default withSentryConfig(nextConfig, {
  silent: true,
  org: process.env.SENTRY_ORG,
  project: process.env.SENTRY_PROJECT,
  authToken: process.env.SENTRY_AUTH_TOKEN,
});
