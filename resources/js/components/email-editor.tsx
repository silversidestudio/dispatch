import { Button } from '@/components/ui/button';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect } from 'react';
export function EmailEditor({ value, onChange, editable = true }: { value: string; onChange: (html: string) => void; editable?: boolean }) {
    const editor = useEditor({
        extensions: [
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            StarterKit.configure({ link: false }),
            Link.extend({
                addAttributes() {
                    return { ...this.parent?.(), style: { default: null } };
                },
            }).configure({ openOnClick: false, isAllowedUri: (url, ctx) => url === '{{unsubscribe_url}}' || ctx.defaultValidate(url) }),
            Image.configure({ allowBase64: false }),
        ],
        content: value,
        editable,
        immediatelyRender: false,
        editorProps: { attributes: { class: 'min-h-80 p-5 outline-none', role: 'textbox', 'aria-label': 'Email content' } },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });
    useEffect(() => {
        if (editor && editor.getHTML() !== value) editor.commands.setContent(value, { emitUpdate: false });
    }, [editor, value]);
    if (!editor) return <p role="status">Loading email editor…</p>;
    return (
        <div className="email-editor overflow-hidden rounded-xl border">
            <div className="bg-muted/30 flex flex-wrap gap-1 border-b p-2">
                {editable && (
                    <>
                        {['left', 'center', 'right'].map((align) => (
                            <Button
                                key={align}
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => editor.chain().focus().setTextAlign(align).run()}
                            >
                                {align[0].toUpperCase() + align.slice(1)}
                            </Button>
                        ))}
                        <Button type="button" variant="ghost" size="sm" onClick={() => editor.chain().focus().toggleBold().run()}>
                            Bold
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={() => editor.chain().focus().toggleItalic().run()}>
                            Italic
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}>
                            Heading
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={() => editor.chain().focus().setParagraph().run()}>
                            Paragraph
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={() => editor.chain().focus().toggleBulletList().run()}>
                            List
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={() => editor.chain().focus().setHorizontalRule().run()}>
                            Section divider
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                const href = prompt('HTTPS link URL');
                                if (href?.startsWith('https://')) editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
                            }}
                        >
                            Link
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                const href = prompt('HTTPS button URL');
                                if (href?.startsWith('https://'))
                                    editor
                                        .chain()
                                        .focus()
                                        .insertContent({
                                            type: 'paragraph',
                                            content: [
                                                {
                                                    type: 'text',
                                                    text: 'Read more',
                                                    marks: [
                                                        {
                                                            type: 'link',
                                                            attrs: { href, style: 'background-color:#4f46e5;color:#ffffff;padding:12px' },
                                                        },
                                                    ],
                                                },
                                            ],
                                        })
                                        .run();
                            }}
                        >
                            Button
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                const src = prompt('HTTPS image URL');
                                if (src?.startsWith('https://')) editor.chain().focus().setImage({ src, alt: 'Email image' }).run();
                            }}
                        >
                            Image
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                editor
                                    .chain()
                                    .focus()
                                    .insertContent('<p>{{sender_name}} · {{sender_address}}</p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>')
                                    .run()
                            }
                        >
                            Sender footer
                        </Button>
                        <Button type="button" variant="ghost" size="sm" onClick={() => editor.chain().focus().insertContent('{{first_name}}').run()}>
                            First name
                        </Button>
                    </>
                )}
            </div>
            <EditorContent editor={editor} />
            <p className="text-muted-foreground border-t px-4 py-3 text-xs">
                Sanitised on save. Personalisation:{' '}
                {'{{first_name}}, {{last_name}}, {{email}}, {{sender_name}}, {{sender_address}}, {{unsubscribe_url}}'}.
            </p>
        </div>
    );
}
