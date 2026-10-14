<?php

namespace App\Rules;

use App\Support\IntegrationLog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class AllowedPublicText implements ValidationRule
{
    public const MESSAGE = 'Please remove inappropriate language.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') return;
        $text = self::normalize($value);
        foreach (config('moderation.allowed_words', []) as $exception) {
            $word = self::normalize($exception);
            if ($word !== '') $text = preg_replace('/(?<![a-z0-9])'.preg_quote($word, '/').'(?![a-z0-9])/', ' ', $text);
        }
        $text = strtr($text, ['@' => 'a', '$' => 's', '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't']);

        foreach (config('moderation.blocked_words', []) as $words) {
            foreach ($words as $word) {
                $normalized = self::normalize($word);
                if ($normalized === '') continue;
                // Bounded separators/repetition catch f.u.c.k and fuuuck without substring matching.
                $letters = str_split(str_replace(' ', '', $normalized));
                $pattern = implode('[\s\p{P}_]{0,3}', array_map(fn ($letter) => preg_quote($letter, '/').'{1,3}', $letters));
                if (preg_match('/(?<![a-z0-9])'.$pattern.'(?![a-z0-9])/u', $text)) {
                    IntegrationLog::write('notice', 'moderation.rejected', IntegrationLog::context([
                        'field' => $attribute,
                        'rule_identifier' => 'public_text.profanity',
                        'user_id' => auth('sanctum')->id(),
                    ]));
                    $fail(self::MESSAGE);
                    return;
                }
            }
        }
    }

    private static function normalize(string $value): string
    {
        $text = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\p{Cf}/u', '', strip_tags($text));
        $text = mb_convert_kana(mb_strtolower($text), 'as', 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', Str::ascii($text)));
    }
}
