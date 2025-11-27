<?php

namespace Tests\Fixtures;

class TwitchFixtures
{
    /**
     * List of popular French Twitch streamers with their real IDs and logins
     *
     * @return array<int, array{id: string, login: string, name: string}>
     */
    public static function popularFrenchStreamers(): array
    {
        return [
            ['id' => '52130765', 'login' => 'squeezie', 'name' => 'Squeezie'],
            ['id' => '27115917', 'login' => 'kamet0', 'name' => 'Kamet0'],
            ['id' => '41719107', 'login' => 'zerator', 'name' => 'ZeratoR'],
            ['id' => '40063341', 'login' => 'domingo', 'name' => 'Domingo'],
            ['id' => '407388596', 'login' => 'aminematue', 'name' => 'AmineMaTue'],
            ['id' => '135468063', 'login' => 'antoinedaniel', 'name' => 'Antoine Daniel'],
            ['id' => '68078157', 'login' => 'joueur_du_grenier', 'name' => 'Joueur Du Grenier'],
            ['id' => '28575692', 'login' => 'mistermv', 'name' => 'MisterMV'],
            ['id' => '131215608', 'login' => 'maghla', 'name' => 'Maghla'],
            ['id' => '100744948', 'login' => 'bagherajones', 'name' => 'Baghera Jones'],
            ['id' => '85800130', 'login' => 'etoiles', 'name' => 'Etoiles'],
            ['id' => '24147592', 'login' => 'gotaga', 'name' => 'Gotaga'],
            ['id' => '174955366', 'login' => 'solary', 'name' => 'Solary'],
        ];
    }

    /**
     * List of popular Twitch categories with their real IDs and names
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function popularCategories(): array
    {
        return [
            ['id' => '509658', 'name' => 'Just Chatting'],
            ['id' => '21779', 'name' => 'League of Legends'],
            ['id' => '516575', 'name' => 'VALORANT'],
            ['id' => '27471', 'name' => 'Minecraft'],
            ['id' => '32982', 'name' => 'Grand Theft Auto V'],
            ['id' => '32399', 'name' => 'Counter-Strike'],
            ['id' => '29595', 'name' => 'Dota 2'],
            ['id' => '33214', 'name' => 'Fortnite'],
            ['id' => '511224', 'name' => 'Apex Legends'],
            ['id' => '18122', 'name' => 'World of Warcraft'],
            ['id' => '512710', 'name' => 'Call of Duty: Warzone'],
            ['id' => '509671', 'name' => 'Talk Shows & Podcasts'],
            ['id' => '512980', 'name' => 'Fall Guys'],
            ['id' => '491487', 'name' => 'Dead by Daylight'],
            ['id' => '30921', 'name' => 'Rocket League'],
            ['id' => '488552', 'name' => 'Overwatch 2'],
            ['id' => '512953', 'name' => 'Elden Ring'],
            ['id' => '138585', 'name' => 'Hearthstone'],
            ['id' => '460630', 'name' => 'Tom Clancy\'s Rainbow Six Siege'],
            ['id' => '518203', 'name' => 'Sports'],
            ['id' => '509660', 'name' => 'Art'],
            ['id' => '509663', 'name' => 'Music'],
        ];
    }

    /**
     * @return string[] List of sample Twitch stream titles
     */
    public static function streamTitles(): array
    {
        return [
            'Chill du soir avec vous !',
            'On test le nouveau patch !',
            'Ranked jusqu\'à top 1 🔥',
            'Session détente avec le chat',
            'Découverte du nouveau jeu !',
            'Gros tournoi ce soir, let\'s go !',
            'AMA avec vous les amis',
            'On farm tranquille',
            'Speed run any% sans glitch',
            'Première fois sur ce jeu !',
            'Retour après une pause, ça fait plaisir !',
            'Marathon jusqu\'à 100 viewers !',
            'Collab avec les copains',
            'On teste vos builds les plus cheatés',
            'Session coaching avec le chat',
            'Just chatting puis on verra',
            'Tryhard mode activé 💪',
            'Détente musicale en live',
            'On finit enfin ce jeu !',
            'Session questions/réponses',
        ];
    }
}
