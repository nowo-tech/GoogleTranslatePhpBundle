<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle;

use Nowo\GoogleTranslatePhpBundle\DependencyInjection\GoogleTranslatePhpExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Wraps stichoza/google-translate-php for Symfony + FrankenPHP worker mode.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class GoogleTranslatePhpBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof GoogleTranslatePhpExtension) {
            $this->extension = new GoogleTranslatePhpExtension();
        }

        return $this->extension;
    }
}
