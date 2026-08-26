const ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
const teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
const tens = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
const hundreds = ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
const scales = ['', 'هزار', 'میلیون', 'میلیارد', 'تریلیون'];

const join = (parts: string[]) => parts.filter(Boolean).join(' و ');

const underThousand = (value: number) => {
    const hundred = Math.floor(value / 100);
    const remainder = value % 100;

    if (remainder < 10) return join([hundreds[hundred], ones[remainder]]);
    if (remainder < 20) return join([hundreds[hundred], teens[remainder - 10]]);

    return join([hundreds[hundred], tens[Math.floor(remainder / 10)], ones[remainder % 10]]);
};

export const numberToPersianWords = (input: number): string => {
    if (!Number.isFinite(input) || input < 0) return '';
    if (input === 0) return 'صفر';

    const parts: string[] = [];
    let value = Math.floor(input);
    let scale = 0;

    while (value > 0 && scale < scales.length) {
        const chunk = value % 1000;
        if (chunk) parts.unshift(join([underThousand(chunk), scales[scale]]));
        value = Math.floor(value / 1000);
        scale += 1;
    }

    return join(parts);
};

export const normalizeDigits = (input: string): string =>
    input
        .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
        .replace(/[٬,\s]/g, '')
        .replace(/[^0-9]/g, '');
