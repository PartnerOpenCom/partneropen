<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Domain;

use InvalidArgumentException;
use PartnerOpen\Connector\Support\Validation;

final class Link
{
    public readonly string $destination;
    public readonly string $label;
    public readonly string $disclosure;
    public readonly string $status;
    /** @var string[] */
    public readonly array $placements;

    /**
     * @param string[] $placements
     * @param string[] $allowed_hosts
     */
    public function __construct(
        string $destination,
        string $label = '',
        string $disclosure = 'Affiliate link',
        string $status = 'active',
        array $placements = [],
        array $allowed_hosts = [],
    ) {
        $normalized = Validation::https_url($destination, $allowed_hosts);
        if ($normalized === null) {
            throw new InvalidArgumentException('Link destination is not an allowed HTTPS URL.');
        }

        $this->destination = $normalized;
        $this->label = Validation::text($label);
        $this->disclosure = Validation::text($disclosure);
        $this->status = in_array($status, ['active', 'inactive'], true) ? $status : 'inactive';
        $this->placements = array_values(array_filter(array_map(
            static fn (mixed $placement): string => Validation::text($placement),
            $placements,
        ), static fn (string $placement): bool => $placement !== ''));
    }

    /**
     * @param array<string, mixed> $data
     * @param string[] $allowed_hosts
     */
    public static function from_array(array $data, array $allowed_hosts = []): ?self
    {
        foreach (['destination', 'label', 'disclosure', 'status'] as $field) {
            if (array_key_exists($field, $data) && ! is_string($data[$field])) {
                return null;
            }
        }
        if (array_key_exists('placements', $data)) {
            if (! is_array($data['placements'])) {
                return null;
            }
            foreach ($data['placements'] as $placement) {
                if (! is_string($placement)) {
                    return null;
                }
            }
        }

        $destination = Validation::https_url((string) ($data['destination'] ?? ''), $allowed_hosts);
        if ($destination === null) {
            return null;
        }

        return new self(
            $destination,
            (string) ($data['label'] ?? ''),
            (string) ($data['disclosure'] ?? 'Affiliate link'),
            (string) ($data['status'] ?? 'active'),
            is_array($data['placements'] ?? null) ? $data['placements'] : [],
            $allowed_hosts,
        );
    }

    /**
     * @return array{destination:string,label:string,disclosure:string,status:string,placements:array<int,string>}
     */
    public function to_array(): array
    {
        return [
            'destination' => $this->destination,
            'label' => $this->label,
            'disclosure' => $this->disclosure,
            'status' => $this->status,
            'placements' => $this->placements,
        ];
    }
}
