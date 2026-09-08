import { LogIn } from "lucide-react";

export function googleLoginUrl(redirect?: string, remember = false) {
    const parameters = new URLSearchParams();
    if (redirect) parameters.set("redirect", redirect);
    if (remember) parameters.set("remember", "1");
    const query = parameters.toString();

    return `/auth/google${query ? `?${query}` : ""}`;
}

export default function GoogleLoginButton({ redirect, remember = false, compact = false }: { redirect?: string; remember?: boolean; compact?: boolean }) {
    return (
        <a className={`flex w-full items-center justify-center gap-3 rounded-2xl border font-black transition hover:-translate-y-0.5 ${compact ? "h-11 border-slate-200 bg-white px-4 text-xs text-slate-800 shadow-sm hover:border-indigo-300" : "h-14 border-white/10 bg-white/[.06] px-5 text-sm text-white hover:border-white/20 hover:bg-white/[.09]"}`} href={googleLoginUrl(redirect, remember)}>
            <svg aria-hidden="true" className="size-5" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.9h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.4Z" />
                <path fill="#34A853" d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1a5.8 5.8 0 0 1-5.5-4H3.2v2.6A10 10 0 0 0 12 22Z" />
                <path fill="#FBBC05" d="M6.5 14.1A6 6 0 0 1 6.2 12c0-.7.1-1.4.3-2.1V7.3H3.2A10 10 0 0 0 2 12c0 1.7.4 3.3 1.2 4.7l3.3-2.6Z" />
                <path fill="#EA4335" d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.9-2.8A9.7 9.7 0 0 0 12 2a10 10 0 0 0-8.8 5.3l3.3 2.6a5.8 5.8 0 0 1 5.5-4Z" />
            </svg>
            ورود با Google
            {compact && <LogIn className="mr-auto text-indigo-500" size={15} />}
        </a>
    );
}
