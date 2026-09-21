import type { ComponentType } from "react";

import type { StorefrontProduct } from "../../../types";
import type { NavigationCategory } from "../../Storefront/Navigation/types";
import DualSpotlightHero, {
    type DualSpotlightContentItem,
} from "../DualSpotlightHero";
import StorefrontCommerceHero from "../StorefrontCommerceHero";

export type HomeTemplateContentItem = DualSpotlightContentItem;

type ProductRailDensity = "regular" | "dense";
type TemplatePlacement = "default" | "template_top" | "after_fresh" | "none";
type CampaignPlacement = "legacy" | "after_template";

export interface HomeTemplateRuntime {
    usesTemplateHero: boolean;
    productRailDensity: ProductRailDensity;
    featuredPlacement: TemplatePlacement;
    latestPlacement: TemplatePlacement;
    campaignPlacement: CampaignPlacement;
}

interface HomeTemplateHeroProps {
    heading: string;
    categories: NavigationCategory[];
    contentItems: HomeTemplateContentItem[];
    featuredProducts: StorefrontProduct[];
    latestProducts: StorefrontProduct[];
}

type TemplateHero = ComponentType<HomeTemplateHeroProps>;

const runtimeRegistry: Record<string, HomeTemplateRuntime> = {
    default: {
        usesTemplateHero: false,
        productRailDensity: "regular",
        featuredPlacement: "default",
        latestPlacement: "default",
        campaignPlacement: "legacy",
    },
    dual_spotlight: {
        usesTemplateHero: true,
        productRailDensity: "regular",
        featuredPlacement: "template_top",
        latestPlacement: "after_fresh",
        campaignPlacement: "after_template",
    },
    storefront: {
        usesTemplateHero: true,
        productRailDensity: "dense",
        featuredPlacement: "template_top",
        latestPlacement: "template_top",
        campaignPlacement: "after_template",
    },
};

const heroRegistry: Record<string, TemplateHero> = {
    dual_spotlight: ({
        heading,
        contentItems,
        featuredProducts,
        latestProducts,
    }) => (
        <DualSpotlightHero
            contentItems={contentItems}
            heading={heading}
            products={
                featuredProducts.length > 0
                    ? featuredProducts
                    : latestProducts
            }
        />
    ),
    storefront: ({
        heading,
        categories,
        contentItems,
        featuredProducts,
        latestProducts,
    }) => (
        <StorefrontCommerceHero
            categories={categories}
            contentItems={contentItems}
            heading={heading}
            latestProducts={latestProducts}
            products={featuredProducts}
        />
    ),
};

export function resolveHomeTemplateRuntime(
    templateKey: string,
): HomeTemplateRuntime {
    return runtimeRegistry[templateKey] ?? runtimeRegistry.default;
}

export function HomeTemplateHero({
    templateKey,
    ...props
}: HomeTemplateHeroProps & { templateKey: string }) {
    const Renderer = heroRegistry[templateKey];

    return Renderer ? <Renderer {...props} /> : null;
}

/**
 * Adding a new Home template:
 * 1. Build its responsive hero/template component.
 * 2. Register its renderer in heroRegistry.
 * 3. Add one runtime entry describing section placement/density.
 * 4. Mark it available in config/home-experience.php after QA.
 *
 * Unknown keys always fall back to the default runtime, so an incomplete
 * deployment cannot break Home rendering.
 */
