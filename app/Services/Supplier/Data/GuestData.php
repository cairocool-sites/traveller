<?php

namespace App\Services\Supplier\Data;

use App\Enums\GuestType;
use InvalidArgumentException;
use JsonSerializable;

final readonly class GuestData implements JsonSerializable
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public GuestType $type = GuestType::Adult,
        public ?int $age = null,
        public bool $isLead = false,
        public array $metadata = [],
    ) {
        if ($firstName === '' || $lastName === '') {
            throw new InvalidArgumentException('Guest names are required.');
        }

        if ($type === GuestType::Child && ($age === null || $age < 0 || $age > 17)) {
            throw new InvalidArgumentException('Child guests require a valid child age.');
        }

        if ($isLead && $type !== GuestType::Adult) {
            throw new InvalidArgumentException('Lead guest must be an adult.');
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'type' => $this->type->value,
            'age' => $this->age,
            'is_lead' => $this->isLead,
            'metadata' => $this->metadata,
        ];
    }
}
