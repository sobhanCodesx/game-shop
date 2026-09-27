import type { ComponentType } from "react";

const appName = "پلی نکسوس";

type PageModule = { default: ComponentType };
type PageSeoProps = { seo?: { absoluteTitle?: boolean } };
export type InertiaPageModules = Record<string, () => Promise<PageModule>>;

export function inertiaTitle(
    title: string,
    page: { props: Record<string, unknown> },
): string {
    if ((page.props as PageSeoProps).seo?.absoluteTitle) {
        return title || appName;
    }

    return title ? `${title} - ${appName}` : appName;
}

export function createInertiaPageResolver(
    pages: InertiaPageModules,
    modulePages: InertiaPageModules = {},
) {
    return async (name: string): Promise<ComponentType> => {
        const suffix = `/Template/Pages/${name}.tsx`;
        const moduleMatch = Object.entries(modulePages).find(([path]) =>
            path.endsWith(suffix),
        );

        if (moduleMatch) {
            return (await moduleMatch[1]()).default;
        }

        const core = pages[`./Pages/${name}.tsx`];

        if (core) {
            return (await core()).default;
        }

        throw new Error(`Inertia page [${name}] was not found.`);
    };
}
