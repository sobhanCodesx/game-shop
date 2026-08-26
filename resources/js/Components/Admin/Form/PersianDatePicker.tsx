import {
    Calendar,
    DateField,
    DatePicker,
    Description,
    FieldError,
    Label,
} from "@heroui/react";
import { parseDate, type DateValue } from "@internationalized/date";
import { I18nProvider } from "react-aria-components";

interface PersianDatePickerProps {
    label: string;
    description?: string;
    error?: string;
    value: string;
    onChange: (value: string) => void;
}

export default function PersianDatePicker({
    label,
    description,
    error,
    value,
    onChange,
}: PersianDatePickerProps) {
    const parsedValue = value ? parseDate(value) : null;

    const handleChange = (date: DateValue | null) => {
        onChange(date?.toString() ?? "");
    };

    return (
        <I18nProvider locale="fa-IR-u-ca-persian">
            <DatePicker
                className="w-full"
                isInvalid={Boolean(error)}
                onChange={handleChange}
                value={parsedValue}
            >
                <Label>{label}</Label>
                <DateField.Group className="w-full">
                    <DateField.Input className="flex-1">
                        {(segment) => <DateField.Segment segment={segment} />}
                    </DateField.Input>
                    <DateField.Suffix>
                        <DatePicker.Trigger aria-label="باز کردن تقویم شمسی">
                            <DatePicker.TriggerIndicator />
                        </DatePicker.Trigger>
                    </DateField.Suffix>
                </DateField.Group>
                {description && <Description>{description}</Description>}
                {error && <FieldError>{error}</FieldError>}
                <DatePicker.Popover>
                    <Calendar aria-label={label} firstDayOfWeek="sat">
                        <Calendar.Header>
                            <Calendar.NavButton slot="previous" />
                            <Calendar.YearPickerTrigger>
                                <Calendar.YearPickerTriggerHeading />
                                <Calendar.YearPickerTriggerIndicator />
                            </Calendar.YearPickerTrigger>
                            <Calendar.NavButton slot="next" />
                        </Calendar.Header>
                        <Calendar.Grid>
                            <Calendar.GridHeader>
                                {(day) => (
                                    <Calendar.HeaderCell>
                                        {day}
                                    </Calendar.HeaderCell>
                                )}
                            </Calendar.GridHeader>
                            <Calendar.GridBody>
                                {(date) => <Calendar.Cell date={date} />}
                            </Calendar.GridBody>
                        </Calendar.Grid>
                    </Calendar>
                </DatePicker.Popover>
            </DatePicker>
        </I18nProvider>
    );
}
