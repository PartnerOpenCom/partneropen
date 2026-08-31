<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Domain;

use PartnerOpen\Connector\Infrastructure\Options;

final class Consent
{
    public const SCOPES = [
        'cloud_connection',
        'partner_email',
        'content_sync',
        'agent_pack',
        'aggregate_metrics',
        'affiliate_service',
    ];

    /**
     * @return array<string, array{label:string,purpose:string,fields:array<int,string>,recipient:string,retention:string,required:bool}>
     */
    public static function scope_meta(): array
    {
        return [
            'cloud_connection' => [
                'label' => 'Cloud connection',
                'purpose' => 'Pair this site with PartnerOpen Cloud so the delegated Space can be managed remotely.',
                'fields' => ['site URL', 'URL prefix', 'technical site identifier', 'connector version'],
                'recipient' => 'The paired PartnerOpen Cloud client',
                'retention' => 'Until consent is withdrawn or the site is disconnected.',
                'required' => true,
            ],
            'partner_email' => [
                'label' => 'Partner invitation email',
                'purpose' => 'Record the partner address used for the invitation and service notices for this Space.',
                'fields' => ['partner email address', 'site URL', 'Space name'],
                'recipient' => 'Stored on this site and shared with the paired client during pairing',
                'retention' => 'Until consent is withdrawn. The Connector sends no email itself.',
                'required' => false,
            ],
            'content_sync' => [
                'label' => 'Content sync',
                'purpose' => 'Receive the published page snapshot that this site renders.',
                'fields' => ['typed page blocks', 'SEO title and description', 'link metadata', 'allowed destination hosts', 'snapshot version'],
                'recipient' => 'This site, received from the paired client',
                'retention' => 'The latest snapshot stays on this site until it is replaced or deleted. No Cloud copy is kept in this milestone.',
                'required' => true,
            ],
            'agent_pack' => [
                'label' => 'Agent context files',
                'purpose' => 'Publish AGENTS.md, llms.txt, ai-context.json, manifest.json and sitemap.xml for the delegated Space.',
                'fields' => ['public Space title and summary', 'public page URLs', 'allowed block types'],
                'recipient' => 'Public visitors and AI agents',
                'retention' => 'Served while the Space is published.',
                'required' => false,
            ],
            'aggregate_metrics' => [
                'label' => 'Aggregate click counters',
                'purpose' => 'Record daily click totals per placement so the partner can measure placements.',
                'fields' => ['date', 'placement identifier', 'click count'],
                'recipient' => 'Stored on this site and readable by the paired client through the signed metrics route',
                'retention' => '90 days on this site, then deleted.',
                'required' => false,
            ],
            'affiliate_service' => [
                'label' => 'Affiliate service links',
                'purpose' => 'Allow links supplied by connected affiliate services to be published in this Space with disclosure.',
                'fields' => ['approved public link identifier', 'placement identifier', 'disclosure text'],
                'recipient' => 'Published on this site with disclosure; no service credentials are stored here',
                'retention' => 'Until consent is withdrawn.',
                'required' => false,
            ],
        ];
    }

    public static function granted(string $scope): bool
    {
        if (! in_array($scope, self::SCOPES, true)) {
            return false;
        }

        $record = self::state()[$scope] ?? null;

        return is_array($record) && (bool) ($record['granted'] ?? false);
    }

    /**
     * @param string[] $scopes
     */
    public static function grant(array $scopes, string $policy_version): void
    {
        $state = self::state();
        $now = self::now();
        $policy_version = trim($policy_version);
        foreach ($scopes as $scope) {
            if (! is_string($scope) || ! in_array($scope, self::SCOPES, true)) {
                continue;
            }
            $previous = is_array($state[$scope] ?? null) ? $state[$scope] : [];
            $state[$scope] = [
                ...$previous,
                'granted' => true,
                'policy_version' => $policy_version,
                'accepted_at' => $now,
                'revoked_at' => 0,
            ];
        }

        Options::save_consent($state);
    }

    public static function revoke(string $scope): void
    {
        if (! in_array($scope, self::SCOPES, true)) {
            return;
        }

        $state = self::state();
        $previous = is_array($state[$scope] ?? null) ? $state[$scope] : [];
        $state[$scope] = [
            ...$previous,
            'granted' => false,
            'policy_version' => is_scalar($previous['policy_version'] ?? null) ? (string) $previous['policy_version'] : '',
            'accepted_at' => max(0, (int) ($previous['accepted_at'] ?? 0)),
            'revoked_at' => self::now(),
        ];
        Options::save_consent($state);
    }

    public static function revoke_all(): void
    {
        foreach (self::SCOPES as $scope) {
            self::revoke($scope);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function state(): array
    {
        return Options::consent();
    }

    private static function now(): int
    {
        return function_exists('current_time') ? (int) current_time('timestamp', true) : time();
    }
}
