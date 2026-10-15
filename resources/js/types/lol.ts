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
