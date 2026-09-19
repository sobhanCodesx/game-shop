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
                // React/Inertia uses Vite HMR for frontend changes. Disable
                // Laravel's full-page reload watcher entirely so local file
                // writes can never create a browser reload loop.
                refresh: false,
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

            // Let Vite infer the HMR host from the page URL. Hard-coding
            // localhost breaks when the app is opened through 127.0.0.1,
            // a .test domain, LAN IP, or another local hostname.
            cors: {
                origin: ["http://localhost:8000", "http://127.0.0.1:8000"],
            },

            watch: {
                ignored: ["**/storage/framework/views/**"],
            },
        },
    };
});
