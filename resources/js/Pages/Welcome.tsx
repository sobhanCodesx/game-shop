import { Head } from '@inertiajs/react';

interface WelcomeProps {
    laravelVersion: string;
    phpVersion: string;
}

export default function Welcome({ laravelVersion, phpVersion }: WelcomeProps) {
    return (
        <>
            <Head title="خوش آمدید" />

            <main className="flex min-h-screen items-center justify-center bg-slate-950 px-6 text-slate-100">
                <section className="w-full max-w-2xl rounded-2xl border border-white/10 bg-white/5 p-10 text-center shadow-2xl">
                    <span className="rounded-full bg-indigo-500/15 px-4 py-2 text-sm font-medium text-indigo-300">
                        نصب با موفقیت انجام شد
                    </span>

                    <h1 className="mt-6 text-4xl font-bold tracking-tight sm:text-5xl">
                        Laravel + Inertia + React
                    </h1>

                    <p className="mt-4 text-lg text-slate-300">
                        پروژه برای توسعه با React و TypeScript آماده است.
                    </p>

                    <div className="mt-8 flex justify-center gap-3 text-sm text-slate-400">
                        <span>Laravel {laravelVersion}</span>
                        <span aria-hidden="true">•</span>
                        <span>PHP {phpVersion}</span>
                    </div>
                </section>
            </main>
        </>
    );
}
