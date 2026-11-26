import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Send } from 'lucide-react';
export default function AppLogo() {
    const { name, workspaceBranding } = usePage<SharedData>().props;
    const primaryColor = workspaceBranding?.primary_color;
    const channels = primaryColor && /^#[0-9a-fA-F]{6}$/.test(primaryColor)
        ? [0, 2, 4].map((offset) => parseInt(primaryColor.slice(offset + 1, offset + 3), 16) / 255)
        : null;
    const luminance = channels?.map((channel) => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4)
        .reduce((sum, channel, index) => sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
    const logoStyle = channels ? { backgroundColor: primaryColor, color: (luminance ?? 0) > 0.179 ? '#111827' : '#ffffff' } : undefined;

    return (
        <>
            <div className="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-xl" style={logoStyle}>
                <Send className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="text-base font-semibold tracking-tight">{name}</span>
                <span className="text-muted-foreground text-xs">Email workspace</span>
            </div>
        </>
    );
}
