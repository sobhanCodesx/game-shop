import { Description, FieldError, Label } from "@heroui/react";
import type { ReactNode } from "react";

interface FormFieldProps {
    label: string;
    description?: string;
    error?: string;
    required?: boolean;
    children: ReactNode;
}

export default function FormField({
    label,
    description,
    error,
    required,
    children,
}: FormFieldProps) {
    return (
        <div className="space-y-2">
            <Label isInvalid={Boolean(error)} isRequired={required}>
                {label}
            </Label>
            {children}
            {description && <Description>{description}</Description>}
            {error && <FieldError>{error}</FieldError>}
        </div>
    );
}
