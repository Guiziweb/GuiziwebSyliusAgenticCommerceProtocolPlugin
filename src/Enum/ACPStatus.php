<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Enum;

/**
 * ACP Checkout Session Status
 *
 * Defines the 5 possible statuses for an ACP checkout session
 * according to OpenAI Agentic Commerce Protocol specification.
 *
 * @see https://developers.openai.com/commerce/specs/acp/
 */
enum ACPStatus: string
{
    case NOT_READY_FOR_PAYMENT = 'not_ready_for_payment';
    case READY_FOR_PAYMENT = 'ready_for_payment';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';

    /**
     * Get all status values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }

    /**
     * Get translation key for this status
     */
    public function getTranslationKey(): string
    {
        return match ($this) {
            self::NOT_READY_FOR_PAYMENT => 'guiziweb.ui.status_not_ready_for_payment',
            self::READY_FOR_PAYMENT => 'guiziweb.ui.status_ready_for_payment',
            self::IN_PROGRESS => 'guiziweb.ui.status_in_progress',
            self::COMPLETED => 'guiziweb.ui.status_completed',
            self::CANCELED => 'guiziweb.ui.status_canceled',
        };
    }

    /**
     * Get choices for Symfony form/grid (translation_key => value)
     *
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $status) {
            $choices[$status->getTranslationKey()] = $status->value;
        }

        return $choices;
    }
}
