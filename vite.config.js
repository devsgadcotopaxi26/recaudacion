import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
  plugins: [
    laravel({
      input: ["resources/css/app.css", "resources/js/app.js"],
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ],
  resolve: {
    alias: {
      "@": "/resources/js",
    },
  },
  // Solo para `npm run dev`: IPv4 explícito. Por defecto Vite usa [::1] en
  // Windows y la CSP (SecurityHeaders) no admite IPv6 literales como origen.
  server: {
    host: "127.0.0.1",
  },
});
