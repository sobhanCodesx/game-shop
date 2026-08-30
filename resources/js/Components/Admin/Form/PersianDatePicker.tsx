import { CalendarDays } from "lucide-react";
import DatePicker from "react-multi-date-picker";
import DateObject from "react-date-object";
import gregorian from "react-date-object/calendars/gregorian";
import persian from "react-date-object/calendars/persian";
import persianFa from "react-date-object/locales/persian_fa";
import { useMemo } from "react";
import "react-multi-date-picker/styles/backgrounds/bg-dark.css";
import "react-multi-date-picker/styles/colors/purple.css";

interface PersianDatePickerProps {
    label: string;
    name?: string;
    description?: string;
    error?: string;
    value: string;
    onChange: (value: string) => void;
    maximumToday?: boolean;
    variant?: "admin" | "storefront";
}

export default function PersianDatePicker({
    label,
    name = "persian_date_picker",
    description,
    error,
    value,
    onChange,
    maximumToday = false,
    variant = "admin",
}: PersianDatePickerProps) {
    const selectedDate = useMemo(
        () =>
            value
                ? new DateObject({
                      date: value,
                      format: "YYYY-MM-DD",
                      calendar: gregorian,
                  }).convert(persian)
                : null,
        [value],
    );

    const handleChange = (date: DateObject | null) => {
        if (!date) {
            onChange("");
            return;
        }

        onChange(
            new DateObject({ date, calendar: persian })
                .convert(gregorian)
                .format("YYYY-MM-DD"),
        );
    };

    return (
        <div className="space-y-1.5">
            <label className={`block font-bold ${variant === "storefront" ? "text-xs text-[var(--store-text)]" : "text-sm text-slate-200"}`}>
                {label}
            </label>
            <div className="relative">
                <DatePicker
                    arrow={false}
                    calendar={persian}
                    calendarPosition="bottom-right"
                    className="bg-dark purple"
                    containerClassName="w-full"
                    editable={false}
                    fixMainPosition
                    fixRelativePosition
                    format="YYYY/MM/DD"
                    inputClass={`${variant === "storefront" ? "h-12 rounded-2xl border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-text)]" : "h-10 rounded-xl border-slate-700 bg-slate-950/50 text-slate-100"} w-full border px-4 pl-11 text-sm outline-none transition placeholder:text-slate-500 focus:ring-2 ${
                        error
                            ? "border-rose-500 focus:ring-rose-500/20"
                            : "focus:border-indigo-500 focus:ring-indigo-500/20"
                    }`}
                    locale={persianFa}
                    maxDate={maximumToday ? new DateObject({ calendar: persian }) : undefined}
                    name={name}
                    onChange={handleChange}
                    onOpenPickNewDate={false}
                    placeholder="انتخاب تاریخ شمسی"
                    value={selectedDate}
                    zIndex={60}
                />
                <CalendarDays
                    className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-indigo-400"
                    size={18}
                />
            </div>
            {description && (
                <p className="text-xs leading-6 text-slate-500">
                    {description}
                </p>
            )}
            {error && <p className="text-xs text-rose-400">{error}</p>}
        </div>
    );
}
