import { Button, Card, Chip, Input } from '@heroui/react';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, Download, Edit3, Filter, Plus, Search, SlidersHorizontal, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import EmptyState from '../../../Components/Admin/EmptyState';
import AdminLayout from '../../../Layouts/AdminLayout';

interface ResourceItem {
    id: number | string;
    cells: string[];
    editUrl?: string;
    deleteUrl?: string;
}

interface Pagination {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
}

interface ResourceIndexProps {
    resource: string;
    title: string;
    description: string;
    createLabel: string | null;
    createUrl?: string | null;
    columns: string[];
    items: ResourceItem[];
    filters: { search: string; status: string };
    pagination: Pagination;
}

const numberFormatter = new Intl.NumberFormat('fa-IR');

export default function ResourceIndex({
    resource,
    title,
    description,
    createLabel,
    createUrl,
    columns,
    items,
    filters,
    pagination,
}: ResourceIndexProps) {
    const [search, setSearch] = useState(filters.search);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(`/admin/${resource}`, { search }, { preserveState: true, replace: true });
    };

    const actions = (
        <>
            <Button variant="secondary">
                <Download aria-hidden="true" size={17} />
                خروجی
            </Button>
            {createLabel && createUrl && (
                <Link href={createUrl}>
                    <Button variant="primary">
                        <Plus aria-hidden="true" size={17} />
                        {createLabel}
                    </Button>
                </Link>
            )}
        </>
    );

    return (
        <AdminLayout actions={actions} description={description} title={title}>
            <Head title={title} />

            <Card className="overflow-hidden border border-slate-800/80 bg-slate-900/55" variant="secondary">
                <Card.Header className="flex flex-col gap-4 border-b border-slate-800/80 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <form className="flex w-full max-w-lg items-center gap-2" onSubmit={submitSearch}>
                        <Input
                            aria-label={`جستجو در ${title}`}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={`جستجو در ${title}...`}
                            value={search}
                        />
                        <Button aria-label="جستجو" isIconOnly type="submit" variant="secondary">
                            <Search size={17} />
                        </Button>
                    </form>

                    <div className="flex items-center gap-2">
                        <Button variant="ghost">
                            <Filter size={16} />
                            فیلترها
                            <ChevronDown size={14} />
                        </Button>
                        <Button aria-label="تنظیم ستون‌ها" isIconOnly variant="ghost">
                            <SlidersHorizontal size={17} />
                        </Button>
                    </div>
                </Card.Header>

                <Card.Content className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] border-collapse text-right">
                            <thead>
                                <tr className="border-b border-slate-800/80 bg-slate-950/30">
                                    <th className="w-12 px-5 py-3.5">
                                        <input aria-label="انتخاب همه" className="accent-indigo-500" type="checkbox" />
                                    </th>
                                    {columns.map((column) => (
                                        <th className="whitespace-nowrap px-4 py-3.5 text-xs font-bold text-slate-500" key={column}>
                                            {column}
                                        </th>
                                    ))}
                                    <th className="w-20 px-4 py-3.5 text-xs font-bold text-slate-500">عملیات</th>
                                </tr>
                            </thead>
                            {items.length > 0 && (
                                <tbody className="divide-y divide-slate-800/60">
                                    {items.map((item) => (
                                        <tr className="transition-colors hover:bg-slate-800/30" key={item.id}>
                                            <td className="px-5 py-4">
                                                <input aria-label={`انتخاب ردیف ${item.id}`} className="accent-indigo-500" type="checkbox" />
                                            </td>
                                            {item.cells.map((cell, index) => (
                                                <td className="whitespace-nowrap px-4 py-4 text-sm text-slate-300" key={`${item.id}-${columns[index]}`}>
                                                    {cell}
                                                </td>
                                            ))}
                                            <td className="px-4 py-4">
                                                <div className="flex items-center gap-1">
                                                    {item.editUrl && (
                                                        <Link href={item.editUrl}>
                                                            <Button aria-label="ویرایش" isIconOnly size="sm" variant="ghost">
                                                                <Edit3 size={15} />
                                                            </Button>
                                                        </Link>
                                                    )}
                                                    {item.deleteUrl && (
                                                        <Button
                                                            aria-label="حذف"
                                                            isIconOnly
                                                            onPress={() => {
                                                                if (window.confirm('از حذف این مورد مطمئن هستید؟')) {
                                                                    router.delete(item.deleteUrl as string);
                                                                }
                                                            }}
                                                            size="sm"
                                                            variant="danger-soft"
                                                        >
                                                            <Trash2 size={15} />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            )}
                        </table>
                    </div>

                    {items.length === 0 && <EmptyState actionLabel={createLabel} actionUrl={createUrl} title={title} />}
                </Card.Content>

                <Card.Footer className="flex items-center justify-between border-t border-slate-800/80 px-5 py-4">
                    <div className="flex items-center gap-2 text-xs text-slate-500">
                        <span>تعداد کل:</span>
                        <Chip size="sm" variant="soft">{numberFormatter.format(pagination.total)}</Chip>
                    </div>
                    <p className="text-xs text-slate-600">
                        صفحه {numberFormatter.format(pagination.currentPage)} از {numberFormatter.format(pagination.lastPage)}
                    </p>
                </Card.Footer>
            </Card>
        </AdminLayout>
    );
}
