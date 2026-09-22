const TELEGRAM_ORIGIN = "https://api.telegram.org";
const PLAYNEXUS_WEBHOOK = "https://playnexus.ir/api/telegram/webhook";

function json(data, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: {
      "content-type": "application/json; charset=utf-8",
      "cache-control": "no-store",
    },
  });
}

function unauthorized(message, status = 403) {
  return json({ ok: false, error: message }, status);
}

function readBotToken(request) {
  const auth = request.headers.get("authorization") || "";
  if (!auth.startsWith("Bearer ")) return null;

  const token = auth.slice(7).trim();
  return /^\d{5,20}:[A-Za-z0-9_-]{20,}$/.test(token) ? token : null;
}

function cleanForwardHeaders(request) {
  const headers = new Headers(request.headers);

  headers.delete("authorization");
  headers.delete("x-playnexus-relay-key");
  headers.delete("host");
  headers.delete("cf-connecting-ip");
  headers.delete("cf-ipcountry");
  headers.delete("cf-ray");
  headers.delete("cf-visitor");
  headers.delete("x-forwarded-for");
  headers.delete("x-forwarded-proto");

  return headers;
}

function upstreamRequest(request, url) {
  const init = {
    method: request.method,
    headers: cleanForwardHeaders(request),
    redirect: "manual",
  };

  if (!["GET", "HEAD"].includes(request.method)) {
    init.body = request.body;
  }

  return new Request(url, init);
}

export default {
  async fetch(request, env) {
    const incoming = new URL(request.url);

    // Inbound Telegram webhook relay. Telegram cannot send our private
    // X-PlayNexus-Relay-Key, so this path authenticates with Telegram's
    // X-Telegram-Bot-Api-Secret-Token and lets PlayNexus validate the value.
    if (incoming.pathname === "/webhook") {
      if (request.method !== "POST") {
        return json({ ok: false, error: "method not allowed" }, 405);
      }

      const telegramSecret =
        request.headers.get("x-telegram-bot-api-secret-token") || "";

      if (
        !telegramSecret ||
        !/^[A-Za-z0-9_-]{1,256}$/.test(telegramSecret)
      ) {
        return unauthorized("telegram webhook secret missing or malformed");
      }

      const contentLength = Number(request.headers.get("content-length") || "0");
      if (contentLength > 1024 * 1024) {
        return json({ ok: false, error: "webhook payload too large" }, 413);
      }

      try {
        const headers = cleanForwardHeaders(request);
        headers.set(
          "x-telegram-bot-api-secret-token",
          telegramSecret,
        );
        headers.set("content-type", "application/json");

        const response = await fetch(PLAYNEXUS_WEBHOOK, {
          method: "POST",
          headers,
          body: request.body,
          redirect: "manual",
        });

        return new Response(response.body, {
          status: response.status,
          statusText: response.statusText,
          headers: {
            "content-type":
              response.headers.get("content-type") ||
              "application/json; charset=utf-8",
            "cache-control": "no-store",
          },
        });
      } catch {
        return json(
          { ok: false, error: "playnexus webhook unavailable" },
          502,
        );
      }
    }

    const relayKey = request.headers.get("x-playnexus-relay-key") || "";
    if (!env.RELAY_KEY || relayKey !== env.RELAY_KEY) {
      return unauthorized("relay key rejected");
    }

    const token = readBotToken(request);
    if (!token) {
      return unauthorized("bot token missing or malformed", 401);
    }

    let upstream;

    if (incoming.pathname === "/health") {
      return json({ ok: true, service: "playnexus-telegram-relay" });
    }

    if (incoming.pathname.startsWith("/api/")) {
      const method = incoming.pathname.slice("/api/".length);
      if (!/^[A-Za-z][A-Za-z0-9_]{0,80}$/.test(method)) {
        return json({ ok: false, error: "invalid bot api method" }, 400);
      }

      upstream = new URL(`/bot${token}/${method}`, TELEGRAM_ORIGIN);
    } else if (incoming.pathname.startsWith("/file/")) {
      const filePath = incoming.pathname.slice("/file/".length);
      if (!filePath || filePath.includes("..")) {
        return json({ ok: false, error: "invalid telegram file path" }, 400);
      }

      upstream = new URL(`/file/bot${token}/${filePath}`, TELEGRAM_ORIGIN);
    } else {
      return json({ ok: false, error: "route not found" }, 404);
    }

    upstream.search = incoming.search;

    try {
      const response = await fetch(upstreamRequest(request, upstream.toString()));
      const headers = new Headers(response.headers);
      headers.set("cache-control", "no-store");
      headers.delete("set-cookie");

      return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers,
      });
    } catch {
      return json({ ok: false, error: "telegram upstream unavailable" }, 502);
    }
  },
};
