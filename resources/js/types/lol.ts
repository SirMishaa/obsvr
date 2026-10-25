export type LolTeam = {
    code: string;
    name: string;
    image: string;
};

export type LolLeague = {
    slug: string;
    label: string;
    image: string;
    subscribed: boolean;
    mode: 'all' | 'teams';
    teamCodes: string[];
    costreamChannel: string | null;
    teams: LolTeam[];
};

export type LolUpcomingMatch = {
    id: string;
    league: string;
    leagueLabel: string;
    startTime: string;
    state: string;
    blockName: string | null;
    bestOf: number | null;
    teams: LolTeam[];
    notified: boolean;
};

export type LolBracketTeam = LolTeam & {
    homeLeague: string | null;
    outcome: 'win' | 'loss' | null;
    gameWins: number | null;
};

export type LolBracketMatch = {
    id: string;
    startTime: string;
    state: string;
    bestOf: number | null;
    teams: LolBracketTeam[];
};

export type LolBracketColumn = {
    /** Null for a match day of a lone stage: the page shows the day. */
    title: string | null;
    matches: LolBracketMatch[];
};

export type LolSwissSection = {
    kind: 'swiss';
    title: string;
    winsNeeded: number;
    teamCount: number;
    matches: LolBracketMatch[];
};

export type LolKnockoutSection = {
    kind: 'bracket';
    title: string;
    columns: LolBracketColumn[];
};

export type LolBracket = {
    league: string;
    leagueLabel: string;
    sections: (LolSwissSection | LolKnockoutSection)[];
};

export type LolViewMode = 'list' | 'tree';
