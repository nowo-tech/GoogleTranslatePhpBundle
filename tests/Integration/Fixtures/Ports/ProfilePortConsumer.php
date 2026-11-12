<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports;

use Nowo\GoogleTranslatePhpBundle\Contract\GoogleTranslatorInterface;

/** Selects a non-default profile through the named autowiring alias. */
final readonly class ProfilePortConsumer
{
    public function __construct(public GoogleTranslatorInterface $marketingTranslator)
    {
    }
}
