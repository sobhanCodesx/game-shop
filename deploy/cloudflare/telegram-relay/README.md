# PlayNexus Telegram Relay

این Worker نسخه امن‌تر ایده TelegramByapss است. هدف فقط عبور درخواست‌های Bot API سرور PlayNexus از Cloudflare است.

## چرا نسخه اختصاصی؟

Worker عمومی دیگران برای Bot واقعی مناسب نیست؛ Bot Token کنترل کامل Bot را می‌دهد. این Relay دو لایه دارد:

- `X-PlayNexus-Relay-Key` باید با Secret خود Worker برابر باشد.
- Bot Token در URL عمومی Worker قرار نمی‌گیرد؛ PlayNexus آن را با Authorization header می‌فرستد و Worker URL اصلی Telegram را داخل Cloudflare می‌سازد.

Relay فقط دو route دارد:

- `/api/<TelegramMethod>`
- `/file/<TelegramFilePath>`

و هیچ مقصد دلخواه دیگری را proxy نمی‌کند.

## راه‌اندازی کم‌دردسر با Cloudflare Dashboard

1. Workers & Pages → Create Worker
2. محتوای `worker.js` را جایگزین کن و Deploy بزن.
3. Settings → Variables and Secrets → یک Secret با نام `RELAY_KEY` بساز.
4. در PlayNexus Admin → Telegram Bot:
   - Transport Mode = `Auto` یا `Cloudflare Relay`
   - Relay URL = آدرس Worker
   - Relay Key = همان Secret
5. Test Connection را بزن و بعد Webhook را Sync کن.

## Wrangler

```bash
cp wrangler.toml.example wrangler.toml
npx wrangler secret put RELAY_KEY
npx wrangler deploy
```

## نکته امنیتی

از Worker عمومی TelegramByapss فقط برای تست با Bot غیرحساس استفاده کن. برای Bot ادمین PlayNexus از Worker متعلق به حساب Cloudflare خودت استفاده شود.
