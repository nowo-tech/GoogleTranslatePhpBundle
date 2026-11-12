<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports;

use Stichoza\GoogleTranslate\GoogleTranslate;

/** A 1.3 host service autowiring the (now deprecated) vendor class. */
final readonly class LegacyPortConsumer
{
    public function __construct(public GoogleTranslate $translator)
    {
    }
}
