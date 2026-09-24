<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Restores the translator profiles to their configured defaults at the start of every main request.
 *
 * Makes per-request setter calls ({@code setTarget()}, {@code setOptions()}, …) safe in long-lived
 * workers even when {@code services_resetter} does not run between requests. Sub-requests keep the
 * state of their main request.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ResetTranslatorsOnRequestSubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<ResetInterface> $translators
     */
    public function __construct(
        private iterable $translators,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 4096]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        foreach ($this->translators as $translator) {
            $translator->reset();
        }
    }
}
