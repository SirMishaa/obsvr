import type {
    LolBracketMatch,
    LolBracketTeam,
    LolSwissSection,
} from '@/types/lol';

/** Winner code of a match, keyed by `SwissMatch.key`. */
export type SwissPicks = Record<string, string>;

export type SwissMatch = {
    key: string;
    startTime: string | null;
    bestOf: number | null;
    state: string;
    teams: LolBracketTeam[];
    /** Winner code, official or simulated. */
    winner: string | null;
    simulated: boolean;
    /** No official result yet and both teams known: a click picks the winner. */
    pickable: boolean;
};

export type SwissPool = {
    record: string;
    slots: number;
    matches: (SwissMatch | null)[];
};

export type SwissBox = {
    record: string;
    slots: number;
    teams: (LolBracketTeam | null)[];
};

export type SwissColumn = {
    title: string;
    pools: SwissPool[];
    advanced: SwissBox | null;
    eliminated: SwissBox | null;
};

type TeamRecord = { wins: number; losses: number };

function binomial(n: number, k: number): number {
    let result = 1;
    for (let i = 1; i <= k; i++) {
        result = (result * (n - k + i)) / i;
    }
    return result;
}

/** Expected number of teams with `wins` after `round` rounds, when every team keeps playing. */
function teamsWithRecord(
    teamCount: number,
    wins: number,
    round: number,
): number {
    return Math.floor((teamCount * binomial(round, wins)) / 2 ** round);
}

function isKnown(team: LolBracketTeam): boolean {
    return team.code !== 'TBD';
}

/** Pairs teams in order, avoiding rematches when possible. */
function pairTeams(
    teams: string[],
    opponents: Map<string, Set<string>>,
): [string, string][] {
    const tryPair = (rest: string[]): [string, string][] | null => {
        if (rest.length < 2) {
            return [];
        }
        const [first, ...others] = rest;
        for (const other of others) {
            if (opponents.get(first)?.has(other)) {
                continue;
            }
            const paired = tryPair(others.filter((code) => code !== other));
            if (paired) {
                return [[first, other], ...paired];
            }
        }
        return null;
    };

    const paired = tryPair(teams);
    if (paired) {
        return paired;
    }

    const fallback: [string, string][] = [];
    for (let i = 0; i + 1 < teams.length; i += 2) {
        fallback.push([teams[i], teams[i + 1]]);
    }
    return fallback;
}

function officialWinner(match: LolBracketMatch): string | null {
    return match.teams.find((team) => team.outcome === 'win')?.code ?? null;
}

/**
 * Replays a Swiss stage round by round: official matches go into the pool of their record, simulated
 * picks feed the records, and once a round is fully decided the undrawn pools of the next round are
 * paired from the teams sharing a record.
 */
export function buildSwissColumns(
    section: LolSwissSection,
    picks: SwissPicks,
): SwissColumn[] {
    const { winsNeeded, teamCount } = section;
    const poolRounds = 2 * winsNeeded - 1;

    const teams = new Map<string, LolBracketTeam>();
    const records = new Map<string, TeamRecord>();
    const opponents = new Map<string, Set<string>>();
    const pending: LolBracketMatch[] = [];
    const placeholders: LolBracketMatch[] = [];

    for (const match of section.matches) {
        if (match.teams.length === 2 && match.teams.every(isKnown)) {
            pending.push(match);
            for (const team of match.teams) {
                if (!teams.has(team.code)) {
                    teams.set(team.code, {
                        ...team,
                        outcome: null,
                        gameWins: null,
                    });
                    records.set(team.code, { wins: 0, losses: 0 });
                    opponents.set(team.code, new Set());
                }
            }
        } else {
            placeholders.push(match);
        }
    }

    const played = (code: string): number => {
        const record = records.get(code);
        return record ? record.wins + record.losses : 0;
    };
    const recordKey = (code: string): string => {
        const record = records.get(code);
        return `${record?.wins ?? 0}-${record?.losses ?? 0}`;
    };

    const columns: SwissColumn[] = [];
    let previousRoundDecided = true;

    for (let round = 0; round <= poolRounds; round++) {
        const pools: SwissPool[] = [];
        const roundMatches: SwissMatch[] = [];

        if (round < poolRounds) {
            const busy = new Set<string>();
            const official = pending.filter((match) => {
                const codes = match.teams.map((team) => team.code);
                if (
                    codes.some(
                        (code) => busy.has(code) || played(code) !== round,
                    )
                ) {
                    return false;
                }
                codes.forEach((code) => busy.add(code));
                return true;
            });
            pending.splice(
                0,
                pending.length,
                ...pending.filter((match) => !official.includes(match)),
            );

            for (
                let wins = Math.min(round, winsNeeded - 1);
                wins >= Math.max(0, round - winsNeeded + 1);
                wins--
            ) {
                const record = `${wins}-${round - wins}`;
                const slots = Math.floor(
                    teamsWithRecord(teamCount, wins, round) / 2,
                );

                const matches: (SwissMatch | null)[] = official
                    .filter(
                        (match) => recordKey(match.teams[0].code) === record,
                    )
                    .map((match) => {
                        const winner =
                            officialWinner(match) ?? picks[match.id] ?? null;
                        return {
                            key: match.id,
                            startTime: match.startTime,
                            bestOf: match.bestOf,
                            state: match.state,
                            teams: match.teams,
                            winner,
                            simulated:
                                officialWinner(match) === null &&
                                winner !== null,
                            pickable: officialWinner(match) === null,
                        };
                    });

                const drawn = new Set(
                    matches.flatMap(
                        (match) => match?.teams.map((team) => team.code) ?? [],
                    ),
                );
                const unpaired = [...teams.keys()].filter(
                    (code) =>
                        !drawn.has(code) &&
                        played(code) === round &&
                        recordKey(code) === record,
                );
                const pairs =
                    previousRoundDecided && unpaired.length >= 2
                        ? pairTeams(unpaired, opponents)
                        : [];

                while (matches.length < slots) {
                    const template = placeholders.shift();
                    const pair = pairs.shift();
                    if (!pair) {
                        matches.push(
                            template
                                ? {
                                      key: template.id,
                                      startTime: template.startTime,
                                      bestOf: template.bestOf,
                                      state: template.state,
                                      teams: [],
                                      winner: null,
                                      simulated: false,
                                      pickable: false,
                                  }
                                : null,
                        );
                        continue;
                    }
                    const key = `sim:${round}:${[...pair].sort().join('-')}`;
                    matches.push({
                        key,
                        startTime: template?.startTime ?? null,
                        bestOf: template?.bestOf ?? null,
                        state: 'unstarted',
                        teams: pair.map((code) => teams.get(code)!),
                        winner: picks[key] ?? null,
                        simulated: picks[key] !== undefined,
                        pickable: true,
                    });
                }

                pools.push({ record, slots, matches });
                roundMatches.push(
                    ...matches.filter(
                        (match): match is SwissMatch => match !== null,
                    ),
                );
            }

            for (const match of roundMatches) {
                if (match.teams.length !== 2) {
                    continue;
                }
                const [a, b] = match.teams.map((team) => team.code);
                opponents.get(a)?.add(b);
                opponents.get(b)?.add(a);
                if (match.winner) {
                    for (const code of [a, b]) {
                        const record = records.get(code)!;
                        records.set(
                            code,
                            code === match.winner
                                ? { ...record, wins: record.wins + 1 }
                                : { ...record, losses: record.losses + 1 },
                        );
                    }
                }
            }

            const expected = pools.reduce((sum, pool) => sum + pool.slots, 0);
            previousRoundDecided =
                previousRoundDecided &&
                roundMatches.length === expected &&
                roundMatches.every((match) => match.winner !== null);
        }

        const box = (wins: number, losses: number): SwissBox => {
            const finishRound = wins + losses;
            const slots = Math.floor(
                (teamCount * binomial(finishRound - 1, winsNeeded - 1)) /
                    2 ** finishRound,
            );
            const finished = [...teams.values()].filter(
                (team) => recordKey(team.code) === `${wins}-${losses}`,
            );
            return {
                record: `${wins}-${losses}`,
                slots,
                teams: [
                    ...finished,
                    ...Array<null>(Math.max(0, slots - finished.length)).fill(
                        null,
                    ),
                ],
            };
        };

        const other = round - winsNeeded;
        columns.push({
            title: round < poolRounds ? `Round ${round + 1}` : 'Final',
            pools,
            advanced: other >= 0 ? box(winsNeeded, other) : null,
            eliminated: other >= 0 ? box(other, winsNeeded) : null,
        });
    }

    return columns;
}
