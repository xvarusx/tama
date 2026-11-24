<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\NotificationTemplate;
use App\Enum\Channel;
use App\Repository\NotificationTemplateRepository;
use App\Service\SettingProvider;
use App\Service\TemplateRenderer;
use PHPUnit\Framework\TestCase;

final class TemplateRendererTest extends TestCase
{
    public function testReplacesPlaceholdersFromParams(): void
    {
        $renderer = $this->makeRenderer(['en'], 'en');

        $rendered = $renderer->render('payment.installment.overdue', Channel::EMAIL, [
            'invoiceNumber' => 'F-012',
            'partyName' => 'Jane',
        ]);

        self::assertSame('Invoice F-012 overdue', $rendered['subject']);
        self::assertSame('<!DOCTYPE html><html><body><p>Jane</p></body></html>', $rendered['body']);
    }

    public function testFallsBackToDefaultLocale(): void
    {
        // Only the built-in fallback locale ('en') has a stored template.
        $renderer = $this->makeRenderer(['en'], 'fr');

        $rendered = $renderer->render('payment.installment.overdue', Channel::EMAIL, [
            'invoiceNumber' => 'F-012',
            'partyName' => 'Jane',
        ]);

        self::assertSame('Invoice F-012 overdue', $rendered['subject']);
        self::assertSame('<!DOCTYPE html><html><body><p>Jane</p></body></html>', $rendered['body']);
    }

    public function testFallsBackToBuiltInTemplateWhenMissing(): void
    {
        $renderer = $this->makeRenderer([], 'en');

        $rendered = $renderer->render('unknown.event', Channel::EMAIL, ['message' => 'Something happened']);

        self::assertSame('unknown.event', $rendered['subject']);
        self::assertSame('<!DOCTYPE html><html><body><p>Something happened</p></body></html>', $rendered['body']);
    }

    public function testMissingPlaceholderDoesNotThrowAndLeavesKeyOut(): void
    {
        $renderer = $this->makeRenderer(['en'], 'en');

        $rendered = $renderer->render('payment.installment.overdue', Channel::EMAIL, [
            'invoiceNumber' => 'F-012',
        ]);

        self::assertSame('Invoice F-012 overdue', $rendered['subject']);
        self::assertSame('<!DOCTYPE html><html><body><p></p></body></html>', $rendered['body']);
    }

    public function testHtmlInParamsIsEscapedInEmailBody(): void
    {
        $renderer = $this->makeRenderer(['en'], 'en');
        $malicious = '<script>alert("x")</script>';

        $email = $renderer->render('payment.installment.overdue', Channel::EMAIL, [
            'invoiceNumber' => 'F-012',
            'partyName' => $malicious,
        ]);
        $inApp = $renderer->render('payment.installment.overdue', Channel::IN_APP, [
            'invoiceNumber' => 'F-012',
            'partyName' => $malicious,
        ]);

        self::assertStringNotContainsString('<script>', $email['body']);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $email['body']);
        self::assertStringContainsString($malicious, $inApp['body']);
    }

    /** @param list<string> $availableLocales */
    private function makeRenderer(array $availableLocales, string $defaultLocale): TemplateRenderer
    {
        $templates = self::createStub(NotificationTemplateRepository::class);
        $templates->method('findByTypeChannelLocale')->willReturnCallback(
            function (string $type, Channel $channel, string $locale) use ($availableLocales): ?NotificationTemplate {
                if ($type !== 'payment.installment.overdue' || !\in_array($locale, $availableLocales, true)) {
                    return null;
                }

                return $channel === Channel::EMAIL
                    ? new NotificationTemplate($type, $channel, $locale, 'Invoice {{ invoiceNumber }} overdue', '<p>{{ partyName }}</p>')
                    : new NotificationTemplate($type, $channel, $locale, 'Invoice {{ invoiceNumber }} overdue', 'Invoice {{ invoiceNumber }}: {{ partyName }}');
            },
        );

        $settings = self::createStub(SettingProvider::class);
        $settings->method('string')->willReturnMap([
            ['notifications.default_locale', $defaultLocale],
        ]);

        return new TemplateRenderer($templates, $settings);
    }
}
