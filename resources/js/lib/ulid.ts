const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

/** A ULID: 48-bit millisecond time then 80 random bits, in Crockford base32. Ids every send so a resend is recognised. */
export function ulid(time = Date.now()): string {
    let head = '';

    for (let i = 0; i < 10; i++) {
        head = ALPHABET[time % 32] + head;
        time = Math.floor(time / 32);
    }

    // 256 is a multiple of 32, so taking each byte mod 32 stays uniform.
    const tail = Array.from(
        crypto.getRandomValues(new Uint8Array(16)),
        (byte) => ALPHABET[byte % 32],
    ).join('');

    return head + tail;
}
