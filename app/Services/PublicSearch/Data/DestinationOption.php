<?php

namespace App\Services\PublicSearch\Data;

use JsonSerializable;

final readonly class DestinationOption implements JsonSerializable
{
    public function __construct(
        public string $token,
        public string $type,
        public int $id,
        public string $label,
        public string $supplierIdentifier,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'token' => $this->token,
            'type' => $this->type,
            'id' => $this->id,
            'label' => $this->label,
            'supplier_identifier' => $this->supplierIdentifier,
        ];
    }
}
