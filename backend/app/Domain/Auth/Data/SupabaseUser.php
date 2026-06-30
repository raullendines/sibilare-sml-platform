<?php

namespace App\Domain\Auth\Data;

class SupabaseUser
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $email,
        public readonly array $payload,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: (string) $payload['id'],
            email: isset($payload['email']) ? (string) $payload['email'] : null,
            payload: $payload,
        );
    }
}
