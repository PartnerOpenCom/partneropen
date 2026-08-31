<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Domain;

use InvalidArgumentException;
use PartnerOpen\Connector\Support\Validation;

final class Space
{
    public readonly string $id;
    public readonly string $slug;
    public readonly string $title;
    public readonly string $status;
    public readonly int $snapshot_version;
    public readonly int $published_at;

    public function __construct(
        string $id,
        string $slug,
        string $title,
        string $status = 'draft',
        int $snapshot_version = 0,
        int $published_at = 0,
    ) {
        $id = trim($id);
        $slug = Validation::space_slug($slug) ?? '';
        if ($id === '' || $slug === '') {
            throw new InvalidArgumentException('Invalid space identity.');
        }
        if (! in_array($status, ['draft', 'published', 'suspended'], true)) {
            $status = 'draft';
        }

        $this->id = $id;
        $this->slug = $slug;
        $this->title = Validation::text($title);
        $this->status = $status;
        $this->snapshot_version = max(0, $snapshot_version);
        $this->published_at = max(0, $published_at);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from_array(array $data): self
    {
        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['slug'] ?? ''),
            (string) ($data['title'] ?? ''),
            (string) ($data['status'] ?? 'draft'),
            (int) ($data['snapshot_version'] ?? 0),
            (int) ($data['published_at'] ?? 0),
        );
    }

    /**
     * @return array{id:string,slug:string,title:string,status:string,snapshot_version:int,published_at:int}
     */
    public function to_array(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status,
            'snapshot_version' => $this->snapshot_version,
            'published_at' => $this->published_at,
        ];
    }
}
