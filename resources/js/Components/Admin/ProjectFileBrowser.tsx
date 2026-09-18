import {
    ChonkyActions,
    FullFileBrowser,
    type FileActionHandler,
    type FileBrowserHandle,
    type FileData,
} from "@samuelncui/chonky";
import { ChonkyIconFA } from "@samuelncui/chonky-icon-fontawesome";
import { useCallback, useEffect, useMemo, useRef } from "react";

export type ProjectEntry = {
    name: string;
    path: string;
    type: "directory" | "file" | "link";
    is_link: boolean;
    accessible: boolean;
    hidden: boolean;
    size: number | null;
    modified_at: string | null;
    readable: boolean;
    writable: boolean;
    extension: string | null;
};

export type ProjectBreadcrumb = {
    label: string;
    path: string;
};

type Props = {
    entries: ProjectEntry[];
    breadcrumbs: ProjectBreadcrumb[];
    selectedPath: string | null;
    onNavigate: (path: string) => void;
    onOpenFile: (path: string) => void;
    onSelectionChange: (path: string | null) => void;
};

const faMessages = {
    "chonky.toolbar.searchPlaceholder": "فیلتر فایل‌ها",
    "chonky.actions.open_selection.button.name": "باز کردن",
    "chonky.actions.open_parent_folder.button.name": "پوشه بالاتر",
    "chonky.actions.select_all_files.button.name": "انتخاب همه",
    "chonky.actions.clear_selection.button.name": "پاک کردن انتخاب",
    "chonky.actions.enable_list_view.button.name": "نمایش لیستی",
    "chonky.actions.enable_grid_view.button.name": "نمایش شبکه‌ای",
    "chonky.actions.sort_files_by_name.button.name": "مرتب‌سازی بر اساس نام",
    "chonky.actions.sort_files_by_size.button.name": "مرتب‌سازی بر اساس حجم",
    "chonky.actions.sort_files_by_date.button.name": "مرتب‌سازی بر اساس تاریخ",
    "chonky.actions.toggle_hidden_files.button.name": "نمایش فایل‌های مخفی",
    "chonky.actions.toggle_show_folders_first.button.name": "پوشه‌ها در ابتدا",
    "chonky.actionGroups.Actions": "عملیات",
    "chonky.actionGroups.Options": "نمایش و مرتب‌سازی",
};

function entryToFile(entry: ProjectEntry): FileData {
    return {
        id: entry.path,
        name: entry.name,
        path: entry.path,
        ext: entry.extension ? `.${entry.extension}` : undefined,
        isDir: entry.type === "directory",
        isHidden: entry.hidden,
        isSymlink: entry.is_link,
        openable: entry.accessible && entry.type !== "link",
        selectable: entry.accessible,
        draggable: false,
        droppable: false,
        size: entry.size ?? undefined,
        modDate: entry.modified_at ?? undefined,
    };
}

export default function ProjectFileBrowser({
    entries,
    breadcrumbs,
    selectedPath,
    onNavigate,
    onOpenFile,
    onSelectionChange,
}: Props) {
    const browserRef = useRef<FileBrowserHandle>(null);

    const files = useMemo<FileData[]>(() => entries.map(entryToFile), [entries]);
    const folderChain = useMemo<FileData[]>(
        () =>
            breadcrumbs.map((crumb, index) => ({
                id: `__folder_chain__${index}:${crumb.path || "/"}`,
                name: crumb.label,
                path: crumb.path,
                isDir: true,
                openable: true,
                selectable: false,
                draggable: false,
                droppable: false,
            })),
        [breadcrumbs],
    );

    const handleFileAction = useCallback<FileActionHandler>(
        (data) => {
            if (data.id === ChonkyActions.ChangeSelection.id) {
                const firstId = data.payload.selection.values().next().value as
                    | string
                    | undefined;
                onSelectionChange(firstId ?? null);
                return;
            }

            if (data.id !== ChonkyActions.OpenFiles.id) return;

            const target = data.payload.targetFile ?? data.payload.files[0];
            if (!target || target.openable === false) return;

            const path = typeof target.path === "string" ? target.path : target.id;
            if (target.isDir) {
                onNavigate(path);
            } else {
                onOpenFile(path);
            }
        },
        [onNavigate, onOpenFile, onSelectionChange],
    );

    useEffect(() => {
        if (!selectedPath || !files.some((file) => file.id === selectedPath)) {
            browserRef.current?.setFileSelection(new Set());
            return;
        }

        browserRef.current?.setFileSelection(new Set([selectedPath]));
        browserRef.current?.revealFile(selectedPath);
    }, [files, selectedPath]);

    return (
        <div className="h-[70vh] min-h-[560px] overflow-hidden rounded-2xl border border-slate-800 bg-slate-950" dir="ltr">
            <FullFileBrowser
                ref={browserRef}
                instanceId="playnexus-project-files"
                files={files}
                folderChain={folderChain}
                onFileAction={handleFileAction}
                iconComponent={ChonkyIconFA}
                darkMode
                disableDragAndDrop
                clearSelectionOnOutsideClick={false}
                defaultFileViewActionId={ChonkyActions.EnableListView.id}
                defaultSortActionId={ChonkyActions.SortFilesByName.id}
                i18n={{
                    locale: "fa",
                    defaultLocale: "fa",
                    messages: faMessages,
                }}
            />
        </div>
    );
}
