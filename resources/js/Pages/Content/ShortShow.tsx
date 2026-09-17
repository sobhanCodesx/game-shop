import { Head } from "@inertiajs/react";
import type { ComponentProps } from "react";

import Show from "./Show";

type Props = ComponentProps<typeof Show>;

export default function ShortShow(props: Props) {
    return (
        <>
            <Head>
                <style>{`
                    .playnexus-watch-page div.relative.bg-black.mx-auto {
                        width: 100%;
                    }
                `}</style>
            </Head>
            <Show {...props} />
        </>
    );
}
