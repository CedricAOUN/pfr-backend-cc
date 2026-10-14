<?php

return [
    // Check both lists for every public field, independent of its declared language.
    'blocked_words' => [
        'en' => ['fuck', 'fucking', 'fucker', 'motherfucker', 'shit', 'shitty', 'bullshit', 'asshole', 'bastard', 'bitch', 'cunt', 'nigger', 'faggot'],
        'fr' => ['putain', 'pute', 'putes', 'salope', 'salopes', 'connard', 'connards', 'connasse', 'connasses', 'enculé', 'enculés', 'enculer', 'merde', 'merdique', 'nique ta mère', 'va te faire foutre'],
    ],
    // Exceptions remove only the matching word/phrase, never exempt the whole input.
    'allowed_words' => ['chicken breast', 'breast', 'cocktail', 'shiitake', 'Dick'],
];
