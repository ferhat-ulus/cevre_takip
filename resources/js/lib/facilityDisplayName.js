export function facilityDisplayName(name) {
    return String(name ?? '')
        .normalize('NFC')
        .trim()
        .replace(/\s+/gu, ' ')
        .toLocaleUpperCase('tr-TR')
        .replace(/LİMİTED\s*ŞİRKETİ/gu, 'LİMİTED ŞİRKETİ')
        .replace(/ANONİM\s*ŞİRKETİ/gu, 'ANONİM ŞİRKETİ')
        .replace(/LTD\s*\.\s*ŞTİ\s*\./gu, 'LTD. ŞTİ.')
        .replace(/A\s*\.\s*Ş\s*\./gu, 'A.Ş.');
}
