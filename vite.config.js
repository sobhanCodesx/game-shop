import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";

export default defineConfig(async ({ command, isSsrBuild }) => {
    const isBuild = command === "build";
    const plugins = [
        laravel({
            input: "resources/js/app.tsx",
            // Never register an SSR entry while Vite is serving locally.
            // This keeps npm run dev strictly client-only.
            ...(isBuild ? { ssr: "resources/js/ssr.tsx" } : {}),
            // React/Inertia uses Vite HMR for frontend changes. Disable
            // Laravel's full-page reload watcher entirely so local file
            // writes can never create a browser reload loop.
            refresh: false,
        }),
        react(),
        tailwindcss(),
    ];

    if (isBuild) {
        // Do not even import the Inertia SSR Vite plugin during local serve.
        // Besides preventing the dev SSR endpoint, this keeps its module graph
        // out of Vite's cold-start path.
        const { default: inertia } = await import("@inertiajs/vite");
        plugins.splice(
            1,
            0,
            inertia({
                ssr: {
                    entry: "resources/js/ssr.tsx",
                    sourcemap: false,
                },
            }),
        );
    }

    return {
        plugins,

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
            // Do not use 0.0.0.0 as the browser-facing Vite URL. The Laravel
            // Vite plugin writes this URL to public/hot and browsers cannot
            // request http://0.0.0.0:5173.
            host: "127.0.0.1",
            port: 5173,
            strictPort: true,

            hmr: {
                host: "127.0.0.1",
                port: 5173,
            },
            cors: {
                origin: ["http://localhost:8000", "http://127.0.0.1:8000"],
            },

            watch: {
                ignored: [
                    "**/storage/**",
                    "**/.playnexus/**",
                    "**/bootstrap/ssr/**",
                    "**/tests/**",
                ],
            },
        },
    };
});
