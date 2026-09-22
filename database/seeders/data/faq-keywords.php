<?php

declare(strict_types=1);

/**
 * Alternate phrasings a visitor might type, per FAQ, keyed by question text.
 *
 * These feed the chatbot's matcher at a heavy weight (see
 * App\Services\Faq\FaqMatchingService) and exist because the FAQ wording is
 * written for reading, not for searching: nobody types "How much does Cirque
 * entertainment cost?", they type "whats your pricing". Editors can extend
 * any of these from the FAQ screen in the admin -- that is the intended way
 * to fix a question the chatbot keeps missing, rather than changing code.
 */
return [
    'What makes Dream World Cirque different from other Cirque entertainment companies?' => 'different, difference, unique, why choose you, compare, competitors, stand out, special, better',

    'What kinds of events does Dream World Cirque perform at?' => 'wedding, weddings, corporate, gala, convention, trade show, birthday, private party, mitzvah, sweet sixteen, holiday party, festival, nightclub, resort, brand activation, what events, event types',

    'Can you create completely custom entertainment for our event?' => 'custom, customize, customized, bespoke, tailored, personalized, original, one of a kind, made to order',

    "I have an idea or theme, but I'm not sure what entertainment I need. Can you help?" => 'idea, theme, not sure, help me decide, suggestions, recommend, advice, concept, guidance, where do i start',

    'What kinds of performers and specialty acts do you offer?' => 'acts, performers, aerialist, contortionist, stilt walker, juggler, fire, dancers, acrobat, talent, roster, specialty acts, what do you offer',

    'Can you provide both ambient entertainment and featured performances?' => 'ambient, roaming, strolling, mingling, walkaround, featured, stage show, background entertainment, cocktail hour',

    'Can Dream World Cirque create a full Cirque show?' => 'full show, full production, cirque show, stage production, theatrical, complete show, whole show',

    'Can you coordinate costumes with our theme or event design?' => 'costume, costumes, wardrobe, outfit, attire, colors, color scheme, dress, theme matching, branding',

    'Do you provide aerial rigging and specialty performance equipment?' => 'rigging, rig, equipment, apparatus, hardware, silks, lyra, hoop, trapeze, install, installation, truss',

    'How much space and ceiling height do you need for aerial performances?' => 'space, ceiling, height, how high, how tall, clearance, room needed, dimensions, venue size, square feet',

    "What if our venue can't accommodate aerial performances?" => 'low ceiling, no rigging, cannot accommodate, ground based, alternatives, outdoor, restrictions, not possible, wont work',

    'Are your performers professionally trained?' => 'trained, training, professional, experience, experienced, qualified, credentials, background check, skilled, safety',

    'Do you provide insurance?' => 'insurance, insured, liability, certificate of insurance, coi, coverage, indemnity',

    // City names are listed deliberately: "do you travel to <city>" is a
    // common question shape, and without them the city words are unknown
    // tokens that get dropped, leaving too little to match on.
    'Do you travel outside Los Angeles and Las Vegas?' => 'travel, traveling, location, locations, cities, city, destination, outside, nationwide, international, out of state, where are you based, do you come to, san diego, orange county, san francisco, phoenix, scottsdale, new york, miami, chicago, dallas, austin, seattle, denver, nashville, texas, florida, arizona, nevada, california',

    'Do you provide performers for film, television and commercial productions?' => 'film, tv, television, commercial, production, camera, shoot, photo shoot, music video, media, on camera',

    'How far in advance should we book?' => 'advance, book, booking, lead time, notice, how soon, how early, availability, last minute, schedule, timeline',

    'How much does Cirque entertainment cost?' => 'price, pricing, cost, budget, how much, rate, rates, quote, fee, fees, expensive, afford, ballpark, estimate',

    'Can you work with our event planner, producer, venue, or creative team?' => 'planner, event planner, producer, coordinator, agency, vendor, creative team, collaborate, work with, production company',

    'How do we get started?' => 'get started, start, begin, next steps, process, how do i book, inquiry, contact you, first step',
];
