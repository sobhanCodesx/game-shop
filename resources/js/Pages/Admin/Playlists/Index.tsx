import { Avatar, Button, Card, Chip } from "@heroui/react";
import { Head, Link, router } from "@inertiajs/react";
import {
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    Edit3,
    Gamepad2,
    ListVideo,
    Plus,
    Trash2,
} from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";

interface Game {
    id: number;
    name: string;
    slug: string;
    status: string;
    image_url: string | null;
    cover_url: string | null;
    playlists_count: number;
}
interface SelectedGame {
    id: number;
    name: string;
    slug: string;
    cover_url: string | null;
}
interface Playlist {
    id: number;
    title: string;
    visibility: "public" | "unlisted" | "private";
    sort_order: number;
    videos_count: number;
    logo_url: string | null;
}
interface Page<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}
const fa = new Intl.NumberFormat("fa-IR");

export default function PlaylistIndex({
    games,
    selectedGame,
    playlists,
}: {
    games: Page<Game>;
    selectedGame: SelectedGame | null;
    playlists: Page<Playlist> | null;
}) {
    return (
        <AdminLayout
            description={
                selectedGame
                    ? `مدیریت کالکشن‌های کانال ${selectedGame.name}`
                    : "یک بازی را انتخاب کنید تا کالکشن‌های ویدیویی آن نمایش داده شود."
            }
            title="کالکشن‌های ویدیو"
        >
            <Head title="کالکشن‌های ویدیو" />
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                {selectedGame ? (
                    <button
                        className="inline-flex items-center gap-2 self-start text-sm font-bold text-slate-400 transition hover:text-white"
                        onClick={() => router.get("/admin/video-playlists")}
                        type="button"
                    >
                        <ArrowRight size={17} /> بازگشت به بازی‌ها
                    </button>
                ) : (
                    <p className="text-sm text-slate-400">
                        {fa.format(games.total)} بازی ثبت شده است.
                    </p>
                )}
                <Link
                    className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 text-sm font-bold text-white transition hover:bg-indigo-500"
                    href="/admin/video-playlists/create"
                >
                    <Plus size={17} /> افزودن کالکشن
                </Link>
            </div>
            {selectedGame && playlists ? (
                <PlaylistList game={selectedGame} page={playlists} />
            ) : (
                <GameList games={games} />
            )}
        </AdminLayout>
    );
}

function GameList({ games }: { games: Page<Game> }) {
    return (
        <>
            <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                {games.data.map((game) => (
                    <button
                        className="group overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 text-right transition hover:-translate-y-1 hover:border-indigo-500/60"
                        key={game.id}
                        onClick={() =>
                            router.get(
                                "/admin/video-playlists",
                                { game: game.id },
                                { preserveState: true },
                            )
                        }
                        type="button"
                    >
                        <div className="relative aspect-[16/7] overflow-hidden bg-slate-950">
                            {game.image_url ? (
                                <img
                                    alt=""
                                    className="size-full object-cover transition duration-300 group-hover:scale-105"
                                    src={game.image_url}
                                />
                            ) : (
                                <span className="grid size-full place-items-center bg-gradient-to-br from-indigo-500/15 to-slate-950 text-indigo-400">
                                    <Gamepad2 size={42} />
                                </span>
                            )}
                            <span className="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent" />
                        </div>
                        <div className="flex items-center gap-3 p-4">
                            <Avatar size="sm">
                                {game.cover_url && (
                                    <Avatar.Image
                                        alt={game.name}
                                        src={game.cover_url}
                                    />
                                )}
                                <Avatar.Fallback>
                                    <Gamepad2 size={16} />
                                </Avatar.Fallback>
                            </Avatar>
                            <span className="min-w-0 flex-1">
                                <strong className="block truncate text-sm text-white">
                                    {game.name}
                                </strong>
                                <small className="mt-1 block text-slate-500">
                                    {fa.format(game.playlists_count)} کالکشن
                                </small>
                            </span>
                            <ChevronLeft
                                className="text-slate-600 transition group-hover:text-indigo-400"
                                size={19}
                            />
                        </div>
                    </button>
                ))}
            </div>
            <Pagination page={games} pageKey="games_page" />
        </>
    );
}

function PlaylistList({
    game,
    page,
}: {
    game: SelectedGame;
    page: Page<Playlist>;
}) {
    return (
        <>
            <div className="mb-5 flex items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900/50 p-4">
                <Avatar>
                    {game.cover_url && (
                        <Avatar.Image alt={game.name} src={game.cover_url} />
                    )}
                    <Avatar.Fallback>
                        <Gamepad2 />
                    </Avatar.Fallback>
                </Avatar>
                <div>
                    <h2 className="font-black text-white">{game.name}</h2>
                    <p className="text-xs text-slate-500">
                        {fa.format(page.total)} کالکشن
                    </p>
                </div>
            </div>
            {page.data.length ? (
                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {page.data.map((playlist) => (
                        <Card
                            className="group overflow-hidden border border-slate-800 bg-slate-900/60"
                            key={playlist.id}
                            variant="secondary"
                        >
                            <Card.Content className="p-0">
                                <div className="relative aspect-video overflow-hidden bg-slate-950">
                                    {playlist.logo_url ? (
                                        <img
                                            alt={playlist.title}
                                            className="size-full object-cover transition duration-300 group-hover:scale-105"
                                            src={playlist.logo_url}
                                        />
                                    ) : (
                                        <span className="grid size-full place-items-center bg-gradient-to-br from-indigo-500/15 to-slate-950 text-indigo-400">
                                            <ListVideo size={48} />
                                        </span>
                                    )}
                                    <Chip
                                        className="absolute left-3 top-3"
                                        color={
                                            playlist.visibility === "public"
                                                ? "success"
                                                : playlist.visibility ===
                                                    "private"
                                                  ? "danger"
                                                  : "warning"
                                        }
                                        size="sm"
                                        variant="soft"
                                    >
                                        {playlist.visibility === "public"
                                            ? "عمومی"
                                            : playlist.visibility === "unlisted"
                                              ? "با لینک"
                                              : "خصوصی"}
                                    </Chip>
                                </div>
                                <div className="p-5">
                                    <h3 className="truncate font-black text-white">
                                        {playlist.title}
                                    </h3>
                                    <div className="mt-4 flex justify-between text-xs text-slate-400">
                                        <span>
                                            {fa.format(playlist.videos_count)}{" "}
                                            ویدیو
                                        </span>
                                        <span>
                                            ترتیب{" "}
                                            {fa.format(playlist.sort_order)}
                                        </span>
                                    </div>
                                </div>
                            </Card.Content>
                            <Card.Footer className="flex justify-end gap-2 border-t border-slate-800 px-4 py-3">
                                <Link
                                    className="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-bold text-slate-300 transition hover:bg-white/5 hover:text-white"
                                    href={`/admin/video-playlists/${playlist.id}/edit`}
                                >
                                    <Edit3 size={15} /> ویرایش
                                </Link>
                                <Button
                                    onPress={() =>
                                        window.confirm(
                                            "این کالکشن و لوگوی آن حذف شود؟",
                                        ) &&
                                        router.delete(
                                            `/admin/video-playlists/${playlist.id}`,
                                            { preserveScroll: true },
                                        )
                                    }
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
                <div className="rounded-3xl border border-dashed border-slate-700 p-14 text-center">
                    <ListVideo className="mx-auto text-slate-600" size={48} />
                    <h2 className="mt-4 font-black text-white">
                        برای این بازی هنوز کالکشنی ساخته نشده است
                    </h2>
                </div>
            )}
            <Pagination
                page={page}
                pageKey="playlists_page"
                selectedGameId={game.id}
            />
        </>
    );
}

function Pagination<T>({
    page,
    pageKey,
    selectedGameId,
}: {
    page: Page<T>;
    pageKey: "games_page" | "playlists_page";
    selectedGameId?: number;
}) {
    if (page.last_page <= 1) return null;
    const go = (target: number) =>
        router.get(
            "/admin/video-playlists",
            {
                ...(selectedGameId ? { game: selectedGameId } : {}),
                [pageKey]: target,
            },
            { preserveScroll: true, preserveState: true },
        );
    return (
        <nav
            aria-label="صفحه‌بندی"
            className="mt-6 flex flex-col items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/50 p-4 sm:flex-row"
        >
            <p className="text-xs text-slate-500">
                نمایش {fa.format(page.from ?? 0)} تا {fa.format(page.to ?? 0)}{" "}
                از {fa.format(page.total)}
            </p>
            <div className="flex items-center gap-2">
                <Button
                    isDisabled={page.current_page === 1}
                    isIconOnly
                    onPress={() => go(page.current_page - 1)}
                    size="sm"
                    variant="ghost"
                >
                    <ChevronRight size={18} />
                </Button>
                <span className="px-2 text-xs">
                    صفحه {fa.format(page.current_page)} از{" "}
                    {fa.format(page.last_page)}
                </span>
                <Button
                    isDisabled={page.current_page === page.last_page}
                    isIconOnly
                    onPress={() => go(page.current_page + 1)}
                    size="sm"
                    variant="ghost"
                >
                    <ChevronLeft size={18} />
                </Button>
            </div>
        </nav>
    );
}
