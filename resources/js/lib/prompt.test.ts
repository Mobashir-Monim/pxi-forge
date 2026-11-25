import { describe, expect, test } from 'bun:test';
import { characterCount, composePrompt, describePosition } from './prompt';
import type { Annotation, ReferenceImage } from '@/types';

const mark = (over: Partial<Annotation>): Annotation => ({
    id: 1,
    x: 0,
    y: 0,
    width: 0.3,
    height: 0.3,
    note: '',
    ...over,
});

const reference = (annotations: Annotation[]): ReferenceImage => ({
    id: 1,
    src: 'x',
    name: 'x',
    file: null,
    annotations,
});

describe('composePrompt', () => {
    test('leaves the prompt alone when there are no notes', () => {
        expect(
            composePrompt('  a fox  ', [reference([mark({ note: '   ' })])]),
        ).toBe('a fox');
    });

    test('appends each note with its reference number, letter, position and box', () => {
        const composed = composePrompt('A lighthouse', [
            reference([
                mark({
                    x: 0.1,
                    y: 0.05,
                    width: 0.3,
                    height: 0.25,
                    note: ' make the sky purple ',
                }),
            ]),
            reference([
                mark({ note: '' }),
                mark({
                    id: 2,
                    x: 0.7,
                    y: 0.7,
                    width: 0.2,
                    height: 0.2,
                    note: 'keep this texture',
                }),
            ]),
        ]);

        expect(composed).toBe(
            'A lighthouse\n\nNotes on the reference images:\n' +
                '- Reference 1, area A (top left, x 10-40%, y 5-30%): make the sky purple\n' +
                // The empty mark A still holds its letter, so the note matches the B the user sees.
                '- Reference 2, area B (bottom right, x 70-90%, y 70-90%): keep this texture',
        );
    });
});

test('describes the middle of the image as center', () => {
    expect(describePosition(mark({ x: 0.35, y: 0.35 }))).toBe('center');
    expect(describePosition(mark({ x: 0.0, y: 0.35 }))).toBe('middle left');
    expect(describePosition(mark({ x: 0.7, y: 0, width: 0.3 }))).toBe(
        'top right',
    );
});

test('counts code points like Pixelforge', () => {
    expect(characterCount('🦊é')).toBe(2);
});
