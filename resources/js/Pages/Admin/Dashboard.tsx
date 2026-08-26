import { Card, Chip } from '@heroui/react';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpLeft,
    Boxes,
    CircleDollarSign,
    ClipboardList,
    PackageOpen,
    ShieldAlert,
    ShoppingBag,
    Users,
    type LucideIcon,
} from 'lucide-react';

import AdminLayout from '../../Layouts/AdminLayout';

interface Stat {
    key: 'revenue' | 'orders' | 'users' | 'products';
    label: string;
    value: number;
    format: 'currency' | 'number';
    change: number;
}

interface HealthItem {
    label: string;
    value: number;
    tone: 'warning' | 'primary' | 'danger' | 'secondary';
}

interface DashboardProps {
    stats: Stat[];
    health: HealthItem[];
    recentOrders: unknown[];
    activities: unknown[];
}

const statIcons: Record<Stat['key'], LucideIcon> = {
    revenue: CircleDollarSign,
    orders: ClipboardList,
    users: Users,
    products: ShoppingBag,
};

const healthIcons: LucideIcon[] = [PackageOpen, Boxes, ShieldAlert, ClipboardList];

const numberFormatter = new Intl.NumberFormat('fa-IR');

const formatStat = (stat: Stat) => {
    const value = numberFormatter.format(stat.value);
    return stat.format === 'currency' ? `${value} تومان` : value;
};

export default function Dashboard({ stats, health }: DashboardProps) {
    return (
        <AdminLayout description="تصویری سریع از وضعیت فروشگاه، کاربران و عملیات پلتفرم" title="داشبورد">
            <Head title="داشبورد مدیریت" />

            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat) => {
                    const Icon = statIcons[stat.key];

                    return (
                        <Card className="border border-slate-800/80 bg-slate-900/55 shadow-xl shadow-black/10" key={stat.key} variant="secondary">
                            <Card.Content className="p-5">
                                <div className="flex items-start justify-between">
                                    <div className="grid size-11 place-items-center rounded-xl bg-indigo-500/10 text-indigo-400">
                                        <Icon aria-hidden="true" size={21} />
                                    </div>
                                    <Chip color={stat.change > 0 ? 'success' : 'default'} size="sm" variant="soft">
                                        {stat.change > 0 ? `+${stat.change}%` : 'بدون تغییر'}
                                    </Chip>
                                </div>
                                <p className="mt-6 text-sm font-medium text-slate-500">{stat.label}</p>
                                <p className="mt-2 text-2xl font-black tracking-tight text-white">{formatStat(stat)}</p>
                            </Card.Content>
                        </Card>
                    );
                })}
            </section>

            <section className="mt-6 grid gap-6 xl:grid-cols-[1.55fr_1fr]">
                <Card className="border border-slate-800/80 bg-slate-900/55" variant="secondary">
                    <Card.Header className="flex items-center justify-between border-b border-slate-800/80 p-5">
                        <div>
                            <Card.Title className="text-base font-black text-white">عملکرد فروش</Card.Title>
                            <Card.Description className="mt-1 text-xs text-slate-500">درآمد ۳۰ روز گذشته</Card.Description>
                        </div>
                        <Chip size="sm" variant="soft">۳۰ روز اخیر</Chip>
                    </Card.Header>
                    <Card.Content className="p-5">
                        <div className="flex h-64 flex-col items-center justify-center rounded-xl border border-dashed border-slate-700/80 bg-slate-950/30 text-center">
                            <div className="grid size-12 place-items-center rounded-xl bg-slate-800/70 text-slate-500">
                                <CircleDollarSign size={23} />
                            </div>
                            <p className="mt-4 text-sm font-bold text-slate-300">نمودار با اولین سفارش شکل می‌گیرد</p>
                            <p className="mt-2 text-xs text-slate-600">داده‌های واقعی فروش به‌صورت روزانه اینجا نمایش داده می‌شوند.</p>
                        </div>
                    </Card.Content>
                </Card>

                <Card className="border border-slate-800/80 bg-slate-900/55" variant="secondary">
                    <Card.Header className="border-b border-slate-800/80 p-5">
                        <Card.Title className="text-base font-black text-white">نیازمند توجه</Card.Title>
                        <Card.Description className="mt-1 text-xs text-slate-500">موارد عملیاتی باز</Card.Description>
                    </Card.Header>
                    <Card.Content className="divide-y divide-slate-800/70 p-0">
                        {health.map((item, index) => {
                            const Icon = healthIcons[index];
                            return (
                                <div className="flex items-center gap-3 p-4" key={item.label}>
                                    <div className="grid size-10 place-items-center rounded-xl bg-slate-800/70 text-slate-400">
                                        <Icon size={19} />
                                    </div>
                                    <p className="flex-1 text-sm font-medium text-slate-300">{item.label}</p>
                                    <span className="text-lg font-black text-white">{numberFormatter.format(item.value)}</span>
                                </div>
                            );
                        })}
                    </Card.Content>
                </Card>
            </section>

            <section className="mt-6 grid gap-6 xl:grid-cols-[1.55fr_1fr]">
                <Card className="border border-slate-800/80 bg-slate-900/55" variant="secondary">
                    <Card.Header className="flex items-center justify-between border-b border-slate-800/80 p-5">
                        <Card.Title className="text-base font-black text-white">آخرین سفارش‌ها</Card.Title>
                        <Link className="flex items-center gap-1 text-xs font-bold text-indigo-400 hover:text-indigo-300" href="/admin/orders">
                            مشاهده همه <ArrowUpLeft size={14} />
                        </Link>
                    </Card.Header>
                    <Card.Content className="flex h-52 flex-col items-center justify-center text-center">
                        <ClipboardList className="text-slate-700" size={32} />
                        <p className="mt-3 text-sm font-bold text-slate-400">سفارشی ثبت نشده است</p>
                    </Card.Content>
                </Card>

                <Card className="border border-slate-800/80 bg-gradient-to-br from-indigo-500/15 to-slate-900/60" variant="secondary">
                    <Card.Content className="flex h-full min-h-52 flex-col justify-between p-6">
                        <div>
                            <Chip color="accent" size="sm" variant="soft">شروع سریع</Chip>
                            <h2 className="mt-5 text-xl font-black text-white">کاتالوگ فروشگاه را بسازید</h2>
                            <p className="mt-3 text-sm leading-7 text-slate-400">ابتدا دسته‌بندی، برند و بازی‌ها را تعریف کنید؛ سپس اولین محصول را منتشر کنید.</p>
                        </div>
                        <Link className="mt-6 flex items-center gap-2 text-sm font-bold text-indigo-300" href="/admin/products">
                            رفتن به محصولات <ArrowUpLeft size={16} />
                        </Link>
                    </Card.Content>
                </Card>
            </section>
        </AdminLayout>
    );
}
