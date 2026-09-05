import { Button, Card, Chip, Input } from "@heroui/react";
import { Head, router, useForm } from "@inertiajs/react";
import { Edit3, ListVideo, Plus, Save, Trash2, X } from "lucide-react";
import { type FormEvent, useState } from "react";

import AdminLayout from "../../../Layouts/AdminLayout";

interface Playlist {
    id: number;
    game_id: number;
    game: string | null;
    title: string;
    description: string | null;
    visibility: "public" | "unlisted" | "private";
    sort_order: number;
    videos_count: number;
}

interface GameOption {
    id: number;
    name: string;
}

export default function PlaylistIndex({
    playlists,
    games,
}: {
    playlists: Playlist[];
    games: GameOption[];
}) {
    const [editing, setEditing] = useState<Playlist | null>(null);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        game_id: "",
        title: "",
        description: "",
        visibility: "public",
        sort_order: 0,
    });
    const startEdit = (playlist: Playlist) => {
        setEditing(playlist);
        setData({
            game_id: String(playlist.game_id),
            title: playlist.title,
            description: playlist.description ?? "",
            visibility: playlist.visibility,
            sort_order: playlist.sort_order,
        });
    };
    const cancel = () => {
        setEditing(null);
        reset();
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: cancel };
        editing
            ? put(`/admin/video-playlists/${editing.id}`, options)
            : post("/admin/video-playlists", options);
    };

    return (
        <AdminLayout
            description="کالکشن‌های هر کانال را بسازید و سپس هنگام ویرایش ویدیو آن‌ها را انتخاب کنید."
            title="کالکشن‌های ویدیو"
        >
            <Head title="کالکشن‌های ویدیو" />
            <div className="grid items-start gap-6 xl:grid-cols-[380px_1fr]">
                <Card
                    className="border border-slate-800 bg-slate-900/60"
                    variant="secondary"
                >
                    <Card.Header className="flex items-center gap-2 border-b border-slate-800 p-5">
                        <Plus size={18} />
                        <Card.Title>
                            {editing ? "ویرایش کالکشن" : "کالکشن جدید"}
                        </Card.Title>
                    </Card.Header>
                    <Card.Content>
                        <form className="space-y-4 p-5" onSubmit={submit}>
                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    کانال / عنوان بازی
                                </span>
                                <select
                                    className="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white"
                                    onChange={(event) =>
                                        setData("game_id", event.target.value)
                                    }
                                    required
                                    value={data.game_id}
                                >
                                    <option value="">انتخاب کانال</option>
                                    {games.map((game) => (
                                        <option key={game.id} value={game.id}>
                                            {game.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.game_id && (
                                    <small className="mt-1 block text-rose-400">
                                        {errors.game_id}
                                    </small>
                                )}
                            </label>
                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    عنوان کالکشن
                                </span>
                                <Input
                                    fullWidth
                                    onChange={(event) =>
                                        setData("title", event.target.value)
                                    }
                                    required
                                    value={data.title}
                                />
                                {errors.title && (
                                    <small className="mt-1 block text-rose-400">
                                        {errors.title}
                                    </small>
                                )}
                            </label>
                            <label className="block">
                                <span className="mb-2 block text-xs font-bold text-slate-300">
                                    توضیحات
                                </span>
                                <textarea
                                    className="min-h-24 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm text-white"
                                    onChange={(event) =>
                                        setData(
                                            "description",
                                            event.target.value,
                                        )
                                    }
                                    value={data.description}
                                />
                            </label>
                            <div className="grid grid-cols-2 gap-3">
                                <label>
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        نمایش
                                    </span>
                                    <select
                                        className="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white"
                                        onChange={(event) =>
                                            setData(
                                                "visibility",
                                                event.target.value,
                                            )
                                        }
                                        value={data.visibility}
                                    >
                                        <option value="public">عمومی</option>
                                        <option value="unlisted">
                                            با لینک
                                        </option>
                                        <option value="private">خصوصی</option>
                                    </select>
                                </label>
                                <label>
                                    <span className="mb-2 block text-xs font-bold text-slate-300">
                                        ترتیب
                                    </span>
                                    <input
                                        className="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white"
                                        min={0}
                                        onChange={(event) =>
                                            setData(
                                                "sort_order",
                                                Number(event.target.value),
                                            )
                                        }
                                        type="number"
                                        value={data.sort_order}
                                    />
                                </label>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    isDisabled={processing}
                                    type="submit"
                                    variant="primary"
                                >
                                    <Save size={16} />
                                    {editing ? "ذخیره تغییرات" : "ساخت کالکشن"}
                                </Button>
                                {editing && (
                                    <Button
                                        onPress={cancel}
                                        type="button"
                                        variant="ghost"
                                    >
                                        <X size={16} />
                                        انصراف
                                    </Button>
                                )}
                            </div>
                        </form>
                    </Card.Content>
                </Card>
                <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                    {playlists.map((playlist) => (
                        <Card
                            className="border border-slate-800 bg-slate-900/60"
                            key={playlist.id}
                            variant="secondary"
                        >
                            <Card.Content className="p-5">
                                <div className="flex items-start justify-between gap-3">
                                    <span className="grid size-11 place-items-center rounded-xl bg-indigo-500/10 text-indigo-400">
                                        <ListVideo size={21} />
                                    </span>
                                    <Chip
                                        color={
                                            playlist.visibility === "public"
                                                ? "success"
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
                                <h2 className="mt-4 font-black text-white">
                                    {playlist.title}
                                </h2>
                                <p className="mt-1 text-xs text-slate-500">
                                    کانال {playlist.game}
                                </p>
                                <p className="mt-4 text-xs text-slate-400">
                                    {playlist.videos_count.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    ویدیو
                                </p>
                            </Card.Content>
                            <Card.Footer className="flex justify-end gap-2 border-t border-slate-800 px-4 py-3">
                                <Button
                                    onPress={() => startEdit(playlist)}
                                    size="sm"
                                    variant="ghost"
                                >
                                    <Edit3 size={15} />
                                    ویرایش
                                </Button>
                                <Button
                                    onPress={() =>
                                        window.confirm("این کالکشن حذف شود؟") &&
                                        router.delete(
                                            `/admin/video-playlists/${playlist.id}`,
                                            { preserveScroll: true },
                                        )
                                    }
                                    size="sm"
                                    variant="danger-soft"
                                >
                                    <Trash2 size={15} />
                                    حذف
                                </Button>
                            </Card.Footer>
                        </Card>
                    ))}
                    {!playlists.length && (
                        <div className="col-span-full rounded-2xl border border-dashed border-slate-700 p-12 text-center text-sm text-slate-500">
                            هنوز کالکشنی ساخته نشده است.
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
