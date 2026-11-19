import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function BrandColorControl({
    label,
    value,
    onChange,
    fallbackColor = '#21564b',
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    fallbackColor?: string;
}) {
    const id = label.toLowerCase().replaceAll(' ', '-');
    const validColor = /^#[0-9a-fA-F]{6}$/.test(value);
    const previewColor = validColor ? value : fallbackColor;

    return (
        <div className="space-y-2">
            <Label htmlFor={`${id}-hex`}>{label}</Label>
            <div className="flex items-center gap-3">
                <Input
                    id={`${id}-picker`}
                    type="color"
                    className="h-11 w-16 shrink-0 cursor-pointer p-1"
                    value={previewColor}
                    onChange={(event) => onChange(event.target.value)}
                    aria-label={`${label} colour picker`}
                />
                <Input
                    id={`${id}-hex`}
                    className="min-w-0 font-mono uppercase"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    aria-label={`${label} hex value`}
                    aria-describedby={`${id}-preview`}
                />
            </div>
            <div id={`${id}-preview`} className="text-muted-foreground flex items-center gap-2 text-xs" aria-live="polite">
                <span className="size-4 rounded border" style={{ backgroundColor: previewColor }} aria-hidden="true" />
                {validColor ? value.toUpperCase() : 'Enter a six-digit hex colour, for example #230EF4.'}
            </div>
        </div>
    );
}
