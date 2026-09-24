import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

// Proxy /api vers le backend Laravel en dev → évite les soucis CORS et
// permet de tester avec `php artisan serve` sans config supplémentaire.
export default defineConfig({
  plugins: [react()],
  server: { port: 5173, proxy: { "/api": "http://localhost:8000" } },
  build: { target: "es2020", sourcemap: false },
});
