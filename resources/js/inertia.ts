import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import type { ComponentType } from "react";

const appName = "پلی نکسوس";

type PageModule = { default: ComponentType };
type PageSeoProps = { seo?: { absoluteTitle?: boolean } };
export type InertiaPageModules = Record<
    string,
    () => Promise<PageModule>
>;

export function inertiaTitle(
    title: string,
    page: { props: Record<string, unknown> },
): string {
    if ((page.props as PageSeoProps).seo?.absoluteTitle) {
        return title || appName;
    }

    return title ? `${title} - ${appName}` : appName;
}

export function createInertiaPageResolver(pages: InertiaPageModules) {
    return async (name: string): Promise<ComponentType> => {
        const page = await resolvePageComponent<PageModule>(
            `./Pages/${name}.tsx`,
            pages,
        );

        return page.default;
    };
}
