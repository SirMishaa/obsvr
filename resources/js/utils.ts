/**
 * Calculates the Levenshtein distance between two strings. The Levenshtein distance is a measure of the difference between two strings,
 * which represents the minimum number of single-character edits (insertions, deletions, or substitutions) required to change one string into the other.
 *
 * @param {string} str1 - The first string to compare.
 * @param {string} str2 - The second string to compare.
 * @return {number} The minimum number of single-character edits required to transform the first string into the second string.
 */
export function levenshteinDistance(str1: string, str2: string): number {
    const track = Array(str2.length + 1)
        .fill(null)
        .map(() => Array(str1.length + 1).fill(null));
    for (let i = 0; i <= str1.length; i += 1) {
        track[0][i] = i;
    }
    for (let j = 0; j <= str2.length; j += 1) {
        track[j][0] = j;
    }
    for (let j = 1; j <= str2.length; j += 1) {
        for (let i = 1; i <= str1.length; i += 1) {
            const indicator = str1[i - 1] === str2[j - 1] ? 0 : 1;
            track[j][i] = Math.min(track[j][i - 1] + 1, track[j - 1][i] + 1, track[j - 1][i - 1] + indicator);
        }
    }
    return track[str2.length][str1.length];
}

export function urlBase64ToUint8Array(base64Url: string) {
    const cleaned = (base64Url || '').trim();
    let base64 = cleaned.replace(/-/g, '+').replace(/_/g, '/');

    base64 = base64.replace(/[^A-Za-z0-9+/=]/g, '');
    if (base64.length % 4 !== 0) {
        base64 += '='.repeat(4 - (base64.length % 4));
    }

    const rawData: string = atob(base64);

    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
}
