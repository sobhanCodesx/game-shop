from pathlib import Path


def load(path):
    return Path(path).read_text()


def save(path, text):
    Path(path).write_text(text)


# Public digital index uses DigitalStoreController::productPayload rather than
# StorefrontDataService, so expose the same top-level platform_name there too.
path = 'app/Http/Controllers/DigitalStoreController.php'
text = load(path)
anchor = """            'platform' => $product->platform ? [\n"""
replacement = """            'platform_name' => $product->platform?->name,\n            'platform' => $product->platform ? [\n"""
if "'platform_name' => $product->platform?->name" not in text:
    if anchor not in text:
        raise SystemExit('DigitalStoreController platform payload anchor missing')
    text = text.replace(anchor, replacement, 1)
save(path, text)

# Keep the custom /digital card aligned with the public DTO contract and retain
# the nested relation fallback for older cached responses.
path = 'resources/js/Pages/Digital/Index.tsx'
text = load(path)
old = """                                                {product.platform?.name && (\n                                                    <span className=\"absolute bottom-2 left-2 rounded-lg bg-black/70 px-2 py-1 text-[9px] font-black text-white\">\n                                                        {product.platform.name}\n                                                    </span>\n                                                )}\n"""
new = """                                                {(product.platform_name ?? product.platform?.name) && (\n                                                    <span className=\"absolute bottom-2 left-2 rounded-lg bg-black/70 px-2 py-1 text-[9px] font-black text-white\">\n                                                        {product.platform_name ?? product.platform?.name}\n                                                    </span>\n                                                )}\n"""
if old in text:
    text = text.replace(old, new, 1)
elif 'product.platform_name ?? product.platform?.name' not in text:
    raise SystemExit('Digital index platform badge anchor missing')
save(path, text)

print('dual platform follow-up fix applied')
