<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports;

use Nowo\GoogleTranslatePhpBundle\Contract\GoogleTranslatorInterface;

/** A host service on the 1.4 port only. */
final readonly class NewPortConsumer
{
    public function __construct(public GoogleTranslatorInterface $translator)
    {
    }
}
