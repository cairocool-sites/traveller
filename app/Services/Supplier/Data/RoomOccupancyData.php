<?php

namespace App\Services\Supplier\Data;

use InvalidArgumentException;
use JsonSerializable;

final readonly class RoomOccupancyData implements JsonSerializable
{
    public function __construct(
        public int $adults,
        public int $children = 0,
        public array $childAges = [],
    ) {
        if ($adults < 1) {
            throw new InvalidArgumentException('Every room must have at least one adult.');
        }

        if ($children < 0 || count($childAges) !== $children) {
            throw new InvalidArgumentException('Child count must match child ages.');
        }

        foreach ($childAges as $age) {
            if (! is_int($age) || $age < 0 || $age > 17) {
                throw new InvalidArgumentException('Child ages must be integers between 0 and 17.');
            }
        }
    }

    public function jsonSerialize(): array
    {
        return ['adults' => $this->adults, 'children' => $this->children, 'child_ages' => $this->childAges];
    }
}
