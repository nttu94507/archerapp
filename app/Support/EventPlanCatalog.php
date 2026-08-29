<?php

namespace App\Support;

use InvalidArgumentException;

final class EventPlanCatalog
{
    public const FREE = 'free';
    public const EVENT_PASS = 'event_pass';
    public const SUBSCRIPTION = 'subscription';
    public const LEGACY = 'legacy';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REFUNDED = 'refunded';

    /** @return array<string, int|null> */
    public static function limits(string $plan): array
    {
        return match ($plan) {
            self::FREE => [
                'active_events'=>1,
                'groups'=>1,
                'staff_members'=>2,
                'athletes'=>32,
                'targets'=>8,
                'arrows_per_phase'=>36,
                'badges'=>1,
            ],
            self::EVENT_PASS, self::SUBSCRIPTION, self::LEGACY => [
                'active_events'=>null,
                'groups'=>null,
                'staff_members'=>null,
                'athletes'=>null,
                'targets'=>null,
                'arrows_per_phase'=>null,
                'badges'=>null,
            ],
            default => throw new InvalidArgumentException('Unknown event plan: '.$plan),
        };
    }

    /** @return array<string, bool> */
    public static function features(string $plan): array
    {
        $paid = [
            'qualification'=>true,
            'check_in'=>true,
            'individual_elimination'=>true,
            'multiple_groups'=>true,
            'multiple_rounds'=>true,
            'live_results'=>true,
            'internal_visibility'=>true,
            'public_visibility'=>true,
            'advanced_judging'=>true,
            'score_audit_log'=>true,
            'data_export'=>true,
            'advanced_badges'=>true,
            'custom_branding'=>true,
        ];

        return match ($plan) {
            self::FREE => [
                'qualification'=>true,
                'check_in'=>false,
                'individual_elimination'=>false,
                'multiple_groups'=>false,
                'multiple_rounds'=>false,
                'live_results'=>true,
                'internal_visibility'=>true,
                'public_visibility'=>true,
                'advanced_judging'=>false,
                'score_audit_log'=>false,
                'data_export'=>false,
                'advanced_badges'=>false,
                'custom_branding'=>false,
            ],
            self::EVENT_PASS, self::SUBSCRIPTION, self::LEGACY => $paid,
            default => throw new InvalidArgumentException('Unknown event plan: '.$plan),
        };
    }

    public static function isKnown(string $plan): bool
    {
        return in_array($plan, [self::FREE, self::EVENT_PASS, self::SUBSCRIPTION, self::LEGACY], true);
    }
}
