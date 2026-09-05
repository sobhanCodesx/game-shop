import { router } from "@inertiajs/react";
import {
    CircleX,
    FileText,
    Gamepad2,
    History,
    LoaderCircle,
    Play,
    Search,
    ShoppingBag,
    Tag,
} from "lucide-react";
import {
    useEffect,
    useId,
    useRef,
    useState,
    type FormEvent,
    type KeyboardEvent,
} from "react";

export interface SearchSuggestion {
    id: string;
    kind: "product" | "video" | "short" | "post" | "channel" | "category";
    kind_label: string;
    title: string;
    subtitle: string;
    image_url: string | null;
    url: string;
}

interface Props {
    initialValue?: string;
    autoFocus?: boolean;
    className?: string;
    onNavigate?: () => void;
    placeholder?: string;
}

const cache = new Map<string, SearchSuggestion[]>();

const kindIcon = {
    product: ShoppingBag,
    video: Play,
    short: Play,
    post: FileText,
    channel: Gamepad2,
    category: Tag,
};

export default function SmartSearch({
    initialValue = "",
    autoFocus = false,
    className = "",
    onNavigate,
    placeholder = "بازی، محصول، ویدیو یا دسته‌بندی را جستجو کنید...",
}: Props) {
    const [value, setValue] = useState(initialValue);
    const [suggestions, setSuggestions] = useState<SearchSuggestion[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const inputRef = useRef<HTMLInputElement>(null);
    const listId = useId();
    const query = value.trim();

    useEffect(() => setValue(initialValue), [initialValue]);

    useEffect(() => {
        if (autoFocus) {
            window.setTimeout(() => inputRef.current?.focus(), 80);
        }
    }, [autoFocus]);

    useEffect(() => {
        setActiveIndex(-1);
        if (query.length < 2) {
            setSuggestions([]);
            setLoading(false);
            return;
        }

        const cached = cache.get(query);
        if (cached) {
            setSuggestions(cached);
            setOpen(true);
            return;
        }

        const controller = new AbortController();
        setLoading(true);
        setSuggestions([]);
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    `/search/suggestions?q=${encodeURIComponent(query)}`,
                    {
                        headers: { Accept: "application/json" },
                        signal: controller.signal,
                    },
                );
                if (!response.ok) return;
                const data = (await response.json()) as {
                    suggestions: SearchSuggestion[];
                };
                cache.set(query, data.suggestions);
                setSuggestions(data.suggestions);
                setOpen(true);
            } catch {
                if (!controller.signal.aborted) setSuggestions([]);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 240);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const navigate = (url: string) => {
        setOpen(false);
        onNavigate?.();
        router.visit(url);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!query) return;
        if (activeIndex >= 0 && suggestions[activeIndex]) {
            navigate(suggestions[activeIndex].url);
            return;
        }
        navigate(`/search?q=${encodeURIComponent(query)}`);
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === "ArrowDown" && suggestions.length) {
            event.preventDefault();
            setOpen(true);
            setActiveIndex((current) => (current + 1) % suggestions.length);
        }
        if (event.key === "ArrowUp" && suggestions.length) {
            event.preventDefault();
            setOpen(true);
            setActiveIndex((current) =>
                current <= 0 ? suggestions.length - 1 : current - 1,
            );
        }
        if (event.key === "Escape") {
            setOpen(false);
            setActiveIndex(-1);
        }
    };

    return (
        <div className={`smart-search relative z-30 ${className}`}>
            <form
                className="smart-search__form"
                onSubmit={submit}
                role="search"
            >
                <Search
                    aria-hidden="true"
                    className="smart-search__icon"
                    size={21}
                />
                <input
                    aria-autocomplete="list"
                    aria-controls={listId}
                    aria-expanded={open}
                    aria-label="جستجوی هوشمند"
                    autoComplete="off"
                    className="smart-search__input"
                    onBlur={() => window.setTimeout(() => setOpen(false), 150)}
                    onChange={(event) => {
                        setValue(event.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => query.length > 1 && setOpen(true)}
                    onKeyDown={handleKeyDown}
                    placeholder={placeholder}
                    ref={inputRef}
                    role="combobox"
                    spellCheck={false}
                    value={value}
                />
                {loading ? (
                    <LoaderCircle
                        aria-label="در حال جستجو"
                        className="smart-search__loader animate-spin"
                        size={19}
                    />
                ) : value ? (
                    <button
                        aria-label="پاک کردن جستجو"
                        className="smart-search__clear"
                        onClick={() => {
                            setValue("");
                            setSuggestions([]);
                            inputRef.current?.focus();
                        }}
                        type="button"
                    >
                        <CircleX size={18} />
                    </button>
                ) : null}
                <button className="smart-search__submit" type="submit">
                    <span>جستجو</span>
                    <Search size={17} />
                </button>
            </form>

            {open && query.length > 1 && (
                <div
                    className="smart-search__results"
                    id={listId}
                    role="listbox"
                >
                    <div className="flex items-center justify-between px-4 pb-2 pt-3 text-[11px] font-bold text-[var(--store-muted)]">
                        <span className="flex items-center gap-1.5">
                            <History size={13} /> پیشنهادهای نزدیک
                        </span>
                        {!loading && (
                            <span>
                                {suggestions.length.toLocaleString("fa-IR")}{" "}
                                نتیجه
                            </span>
                        )}
                    </div>
                    {!loading && suggestions.length === 0 ? (
                        <button
                            className="flex w-full items-center gap-3 px-4 py-5 text-right text-sm text-[var(--store-muted)] hover:bg-[var(--store-accent-soft)]"
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() =>
                                navigate(
                                    `/search?q=${encodeURIComponent(query)}`,
                                )
                            }
                            type="button"
                        >
                            <span className="grid size-10 place-items-center rounded-xl bg-[var(--store-surface-strong)]">
                                <Search size={18} />
                            </span>
                            <span>
                                نتیجه فوری پیدا نشد؛ جستجوی کامل برای «{query}»
                            </span>
                        </button>
                    ) : (
                        suggestions.map((suggestion, index) => {
                            const Icon = kindIcon[suggestion.kind];
                            return (
                                <button
                                    aria-selected={activeIndex === index}
                                    className="smart-search__result"
                                    key={suggestion.id}
                                    onClick={() => navigate(suggestion.url)}
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onMouseEnter={() => setActiveIndex(index)}
                                    role="option"
                                    type="button"
                                >
                                    <span className="smart-search__thumb">
                                        {suggestion.image_url ? (
                                            <img
                                                alt=""
                                                src={suggestion.image_url}
                                            />
                                        ) : (
                                            <Icon size={20} />
                                        )}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <strong className="block truncate text-sm text-[var(--store-text)]">
                                            {suggestion.title}
                                        </strong>
                                        <small className="mt-1 block truncate text-[11px] text-[var(--store-muted)]">
                                            {suggestion.subtitle}
                                        </small>
                                    </span>
                                    <span className="smart-search__kind">
                                        {suggestion.kind_label}
                                    </span>
                                </button>
                            );
                        })
                    )}
                    {!!suggestions.length && (
                        <button
                            className="smart-search__all"
                            onClick={() =>
                                navigate(
                                    `/search?q=${encodeURIComponent(query)}`,
                                )
                            }
                            onMouseDown={(event) => event.preventDefault()}
                            type="button"
                        >
                            نمایش همه نتایج برای «{query}»
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
