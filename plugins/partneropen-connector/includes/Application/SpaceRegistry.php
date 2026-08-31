<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Application;

use PartnerOpen\Connector\Infrastructure\Options;
use PartnerOpen\Connector\Support\Validation;

final class SpaceRegistry
{
    public const MAX_SPACES = 5;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return Options::spaces();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find_by_slug(string $slug): ?array
    {
        $slug = Validation::space_slug($slug);
        if ($slug === null) {
            return null;
        }

        foreach (self::all() as $space) {
            if ((string) ($space['slug'] ?? '') === $slug) {
                return $space;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        $spaces = self::all();
        if (isset($spaces[$id]) && is_array($spaces[$id])) {
            return $spaces[$id];
        }

        foreach ($spaces as $space) {
            if ((string) ($space['id'] ?? '') === $id) {
                return $space;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $space
     */
    public static function save(array $space): void
    {
        $id = trim((string) ($space['id'] ?? ''));
        $slug = Validation::space_slug((string) ($space['slug'] ?? ''));
        if ($id === '' || $slug === null) {
            throw new \RuntimeException('A valid space id and slug are required.');
        }

        $spaces = self::all();
        $existing = self::find($id);
        if ($existing === null && count($spaces) >= self::MAX_SPACES) {
            throw new \RuntimeException('The maximum number of spaces has been reached.');
        }

        $current = is_array($existing) ? $existing : [];
        $status = (string) ($space['status'] ?? ($current['status'] ?? 'draft'));
        if (! in_array($status, ['draft', 'published', 'suspended'], true)) {
            $status = 'draft';
        }
        $record = array_merge($current, $space, [
            'id' => $id,
            'slug' => $slug,
            'title' => Validation::text($space['title'] ?? ($current['title'] ?? '')),
            'status' => $status,
            'snapshot_version' => max(0, (int) ($space['snapshot_version'] ?? ($current['snapshot_version'] ?? 0))),
            'published_at' => max(0, (int) ($space['published_at'] ?? ($current['published_at'] ?? 0))),
        ]);
        $spaces[$id] = $record;

        Options::save_spaces($spaces);
    }

    public static function suspend(string $id): void
    {
        $space = self::find($id);
        if ($space === null) {
            return;
        }
        $space['status'] = 'suspended';
        self::save($space);
    }

    public static function resume(string $id): void
    {
        $space = self::find($id);
        if ($space === null) {
            return;
        }
        $space['status'] = 'published';
        self::save($space);
    }

    public static function count_published(): int
    {
        $count = 0;
        foreach (self::all() as $space) {
            if (($space['status'] ?? '') === 'published') {
                $count++;
            }
        }

        return $count;
    }
}
