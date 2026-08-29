<?php

/*
 * The prompt handed to an image generator, in English.
 *
 * Written as instructions to a model, not as UI copy: full sentences, concrete
 * nouns, and no hedging. Two lines carry the whole approach — no lettering, and
 * a clear reserved area — because models cannot draw a scannable code and
 * render text badly, so both are composited afterwards.
 */
return [
    'lead' => 'A striking :mood promotional poster background for a :kind. Editorial quality, suitable for print and social media.',
    'about' => 'The event is called ":title", held at :venue on :when. Let that inform the subject and atmosphere of the artwork, but do not write any of it on the image.',
    'palette' => 'Use this exact palette and nothing outside it: :colors.',
    'composition' => 'Composition: :ratio, with the visual weight in the upper two thirds.',
    'no_text' => 'Critical: no lettering, no words, no numbers, no signage, no watermark anywhere in the image. Any text will be added afterwards.',
    'reserve_qr' => 'Critical: leave the lower third calm and uncluttered — flat colour or soft gradient, no detail, no faces — so a title block and a QR code can be placed there cleanly.',
    'negative' => 'text, letters, words, numbers, captions, watermark, signature, logo, busy foreground in the lower third, distorted faces, extra limbs',

    'kind' => [
        'concert' => 'live music concert',
        'theatre' => 'stage theatre performance',
        'film' => 'film screening',
        'lecture' => 'talk or lecture',
        'exhibition' => 'art exhibition',
        'children' => "children's show",
        'festival' => 'community festival',
        'private' => 'private celebration',
    ],

    'mood' => [
        'elegant' => 'elegant and restrained',
        'bold' => 'bold and high-contrast',
        'minimal' => 'minimal, with generous negative space',
        'heritage' => 'rooted in Levantine and Syrian visual heritage, with basalt stone and traditional pattern',
        'nocturne' => 'nocturnal and atmospheric, lit from a single source',
        'warm' => 'warm and inviting, golden hour light',
    ],
];
