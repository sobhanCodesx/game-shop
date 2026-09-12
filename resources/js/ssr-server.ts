import { Server, type IncomingMessage } from "node:http";

const localSsrPort = 13714;
const passengerBasePath = "/__inertia_ssr";

type PassengerGlobal = typeof globalThis & {
    PhusionPassenger?: unknown;
};

const isPassenger = (): boolean =>
    typeof (globalThis as PassengerGlobal).PhusionPassenger !== "undefined";

const runtimePort = (): number => {
    if (isPassenger()) return 0;

    const value = process.env.INERTIA_SSR_PORT ?? process.env.PORT;
    if (value === undefined || value === "") return localSsrPort;

    const port = Number.parseInt(value, 10);
    if (!Number.isInteger(port) || port < 1 || port > 65535) {
        throw new Error(
            "INERTIA_SSR_PORT or PORT must be an integer between 1 and 65535.",
        );
    }

    return port;
};

export const resolveSsrServerOptions = (): {
    host: string;
    port: number;
} => ({
    host: "127.0.0.1",
    // Passenger replaces the first listen() call with its own Unix socket.
    // Port 0 avoids a production dependency on a preselected TCP port.
    port: runtimePort(),
});

export const installPassengerBasePathSupport = (): void => {
    const originalEmit = Server.prototype.emit;

    Server.prototype.emit = function (
        event: string | symbol,
        ...args: unknown[]
    ): boolean {
        if (event === "request") {
            const request = args[0] as IncomingMessage | undefined;
            const url = request?.url;

            if (
                request &&
                url &&
                (url === passengerBasePath ||
                    url.startsWith(`${passengerBasePath}/`))
            ) {
                request.url = url.slice(passengerBasePath.length) || "/";
            }
        }

        return originalEmit.call(this, event, ...args);
    };
};
