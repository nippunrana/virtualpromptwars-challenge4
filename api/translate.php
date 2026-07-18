<?php
/**
 * ArenaNexus 2026 AI Translation & Info-Finder Endpoint
 * Translates fan queries contextually using Gemini 3.1 Flash-Lite.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/gemini.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Only POST requests allowed'], 405);
}

// Read POST JSON input
$input = json_decode(file_get_contents('php://input'), true);

$lang = isset($input['language']) ? trim(strip_tags($input['language'])) : '';
$text = isset($input['text']) ? trim(strip_tags($input['text'])) : '';

if (empty($lang) || empty($text)) {
    sendResponse(['error' => 'Missing required fields: language, text'], 400);
}

try {
    $systemInstruction = "You are the FIFA World Cup 2026 Multilingual Support Agent. "
        . "Your job is to translate fan inquiries, identify their stadium-related intent, "
        . "and build a helpful English response + a translated response back into the fan's language.\n\n"
        . "FACTUAL GUIDELINES FOR ARENANEXUS STADIUM:\n"
        . "- Bathrooms: ADA-accessible restrooms are located at Gate C (ADA) and Section 103. Standard restrooms are at Gate A and Section 201.\n"
        . "- Food Concessions:\n"
        . "  1. Aztec Tacos: Mexican, is vegan & gluten-free (Located at Gate B/Section 102 area).\n"
        . "  2. Green Field Salads: Organic, is vegan, vegetarian, non-veg & gluten-free (Located at Gate E area).\n"
        . "  3. Golden Boot Grills: Burgers & Fries, is non-veg (Located at Gate A area).\n"
        . "  4. FIFA Café: Coffee, drinks, and pastries. Gluten-free options available (Located at Gate F area).\n"
        . "- First Aid Stations: Located at Gate C (ADA accessible) and Section 103.\n"
        . "- Transport hubs:\n"
        . "  1. Metro Stadium Station: outside Gate D.\n"
        . "  2. Shuttle Bus Hub: outside Gate B.\n"
        . "  3. Rideshare Drop-off Zone: outside Gate E.\n"
        . "  4. VIP Valet Parking: outside Gate C/F.";

    $prompt = "Fan Inquiry in {$lang}:\n"
        . "\"{$text}\"\n\n"
        . "Perform translation. Formulate the best response based on the factual guidelines. "
        . "Conform your response to the JSON schema.";

    // JSON Schema for structured output
    $jsonSchema = [
        'type' => 'object',
        'properties' => [
            'translation' => [
                'type' => 'string',
                'description' => 'Literal English translation of the fan\'s inquiry.'
            ],
            'response_en' => [
                'type' => 'string',
                'description' => 'The English response to guide the volunteer steward on what to do/say.'
            ],
            'response_lang' => [
                'type' => 'string',
                'description' => 'The response translated back into the fan\'s local language (e.g. Spanish, French, Japanese) for the steward to display on screen.'
            ]
        ],
        'required' => ['translation', 'response_en', 'response_lang']
    ];

    $gemini = new GeminiClient();
    $aiResponse = $gemini->generateContent($prompt, $systemInstruction, $jsonSchema);
    $data = json_decode($aiResponse, true);

    if (!$data || !isset($data['translation'])) {
        // Fallback mock if Gemini returned invalid response
        $data = [
            'translation' => '[Translated Query]: ' . $text,
            'response_en' => 'Guidance: Guide the visitor to the nearest service desk at Gate A.',
            'response_lang' => 'Asistencia disponible en el mostrador de servicio de la Puerta A.'
        ];
    }

    sendResponse($data);

} catch (Exception $e) {
    sendResponse(['error' => 'Translation failed: ' . $e->getMessage()], 500);
}
