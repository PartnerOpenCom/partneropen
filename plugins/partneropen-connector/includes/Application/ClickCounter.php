<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Application;

use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Support\Validation;

final class ClickCounter
{
    public const RETENTION_DAYS = 90;

    public static function record(string $placement_id): void
    {
        if (class_exists(Consent::class) && ! Consent::granted('aggregate_metrics')) {
            return;
        }

        $placement_id = Validation::text($placement_id);
        if ($placement_id === '') {
            return;
        }

        $clicks = self::all();
        $date = self::today();
        if (! isset($clicks[$date])) {
            $clicks[$date] = [];
        }
        $clicks[$date][$placement_id] = (int) ($clicks[$date][$placement_id] ?? 0) + 1;
        self::save($clicks);
        self::prune(self::RETENTION_DAYS);
    }

    public static function prune_scheduled(): void
    {
        self::prune(self::RETENTION_DAYS);
    }

    /**
     * @return array<string, array<string, int>>
     */
    public static function all(): array
    {
        $stored = function_exists('get_option') ? get_option('partneropen_clicks', []) : [];
        if (! is_array($stored)) {
            return [];
        }

        $clicks = [];
        foreach ($stored as $date => $placements) {
            if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || ! is_array($placements)) {
                continue;
            }
            foreach ($placements as $placement => $count) {
                if (! is_string($placement) || $placement === '') {
                    continue;
                }
                $clicks[$date][$placement] = max(0, (int) $count);
            }
            if (($clicks[$date] ?? []) === []) {
                unset($clicks[$date]);
            }
        }

        ksort($clicks);
        return $clicks;
    }

    public static function prune(int $days = self::RETENTION_DAYS): void
    {
        $days = max(1, $days);
        $threshold = strtotime('-' . ($days - 1) . ' days', strtotime(self::today()));
        if ($threshold === false) {
            return;
        }

        $clicks = self::all();
        foreach (array_keys($clicks) as $date) {
            $date_timestamp = strtotime($date . ' 00:00:00');
            if ($date_timestamp === false || $date_timestamp < $threshold) {
                unset($clicks[$date]);
            }
        }
        self::save($clicks);
    }

    /**
     * @param array<string, array<string, int>> $clicks
     */
    private static function save(array $clicks): void
    {
        if (function_exists('update_option')) {
            update_option('partneropen_clicks', $clicks, false);
        }
    }

    private static function today(): string
    {
        if (function_exists('current_time')) {
            $date = current_time('Y-m-d');
            if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                return $date;
            }
        }

        return gmdate('Y-m-d');
    }
}
