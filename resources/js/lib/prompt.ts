import type { Annotation, ReferenceImage } from '@/types';

/** Pixelforge's prompt limit, in characters (code points). */
export const PROMPT_LIMIT = 4000;

/** A, B, C... from a mark's place in its image's list. */
export function markLetter(index: number): string {
    return String.fromCharCode(65 + index);
}

/** Counts characters the way Pixelforge does: code points, not UTF-16 units, so an emoji is one. */
export function characterCount(text: string): number {
    // Code points on purpose: it's what Pixelforge and PHP's mb_strlen count, so the limit check matches the server.
    return Array.from(text).length;
}

const percent = (fraction: number) => Math.round(fraction * 100);

/** "top left", "center", "bottom right"... from where the box's middle falls in a 3×3 grid. */
export function describePosition(mark: Annotation): string {
    const third = (centre: number) => Math.min(2, Math.floor(centre * 3));
    const row = ['top', 'middle', 'bottom'][third(mark.y + mark.height / 2)];
    const column = ['left', 'center', 'right'][third(mark.x + mark.width / 2)];

    return row === 'middle'
        ? column === 'center'
            ? 'center'
            : `middle ${column}`
        : `${row} ${column}`;
}

/**
 * The prompt Pixelforge receives. Pixelforge takes no regions or masks, so each note becomes a line of text naming the
 * reference it belongs to, the mark's letter, where it is in words and its exact box. Marks without a note add
 * nothing. The estimate prices this same text, so what's shown is what's charged for.
 */
export function composePrompt(
    prompt: string,
    references: ReferenceImage[],
): string {
    const lines = references.flatMap((reference, r) =>
        reference.annotations.flatMap((mark, m) => {
            const note = mark.note.trim();

            if (note === '') {
                return [];
            }

            const box = `x ${percent(mark.x)}-${percent(mark.x + mark.width)}%, y ${percent(mark.y)}-${percent(mark.y + mark.height)}%`;

            return [
                `- Reference ${r + 1}, area ${markLetter(m)} (${describePosition(mark)}, ${box}): ${note}`,
            ];
        }),
    );

    const base = prompt.trim();

    return lines.length === 0
        ? base
        : `${base}\n\nNotes on the reference images:\n${lines.join('\n')}`;
}
