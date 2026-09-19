import { defineConfig } from "vite";
import inertia from "@inertiajs/vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";

export default defineConfig(({ isSsrBuild }) => {
    return {
        plugins: [
            laravel({
                input: "resources/js/app.tsx",
                ssr: "resources/js/ssr.tsx",
                // Inertia/React already handles component HMR. A broad Laravel
                // full-reload watcher can turn unrelated route/view writes into
                // hard browser refreshes during local development.
                refresh: [
                    {
                        paths: ["resources/views/**"],
                        config: { delay: 250 },
                    },
                ],
            }),
            inertia({
                ssr: {
                    entry: "resources/js/ssr.tsx",
                    sourcemap: false,
                },
            }),
            react(),
            tailwindcss(),
        ],

        // The SSR output is one self-contained ESM file. Production only needs
        // Node.js and bootstrap/ssr/ssr.js; node_modules is not required.
        ssr: isSsrBuild ? { noExternal: true } : undefined,
        build: isSsrBuild
            ? {
                  sourcemap: false,
                  ssrManifest: false,
                  rollupOptions: {
                      output: {
                          entryFileNames: "ssr.js",
                          inlineDynamicImports: true,
                      },
                  },
              }
            : undefined,

        server: {
            host: "0.0.0.0",
            port: 5173,
            strictPort: true,

            hmr: {
                host: "localhost",
                port: 5173,
            },

            cors: {
                origin: ["http://localhost:8000", "http://127.0.0.1:8000"],
            },

            watch: {
                ignored: ["**/storage/framework/views/**"],
            },
        },
    };
});
