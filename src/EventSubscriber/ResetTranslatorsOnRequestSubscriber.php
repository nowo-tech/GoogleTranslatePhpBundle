<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Restores the translator profiles to their configured defaults at every unit-of-work boundary.
 *
 * Makes per-call setter calls ({@code setTarget()}, {@code setOptions()}, …) safe in long-lived
 * processes even when {@code services_resetter} does not run between units of work:
 *
 * - HTTP: at the start of every main request (sub-requests keep the state of their main request);
 * - Messenger consumers: when a message is received and after it is handled or failed;
 * - console: when a command starts and when it terminates.
 *
 * The Messenger and console events are subscribed by name, so symfony/messenger and symfony/console
 * stay optional. Only already-instantiated profiles are reset (the iterator holds
 * {@code IGNORE_ON_UNINITIALIZED_REFERENCE} references), so nothing is built at a boundary.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ResetTranslatorsOnRequestSubscriber implements EventSubscriberInterface
{
    /** Messenger event names (class names), subscribed without requiring symfony/messenger. */
    public const MESSENGER_WORKER_MESSAGE_RECEIVED = 'Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent';
    public const MESSENGER_WORKER_MESSAGE_HANDLED  = 'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent';
    public const MESSENGER_WORKER_MESSAGE_FAILED   = 'Symfony\Component\Messenger\Event\WorkerMessageFailedEvent';

    /** Console event names (ConsoleEvents::COMMAND / TERMINATE), subscribed without requiring symfony/console. */
    public const CONSOLE_COMMAND   = 'console.command';
    public const CONSOLE_TERMINATE = 'console.terminate';

    /**
     * @param iterable<ResetInterface> $translators
     */
    public function __construct(
        private iterable $translators,
    ) {
    }

    /**
     * Subscribes to kernel.request, the Messenger worker events and the console command events.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                   => ['onKernelRequest', 4096],
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => ['resetTranslators', 4096],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => ['resetTranslators', -4096],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => ['resetTranslators', -4096],
            self::CONSOLE_COMMAND                   => ['resetTranslators', 4096],
            self::CONSOLE_TERMINATE                 => ['resetTranslators', -4096],
        ];
    }

    /**
     * Resets every profile translator at the start of each main request (FrankenPHP worker safety).
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->resetTranslators();
    }

    /**
     * Resets every already-instantiated profile translator. Called at the Messenger message and console
     * command boundaries, where there is no kernel.request.
     */
    public function resetTranslators(): void
    {
        foreach ($this->translators as $translator) {
            $translator->reset();
        }
    }
}
