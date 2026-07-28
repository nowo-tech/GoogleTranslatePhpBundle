<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Unit;

use Nowo\GoogleTranslatePhpBundle\DependencyInjection\GoogleTranslatePhpExtension;
use Nowo\GoogleTranslatePhpBundle\GoogleTranslatePhpBundle;
use PHPUnit\Framework\TestCase;

final class GoogleTranslatePhpBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsExtension(): void
    {
        $bundle = new GoogleTranslatePhpBundle();
        $ext    = $bundle->getContainerExtension();

        self::assertInstanceOf(GoogleTranslatePhpExtension::class, $ext);
        self::assertSame($ext, $bundle->getContainerExtension());
    }
}
