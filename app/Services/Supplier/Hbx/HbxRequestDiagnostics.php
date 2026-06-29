<?php

namespace App\Services\Supplier\Hbx;

final readonly class HbxRequestDiagnostics
{
    public function __construct(
        public string $targetHost,
        public string $targetPath,
        public string $method,
        public int $connectTimeoutSeconds,
        public int $timeoutSeconds,
        public bool $proxyConfigured,
        public bool $responseReceived,
        public ?int $httpStatus = null,
    ) {}

    public function withResponse(?int $status): self
    {
        return new self(
            targetHost: $this->targetHost,
            targetPath: $this->targetPath,
            method: $this->method,
            connectTimeoutSeconds: $this->connectTimeoutSeconds,
            timeoutSeconds: $this->timeoutSeconds,
            proxyConfigured: $this->proxyConfigured,
            responseReceived: $status !== null,
            httpStatus: $status,
        );
    }
}
