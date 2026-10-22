import { Send } from 'lucide-react';
export default function AppLogo() {
    return (
        <>
            <div className="flex size-9 items-center justify-center rounded-xl bg-indigo-600 text-white">
                <Send className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="text-base font-semibold tracking-tight">Dispatch</span>
                <span className="text-muted-foreground text-xs">Email workspace</span>
            </div>
        </>
    );
}
