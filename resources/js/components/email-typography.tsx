import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { Editor } from '@tiptap/core';
import { ChevronDown } from 'lucide-react';
export const emailFonts = [
    ['Arial', 'Arial, Helvetica, sans-serif'],
    ['Verdana', 'Verdana, Geneva, sans-serif'],
    ['Tahoma', 'Tahoma, Arial, sans-serif'],
    ['Georgia', 'Georgia, serif'],
    ['Times New Roman', '"Times New Roman", Times, serif'],
];
export const emailSizes = [10, 12, 14, 16, 18, 20, 24, 28, 32, 36];
function hex(value: string) {
    if (/^#[0-9a-f]{6}$/i.test(value)) return value;
    const rgb = value.match(/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/);
    return rgb
        ? '#' +
              rgb
                  .slice(1)
                  .map((v) => Number(v).toString(16).padStart(2, '0'))
                  .join('')
        : '#1e293b';
}
export function EmailTypography({ editor, font, size, color }: { editor: Editor; font: string; size: string; color: string }) {
    const set = (attr: string, value: string) =>
        editor
            .chain()
            .focus()
            .setMark('emailTextStyle', { [attr]: value })
            .run();
    const label = emailFonts.find(([, css]) => css.replaceAll('"', '').toLowerCase() === font.replaceAll('"', '').toLowerCase())?.[0] ?? 'Font';
    return (
        <div className="flex basis-full items-center gap-1 border-b pb-1 sm:basis-auto sm:border-0 sm:pb-0">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button type="button" variant="ghost" aria-label="Font family" className="h-10 w-24 justify-between px-2 text-xs sm:w-32">
                        {label}
                        <ChevronDown className="size-3" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    {emailFonts.map(([name, css]) => (
                        <DropdownMenuItem key={name} onSelect={() => set('fontFamily', css)} style={{ fontFamily: css }}>
                            {name}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button type="button" variant="ghost" aria-label="Font size" className="h-10 w-16 justify-between px-2 text-xs">
                        {size || 'Size'}
                        <ChevronDown className="size-3" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    {emailSizes.map((n) => (
                        <DropdownMenuItem key={n} onSelect={() => set('fontSize', `${n}px`)}>
                            {n} px
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
            <label
                className="hover:bg-accent relative flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-md"
                title="Text colour"
            >
                <span aria-hidden="true" className="border-b-4 px-1 font-semibold" style={{ borderColor: hex(color) }}>
                    A
                </span>
                <input
                    aria-label="Text colour"
                    type="color"
                    value={hex(color)}
                    onChange={(e) => set('color', e.target.value)}
                    className="absolute inset-0 size-full cursor-pointer opacity-0 focus-visible:opacity-100"
                />
            </label>
        </div>
    );
}
