import path from "path"
import tailwindcss from "@tailwindcss/vite"
import react, { reactCompilerPreset } from '@vitejs/plugin-react'
import babel from '@rolldown/plugin-babel'
import { defineConfig, loadEnv } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "")
  // The nginx gateway exposes every service under /api/*.
  const apiTarget = env.VITE_API_PROXY_TARGET || "http://127.0.0.1:8081"
  // Optional direct product-service target for host-only development. When it
  // is unset, /api/product/* goes through the gateway, which rewrites /api/v1.
  const productsTarget = env.VITE_PRODUCTS_API_URL
  const proxy = {
    ...(productsTarget
      ? {
          "/api/product": {
            target: productsTarget,
            changeOrigin: true,
            rewrite: (requestPath: string) =>
              requestPath.replace(/^\/api\/product/, "/api/v1"),
          },
        }
      : {}),
    "/api": { target: apiTarget, changeOrigin: true },
  }

  return {
    plugins: [
      react(),
      babel({ presets: [reactCompilerPreset()] }),
      tailwindcss()
    ],
    resolve: {
      alias: {
        "@": path.resolve(import.meta.dirname, "./src"),
      },
    },
    server: { proxy },
    preview: { proxy },
  }
})
