<?php

namespace Tests\Unit;

use App\Support\IntegrationLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use LucianoTonet\GroqPHP\GroqException;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class IntegrationLogTest extends TestCase
{
    public function test_credentials_and_addresses_are_redacted_without_losing_the_provider_error(): void
    {
        config([
            'groq.api_key' => 'gsk_private_key', 'services.resend.key' => 're_private_key',
            'mail.mailers.smtp.password' => 'private-smtp-password',
        ]);
        $error = IntegrationLog::error(new GroqException(
            'HTTP 401: invalid key gsk_private_key, re_private_key, Bearer another-token, private-smtp-password for person@example.com',
            401, 'authentication_error',
        ));
        $this->assertSame('authentication_error', $error['provider_error_type']);
        $this->assertSame(401, $error['exception_code']);
        $this->assertStringContainsString('HTTP 401: invalid key', $error['error_message']);
        foreach (['gsk_private_key', 're_private_key', 'another-token', 'private-smtp-password', 'person@example.com'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($error));
        }
    }

    public function test_database_diagnostics_do_not_include_query_bindings_or_generated_ai_text(): void
    {
        $error = IntegrationLog::error(new QueryException('sqlite', 'insert into suggestions (suggestion) values (?)',
            ['Private generated recipe text'], new PDOException('SQLSTATE: missing suggestions table')));
        $this->assertSame('SQLSTATE: missing suggestions table', $error['error_message']);
        $this->assertStringNotContainsString('Private generated recipe text', json_encode($error));
    }

    public function test_diagnostics_use_json_and_do_not_depend_on_the_application_log_level(): void
    {
        config([
            'logging.channels.integrations.handler_with.stream' => 'php://memory',
            'logging.channels.single.level' => 'critical',
            'mail.default' => 'test-log', 'mail.mailers.test-log.transport' => 'log',
            'mail.from.address' => 'private-sender@example.com',
        ]);
        Log::forgetChannel('integrations');
        $context = IntegrationLog::mailContext('welcome', 123);
        IntegrationLog::write('info', 'mail.send.started', $context);
        IntegrationLog::write('error', 'mail.send.failed', [...$context, ...IntegrationLog::error(new RuntimeException('SMTP connection timed out'))]);
        $stream = Log::channel('integrations')->getLogger()->getHandlers()[0]->getStream();
        rewind($stream);
        $started = json_decode(fgets($stream), true, flags: JSON_THROW_ON_ERROR);
        $failed = json_decode(fgets($stream), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('mail.send.started', $started['message']);
        $this->assertSame('mail.send.failed', $failed['message']);
        $this->assertSame($started['context']['operation_id'], $failed['context']['operation_id']);
        $this->assertSame('simulated', $started['context']['delivery_mode']);
        $this->assertSame('example.com', $started['context']['from_domain']);
        $this->assertStringNotContainsString('private-sender', json_encode($started));
    }
}
