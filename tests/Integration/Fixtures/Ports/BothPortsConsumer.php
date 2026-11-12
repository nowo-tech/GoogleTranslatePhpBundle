<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports;

use Nowo\GoogleTranslatePhpBundle\Contract\GoogleTranslatorInterface;
use Stichoza\GoogleTranslate\GoogleTranslate;

/** A host service in the middle of the migration: both types. */
final readonly class BothPortsConsumer
{
    public function __construct(public GoogleTranslatorInterface $translator, public GoogleTranslate $legacy)
    {
    }
}
