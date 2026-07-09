import { PASSWORD_MIN_LENGTH } from '@/lib/passwordHint';

const LOWERCASE = 'abcdefghijklmnopqrstuvwxyz';
const UPPERCASE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const DIGITS = '0123456789';
const SYMBOLS = '!@#$%^&*()-_=+[]{}|:,.?';

function pickRandomChar(charset: string): string {
    const values = new Uint32Array(1);
    crypto.getRandomValues(values);

    return charset[values[0] % charset.length];
}

function shuffle<T>(items: T[]): T[] {
    const result = [...items];

    for (let index = result.length - 1; index > 0; index--) {
        const values = new Uint32Array(1);
        crypto.getRandomValues(values);
        const swapIndex = values[0] % (index + 1);

        [result[index], result[swapIndex]] = [result[swapIndex], result[index]];
    }

    return result;
}

export function generateRandomPassword(
    length: number = PASSWORD_MIN_LENGTH,
): string {
    const targetLength = Math.max(length, PASSWORD_MIN_LENGTH);
    const allChars = LOWERCASE + UPPERCASE + DIGITS + SYMBOLS;

    const passwordChars = [
        pickRandomChar(LOWERCASE),
        pickRandomChar(UPPERCASE),
        pickRandomChar(DIGITS),
        pickRandomChar(SYMBOLS),
    ];

    while (passwordChars.length < targetLength) {
        passwordChars.push(pickRandomChar(allChars));
    }

    return shuffle(passwordChars).join('');
}
