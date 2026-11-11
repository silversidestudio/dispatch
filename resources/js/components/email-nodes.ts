import { Extension, Mark, Node, mergeAttributes } from '@tiptap/core';

export const safeHttps = (value: string) => {
    try {
        const u = new URL(value);
        return u.protocol === 'https:' && !u.username && !u.password && !/[\s<>]/.test(value);
    } catch {
        return false;
    }
};
const colour = (value: string | null, fallback: string) => (/^#[0-9a-f]{6}$/i.test(value ?? '') ? value : fallback);
const align = (value: string | null) => (['left', 'center', 'right'].includes(value ?? '') ? value : 'left');
const style = (el: HTMLElement) => {
    const allowed = [
        'color',
        'background-color',
        'font-size',
        'font-family',
        'font-weight',
        'text-align',
        'padding',
        'margin',
        'border',
        'border-radius',
        'border-top',
        'border-left',
        'line-height',
        'letter-spacing',
        'width',
        'max-width',
        'text-decoration',
    ];
    return allowed
        .map((k) => (el.style.getPropertyValue(k) ? `${k}:${el.style.getPropertyValue(k)}` : ''))
        .filter(Boolean)
        .join(';');
};
export const EmailTextStyle = Mark.create({
    name: 'emailTextStyle',
    addAttributes: () => ({
        style: { default: null, parseHTML: (el) => style(el) },
        fontFamily: {
            default: null,
            parseHTML: (el) => el.style.fontFamily || null,
            renderHTML: (a) => (a.fontFamily ? { style: `font-family:${a.fontFamily}` } : {}),
        },
        fontSize: {
            default: null,
            parseHTML: (el) => el.style.fontSize || null,
            renderHTML: (a) => (a.fontSize ? { style: `font-size:${a.fontSize}` } : {}),
        },
        color: { default: null, parseHTML: (el) => el.style.color || null, renderHTML: (a) => (a.color ? { style: `color:${a.color}` } : {}) },
    }),
    parseHTML: () => [{ tag: 'span[style]' }],
    renderHTML: ({ HTMLAttributes }) => ['span', HTMLAttributes, 0],
});
export const EmailStyles = Extension.create({
    name: 'emailStyles',
    addGlobalAttributes() {
        return [
            {
                types: ['paragraph', 'heading', 'table', 'tableCell', 'tableHeader', 'bulletList', 'orderedList', 'listItem', 'blockquote'],
                attributes: {
                    style: { default: null, parseHTML: (el) => style(el), renderHTML: (attrs) => (attrs.style ? { style: attrs.style } : {}) },
                    align: {
                        default: null,
                        parseHTML: (el) => el.getAttribute('align'),
                        renderHTML: (attrs) => (attrs.align ? { align: attrs.align } : {}),
                    },
                    bgcolor: {
                        default: null,
                        parseHTML: (el) => el.getAttribute('bgcolor'),
                        renderHTML: (attrs) => (attrs.bgcolor ? { bgcolor: attrs.bgcolor } : {}),
                    },
                    width: {
                        default: null,
                        parseHTML: (el) => el.getAttribute('width'),
                        renderHTML: (attrs) => (attrs.width ? { width: attrs.width } : {}),
                    },
                },
            },
        ];
    },
});
export const EmailSection = Node.create({
    name: 'emailSection',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes: () => ({ style: { default: null, parseHTML: (el) => style(el) } }),
    parseHTML: () => [{ tag: 'div:not([data-email-image])' }],
    renderHTML: ({ HTMLAttributes }) => ['div', HTMLAttributes, 0],
});
export const EmailButton = Node.create({
    name: 'emailButton',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: true,
    addAttributes() {
        return {
            label: { default: 'Find out more', parseHTML: (el) => el.querySelector('a')?.textContent ?? 'Find out more' },
            href: {
                default: 'https://example.com',
                parseHTML: (el) => {
                    const v = (el.querySelector('a') ?? el.closest('a'))?.getAttribute('href') ?? '';
                    return safeHttps(v) ? v : 'https://example.com';
                },
            },
            background: { default: '#21564b', parseHTML: (el) => colour(el.getAttribute('data-background'), '#21564b') },
            color: { default: '#ffffff', parseHTML: (el) => colour(el.getAttribute('data-color'), '#ffffff') },
            align: { default: 'left', parseHTML: (el) => align(el.getAttribute('data-align')) },
            radius: { default: '8', parseHTML: (el) => String(Math.min(24, Math.max(0, Number(el.getAttribute('data-radius')) || 0))) },
        };
    },
    parseHTML: () => [{ tag: 'table[data-email-button]', priority: 1000 }],
    renderHTML({ node }) {
        const a = node.attrs;
        return [
            'table',
            {
                'data-email-button': 'true',
                'data-background': a.background,
                'data-color': a.color,
                'data-align': a.align,
                'data-radius': a.radius,
                role: 'presentation',
                cellpadding: '0',
                cellspacing: '0',
                style: 'width:100%;margin:24px 0;',
            },
            [
                'tbody',
                {},
                [
                    'tr',
                    {},
                    [
                        'td',
                        { align: a.align, style: `text-align:${a.align};padding:0;` },
                        [
                            'a',
                            {
                                href: safeHttps(a.href) ? a.href : 'https://example.com',
                                style: `display:inline-block;background-color:${colour(a.background, '#21564b')};color:${colour(a.color, '#ffffff')};border-radius:${Math.min(24, Math.max(0, Number(a.radius) || 0))}px;padding:14px 24px;text-decoration:none;font-size:15px;font-weight:700;line-height:1.4;`,
                            },
                            a.label,
                        ],
                    ],
                ],
            ],
        ];
    },
});
export const EmailImage = Node.create({
    name: 'image',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: true,
    addAttributes() {
        const image = (el: HTMLElement) => (el.tagName === 'IMG' ? el : el.querySelector('img'));
        return {
            src: { default: '', parseHTML: (el) => image(el)?.getAttribute('src') ?? '' },
            alt: { default: '', parseHTML: (el) => image(el)?.getAttribute('alt') ?? '' },
            align: { default: 'center', parseHTML: (el) => align(el.getAttribute('data-align')) },
            width: { default: '520', parseHTML: (el) => String(Math.min(520, Math.max(40, Number(image(el)?.getAttribute('width')) || 520))) },
            link: { default: '', parseHTML: (el) => (el.querySelector('a') ?? el.closest('a'))?.getAttribute('href') ?? '' },
        };
    },
    parseHTML: () => [{ tag: 'div[data-email-image]', priority: 1100 }, { tag: 'img[src]' }],
    renderHTML({ node }) {
        const a = node.attrs;
        const src = safeHttps(a.src) || /^\/email-assets\/[a-z0-9-]+\.png$/.test(a.src) ? a.src : '';
        const image: [string, Record<string, string>] = [
            'img',
            {
                src,
                alt: a.alt,
                width: String(Math.min(520, Math.max(40, Number(a.width) || 520))),
                style: `width:${Math.min(520, Math.max(40, Number(a.width) || 520))}px;max-width:100%;height:auto;display:inline-block;border:0;`,
            },
        ];
        return [
            'div',
            mergeAttributes({ 'data-email-image': 'true', 'data-align': align(a.align), style: `text-align:${align(a.align)};margin:24px 0;` }),
            safeHttps(a.link) ? ['a', { href: a.link }, image] : image,
        ];
    },
});
