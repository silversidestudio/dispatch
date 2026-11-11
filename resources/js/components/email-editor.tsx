import { EmailButton, EmailImage, EmailSection, EmailStyles, EmailTextStyle, safeHttps } from '@/components/email-nodes';
import { EmailTypography } from '@/components/email-typography';
import { Field, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import Link from '@tiptap/extension-link';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { NodeSelection } from '@tiptap/pm/state';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Braces,
    ChevronDown,
    Ellipsis,
    Heading,
    ImageIcon,
    Italic,
    LinkIcon,
    List,
    ListOrdered,
    Minus,
    MousePointerClick,
    Pilcrow,
    Redo2,
    Trash2,
    Underline,
    Undo2,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useState } from 'react';
const variables = [
    ['First name', 'first_name'],
    ['Last name', 'last_name'],
    ['Email address', 'email'],
    ['Sender name', 'sender_name'],
    ['Sender postal address', 'sender_address'],
    ['Unsubscribe URL', 'unsubscribe_url'],
];
type Mode = 'button' | 'image' | 'link' | null;
function Tool({
    label,
    icon: Icon,
    action,
    active = false,
    disabled = false,
    className = '',
    shortcut,
}: {
    label: string;
    icon: LucideIcon;
    action: () => void;
    active?: boolean;
    disabled?: boolean;
    className?: string;
    shortcut?: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    aria-label={label}
                    aria-pressed={active}
                    disabled={disabled}
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={action}
                    className={cn('size-10 shrink-0 rounded-md', active && 'bg-primary/10 text-primary', className)}
                >
                    <Icon className="size-4" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {label}
                {shortcut && <span className="ml-3 opacity-60">{shortcut}</span>}
            </TooltipContent>
        </Tooltip>
    );
}
export function EmailEditor({
    value,
    onChange,
    editable = true,
    buttonColour = '#21564b',
    onReady,
}: {
    value: string;
    onChange: (html: string) => void;
    editable?: boolean;
    buttonColour?: string;
    onReady?: (html: string) => void;
}) {
    const [mode, setMode] = useState<Mode>(null);
    const [form, setForm] = useState({
        label: 'Find out more',
        href: 'https://',
        background: buttonColour,
        color: '#ffffff',
        align: 'left',
        radius: '8',
        src: '',
        width: '520',
        alt: '',
        link: '',
    });
    const [error, setError] = useState('');
    const [range, setRange] = useState({ from: 0, to: 0 });
    const [editing, setEditing] = useState(false);
    const editor = useEditor({
        extensions: [
            StarterKit.configure({ link: false }),
            Link.extend({
                addAttributes() {
                    return { ...this.parent?.(), style: { default: null } };
                },
            }).configure({ openOnClick: false, isAllowedUri: (url) => url === '{{unsubscribe_url}}' || safeHttps(url) || url.startsWith('mailto:') }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            TableKit.configure({ table: { resizable: false } }),
            EmailStyles,
            EmailTextStyle,
            EmailSection,
            EmailButton,
            EmailImage,
        ],
        content: value,
        editable,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'email-canvas min-h-[420px] outline-none',
                role: 'textbox',
                'aria-label': 'Email content',
                'data-placeholder': 'Start with a headline. Write something your audience will value.',
            },
            handleClickOn(view, _pos, node, nodePos, event, direct) {
                if (direct && ['emailButton', 'image'].includes(node.type.name)) {
                    event.preventDefault();
                    view.dispatch(view.state.tr.setSelection(NodeSelection.create(view.state.doc, nodePos)));
                    return false;
                }
                return false;
            },
            handleDOMEvents: {
                click: (_view, e) => {
                    if ((e.target as HTMLElement).closest('a')) {
                        e.preventDefault();
                    }
                    return false;
                },
            },
        },
        onCreate: ({ editor }) => onReady?.(editor.getHTML()),
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });
    const state = useEditorState({
        editor,
        selector: ({ editor: e }) =>
            e
                ? {
                      bold: e.isActive('bold'),
                      underline: e.isActive('underline'),
                      font: e.getAttributes('emailTextStyle').fontFamily ?? '',
                      size: e.getAttributes('emailTextStyle').fontSize ?? '',
                      color: e.getAttributes('emailTextStyle').color ?? '',
                      canBold: e.can().toggleBold(),
                      canItalic: e.can().toggleItalic(),
                      italic: e.isActive('italic'),
                      h1: e.isActive('heading', { level: 1 }),
                      h2: e.isActive('heading', { level: 2 }),
                      h3: e.isActive('heading', { level: 3 }),
                      bullet: e.isActive('bulletList'),
                      ordered: e.isActive('orderedList'),
                      left: e.isActive({ textAlign: 'left' }),
                      center: e.isActive({ textAlign: 'center' }),
                      right: e.isActive({ textAlign: 'right' }),
                      undo: e.can().undo(),
                      redo: e.can().redo(),
                      button: e.isActive('emailButton'),
                      image: e.isActive('image'),
                  }
                : null,
    });
    useEffect(() => {
        if (editor && editor.getHTML() !== value) editor.commands.setContent(value, { emitUpdate: false });
    }, [editor, value]);
    useEffect(() => {
        editor?.setEditable(editable);
    }, [editor, editable]);
    if (!editor || !state) return <p role="status">Loading email editor…</p>;
    const open = (next: Mode) => {
        const selected = next === 'button' ? editor.isActive('emailButton') : next === 'image' ? editor.isActive('image') : editor.isActive('link');
        const attrs =
            next === 'button' ? editor.getAttributes('emailButton') : next === 'image' ? editor.getAttributes('image') : editor.getAttributes('link');
        setRange({ from: editor.state.selection.from, to: editor.state.selection.to });
        setEditing(selected);
        setError('');
        setForm({
            label:
                next === 'link'
                    ? editor.state.doc.textBetween(editor.state.selection.from, editor.state.selection.to, ' ') || 'Read more'
                    : (attrs.label ?? 'Find out more'),
            href: attrs.href ?? 'https://',
            background: attrs.background ?? buttonColour,
            color: attrs.color ?? '#ffffff',
            align: attrs.align ?? (next === 'image' ? 'center' : 'left'),
            radius: String(attrs.radius ?? 8),
            src: attrs.src ?? '',
            width: String(attrs.width ?? 520),
            alt: attrs.alt ?? '',
            link: attrs.link ?? '',
        });
        setMode(next);
    };
    const apply = () => {
        if (
            mode === 'image' &&
            ((!safeHttps(form.src) && !(editing && /^\/email-assets\/[a-z0-9-]+\.png$/.test(form.src))) || (form.link && !safeHttps(form.link)))
        ) {
            setError('Use HTTPS image and destination URLs without credentials.');
            return;
        }
        if (
            mode !== 'image' &&
            !safeHttps(form.href) &&
            !(mode === 'link' && (form.href === '{{unsubscribe_url}}' || /^mailto:[^\s<>]+@[^\s<>]+$/i.test(form.href)))
        ) {
            setError('Use an HTTPS destination URL without credentials.');
            return;
        }
        if (mode === 'button' && (!form.label.trim() || !/^#[0-9a-f]{6}$/i.test(form.background) || !/^#[0-9a-f]{6}$/i.test(form.color))) {
            setError('Enter a button label and valid six-digit colours.');
            return;
        }
        const chain = editor.chain().focus().setTextSelection(range);
        if (mode === 'link') {
            if (range.from === range.to && !editing)
                chain.insertContent({ type: 'text', text: form.label || form.href, marks: [{ type: 'link', attrs: { href: form.href } }] }).run();
            else chain.extendMarkRange('link').setLink({ href: form.href }).run();
        } else {
            const type = mode === 'button' ? 'emailButton' : 'image';
            if (editing) editor.chain().focus().setNodeSelection(range.from).updateAttributes(type, form).run();
            else chain.insertContent({ type, attrs: form }).run();
        }
        setMode(null);
    };
    const typography = (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="ghost" size="icon" aria-label="Text style" className="size-10 shrink-0">
                    <Heading className="size-4" />
                    <ChevronDown className="size-3" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuLabel>Typography</DropdownMenuLabel>
                <DropdownMenuItem onSelect={() => editor.chain().focus().setParagraph().run()}>
                    <Pilcrow className="mr-2 size-4" />
                    Paragraph
                </DropdownMenuItem>
                {([1, 2, 3] as const).map((level) => (
                    <DropdownMenuItem key={level} onSelect={() => editor.chain().focus().toggleHeading({ level }).run()}>
                        Heading {level}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
    const more = (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" size="icon" variant="ghost" aria-label="More editor tools" className="size-10">
                    <Ellipsis className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="max-h-[70dvh] overflow-y-auto">
                <DropdownMenuLabel>Insert & format</DropdownMenuLabel>
                <DropdownMenuItem onSelect={() => open('image')}>Insert image</DropdownMenuItem>
                <DropdownMenuItem onSelect={() => editor.chain().focus().setParagraph().run()}>Paragraph</DropdownMenuItem>
                {([1, 2, 3] as const).map((level) => (
                    <DropdownMenuItem key={level} onSelect={() => editor.chain().focus().toggleHeading({ level }).run()}>
                        Heading {level}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                {variables.map(([label, token]) => (
                    <DropdownMenuItem key={token} onSelect={() => editor.chain().focus().insertContent(`{{${token}}}`).run()}>
                        Insert {label.toLowerCase()}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={() => open('button')}>
                    <MousePointerClick className="mr-2 size-4" />
                    Call-to-action button
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => editor.chain().focus().setHorizontalRule().run()}>
                    <Minus className="mr-2 size-4" />
                    Divider
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={() => editor.chain().focus().toggleBulletList().run()}>
                    <List className="mr-2 size-4" />
                    Bullet list
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => editor.chain().focus().toggleOrderedList().run()}>
                    <ListOrdered className="mr-2 size-4" />
                    Numbered list
                </DropdownMenuItem>
                {(['left', 'center', 'right'] as const).map((a) => (
                    <DropdownMenuItem key={a} onSelect={() => editor.chain().focus().setTextAlign(a).run()}>
                        Align {a}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    onSelect={() =>
                        editor
                            .chain()
                            .focus()
                            .insertContent('<p>{{sender_name}} · {{sender_address}}</p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>')
                            .run()
                    }
                >
                    Insert sender footer
                </DropdownMenuItem>
                <DropdownMenuItem disabled={!state.undo} onSelect={() => editor.chain().focus().undo().run()}>
                    Undo
                </DropdownMenuItem>
                <DropdownMenuItem disabled={!state.redo} onSelect={() => editor.chain().focus().redo().run()}>
                    Redo
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
    return (
        <div className="email-editor min-w-0 overflow-hidden rounded-xl border">
            {editable && (
                <TooltipProvider delayDuration={250}>
                    <div role="toolbar" aria-label="Email formatting" className="bg-background flex flex-wrap items-center gap-0.5 border-b p-2">
                        <EmailTypography editor={editor} font={state.font} size={state.size} color={state.color} />
                        <div className="hidden sm:block">{typography}</div>
                        <Separator orientation="vertical" className="mx-1 hidden h-6 sm:block" />
                        <Tool
                            label="Bold"
                            icon={Bold}
                            active={state.bold}
                            disabled={!state.canBold}
                            action={() => editor.chain().focus().toggleBold().run()}
                            shortcut="⌘/Ctrl B"
                        />
                        <Tool
                            label="Italic"
                            icon={Italic}
                            active={state.italic}
                            disabled={!state.canItalic}
                            action={() => editor.chain().focus().toggleItalic().run()}
                            shortcut="⌘/Ctrl I"
                        />
                        <Tool
                            label="Underline"
                            icon={Underline}
                            active={state.underline}
                            action={() => editor.chain().focus().toggleUnderline().run()}
                            shortcut="⌘/Ctrl U"
                        />
                        <Tool label="Insert link" icon={LinkIcon} action={() => open('link')} />
                        <Tool label="Insert button" icon={MousePointerClick} action={() => open('button')} className="hidden" />
                        <Tool label="Insert image" icon={ImageIcon} action={() => open('image')} className="hidden sm:inline-flex" />
                        <Tool
                            label="Insert divider"
                            icon={Minus}
                            action={() => editor.chain().focus().setHorizontalRule().run()}
                            className="hidden"
                        />
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Insert variable"
                                    className="hidden size-10 sm:inline-flex"
                                >
                                    <Braces className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>Personalisation</DropdownMenuLabel>
                                {variables.map(([label, token]) => (
                                    <DropdownMenuItem key={token} onSelect={() => editor.chain().focus().insertContent(`{{${token}}}`).run()}>
                                        {label}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <div className="ml-auto flex">
                            {more}
                            <Tool
                                label="Undo"
                                icon={Undo2}
                                disabled={!state.undo}
                                action={() => editor.chain().focus().undo().run()}
                                className="hidden 2xl:inline-flex"
                            />
                            <Tool
                                label="Redo"
                                icon={Redo2}
                                disabled={!state.redo}
                                action={() => editor.chain().focus().redo().run()}
                                className="hidden 2xl:inline-flex"
                            />
                        </div>
                    </div>
                </TooltipProvider>
            )}
            {editable && (state.button || state.image) && (
                <div className="bg-primary/5 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-2 text-xs">
                    <span>{state.button ? 'Button' : 'Image'} selected</span>
                    <div className="flex gap-2">
                        <Button type="button" variant="ghost" size="sm" onClick={() => open(state.button ? 'button' : 'image')}>
                            Edit {state.button ? 'button' : 'image'}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            aria-label="Remove selected block"
                            onClick={() => editor.chain().focus().deleteSelection().run()}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                </div>
            )}
            <div className="email-workspace bg-slate-100 p-2 sm:p-6 dark:bg-slate-900/50">
                <div className="mx-auto max-w-[600px] overflow-hidden rounded-sm bg-white shadow-sm">
                    <EditorContent editor={editor} />
                </div>
            </div>
            <div className="text-muted-foreground bg-background flex flex-wrap justify-between gap-2 border-t px-4 py-3 text-xs">
                <span>600 px email canvas · sanitised on save</span>
                <span>Variables use sample values in previews</span>
            </div>
            <Dialog
                open={mode !== null}
                onOpenChange={(open) => {
                    if (!open) setMode(null);
                }}
            >
                <DialogContent className="max-h-[90dvh] max-w-[calc(100%-2rem)] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit' : 'Insert'} {mode === 'button' ? 'call-to-action button' : mode === 'image' ? 'image' : 'link'}
                        </DialogTitle>
                        <DialogDescription>
                            {mode === 'image'
                                ? 'Use a publicly hosted HTTPS image. Add descriptive alternative text for accessibility.'
                                : 'Choose a clear label and a secure destination. Changes are saved with your email.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        {mode === 'image' ? (
                            <>
                                <Field label="HTTPS image URL" value={form.src} change={(src) => setForm({ ...form, src })} />
                                <Select label="Maximum image width" value={form.width} change={(width) => setForm({ ...form, width })}>
                                    {[120, 240, 320, 520].map((w) => (
                                        <option key={w} value={w}>
                                            {w === 520 ? 'Full email width' : `${w} px`}
                                        </option>
                                    ))}
                                </Select>
                                <Field label="Alternative text" value={form.alt} change={(alt) => setForm({ ...form, alt })} />
                                <Field label="Linked destination (optional)" value={form.link} change={(link) => setForm({ ...form, link })} />
                                {(safeHttps(form.src) || /^\/email-assets\/[a-z0-9-]+\.png$/.test(form.src)) && (
                                    <div className="rounded-lg border bg-slate-50 p-3">
                                        <img
                                            alt={form.alt || 'Image preview'}
                                            src={form.src}
                                            referrerPolicy="no-referrer"
                                            className="mx-auto max-h-40 max-w-full object-contain"
                                        />
                                    </div>
                                )}
                            </>
                        ) : (
                            <>
                                {mode === 'link' && range.from === range.to && !editing && (
                                    <Field label="Link text" value={form.label} change={(label) => setForm({ ...form, label })} />
                                )}
                                {mode === 'button' && (
                                    <Field label="Button label" value={form.label} change={(label) => setForm({ ...form, label })} />
                                )}
                                <Field label="Destination URL" value={form.href} change={(href) => setForm({ ...form, href })} />
                            </>
                        )}
                        {mode === 'button' && (
                            <>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field
                                        label="Background colour"
                                        type="color"
                                        value={form.background}
                                        change={(background) => setForm({ ...form, background })}
                                    />
                                    <Field label="Text colour" type="color" value={form.color} change={(color) => setForm({ ...form, color })} />
                                </div>
                                <Select label="Corner radius" value={form.radius} change={(radius) => setForm({ ...form, radius })}>
                                    {['0', '4', '8', '16', '24'].map((r) => (
                                        <option key={r} value={r}>
                                            {r} px
                                        </option>
                                    ))}
                                </Select>
                                <div className="rounded-lg border bg-white p-4 text-center">
                                    <span
                                        style={{
                                            backgroundColor: form.background,
                                            color: form.color,
                                            borderRadius: `${form.radius}px`,
                                            padding: '14px 24px',
                                            display: 'inline-block',
                                            fontWeight: 700,
                                        }}
                                    >
                                        {form.label || 'Button preview'}
                                    </span>
                                </div>
                            </>
                        )}
                        {mode !== 'link' && (
                            <Select label="Alignment" value={form.align} change={(align) => setForm({ ...form, align })}>
                                <option value="left">Left</option>
                                <option value="center">Centre</option>
                                <option value="right">Right</option>
                            </Select>
                        )}
                        {error && (
                            <p role="alert" className="text-destructive text-sm">
                                {error}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        {editing && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    if (mode === 'link') editor.chain().focus().setTextSelection(range).extendMarkRange('link').unsetLink().run();
                                    else editor.chain().focus().setNodeSelection(range.from).deleteSelection().run();
                                    setMode(null);
                                }}
                            >
                                Remove {mode}
                            </Button>
                        )}
                        <Button type="button" variant="outline" onClick={() => setMode(null)}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={apply}>
                            Apply {mode}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
