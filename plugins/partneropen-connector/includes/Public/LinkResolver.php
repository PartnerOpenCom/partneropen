<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Public;

use PartnerOpen\Connector\Application\ClickCounter;
use PartnerOpen\Connector\Application\SnapshotStore;
use PartnerOpen\Connector\Application\SpaceRegistry;
use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Infrastructure\Options;
use PartnerOpen\Connector\Support\Validation;

final class LinkResolver
{
    /**
     * Resolve a published link only after checking the publication overlay,
     * space status, placement scope, and destination allowlist.
     *
     * @return array{status:int,target?:string,reason?:string}
     */
    public static function resolve(string $link_id, string $placement_id): array
    {
        if (Options::paused()) {
            return ['status' => 404, 'reason' => 'paused'];
        }

        $link_id = trim($link_id);
        $placement_id = trim($placement_id);
        if ($link_id === '' || $placement_id === '') {
            return ['status' => 404, 'reason' => 'unknown_link'];
        }

        if (defined('PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD') && PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD === true) {
            return ['status' => 404, 'reason' => 'directory_build'];
        }

        if (! Consent::granted('affiliate_service')) {
            return ['status' => 404, 'reason' => 'consent'];
        }

        $registry = class_exists(SpaceRegistry::class) ? SpaceRegistry::all() : [];
        $published = class_exists(SnapshotStore::class) ? SnapshotStore::published() : [];
        if (! is_array($registry)) {
            $registry = [];
        }
        if (! is_array($published)) {
            $published = [];
        }

        $spaces = [];
        foreach ($registry as $key => $space) {
            if (! is_array($space)) {
                continue;
            }
            $id = trim((string) ($space['id'] ?? $key));
            if ($id === '') {
                continue;
            }
            $spaces[$id] = [
                'space' => $space,
                'snapshot' => self::snapshot_for($id, $published),
            ];
        }
        foreach ($published as $id => $snapshot) {
            if (! is_array($snapshot)) {
                continue;
            }
            $id = trim((string) $id);
            if ($id === '') {
                $id = trim((string) ($snapshot['space']['id'] ?? ''));
            }
            if ($id === '') {
                continue;
            }
            if (! isset($spaces[$id])) {
                $spaces[$id] = [
                    'space' => is_array($snapshot['space'] ?? null) ? $snapshot['space'] : [],
                    'snapshot' => $snapshot,
                ];
            } elseif (! is_array($spaces[$id]['snapshot'])) {
                $spaces[$id]['snapshot'] = $snapshot;
            }
        }
        $found_inactive = false;
        $found_placement = false;
        $found_destination = false;
        $found_suspended = false;
        $resolved_target = null;

        foreach ($spaces as $entry) {
            $space = is_array($entry['space'] ?? null) ? $entry['space'] : [];
            $snapshot = is_array($entry['snapshot'] ?? null) ? $entry['snapshot'] : null;
            if ($snapshot === null) {
                continue;
            }
            $links = is_array($snapshot['links'] ?? null) ? $snapshot['links'] : [];
            $link = is_array($links[$link_id] ?? null) ? $links[$link_id] : null;
            if ($link === null) {
                continue;
            }
            $registry_status = self::text($space['status'] ?? '');
            $snapshot_status = is_array($snapshot['space'] ?? null)
                ? self::text($snapshot['space']['status'] ?? '')
                : '';
            if ($registry_status === 'suspended' || $snapshot_status === 'suspended') {
                $found_suspended = true;
                continue;
            }
            if (($registry_status !== '' && $registry_status !== 'published')
                || ($snapshot_status !== '' && $snapshot_status !== 'published')) {
                $found_inactive = true;
                continue;
            }
            if (self::text($link['status'] ?? '') !== 'active') {
                $found_inactive = true;
                continue;
            }
            $placements = is_array($link['placements'] ?? null) ? $link['placements'] : [];
            $placement_allowed = false;
            foreach ($placements as $placement) {
                if (self::text($placement) === $placement_id) {
                    $placement_allowed = true;
                    break;
                }
            }
            if (! $placement_allowed) {
                $found_placement = true;
                continue;
            }
            $allowed_hosts = is_array($snapshot['allowed_hosts'] ?? null) ? $snapshot['allowed_hosts'] : [];
            $destination = is_scalar($link['destination'] ?? null) ? (string) $link['destination'] : '';
            $target = Validation::https_url($destination, $allowed_hosts);
            if ($target === null) {
                $found_destination = true;
                continue;
            }

            $resolved_target = $target;
        }

        if ($found_suspended) {
            return ['status' => 404, 'reason' => 'suspended'];
        }
        if ($resolved_target !== null) {
            ClickCounter::record($placement_id);

            return ['status' => 302, 'target' => $resolved_target];
        }

        if ($found_inactive) {
            return ['status' => 404, 'reason' => 'inactive'];
        }
        if ($found_placement) {
            return ['status' => 404, 'reason' => 'placement'];
        }
        if ($found_destination) {
            return ['status' => 404, 'reason' => 'destination'];
        }

        return ['status' => 404, 'reason' => 'unknown_link'];
    }

    /** @param array<string, array<string, mixed>> $published */
    private static function snapshot_for(string $id, array $published): ?array
    {
        if (isset($published[$id]) && is_array($published[$id])) {
            return $published[$id];
        }
        if (class_exists(SnapshotStore::class)) {
            $snapshot = SnapshotStore::get($id);
            if (is_array($snapshot)) {
                return $snapshot;
            }
        }

        return null;
    }

    private static function text(mixed $value): string
    {
        if (is_array($value) || is_object($value) || $value === null) {
            return '';
        }
        $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        return class_exists(Validation::class) ? Validation::text($value) : trim($value);
    }
}
