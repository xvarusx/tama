<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\Channel;
use App\Repository\NotificationTemplateRepository;

/**
 * Renders a stored (type, channel, locale) template, falling back to the
 * built-in locale then to a built-in generic template. Missing placeholders
 * render as an empty string so a missing param never drops a notification.
 */
final readonly class TemplateRenderer
{
    private const string FALLBACK_LOCALE = 'en';
    private const string GENERIC_SUBJECT = '{{ type }}';
    private const string GENERIC_BODY = '<p>{{ message }}</p>';
    private const string EMAIL_SHELL = '<!DOCTYPE html><html><body>%s</body></html>';

    public function __construct(
        private NotificationTemplateRepository $templates,
        private SettingProvider $settings,
    ) {}

    /**
     * @param array<string, string|int|float|bool> $params
     *
     * @return array{subject: string, body: string}
     */
    public function render(string $type, Channel $channel, array $params): array
    {
        $locale = $this->settings->string('notifications.default_locale');
        $template = $this->templates->findByTypeChannelLocale($type, $channel, $locale)
            ?? $this->templates->findByTypeChannelLocale($type, $channel, self::FALLBACK_LOCALE);

        $values = ['type' => $type] + $params;
        $escape = $channel === Channel::EMAIL;

        $subject = $this->replace($template?->getSubject() ?? self::GENERIC_SUBJECT, $values, $escape);
        $body = $this->replace($template?->getBodyTemplate() ?? self::GENERIC_BODY, $values, $escape);

        if ($escape) {
            $body = sprintf(self::EMAIL_SHELL, $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * @param array<string, string|int|float|bool> $values
     */
    private function replace(string $template, array $values, bool $escape): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/',
            static function (array $matches) use ($values, $escape): string {
                $value = $values[$matches[1]] ?? '';
                if (\is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                $string = (string)$value;

                return $escape ? htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $string;
            },
            $template,
        );
    }
}
