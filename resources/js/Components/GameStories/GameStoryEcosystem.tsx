import { Link } from "@inertiajs/react";
import { ArrowLeft, BookOpen, Building2, Gamepad2, Layers3, ShoppingBag } from "lucide-react";

export interface GameStoryEcosystemData {
    game: { id: number; name: string; url: string; image_url: string | null };
    studio: { id: number; name: string; url: string; image_url: string | null } | null;
    products: Array<{
        id: number;
        title: string;
        url: string;
        image_url: string | null;
        type: "digital" | "physical";
    }>;
    products_url: string;
}

/** Grounded links only: game_id + active studio + publicly visible products. */
export default function GameStoryEcosystem({ data }: { data: GameStoryEcosystemData }) {
    const { game, studio, products } = data;
    return (
        <section className="gs-ecosystem" aria-labelledby="gs-ecosystem-title" id="game-world-links">
            <div className="gs-ecosystem-heading">
                <span className="gs-ecosystem-eyebrow">BEYOND THE PAGES · PLAYNEXUS UNIVERSE</span>
                <h2 id="gs-ecosystem-title">این داستان، بخشی از یک جهان بزرگ‌تر است</h2>
                <p>
                    برای شناخت کامل‌تر جهان <Link href={game.url}>{game.name}</Link> از کانال بازی شروع کن.
                    {studio && <> این بازی در مجموعهٔ آثار <Link href={studio.url}>{studio.name}</Link> قرار دارد.</>}
                    {products.length > 0 && <> محصولات مرتبط با همین بازی هم در بخش فروشگاه قرار گرفته‌اند.</>}
                </p>
            </div>
            <div className={"gs-ecosystem-entities " + (!studio ? "gs-ecosystem-one" : "")}>
                <Link className="gs-entity" href={game.url}>
                    <span className="gs-entity-media">{game.image_url ? <img src={game.image_url} loading="lazy" alt={"کاور بازی " + game.name} /> : <Gamepad2 size={30}/>}</span>
                    <span className="gs-entity-copy"><small><Gamepad2 size={13}/> کانال اصلی بازی</small><strong>{game.name}</strong><span>اخبار، فید، ویدئو و محتوای مرتبط با همین جهان</span></span>
                    <ArrowLeft className="gs-entity-arrow" size={18} aria-hidden="true" />
                </Link>
                {studio && <Link className="gs-entity" href={studio.url}>
                    <span className="gs-entity-media">{studio.image_url ? <img src={studio.image_url} loading="lazy" alt={"لوگوی استودیوی " + studio.name} /> : <Building2 size={30}/>}</span>
                    <span className="gs-entity-copy"><small><Building2 size={13}/> استودیوی مرتبط</small><strong>{studio.name}</strong><span>آشنایی با استودیو و دیگر بازی‌های ثبت‌شدهٔ آن</span></span>
                    <ArrowLeft className="gs-entity-arrow" size={18} aria-hidden="true" />
                </Link>}
            </div>
            {products.length > 0 && (
                <div className="gs-ecosystem-products">
                    <div className="gs-ecosystem-products-title">
                        <div><span>FROM THE GAME TO THE STORE</span><h3><ShoppingBag size={19}/> محصولات مرتبط با {game.name}</h3></div>
                        <Link href={data.products_url}>اکانت‌های دیجیتال این بازی <ArrowLeft size={16}/></Link>
                    </div>
                    <div className="gs-ecosystem-products-grid">
                        {products.map(product => (
                            <Link className="gs-ecosystem-product" href={product.url} key={product.type + "-" + product.id}>
                                <span className="gs-ecosystem-product-image">
                                    {product.image_url ? <img src={product.image_url} loading="lazy" alt={"تصویر محصول " + product.title} /> : <Layers3 size={27}/>}
                                </span>
                                <span className="gs-ecosystem-product-info">
                                    <small>{product.type === "digital" ? "اکانت دیجیتال" : "محصول فیزیکی"}</small>
                                    <strong>{product.title}</strong>
                                    <span>مشاهده جزئیات <ArrowLeft size={13}/></span>
                                </span>
                            </Link>
                        ))}
                    </div>
                </div>
            )}
            <div className="gs-ecosystem-foot"><BookOpen size={16}/><span>همهٔ این پیوندها بر اساس بازی مرتبطِ ثبت‌شده در PlayNexus انتخاب شده‌اند.</span></div>
        </section>
    );
}
