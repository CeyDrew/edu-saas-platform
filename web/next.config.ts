import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  devIndicators: false,
  // Every storefront page depends on the brand (request host), so pages render per request.
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
