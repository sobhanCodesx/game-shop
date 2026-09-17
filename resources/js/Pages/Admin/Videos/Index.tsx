import { Button, Card, Chip, Input } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    Edit3,
    Film,
    Filter,
    Gamepad2,
    Layers3,
    Plus,
    Search,
    Trash2,
    X,
} from "lucide-react";
import { type FormEvent, useMemo, useState } from "react";

import AdminLayout from "../../../Layouts/AdminLayout";

type CollectionType = "game" | "studio" | "general";

interface VideoItem {
    id: number;
    title: string;
    excerpt: string | null;
    duration: number | null;
    views: number;
    status: string;
    featured: boolean;
    thumbnail_url: string | null;
    edit_url: string;
    channel: string | null;
    studio: string | null;
    collections: Array<{
        id: number;
        title: string;
        type: CollectionType;
    }>;
}

interface FilterOption {
    id: number;
    name: string;
}

interface PlaylistOption {
    id: number;
    title: string;
    type: CollectionType;
    owner: string | null;
}

interface Filters {
    search: string;
    collection_type: "" | CollectionType | "none";
    playlist: number | null;
    game: number | null;
    studio: number | null;
    status: "" | "draft" | "published";
}

interface Props {
    videos: {
        data: VideoItem[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        all_total: number;
    };
    filters: Filters;
    filterOptions: {
        games: FilterOption[];
        studios: FilterOption[];
        playlists: PlaylistOption[];
    };
}

const number = new Intl.NumberFormat("fa-IR");
const duration = (seconds: number | null) => {
    if (!seconds) return "—";
    const minutes = Math.floor(seconds / 60);
    return `${number.format(minutes)}:${number.format(seconds % 60).padStart(2, "۰")}`;
};

const collectionTypeLabel: Record<CollectionType, string> = {
    game: "کالکشن بازی",
    studio: "کالکشن استودیو",
    general: "کالکشن عمومی",
};

export default function VideoIndex({ videos, filters, filterOptions }: Props) {
    const [search, setSearch] = useState(filters.search);
    const activeFilters = [
        filters.search,
        filters.collection_type,
        filters.playlist,
        filters.game,
        filters.studio,
        filters.status,
    ].filter(Boolean).length;

    const availablePlaylists = useMemo(() => {
        if (!filters.collection_type || filters.collection_type === "none") {
            return filters.collection_type === "none" ? [] : filterOptions.playlists;
        }

        return filterOptions.playlists.filter(
            (playlist) => playlist.type === filters.collection_type,
        );
    }, [filterOptions.playlists, filters.collection_type]);

    const query = (next: Record<string, unknown>) =>
        router.get(
            "/admin/videos",
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const clearFilters = () => {
        setSearch("");
        router.get(
            "/admin/videos",
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        query({ search: search.trim(), page: 1 });
    };

    const go = (page: number) => query({ page });

    return (
        <AdminLayout
            actions={
                <Link href="/admin/videos/create">
                    <Button variant="primary">
                        <Plus size={17} /> ویدیوی جدید
                    </Button>
                </Link>
            }
            description="ویدیوها را سریع بر اساس عنوان، کالکشن، بازی یا کانال و استودیو پیدا و مدیریت کنید."
            title="مدیریت ویدیوها"
        >
            <Head title="مدیریت ویدیوها" />

            <Card
                className="mb-5 overflow-visible border border-slate-800 bg-slate-900/60 shadow-xl shadow-black/10"
                variant="secondary"
            >
                <Card.Header className="flex items-center justify-between gap-3 border-b border-slate-800/80 px-4 py-3 sm:px-5">
                    <div className="flex items-center gap-2">
                        <span className="grid size-9 place-items-center rounded-xl bg-indigo-500/10 text-indigo-400">
                            <Filter size={17} />
                        </span>
                        <div>
                            <h2 className="text-sm font-black text-white">
                                فیلتر ویدیوها
                            </h2>
                            <p className="text-[11px] text-slate-500">
                                فیلترها همزمان قابل ترکیب هستند
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {activeFilters > 0 && (
                            <Chip color="accent" size="sm" variant="soft">
                                {number.format(activeFilters)} فیلتر فعال
                            </Chip>
                        )}
                        {activeFilters > 0 && (
                            <Button
                                onPress={clearFilters}
                                size="sm"
                                variant="ghost"
                            >
                                <X size={14} /> پاک کردن
                            </Button>
                        )}
                    </div>
                </Card.Header>

                <Card.Content className="p-4 sm:p-5">
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-12">
                        <form
                            className="flex gap-2 md:col-span-2 xl:col-span-4"
                            onSubmit={submitSearch}
                        >
                            <Input
                                aria-label="جستجو بر اساس عنوان ویدیو"
                                fullWidth
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="جستجو در عنوان ویدیو..."
                                value={search}
                            />
                            <Button
                                aria-label="جستجو"
                                isIconOnly
                                type="submit"
                                variant="primary"
                            >
                                <Search size={17} />
                            </Button>
                        </form>

                        <FilterSelect
                            className="xl:col-span-2"
                            label="نوع کالکشن"
                            onChange={(value) =>
                                query({
                                    collection_type: value,
                                    playlist: null,
                                    page: 1,
                                })
                            }
                            value={filters.collection_type}
                        >
                            <option value="">همه نوع‌ها</option>
                            <option value="game">کالکشن بازی / کانال</option>
                            <option value="studio">کالکشن استودیو</option>
                            <option value="general">کالکشن عمومی</option>
                            <option value="none">بدون کالکشن</option>
                        </FilterSelect>

                        <FilterSelect
                            className="xl:col-span-2"
                            disabled={filters.collection_type === "none"}
                            label="کالکشن"
                            onChange={(value) =>
                                query({ playlist: value ? Number(value) : null, page: 1 })
                            }
                            value={filters.playlist ?? ""}
                        >
                            <option value="">همه کالکشن‌ها</option>
                            {availablePlaylists.map((playlist) => (
                                <option key={playlist.id} value={playlist.id}>
                                    {playlist.title}
                                    {playlist.owner ? ` — ${playlist.owner}` : ""}
                                </option>
                            ))}
                        </FilterSelect>

                        <FilterSelect
                            className="xl:col-span-2"
                            label="بازی / کانال"
                            onChange={(value) =>
                                query({ game: value ? Number(value) : null, page: 1 })
                            }
                            value={filters.game ?? ""}
                        >
                            <option value="">همه بازی‌ها و کانال‌ها</option>
                            {filterOptions.games.map((game) => (
                                <option key={game.id} value={game.id}>
                                    {game.name}
                                </option>
                            ))}
                        </FilterSelect>

                        <FilterSelect
                            className="xl:col-span-2"
                            label="استودیو"
                            onChange={(value) =>
                                query({ studio: value ? Number(value) : null, page: 1 })
                            }
                            value={filters.studio ?? ""}
                        >
                            <option value="">همه استودیوها</option>
                            {filterOptions.studios.map((studio) => (
                                <option key={studio.id} value={studio.id}>
                                    {studio.name}
                                </option>
                            ))}
                        </FilterSelect>
                    </div>

                    <div className="mt-3 flex flex-col gap-3 border-t border-slate-800/70 pt-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <span>
                                {activeFilters
                                    ? `${number.format(videos.total)} نتیجه از ${number.format(videos.all_total)} ویدیو`
                                    : `${number.format(videos.total)} ویدیو`}
                            </span>
                            {filters.collection_type && filters.collection_type !== "none" && (
                                <Chip size="sm" variant="soft">
                                    {collectionTypeLabel[
                                        filters.collection_type as CollectionType
                                    ] ?? "کالکشن"}
                                </Chip>
                            )}
                        </div>
                        <label className="flex items-center gap-2 text-xs font-bold text-slate-500">
                            وضعیت
                            <select
                                className="h-9 min-w-36 rounded-xl border border-slate-700 bg-slate-950 px-3 text-xs text-slate-200 outline-none transition focus:border-indigo-500"
                                onChange={(event) =>
                                    query({ status: event.target.value, page: 1 })
                                }
                                value={filters.status}
                            >
                                <option value="">همه وضعیت‌ها</option>
                                <option value="published">منتشرشده</option>
                                <option value="draft">پیش‌نویس</option>
                            </select>
                        </label>
                    </div>
                </Card.Content>
            </Card>

            {videos.data.length ? (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {videos.data.map((video) => (
                        <Card
                            className="overflow-hidden border border-slate-800 bg-slate-900/60 transition duration-200 hover:-translate-y-0.5 hover:border-slate-700"
                            key={video.id}
                            variant="secondary"
                        >
                            <div className="relative aspect-video bg-slate-950">
                                {video.thumbnail_url ? (
                                    <img
                                        alt={video.title}
                                        className="size-full object-cover"
                                        loading="lazy"
                                        src={video.thumbnail_url}
                                    />
                                ) : (
                                    <div className="grid size-full place-items-center text-indigo-400">
                                        <Film size={42} />
                                    </div>
                                )}
                                <span className="absolute bottom-2 left-2 rounded-lg bg-black/75 px-2 py-1 text-[10px] text-white backdrop-blur-sm">
                                    {duration(video.duration)}
                                </span>
                                <Chip
                                    className="absolute right-2 top-2"
                                    color={
                                        video.status === "published"
                                            ? "success"
                                            : "warning"
                                    }
                                    size="sm"
                                    variant="soft"
                                >
                                    {video.status === "published"
                                        ? "منتشرشده"
                                        : "پیش‌نویس"}
                                </Chip>
                            </div>
                            <Card.Content className="p-4">
                                <h2 className="line-clamp-1 font-black text-white">
                                    {video.title}
                                </h2>
                                <p className="mt-2 line-clamp-2 min-h-10 text-xs leading-5 text-slate-500">
                                    {video.excerpt || "بدون توضیح کوتاه"}
                                </p>

                                <div className="mt-4 space-y-2 rounded-xl bg-slate-950/55 p-3 text-[11px]">
                                    <MetaRow
                                        icon={<Gamepad2 size={13} />}
                                        label="بازی / کانال"
                                        value={video.channel ?? "بدون کانال"}
                                    />
                                    <MetaRow
                                        icon={<Building2 size={13} />}
                                        label="استودیو"
                                        value={video.studio ?? "بدون استودیو"}
                                    />
                                    <div className="flex items-start gap-2 text-slate-500">
                                        <Layers3 className="mt-0.5 shrink-0" size={13} />
                                        <span className="shrink-0">کالکشن:</span>
                                        <div className="flex min-w-0 flex-wrap gap-1">
                                            {video.collections.length ? (
                                                video.collections.slice(0, 3).map((collection) => (
                                                    <span
                                                        className="max-w-40 truncate rounded-md bg-slate-800 px-1.5 py-0.5 text-slate-300"
                                                        key={collection.id}
                                                        title={collection.title}
                                                    >
                                                        {collection.title}
                                                    </span>
                                                ))
                                            ) : (
                                                <span className="text-slate-600">
                                                    بدون کالکشن
                                                </span>
                                            )}
                                            {video.collections.length > 3 && (
                                                <span className="rounded-md bg-slate-800 px-1.5 py-0.5 text-slate-400">
                                                    +{number.format(video.collections.length - 3)}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <p className="mt-3 text-[11px] text-slate-600">
                                    {number.format(video.views)} بازدید
                                </p>
                            </Card.Content>
                            <Card.Footer className="flex justify-end gap-2 border-t border-slate-800 px-4 py-3">
                                <Link href={video.edit_url}>
                                    <Button size="sm" variant="ghost">
                                        <Edit3 size={15} /> ویرایش
                                    </Button>
                                </Link>
                                <Button
                                    onPress={() => {
                                        if (window.confirm("این ویدیو حذف شود؟")) {
                                            router.delete(`/admin/videos/${video.id}`);
                                        }
                                    }}
                                    size="sm"
                                    variant="danger-soft"
                                >
                                    <Trash2 size={15} /> حذف
                                </Button>
                            </Card.Footer>
                        </Card>
                    ))}
                </div>
            ) : (
                <div className="rounded-3xl border border-dashed border-slate-700 bg-slate-900/30 p-14 text-center">
                    <Film className="mx-auto text-slate-600" size={48} />
                    <h2 className="mt-4 font-black text-white">
                        ویدیویی با این فیلترها پیدا نشد
                    </h2>
                    <p className="mt-2 text-sm text-slate-500">
                        فیلترها را تغییر دهید یا همه فیلترها را پاک کنید.
                    </p>
                    {activeFilters > 0 && (
                        <Button
                            className="mt-5"
                            onPress={clearFilters}
                            variant="secondary"
                        >
                            <X size={16} /> پاک کردن همه فیلترها
                        </Button>
                    )}
                </div>
            )}

            {videos.last_page > 1 && (
                <nav
                    aria-label="صفحه‌بندی ویدیوها"
                    className="mt-6 flex flex-col items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/50 p-4 sm:flex-row"
                >
                    <p className="text-xs text-slate-500">
                        نمایش {number.format(videos.from ?? 0)} تا{" "}
                        {number.format(videos.to ?? 0)} از{" "}
                        {number.format(videos.total)} ویدیو
                    </p>
                    <div className="flex items-center gap-2">
                        <Button
                            aria-label="صفحه قبلی"
                            isDisabled={videos.current_page === 1}
                            isIconOnly
                            onPress={() => go(videos.current_page - 1)}
                            size="sm"
                            variant="ghost"
                        >
                            <ChevronRight size={17} />
                        </Button>
                        <span className="min-w-28 text-center text-xs text-slate-400">
                            صفحه {number.format(videos.current_page)} از{" "}
                            {number.format(videos.last_page)}
                        </span>
                        <Button
                            aria-label="صفحه بعدی"
                            isDisabled={videos.current_page === videos.last_page}
                            isIconOnly
                            onPress={() => go(videos.current_page + 1)}
                            size="sm"
                            variant="ghost"
                        >
                            <ChevronLeft size={17} />
                        </Button>
                    </div>
                </nav>
            )}
        </AdminLayout>
    );
}

function FilterSelect({
    label,
    value,
    onChange,
    children,
    className = "",
    disabled = false,
}: {
    label: string;
    value: string | number;
    onChange: (value: string) => void;
    children: React.ReactNode;
    className?: string;
    disabled?: boolean;
}) {
    return (
        <label className={`block ${className}`}>
            <span className="mb-1.5 block text-[11px] font-bold text-slate-500">
                {label}
            </span>
            <select
                className="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-slate-200 outline-none transition enabled:cursor-pointer enabled:hover:border-slate-600 focus:border-indigo-500 disabled:cursor-not-allowed disabled:opacity-45"
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                value={value}
            >
                {children}
            </select>
        </label>
    );
}

function MetaRow({
    icon,
    label,
    value,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-center gap-2 text-slate-500">
            <span className="shrink-0">{icon}</span>
            <span className="shrink-0">{label}:</span>
            <strong className="min-w-0 truncate font-bold text-slate-300">
                {value}
            </strong>
        </div>
    );
}
