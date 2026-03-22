/**
 * Разбива текст на части за безопасно рендериране: обикновен текст и http(s) връзки.
 */

export type LinkifyPart =
    | { type: 'text'; text: string }
    | { type: 'link'; href: string; text: string };

function isSafeHttpUrl(href: string): boolean {
    try {
        const u = new URL(href);

        return u.protocol === 'http:' || u.protocol === 'https:';
    } catch {
        return false;
    }
}

/**
 * @returns Масив от части; ако няма връзки, един текстов сегмент.
 */
export function linkifyParts(text: string): LinkifyPart[] {
    if (text.length === 0) {
        return [];
    }

    const re = /\bhttps?:\/\/[^\s<]+/gi;
    const parts: LinkifyPart[] = [];
    let lastIndex = 0;
    let m: RegExpExecArray | null;

    while ((m = re.exec(text)) !== null) {
        if (m.index > lastIndex) {
            parts.push({ type: 'text', text: text.slice(lastIndex, m.index) });
        }

        const raw = m[0];
        const href = raw.replace(/[.,;:!?)\]]+$/u, '');

        if (isSafeHttpUrl(href)) {
            parts.push({ type: 'link', href, text: raw });
        } else {
            parts.push({ type: 'text', text: raw });
        }

        lastIndex = m.index + raw.length;
    }

    if (lastIndex < text.length) {
        parts.push({ type: 'text', text: text.slice(lastIndex) });
    }

    if (parts.length === 0) {
        return [{ type: 'text', text }];
    }

    return parts;
}
