import { Chip, Input } from '@heroui/react';

import { normalizeDigits, numberToPersianWords } from '../../../utils/persian-number';
import FormField from './FormField';

interface PriceInputProps {
    label: string;
    description: string;
    error?: string;
    required?: boolean;
    value: number | '';
    onChange: (value: number | '') => void;
}

const formatter = new Intl.NumberFormat('fa-IR');

export default function PriceInput({ label, description, error, required, value, onChange }: PriceInputProps) {
    const numericValue = value === '' ? 0 : value;

    return (
        <FormField description={description} error={error} label={label} required={required}>
            <div className="flex items-stretch gap-2">
                <Input
                    className="min-w-0 flex-1 text-left text-base font-bold tabular-nums"
                    dir="ltr"
                    fullWidth
                    inputMode="numeric"
                    onChange={(event) => {
                        const normalized = normalizeDigits(event.target.value);
                        onChange(normalized === '' ? '' : Number(normalized));
                    }}
                    placeholder="۰"
                    value={value === '' ? '' : formatter.format(value)}
                />
                <Chip className="h-auto shrink-0 px-3 text-sm font-bold" color="accent" variant="soft">تومان</Chip>
            </div>
            <p className="min-h-6 rounded-lg bg-slate-950/30 px-3 py-1.5 text-xs leading-6 text-slate-400">
                {numericValue > 0 ? `${numberToPersianWords(numericValue)} تومان` : 'مبلغ به حروف اینجا نمایش داده می‌شود.'}
            </p>
        </FormField>
    );
}
