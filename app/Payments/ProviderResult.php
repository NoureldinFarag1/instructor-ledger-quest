<?php

namespace App\Payments;

final class ProviderResult
{
    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const NOT_FOUND = 'not_found';

    public function __construct(
        public readonly string $status,
        public readonly ?string $reference = null,
        public readonly ?string $error = null,
    ) {}
}
