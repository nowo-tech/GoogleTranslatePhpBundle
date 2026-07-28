<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Unit\Exception;

use Nowo\GoogleTranslatePhpBundle\Exception\UnknownProfileException;
use PHPUnit\Framework\TestCase;

final class UnknownProfileExceptionTest extends TestCase
{
    public function testForProfileMessage(): void
    {
        $exception = UnknownProfileException::forProfile('fast');

        self::assertStringContainsString('fast', $exception->getMessage());
        self::assertStringContainsString('nowo_google_translate_php.profiles', $exception->getMessage());
    }
}
