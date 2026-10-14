<?php

namespace Tests\Unit;

use App\Rules\AllowedPublicText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class PublicTextModerationTest extends TestCase
{
    #[DataProvider('samples')]
    public function test_public_text_detection(string $text, bool $allowed): void
    {
        $logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
        Log::shouldReceive('channel')->with('integrations')->andReturn($logger);
        $validator = Validator::make(['text' => $text], ['text' => [new AllowedPublicText]]);
        $this->assertSame($allowed, $validator->passes());
        if ($allowed) $this->assertSame($text, $validator->validated()['text']);
        else $this->assertSame(AllowedPublicText::MESSAGE, $validator->errors()->first('text'));
    }

    public static function samples(): array
    {
        return [
            ['FUCK', false], ['f.u.c.k', false], ['f u c k', false], ['fuuuck', false],
            ['sh1t', false], ['ＦＵＣＫ', false], ["fu\u{200B}ck", false],
            ['enculé', false], ["encule\u{0301}", false], ['ENCU LÉ', false],
            ['nique ta mère', false], ['va te faire foutre', false], ['<b>fu</b>ck', false], ['f&#117;ck', false],
            ['Chicken breast and shiitake mushrooms', true], ['Cocktail with bitters', true],
            ['Dick Martin from Scunthorpe', true], ['Classic tart with a pinch of salt', true],
            ['Une pâte feuilletée avec des champignons', true], ['Shellfish stock', true],
        ];
    }

    public function test_exceptions_do_not_exempt_other_offensive_words(): void
    {
        config(['moderation.blocked_words' => ['en' => ['breast', 'shit']]]);
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log')->once()->withArgs(function ($level, $event, $context) {
            $this->assertSame('moderation.rejected', $event);
            $this->assertSame('public_text.profanity', $context['rule_identifier']);
            $this->assertSame('biography', $context['field']);
            $this->assertStringNotContainsString('breast', json_encode($context));
            $this->assertStringNotContainsString('shit', json_encode($context));
            return true;
        });
        Log::shouldReceive('channel')->with('integrations')->andReturn($logger);
        $this->assertTrue(Validator::make(['biography' => 'Chicken breast'], ['biography' => [new AllowedPublicText]])->passes());
        $this->assertFalse(Validator::make(['biography' => 'Chicken breast shit'], ['biography' => [new AllowedPublicText]])->passes());
    }
}
