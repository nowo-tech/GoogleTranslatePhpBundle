<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Exception;

use InvalidArgumentException;

use function sprintf;

/**
 * Thrown when a configured default_profile is missing from profiles.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class UnknownProfileException extends InvalidArgumentException
{
    public static function forProfile(string $profile): self
    {
        return new self(sprintf('The profile "%s" is not defined in nowo_google_translate_php.profiles.', $profile));
    }
}
