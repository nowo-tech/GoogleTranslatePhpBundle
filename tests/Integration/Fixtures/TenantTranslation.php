<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures;

/**
 * Messenger message of the worker test: one unit of work for one tenant.
 */
final readonly class TenantTranslation
{
    public function __construct(
        public string $tenant,
        public int $index,
        public bool $fail = false,
    ) {
    }
}
