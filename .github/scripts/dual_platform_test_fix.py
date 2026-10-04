from pathlib import Path

path = Path('tests/Feature/DigitalCommerceTest.php')
text = path.read_text()
old = "->where('products.data.0.platform_name', 'PS5 / PS4')"
new = "->where('products.data.0.platform.name', 'PS5 / PS4')"
if old not in text:
    raise SystemExit('dual platform index assertion anchor missing')
path.write_text(text.replace(old, new, 1))
