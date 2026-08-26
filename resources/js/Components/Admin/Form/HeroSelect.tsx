import { FieldError, Label, ListBox, Select } from "@heroui/react";
import { Check, ChevronDown } from "lucide-react";

interface SelectOption {
    id: string;
    label: string;
    description?: string;
}

interface HeroSelectProps {
    label: string;
    options: SelectOption[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    error?: string;
    required?: boolean;
}

export default function HeroSelect({
    label,
    options,
    value,
    onChange,
    placeholder,
    error,
    required,
}: HeroSelectProps) {
    return (
        <Select
            fullWidth
            isInvalid={Boolean(error)}
            onSelectionChange={(key) => onChange(key?.toString() ?? "")}
            placeholder={placeholder}
            selectedKey={value || null}
        >
            <Label isRequired={required}>{label}</Label>
            <Select.Trigger>
                <Select.Value />
                <Select.Indicator>
                    <ChevronDown size={16} />
                </Select.Indicator>
            </Select.Trigger>
            <Select.Popover>
                <ListBox>
                    {options.map((option) => (
                        <ListBox.Item
                            id={option.id}
                            key={option.id}
                            textValue={option.label}
                        >
                            <div className="flex flex-1 items-center justify-between gap-3">
                                <div>
                                    <p>{option.label}</p>
                                    {option.description && (
                                        <p className="text-xs text-slate-500">
                                            {option.description}
                                        </p>
                                    )}
                                </div>
                                <ListBox.ItemIndicator>
                                    <Check size={15} />
                                </ListBox.ItemIndicator>
                            </div>
                        </ListBox.Item>
                    ))}
                </ListBox>
            </Select.Popover>
            {error && <FieldError>{error}</FieldError>}
        </Select>
    );
}
