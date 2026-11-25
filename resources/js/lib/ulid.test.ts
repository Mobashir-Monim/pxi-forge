import { expect, test } from 'bun:test';
import { ulid } from './ulid';

test('encodes the time first, so ids sort by when they were made', () => {
    expect(ulid(0).slice(0, 10)).toBe('0000000000');
    expect(ulid(1469918176385).slice(0, 10)).toBe('01ARYZ6S41');
    expect(ulid(1000) < ulid(2000)).toBe(true);
});

test('is 26 Crockford base32 characters, as Laravel validates', () => {
    expect(ulid()).toMatch(/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/);
});
