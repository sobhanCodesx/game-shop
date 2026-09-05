<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Platform;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMedia;
use App\Models\ProductType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\MediaStorage;
use Illuminate\Support\Str;

class StorefrontCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $playstation = $this->category('پلی‌استیشن', 'playstation');
        $games = $this->category('بازی‌ها', 'games');
        $ps5Games = $this->category('بازی‌های PS5', 'ps5-games', $games->id);
        $consoles = $this->category('کنسول‌ها', 'consoles', $playstation->id);
        $accessories = $this->category('تجهیزات گیمینگ', 'gaming-accessories', $playstation->id);
        $accounts = $this->category('اکانت ظرفیتی', 'capacity-accounts', $games->id);

        $ps5 = Platform::withTrashed()->updateOrCreate(['slug' => 'ps5'], ['name' => 'PlayStation 5', 'manufacturer' => 'Sony', 'status' => 'active', 'sort_order' => 1]);

        foreach ($this->products() as $index => $item) {
            $category = match ($item['kind']) {
                'console' => $consoles, 'accessory' => $accessories, 'account' => $accounts, default => $ps5Games,
            };
            $typeSlug = match ($item['kind']) {
                'console' => 'console', 'accessory' => $item['type'], 'account' => 'capacity_account', default => 'physical_game',
            };
            $type = ProductType::query()->where('slug', $typeSlug)->firstOrFail();
            $brand = Brand::withTrashed()->updateOrCreate(['slug' => $item['brand_slug']], ['name' => $item['brand'], 'status' => 'active']);
            $product = Product::withTrashed()->updateOrCreate(['slug' => $item['slug']], [
                'category_id' => $category->id, 'brand_id' => $brand->id, 'product_type_id' => $type->id,
                'title' => $item['title'], 'sku' => 'NP-'.strtoupper(Str::replace('-', '', $item['slug'])),
                'short_description' => $item['description'], 'description' => $item['description'],
                'product_type' => $typeSlug, 'price' => $item['price'], 'discount_price' => $item['discount'] ?? null,
                'stock' => $item['stock'], 'reserved_stock' => 0, 'sold_stock' => max(4, 110 - ($index * 3)),
                'low_stock_threshold' => 3, 'availability' => $item['stock'] > 0 ? 'in_stock' : 'out_of_stock',
                'release_date' => $item['release'], 'status' => 'published', 'visibility' => 'public',
                'featured' => $index < 8, 'condition' => 'new', 'requires_shipping' => $item['kind'] !== 'account',
                'delivery_method' => $item['kind'] === 'account' ? 'digital' : 'shipping', 'minimum_quantity' => 1,
                'maximum_quantity' => 3, 'show_stock' => true, 'published_at' => now()->subDays(30 - min($index, 29)),
                'seo_title' => $item['title'], 'seo_description' => $item['description'],
            ]);
            $product->restore();
            $product->platforms()->syncWithoutDetaching([$ps5->id]);

            if ($item['kind'] === 'account') {
                $this->capacityVariants($product, $item['price']);
            }

            $this->content($product, $category, $item);
            $this->cover($product, $item['wiki']);
        }

        $this->enrichExistingProducts();
    }

    private function enrichExistingProducts(): void
    {
        Product::query()
            ->with(['category', 'coverMedia'])
            ->doesntHave('attributeValues')
            ->each(function (Product $product): void {
                if (! $product->category) {
                    return;
                }

                $kind = match (true) {
                    $product->product_type === 'capacity_account' => 'account',
                    $product->product_type === 'console' => 'console',
                    in_array($product->product_type, ['controller', 'headset', 'gaming_accessory'], true) => 'accessory',
                    default => 'game',
                };

                $this->content($product, $product->category, [
                    'kind' => $kind,
                    'type' => $product->product_type ?: 'physical_game',
                    'description' => $product->short_description ?: "اطلاعات کامل {$product->title} برای بررسی و خرید مطمئن.",
                ]);
            });

        Product::query()
            ->with(['category', 'coverMedia'])
            ->doesntHave('media')
            ->each(function (Product $product): void {
                $source = Product::query()
                    ->whereKeyNot($product->id)
                    ->where(function ($query) use ($product): void {
                        $query->where('category_id', $product->category_id)
                            ->orWhere('product_type', $product->product_type);
                    })
                    ->whereHas('coverMedia')
                    ->with('coverMedia')
                    ->first();

                if ($source?->coverMedia) {
                    ProductMedia::query()->create([
                        'product_id' => $product->id,
                        'type' => 'image',
                        'path' => $source->coverMedia->path,
                        'alt' => "کاور {$product->title}",
                        'sort_order' => 0,
                        'is_primary' => true,
                    ]);
                }
            });
    }

    private function content(Product $product, Category $category, array $item): void
    {
        $details = $this->catalogContent()[$product->slug] ?? [
            'نوع محصول' => $item['type'],
            'وضعیت' => 'نو و آکبند',
            'پلتفرم' => 'PlayStation 5',
        ];

        $summary = $item['description'];
        $specificationText = collect($details)
            ->map(fn (string $value, string $name) => "{$name}: {$value}")
            ->implode('، ');

        $product->update([
            'short_description' => $summary,
            'description' => "{$summary}\n\nاین محصول برای کاربران پلی‌استیشن ۵ آماده شده و اطلاعات نسخه، شیوه استفاده و ویژگی‌های اصلی آن پیش از خرید در همین صفحه در دسترس است. {$specificationText}.\n\nتمام سفارش‌ها پس از ثبت بررسی می‌شوند. محصولات فیزیکی با بسته‌بندی مناسب ارسال می‌شوند و محصولات دیجیتال مطابق راهنمای تحویل سفارش در اختیار خریدار قرار می‌گیرند.",
            'purchase_notes' => $item['kind'] === 'account'
                ? 'پیش از خرید، راهنمای ظرفیت انتخابی را مطالعه کنید؛ روش فعال‌سازی هر ظرفیت متفاوت است.'
                : 'پلتفرم، نسخه و سازگاری محصول را پیش از ثبت سفارش بررسی کنید.',
            'delivery_notes' => $item['kind'] === 'account'
                ? 'اطلاعات فعال‌سازی پس از تأیید سفارش به‌صورت دیجیتال تحویل می‌شود.'
                : 'ارسال فیزیکی با بسته‌بندی محافظ و امکان پیگیری سفارش انجام می‌شود.',
            'return_policy' => $item['kind'] === 'account'
                ? 'به‌دلیل ماهیت دیجیتال محصول، پس از تحویل اطلاعات فعال‌سازی امکان مرجوعی وجود ندارد.'
                : 'مرجوعی کالای فیزیکی مطابق شرایط سلامت بسته‌بندی و قوانین فروشگاه انجام می‌شود.',
            'warranty' => $item['kind'] === 'account' ? 'ضمانت صحت اطلاعات هنگام تحویل' : 'ضمانت اصالت و سلامت فیزیکی',
            'tags' => array_values(array_unique([$item['kind'], $item['type'], 'ps5', ...array_values($details)])),
            'seo_description' => $summary.' مشخصات کامل، قیمت روز و وضعیت موجودی را در پلی نکسوس ببینید.',
            'seo_keywords' => implode('، ', [$product->title, 'خرید بازی PS5', ...array_values($details)]),
        ]);

        foreach ($details as $slug => $value) {
            $attribute = CategoryAttribute::query()->updateOrCreate(
                ['category_id' => $category->id, 'slug' => Str::slug($slug) ?: md5($slug)],
                [
                    'name' => $slug,
                    'type' => 'text',
                    'is_required' => false,
                    'is_filterable' => true,
                    'sort_order' => array_search($slug, array_keys($details), true) + 1,
                ],
            );

            ProductAttributeValue::query()->updateOrCreate(
                ['product_id' => $product->id, 'category_attribute_id' => $attribute->id],
                ['value' => $value],
            );
        }
    }

    private function catalogContent(): array
    {
        return [
            'marvel-spider-man-2-ps5' => ['ژانر' => 'اکشن ماجراجویی', 'حالت بازی' => 'تک‌نفره', 'رده سنی' => '+۱۶', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'تعویض آزاد میان پیتر پارکر و مایلز مورالز'],
            'god-of-war-ragnarok-ps5' => ['ژانر' => 'اکشن ماجراجویی', 'حالت بازی' => 'تک‌نفره', 'رده سنی' => '+۱۸', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'نبرد و روایت سینمایی در قلمروهای نورس'],
            'horizon-forbidden-west-ps5' => ['ژانر' => 'اکشن نقش‌آفرینی جهان‌باز', 'حالت بازی' => 'تک‌نفره', 'رده سنی' => '+۱۶', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'کاوش جهان وسیع و نبرد با ماشین‌ها'],
            'gran-turismo-7-ps5' => ['ژانر' => 'شبیه‌ساز رانندگی', 'حالت بازی' => 'تک‌نفره و آنلاین', 'رده سنی' => '+۳', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'صدها خودرو و پیست با تنظیمات حرفه‌ای'],
            'demons-souls-ps5' => ['ژانر' => 'اکشن نقش‌آفرینی', 'حالت بازی' => 'تک‌نفره و آنلاین', 'رده سنی' => '+۱۸', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'بازسازی کامل با مبارزات چالش‌برانگیز'],
            'ratchet-clank-rift-apart-ps5' => ['ژانر' => 'اکشن پلتفرمر', 'حالت بازی' => 'تک‌نفره', 'رده سنی' => '+۷', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'جابجایی سریع میان دنیاها با SSD کنسول'],
            'final-fantasy-vii-rebirth-ps5' => ['ژانر' => 'نقش‌آفرینی اکشن', 'حالت بازی' => 'تک‌نفره', 'رده سنی' => '+۱۶', 'نسخه' => 'نسخه دو دیسک', 'ویژگی شاخص' => 'جهان گسترده و سیستم مبارزه ترکیبی'],
            'stellar-blade-ps5' => ['ژانر' => 'اکشن ماجراجویی', 'حالت بازی' => 'تک‌نفره', 'رده سنی' => '+۱۸', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'مبارزات سریع و باس‌فایت‌های سینمایی'],
            'the-last-of-us-part-i-ps5' => ['ژانر' => 'اکشن ماجراجویی', 'حالت بازی' => 'تک‌نفره', 'رده سنی' => '+۱۸', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'بازسازی کامل داستان جوئل و الی'],
            'ghost-of-tsushima-directors-cut-ps5' => ['ژانر' => 'اکشن ماجراجویی جهان‌باز', 'حالت بازی' => 'تک‌نفره و آنلاین', 'رده سنی' => '+۱۸', 'نسخه' => 'Director’s Cut', 'ویژگی شاخص' => 'شامل جزیره Iki و حالت Legends'],
            'returnal-ps5' => ['ژانر' => 'شوتر سوم‌شخص روگ‌لایک', 'حالت بازی' => 'تک‌نفره و همکاری آنلاین', 'رده سنی' => '+۱۶', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'چرخه‌های پویا و بازخورد لمسی DualSense'],
            'helldivers-2-ps5' => ['ژانر' => 'شوتر همکاری‌محور', 'حالت بازی' => 'آنلاین ۱ تا ۴ نفره', 'رده سنی' => '+۱۸', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'مأموریت‌های گروهی و محتوای زنده'],
            'ea-sports-fc-25-capacity-ps5' => ['ژانر' => 'ورزشی / فوتبال', 'حالت بازی' => 'تک‌نفره و آنلاین', 'رده سنی' => '+۳', 'نوع تحویل' => 'اکانت دیجیتال ظرفیتی', 'زبان' => 'انگلیسی'],
            'call-of-duty-black-ops-6-capacity-ps5' => ['ژانر' => 'شوتر اول‌شخص', 'حالت بازی' => 'کمپین و آنلاین', 'رده سنی' => '+۱۸', 'نوع تحویل' => 'اکانت دیجیتال ظرفیتی', 'زبان' => 'انگلیسی'],
            'tekken-8-capacity-ps5' => ['ژانر' => 'مبارزه‌ای', 'حالت بازی' => 'تک‌نفره و آنلاین', 'رده سنی' => '+۱۶', 'نوع تحویل' => 'اکانت دیجیتال ظرفیتی', 'زبان' => 'انگلیسی'],
            'resident-evil-4-remake-ps5' => ['ژانر' => 'ترس و بقا', 'حالت بازی' => 'تک‌نفره', 'رده سنی' => '+۱۸', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'بازسازی مدرن با مبارزات و روایت ارتقایافته'],
            'cyberpunk-2077-ultimate-ps5' => ['ژانر' => 'نقش‌آفرینی جهان‌باز', 'حالت بازی' => 'تک‌نفره', 'رده سنی' => '+۱۸', 'نسخه' => 'Ultimate Edition', 'ویژگی شاخص' => 'شامل بسته الحاقی Phantom Liberty'],
            'elden-ring-ps5' => ['ژانر' => 'اکشن نقش‌آفرینی جهان‌باز', 'حالت بازی' => 'تک‌نفره و آنلاین', 'رده سنی' => '+۱۶', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'اکتشاف آزاد و مبارزات عمیق'],
            'hogwarts-legacy-ps5' => ['ژانر' => 'اکشن نقش‌آفرینی جهان‌باز', 'حالت بازی' => 'تک‌نفره', 'رده سنی' => '+۱۲', 'نسخه' => 'دیسک استاندارد', 'ویژگی شاخص' => 'کاوش هاگوارتز و دنیای جادوگری'],
            'playstation-5-slim-standard' => ['مدل' => 'CFI-2000 Slim', 'حافظه داخلی' => '۱ ترابایت SSD', 'درایو' => 'Ultra HD Blu-ray', 'خروجی تصویر' => 'تا 4K / 120Hz', 'اقلام اصلی' => 'کنسول، کنترلر DualSense و کابل‌ها'],
            'dualsense-wireless-controller-white' => ['رنگ' => 'سفید', 'اتصال' => 'Bluetooth و USB-C', 'باتری' => 'داخلی قابل شارژ', 'ویژگی شاخص' => 'بازخورد لمسی و تریگرهای تطبیقی', 'سازگاری' => 'PS5 و رایانه شخصی'],
            'pulse-3d-wireless-headset' => ['رنگ' => 'سفید', 'اتصال' => 'دانگل بی‌سیم و جک ۳.۵ میلی‌متری', 'میکروفن' => 'دو میکروفن داخلی', 'ویژگی شاخص' => 'بهینه‌شده برای صدای سه‌بعدی', 'سازگاری' => 'PS5، PS4 و رایانه شخصی'],
            'playstation-portal-remote-player' => ['نمایشگر' => 'LCD هشت اینچی 1080p', 'نرخ تصویر' => 'تا ۶۰ فریم بر ثانیه', 'اتصال' => 'Wi-Fi', 'کنترل‌ها' => 'ویژگی‌های اصلی DualSense', 'کاربری' => 'Remote Play از کنسول PS5'],
            'dualsense-charging-station' => ['ظرفیت شارژ' => 'دو کنترلر هم‌زمان', 'اتصال کنترلر' => 'پایه شارژ اختصاصی', 'برق ورودی' => 'آداپتور همراه', 'سازگاری' => 'کنترلر DualSense', 'رنگ' => 'سفید و مشکی'],
        ];
    }

    private function category(string $name, string $slug, ?int $parentId = null): Category
    {
        $category = Category::withTrashed()->updateOrCreate(['slug' => $slug], ['name' => $name, 'parent_id' => $parentId, 'status' => 'active', 'sort_order' => 10]);
        $category->restore();

        return $category;
    }

    private function capacityVariants(Product $product, int $basePrice): void
    {
        foreach ([1 => 0.72, 2 => 1.0, 3 => 0.84] as $capacity => $ratio) {
            $product->variants()->updateOrCreate(['sku' => "{$product->sku}-C{$capacity}"], [
                'name' => "ظرفیت {$capacity}", 'attributes' => ['capacity' => (string) $capacity],
                'price' => (int) round($basePrice * $ratio / 10000) * 10000, 'stock' => 8 + $capacity,
                'status' => 'active',
            ]);
        }
    }

    private function cover(Product $product, string $wikiTitle): void
    {
        if ($product->coverMedia()->exists()) {
            return;
        }

        foreach (['jpg', 'png', 'webp'] as $extension) {
            $localPath = "products/catalog/{$product->slug}.{$extension}";
            if (MediaStorage::disk()->exists($localPath)) {
                ProductMedia::query()->updateOrCreate(['product_id' => $product->id, 'is_primary' => true], [
                    'type' => 'image', 'path' => $localPath, 'alt' => "کاور {$product->title}", 'sort_order' => 0,
                ]);

                return;
            }
        }

        try {
            $url = $this->fallbackImages()[$product->slug] ?? null;
            if (! $url) {
                usleep(1_250_000);
                $response = Http::withUserAgent('NexusPlayCatalogSeeder/1.0 (local development catalog; contact: admin@localhost)')->timeout(30)->retry(3, 3_000)->get('https://en.wikipedia.org/w/api.php', [
                    'action' => 'query', 'format' => 'json', 'redirects' => 1, 'prop' => 'images|pageimages',
                    'imlimit' => 100, 'piprop' => 'thumbnail', 'pithumbsize' => 700, 'titles' => $wikiTitle,
                ])->throw()->json();
                $page = collect(data_get($response, 'query.pages', []))->first();
                $file = collect($page['images'] ?? [])->pluck('title')->first(fn (string $title) => preg_match('/(box|cover|poster|pack|console|controller|headset|portal)/i', $title));
                $url = $file ? 'https://en.wikipedia.org/wiki/Special:Redirect/file/'.rawurlencode(Str::after($file, 'File:')).'?width=700' : data_get($page, 'thumbnail.source');
            }
            if (! $url) {
                return;
            }

            usleep(1_250_000);
            $image = Http::withUserAgent('NexusPlayCatalogSeeder/1.0 (local development catalog; contact: admin@localhost)')->timeout(40)->retry(3, 3_000)->get($url)->throw();
            if (strlen($image->body()) < 2000) {
                return;
            }
            $extension = str_contains($image->header('Content-Type'), 'png') ? 'png' : 'jpg';
            $path = "products/catalog/{$product->slug}.{$extension}";
            MediaStorage::disk()->put($path, $image->body());
            ProductMedia::query()->updateOrCreate(['product_id' => $product->id, 'is_primary' => true], [
                'type' => 'image', 'path' => $path, 'alt' => "کاور {$product->title}", 'sort_order' => 0,
            ]);
        } catch (\Throwable $exception) {
            $this->command?->warn("تصویر {$product->title} دریافت نشد: {$exception->getMessage()}");
        }
    }

    private function fallbackImages(): array
    {
        return [
            'ratchet-clank-rift-apart-ps5' => 'https://shared.fastly.steamstatic.com/store_item_assets/steam/apps/1895880/library_600x900_2x.jpg',
            'ghost-of-tsushima-directors-cut-ps5' => 'https://shared.fastly.steamstatic.com/store_item_assets/steam/apps/2215430/library_600x900_2x.jpg',
            'ea-sports-fc-25-capacity-ps5' => 'https://shared.fastly.steamstatic.com/store_item_assets/steam/apps/2669320/library_600x900_2x.jpg',
            'call-of-duty-black-ops-6-capacity-ps5' => 'https://shared.fastly.steamstatic.com/store_item_assets/steam/apps/1938090/library_600x900_2x.jpg',
            'playstation-5-slim-standard' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/77/Black_and_white_Playstation_5_base_edition_with_controller.png/960px-Black_and_white_Playstation_5_base_edition_with_controller.png',
            'dualsense-wireless-controller-white' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e2/DualSense_Controller_Chroma_Pearl.jpg/960px-DualSense_Controller_Chroma_Pearl.jpg',
            'pulse-3d-wireless-headset' => 'https://image.ceneostatic.pl/data/products/171005971/c5d3e7e2-af52-44a2-a805-9f57f0aad2c9_i-sony-playstation-5-pulse-3d-wireless-headset-elite-white.jpg',
            'playstation-portal-remote-player' => 'https://static.comet.it/b2c/public/products/PSX01926V/6ce8697a947883590fc1d59074fe1f3f-6787e9f1c822f4.11265182-PORTAL_MIDNIGHTBLACK_PR_01_UI_RGB.jpg',
            'dualsense-charging-station' => 'https://static01.galaxus.com/productimages/7/8/4/9/0/1/4/7/2/4/5/6/4/6/7/5/2/8/9/01994a6b-b2b3-7d8c-9fe2-ecc4edd3fbfa_720.jpeg',
        ];
    }

    private function products(): array
    {
        return [
            ['title' => 'Marvel’s Spider-Man 2 برای PS5', 'slug' => 'marvel-spider-man-2-ps5', 'wiki' => "Marvel's Spider-Man 2", 'brand' => 'Insomniac Games', 'brand_slug' => 'insomniac-games', 'kind' => 'game', 'type' => 'physical_game', 'price' => 4_890_000, 'discount' => 4_590_000, 'stock' => 12, 'release' => '2023-10-20', 'description' => 'نسخه فیزیکی بازی مرد عنکبوتی ۲ برای پلی‌استیشن ۵.'],
            ['title' => 'God of War Ragnarök برای PS5', 'slug' => 'god-of-war-ragnarok-ps5', 'wiki' => 'God of War Ragnarök', 'brand' => 'Santa Monica Studio', 'brand_slug' => 'santa-monica-studio', 'kind' => 'game', 'type' => 'physical_game', 'price' => 4_390_000, 'discount' => 3_990_000, 'stock' => 9, 'release' => '2022-11-09', 'description' => 'نسخه PS5 ماجراجویی کریتوس و آترئوس در اساطیر نورس.'],
            ['title' => 'Horizon Forbidden West برای PS5', 'slug' => 'horizon-forbidden-west-ps5', 'wiki' => 'Horizon Forbidden West', 'brand' => 'Guerrilla Games', 'brand_slug' => 'guerrilla-games', 'kind' => 'game', 'type' => 'physical_game', 'price' => 3_790_000, 'discount' => 3_490_000, 'stock' => 11, 'release' => '2022-02-18', 'description' => 'ماجراجویی جهان‌باز الوی در غرب ممنوعه با نسخه مخصوص PS5.'],
            ['title' => 'Gran Turismo 7 برای PS5', 'slug' => 'gran-turismo-7-ps5', 'wiki' => 'Gran Turismo 7', 'brand' => 'Polyphony Digital', 'brand_slug' => 'polyphony-digital', 'kind' => 'game', 'type' => 'physical_game', 'price' => 4_150_000, 'discount' => null, 'stock' => 7, 'release' => '2022-03-04', 'description' => 'شبیه‌ساز رانندگی Gran Turismo 7 با پشتیبانی از ویژگی‌های DualSense.'],
            ['title' => 'Demon’s Souls برای PS5', 'slug' => 'demons-souls-ps5', 'wiki' => "Demon's Souls (2020 video game)", 'brand' => 'Bluepoint Games', 'brand_slug' => 'bluepoint-games', 'kind' => 'game', 'type' => 'physical_game', 'price' => 3_690_000, 'discount' => 3_390_000, 'stock' => 6, 'release' => '2020-11-12', 'description' => 'بازسازی کامل Demon’s Souls برای نسل نهم پلی‌استیشن.'],
            ['title' => 'Ratchet & Clank: Rift Apart برای PS5', 'slug' => 'ratchet-clank-rift-apart-ps5', 'wiki' => 'Ratchet & Clank: Rift Apart', 'brand' => 'Insomniac Games', 'brand_slug' => 'insomniac-games', 'kind' => 'game', 'type' => 'physical_game', 'price' => 3_590_000, 'discount' => null, 'stock' => 8, 'release' => '2021-06-11', 'description' => 'ماجراجویی سریع و رنگارنگ Ratchet و Clank میان دنیاهای مختلف.'],
            ['title' => 'Final Fantasy VII Rebirth برای PS5', 'slug' => 'final-fantasy-vii-rebirth-ps5', 'wiki' => 'Final Fantasy VII Rebirth', 'brand' => 'Square Enix', 'brand_slug' => 'square-enix', 'kind' => 'game', 'type' => 'physical_game', 'price' => 5_290_000, 'discount' => 4_890_000, 'stock' => 10, 'release' => '2024-02-29', 'description' => 'بخش دوم پروژه بازسازی Final Fantasy VII در قالب دو دیسک.'],
            ['title' => 'Stellar Blade برای PS5', 'slug' => 'stellar-blade-ps5', 'wiki' => 'Stellar Blade', 'brand' => 'Shift Up', 'brand_slug' => 'shift-up', 'kind' => 'game', 'type' => 'physical_game', 'price' => 5_150_000, 'discount' => null, 'stock' => 7, 'release' => '2024-04-26', 'description' => 'بازی اکشن ماجراجویی Stellar Blade، انحصاری کنسولی PS5.'],
            ['title' => 'The Last of Us Part I برای PS5', 'slug' => 'the-last-of-us-part-i-ps5', 'wiki' => 'The Last of Us Part I', 'brand' => 'Naughty Dog', 'brand_slug' => 'naughty-dog', 'kind' => 'game', 'type' => 'physical_game', 'price' => 4_490_000, 'discount' => 4_190_000, 'stock' => 9, 'release' => '2022-09-02', 'description' => 'بازسازی نسل نهمی داستان جوئل و الی با گرافیک و دسترسی‌پذیری ارتقایافته.'],
            ['title' => 'Ghost of Tsushima Director’s Cut برای PS5', 'slug' => 'ghost-of-tsushima-directors-cut-ps5', 'wiki' => 'Ghost of Tsushima', 'brand' => 'Sucker Punch Productions', 'brand_slug' => 'sucker-punch', 'kind' => 'game', 'type' => 'physical_game', 'price' => 4_290_000, 'discount' => null, 'stock' => 5, 'release' => '2021-08-20', 'description' => 'نسخه Director’s Cut بازی Ghost of Tsushima همراه جزیره Iki.'],
            ['title' => 'Returnal برای PS5', 'slug' => 'returnal-ps5', 'wiki' => 'Returnal (video game)', 'brand' => 'Housemarque', 'brand_slug' => 'housemarque', 'kind' => 'game', 'type' => 'physical_game', 'price' => 3_850_000, 'discount' => 3_590_000, 'stock' => 6, 'release' => '2021-04-30', 'description' => 'اکشن علمی‌تخیلی Roguelike با استفاده کامل از بازخورد لمسی DualSense.'],
            ['title' => 'Helldivers 2 برای PS5', 'slug' => 'helldivers-2-ps5', 'wiki' => 'Helldivers 2', 'brand' => 'Arrowhead Game Studios', 'brand_slug' => 'arrowhead', 'kind' => 'game', 'type' => 'physical_game', 'price' => 3_990_000, 'discount' => null, 'stock' => 14, 'release' => '2024-02-08', 'description' => 'شوتر همکاری‌محور آنلاین برای دفاع از Super Earth.'],
            ['title' => 'EA Sports FC 25 اکانت ظرفیتی PS5', 'slug' => 'ea-sports-fc-25-capacity-ps5', 'wiki' => 'EA Sports FC 25', 'brand' => 'EA Sports', 'brand_slug' => 'ea-sports', 'kind' => 'account', 'type' => 'capacity_account', 'price' => 2_850_000, 'discount' => null, 'stock' => 30, 'release' => '2024-09-27', 'description' => 'اکانت ظرفیتی EA Sports FC 25 با سه ظرفیت و قیمت مستقل.'],
            ['title' => 'Call of Duty: Black Ops 6 اکانت PS5', 'slug' => 'call-of-duty-black-ops-6-capacity-ps5', 'wiki' => 'Call of Duty: Black Ops 6', 'brand' => 'Activision', 'brand_slug' => 'activision', 'kind' => 'account', 'type' => 'capacity_account', 'price' => 3_650_000, 'discount' => null, 'stock' => 24, 'release' => '2024-10-25', 'description' => 'نسخه دیجیتال ظرفیتی Black Ops 6 برای PS5.'],
            ['title' => 'Tekken 8 اکانت ظرفیتی PS5', 'slug' => 'tekken-8-capacity-ps5', 'wiki' => 'Tekken 8', 'brand' => 'Bandai Namco', 'brand_slug' => 'bandai-namco', 'kind' => 'account', 'type' => 'capacity_account', 'price' => 3_190_000, 'discount' => null, 'stock' => 21, 'release' => '2024-01-26', 'description' => 'نسخه دیجیتال Tekken 8 با انتخاب ظرفیت ۱، ۲ یا ۳.'],
            ['title' => 'Resident Evil 4 برای PS5', 'slug' => 'resident-evil-4-remake-ps5', 'wiki' => 'Resident Evil 4 (2023 video game)', 'brand' => 'Capcom', 'brand_slug' => 'capcom', 'kind' => 'game', 'type' => 'physical_game', 'price' => 4_190_000, 'discount' => 3_890_000, 'stock' => 8, 'release' => '2023-03-24', 'description' => 'بازسازی Resident Evil 4 با گیم‌پلی و گرافیک مدرن.'],
            ['title' => 'Cyberpunk 2077 Ultimate Edition برای PS5', 'slug' => 'cyberpunk-2077-ultimate-ps5', 'wiki' => 'Cyberpunk 2077', 'brand' => 'CD Projekt Red', 'brand_slug' => 'cd-projekt-red', 'kind' => 'game', 'type' => 'physical_game', 'price' => 4_790_000, 'discount' => null, 'stock' => 7, 'release' => '2023-12-05', 'description' => 'نسخه Ultimate بازی Cyberpunk 2077 همراه بسته Phantom Liberty.'],
            ['title' => 'Elden Ring برای PS5', 'slug' => 'elden-ring-ps5', 'wiki' => 'Elden Ring', 'brand' => 'FromSoftware', 'brand_slug' => 'fromsoftware', 'kind' => 'game', 'type' => 'physical_game', 'price' => 4_590_000, 'discount' => 4_290_000, 'stock' => 13, 'release' => '2022-02-25', 'description' => 'نقش‌آفرینی جهان‌باز تحسین‌شده ساخته FromSoftware.'],
            ['title' => 'Hogwarts Legacy برای PS5', 'slug' => 'hogwarts-legacy-ps5', 'wiki' => 'Hogwarts Legacy', 'brand' => 'Warner Bros. Games', 'brand_slug' => 'warner-bros-games', 'kind' => 'game', 'type' => 'physical_game', 'price' => 4_250_000, 'discount' => 3_950_000, 'stock' => 11, 'release' => '2023-02-10', 'description' => 'ماجراجویی جهان‌باز در دنیای جادوگری هاگوارتز.'],
            ['title' => 'کنسول PlayStation 5 Slim استاندارد', 'slug' => 'playstation-5-slim-standard', 'wiki' => 'PlayStation 5', 'brand' => 'Sony', 'brand_slug' => 'sony', 'kind' => 'console', 'type' => 'console', 'price' => 48_900_000, 'discount' => null, 'stock' => 5, 'release' => '2023-11-10', 'description' => 'کنسول PS5 Slim نسخه استاندارد مجهز به درایو دیسک.'],
            ['title' => 'کنترلر بی‌سیم DualSense سفید', 'slug' => 'dualsense-wireless-controller-white', 'wiki' => 'DualSense', 'brand' => 'Sony', 'brand_slug' => 'sony', 'kind' => 'accessory', 'type' => 'controller', 'price' => 5_490_000, 'discount' => 5_190_000, 'stock' => 18, 'release' => '2020-11-12', 'description' => 'کنترلر اصلی DualSense با بازخورد لمسی و تریگرهای تطبیقی.'],
            ['title' => 'هدست بی‌سیم Pulse 3D', 'slug' => 'pulse-3d-wireless-headset', 'wiki' => 'Pulse 3D Wireless Headset', 'brand' => 'Sony', 'brand_slug' => 'sony', 'kind' => 'accessory', 'type' => 'headset', 'price' => 7_250_000, 'discount' => null, 'stock' => 8, 'release' => '2020-11-12', 'description' => 'هدست بی‌سیم رسمی PlayStation با طراحی‌شده برای صدای سه‌بعدی.'],
            ['title' => 'ریموت پلیر PlayStation Portal', 'slug' => 'playstation-portal-remote-player', 'wiki' => 'PlayStation Portal', 'brand' => 'Sony', 'brand_slug' => 'sony', 'kind' => 'accessory', 'type' => 'gaming_accessory', 'price' => 18_900_000, 'discount' => 18_400_000, 'stock' => 4, 'release' => '2023-11-15', 'description' => 'دستگاه Remote Player پلی‌استیشن با نمایشگر ۸ اینچی و کنترل‌های DualSense.'],
            ['title' => 'پایه شارژ DualSense', 'slug' => 'dualsense-charging-station', 'wiki' => 'DualSense', 'brand' => 'Sony', 'brand_slug' => 'sony', 'kind' => 'accessory', 'type' => 'gaming_accessory', 'price' => 3_250_000, 'discount' => null, 'stock' => 10, 'release' => '2020-11-12', 'description' => 'پایه شارژ رسمی برای شارژ هم‌زمان دو کنترلر DualSense.'],
        ];
    }
}
