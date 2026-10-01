<?php

namespace App\Domain\Identity\DTO;

use App\Domain\Identity\Enums\DevicePlatform;

final readonly class DeviceContext
{
    public function __construct(
        public string $deviceId,
        public DevicePlatform $platform,
        public ?string $name,
        public ?string $ip,
    ) {}

    public static function fromArray(array $data, ?string $ip): self
    {
        return new self($data['device_id'], DevicePlatform::from($data['platform']), $data['name'] ?? null, $ip);
    }

    public function toArray(): array
    {
        return ['device_id' => $this->deviceId, 'platform' => $this->platform->value, 'name' => $this->name, 'ip' => $this->ip];
    }
}
