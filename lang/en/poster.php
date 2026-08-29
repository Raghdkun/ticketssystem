<?php

/*
 * The prompt handed to an image generator, in English.
 *
 * Written as instructions to a model, not as UI copy: concrete nouns, no
 * hedging, and the constraints stated as constraints. Three lines carry the
 * whole approach — no lettering, no code-like patterns, and a reserved band —
 * because models cannot draw a scannable code and render text badly, so both
 * are composited afterwards.
 */
return [
    'lead' => 'Design a promotional poster artwork for a :kind. The feeling should be :mood, made in the manner of :style. Gallery-grade graphic design — the kind of thing that would be pinned to a wall on its own merits.',

    'about' => 'The event is ":title", at :venue on :when. Let that shape the subject, symbolism and atmosphere — but do not write any of it on the image.',

    'subject' => 'What it is about, for your reference only: :description',

    'setting' => 'It happens at :name, :where. Draw on the character of that place — its architecture, materials and light.',

    'palette' => 'Use this exact palette and nothing outside it: :colors. Commit to it; let two of these dominate and the rest accent.',

    'elements' => 'Build the composition from these devices: :elements.',

    'composition' => 'Format :ratio. Strong focal hierarchy, deliberate negative space, and a composition that still reads at thumbnail size.',

    'craft' => 'Make real design decisions: an intentional focal point, controlled contrast, layered depth, and edges that feel drawn rather than filtered. Avoid the generic centred-symmetrical look.',

    'no_text' => 'CRITICAL — no lettering of any kind: no words, letters, numbers, captions, signage, watermarks or signatures anywhere in the image. Every piece of text is added afterwards.',

    // Image 2 of the owner's own tests came back with two invented QR codes in
    // the reserved band, so this is now said outright rather than implied.
    'reserve_qr' => 'CRITICAL — keep the bottom third calm: flat colour or a soft gradient, no detail, no faces, no small repeating marks. A real QR code and the title are placed there afterwards. Do NOT draw a QR code, barcode, data matrix, or any small square checkerboard pattern anywhere in the image.',

    'full_bleed' => 'This is a full-bleed banner: compose edge to edge, and keep the centre clear enough that a title could sit over it later.',

    'negative' => 'text, letters, words, numbers, captions, watermark, signature, logo, QR code, barcode, data matrix, checkerboard, small square grids, busy lower third, distorted faces, extra limbs, stock-photo look, generic gradient background',

    'kind' => [
        'concert' => 'live music concert',
        'theatre' => 'stage theatre performance',
        'film' => 'film screening',
        'lecture' => 'talk or lecture',
        'exhibition' => 'art exhibition',
        'children' => 'show for children',
        'festival' => 'community festival',
        'private' => 'private celebration',
        'wedding' => 'wedding celebration',
        'poetry' => 'poetry evening',
        'folk' => 'folk dance and dabke night',
        'religious' => 'religious commemoration',
        'sports' => 'sporting event',
        'book_fair' => 'book fair',
        'workshop' => 'hands-on workshop',
        'graduation' => 'graduation ceremony',
        'comedy' => 'comedy night',
        'classical' => 'classical or operatic concert',
        'bazaar' => 'craft market and bazaar',
        'memorial' => 'memorial gathering',
    ],

    'mood' => [
        'elegant' => 'elegant and restrained',
        'bold' => 'bold and high-contrast',
        'minimal' => 'minimal, with generous negative space',
        'heritage' => 'rooted in Levantine and Syrian visual heritage',
        'nocturne' => 'nocturnal and atmospheric, lit from a single source',
        'warm' => 'warm and inviting, golden-hour light',
        'festive' => 'festive and celebratory',
        'solemn' => 'solemn and dignified',
        'playful' => 'playful and light-hearted',
        'cinematic' => 'cinematic and dramatic',
    ],

    'style' => [
        'risograph' => 'risograph printing, with misregistered spot colours and visible paper grain',
        'screenprint' => 'silkscreen poster printing, flat inks and hand-cut shapes',
        'art_deco' => 'art deco, with symmetry, gold linework and stepped geometry',
        'bauhaus' => 'Bauhaus graphic design, primary geometry and hard edges',
        'collage' => 'cut-paper collage, layered textures and torn edges',
        'woodcut' => 'woodcut relief printing, gouged lines and heavy blacks',
        'arabesque' => 'Islamic geometric ornament, interlaced tessellation and muqarnas rhythm',
        'brutalist' => 'brutalist graphic design, raw blocks and stark division',
        'retro_future' => 'retro-futurism, chrome curves and optimistic 1970s space graphics',
        'watercolour' => 'watercolour painting, bleeding pigment and soft edges',
        'papercut' => 'layered papercut, hard shadows and depth from stacked planes',
        'photographic' => 'documentary photography, natural light and real texture',
        'flat_vector' => 'flat vector illustration, clean shapes and confident curves',
        'engraving' => 'fine-line engraving, cross-hatching and etched detail',
    ],

    'element' => [
        'strokes' => 'confident brush strokes',
        'grain' => 'halftone grain and print texture',
        'geometry' => 'bold geometric shapes',
        'arabesque_tile' => 'arabesque tilework patterning',
        'torn_paper' => 'torn paper edges',
        'gradient_mesh' => 'soft gradient meshes',
        'line_art' => 'fine line art',
        'duotone' => 'duotone treatment',
        'ink_splatter' => 'ink splatter and spray',
        'grid' => 'an underlying modular grid',
    ],
];
