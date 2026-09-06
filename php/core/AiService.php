<?php
/**
 * AI Processing Service (Core/AiService.php)
 * ------------------------------------------
 * بازنویسی و غنی‌سازی متن با هوش مصنوعی (Google Gemini یا OpenRouter)
 */

namespace Core;

class AiService {
    /**
     * بازنویسی هوشمند متن با مدل انتخابی
     */
    public static function rewriteText(string $text, string $promptInstructions = ''): string {
        $aiConfig = Database::getSetting('ai_config', []);
        if (empty($aiConfig['enabled']) || empty($aiConfig['api_key'])) {
            return $text;
        }

        $provider = $aiConfig['provider'] ?? 'gemini';
        $apiKey   = $aiConfig['api_key'];

        $defaultPrompt = "لطفاً متن زیر را با حفظ لحن و پیام اصلی، بازنویسی روان و جذاب کن. هیچ لینک یا تبلیغ اضافی اضافه نکن:\n\n";
        $finalPrompt   = (!empty($promptInstructions) ? $promptInstructions . "\n\n" : $defaultPrompt) . $text;

        if ($provider === 'gemini') {
            return self::callGemini($apiKey, $finalPrompt, $text);
        } else {
            return self::callOpenRouter($apiKey, $finalPrompt, $text, $aiConfig['model'] ?? 'openai/gpt-3.5-turbo');
        }
    }

    private static function callGemini(string $apiKey, string $prompt, string $fallbackText): string {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
        
        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return $fallbackText;

        $json = json_decode($response, true);
        $rewritten = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return !empty($rewritten) ? trim($rewritten) : $fallbackText;
    }

    private static function callOpenRouter(string $apiKey, string $prompt, string $fallbackText, string $model): string {
        $url = "https://openrouter.ai/api/v1/chat/completions";

        $body = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'شما یک ویراستار حرفه‌ای زبان فارسی هستید.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$apiKey}"
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return $fallbackText;

        $json = json_decode($response, true);
        $rewritten = $json['choices'][0]['message']['content'] ?? null;

        return !empty($rewritten) ? trim($rewritten) : $fallbackText;
    }
}
