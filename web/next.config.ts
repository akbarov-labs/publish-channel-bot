import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Emits a minimal server bundle so the runtime image stays small.
  output: "standalone",
  /* config options here */
};

export default nextConfig;
