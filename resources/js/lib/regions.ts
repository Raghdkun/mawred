/** ISO 3166-1 alpha-2 codes; names come from Intl.DisplayNames in the UI language. */
export const COUNTRY_CODES =
    'AD AE AF AG AI AL AM AO AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GT GU GW GY HK HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW'.split(
        ' ',
    );

/** Countries most Mawred companies operate in, listed first. */
export const PRIORITY_COUNTRIES = ['SA', 'AE', 'KW', 'QA', 'BH', 'OM', 'EG', 'JO', 'US', 'GB'];

/** Rough capital-city centres so the geofence map opens near the branch. */
export const COUNTRY_CENTERS: Record<string, [number, number]> = {
    SA: [24.7136, 46.6753],
    AE: [25.2048, 55.2708],
    KW: [29.3759, 47.9774],
    QA: [25.2854, 51.531],
    BH: [26.2285, 50.586],
    OM: [23.588, 58.3829],
    EG: [30.0444, 31.2357],
    JO: [31.9454, 35.9284],
    US: [40.7128, -74.006],
    GB: [51.5072, -0.1276],
};

export const COUNTRY_CURRENCY: Record<string, string> = {
    SA: 'SAR',
    AE: 'AED',
    KW: 'KWD',
    QA: 'QAR',
    BH: 'BHD',
    OM: 'OMR',
    EG: 'EGP',
    JO: 'JOD',
    US: 'USD',
    GB: 'GBP',
};

export function countryName(code: string, locale: string): string {
    try {
        return new Intl.DisplayNames([locale], { type: 'region' }).of(code) ?? code;
    } catch {
        return code;
    }
}

export function timeZones(): string[] {
    return typeof Intl.supportedValuesOf === 'function'
        ? Intl.supportedValuesOf('timeZone')
        : ['UTC'];
}

export function currencies(): string[] {
    return typeof Intl.supportedValuesOf === 'function'
        ? Intl.supportedValuesOf('currency')
        : ['SAR', 'USD'];
}

/** ISO weekday (1 = Monday … 7 = Sunday) as a short name in the UI language. */
export function weekdayName(
    isoDay: number,
    locale: string,
    style: 'short' | 'long' = 'short',
): string {
    // 2024-01-01 was a Monday.
    return new Intl.DateTimeFormat(locale, { weekday: style, timeZone: 'UTC' }).format(
        new Date(Date.UTC(2024, 0, isoDay)),
    );
}
