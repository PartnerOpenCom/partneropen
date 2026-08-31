<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Domain;

final class Block
{
    public const ALLOWED_TYPES = [
        'hero',
        'text',
        'cards',
        'cta',
        'link',
        'faq',
        'comparison',
        'table',
        'image',
    ];

    public readonly string $type;
    /** @var array<string, mixed> */
    public readonly array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $type, array $data = [])
    {
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported block type.');
        }

        $this->type = $type;
        $this->data = $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from_array(array $data): ?self
    {
        $type = is_string($data['type'] ?? null) ? (string) $data['type'] : '';
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            return null;
        }
        unset($data['type']);

        return new self($type, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return ['type' => $this->type, ...$this->data];
    }
}
