<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

use function max;

/**
 * Restores the translator profiles to their configured defaults at every unit-of-work boundary.
 *
 * Makes per-call setter calls ({@code setTarget()}, {@code setOptions()}, …) safe in long-lived
 * processes even when {@code services_resetter} does not run between units of work:
 *
 * - HTTP: at the start of every main request (sub-requests keep the state of their main request);
 * - Messenger consumers: when a message is received and after it is handled or failed;
 * - console: when the OUTERMOST command starts and when it terminates, and only while no Messenger message is in
 *   flight. A command run in-process from another command or from a message handler ({@code Application::doRun()})
 *   must not reset the profiles the outer unit configured: the nesting depth is counted on console.command /
 *   console.terminate, and the in-flight flag is set when a message is received and unset when it is handled, failed
 *   or the worker stops.
 *
 * The Messenger and console events are subscribed by name, so symfony/messenger and symfony/console
 * stay optional. Only already-instantiated profiles are reset (the iterator holds
 * {@code IGNORE_ON_UNINITIALIZED_REFERENCE} references), so nothing is built at a boundary.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ResetTranslatorsOnRequestSubscriber implements EventSubscriberInterface
{
    /** Messenger event names (class names), subscribed without requiring symfony/messenger. */
    public const MESSENGER_WORKER_MESSAGE_RECEIVED = 'Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent';
    public const MESSENGER_WORKER_MESSAGE_HANDLED  = 'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent';
    public const MESSENGER_WORKER_MESSAGE_FAILED   = 'Symfony\Component\Messenger\Event\WorkerMessageFailedEvent';

    /** Console event names (ConsoleEvents::COMMAND / TERMINATE), subscribed without requiring symfony/console. */
    public const CONSOLE_COMMAND   = 'console.command';
    public const CONSOLE_TERMINATE = 'console.terminate';

    /** Messenger event name of a stopped worker (a listener may skip a received message: no handled / failed follows). */
    public const MESSENGER_WORKER_STOPPED = 'Symfony\Component\Messenger\Event\WorkerStoppedEvent';

    /**
     * Console commands currently running in this process (nested in-process runs count). Process-lifetime counter,
     * bounded by the nesting depth; intentionally not reset by kernel.reset.
     */
    private int $consoleDepth = 0;

    /** A Messenger message is being handled (between received and handled / failed / worker stopped). */
    private bool $messageInFlight = false;

    /**
     * @param iterable<ResetInterface> $translators
     */
    public function __construct(
        private readonly iterable $translators,
    ) {
    }

    /**
     * Subscribes to kernel.request, the Messenger worker events and the console command events.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                   => ['onKernelRequest', 4096],
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => ['onMessageReceived', 4096],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => ['onMessageFinished', -4096],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => ['onMessageFinished', -4096],
            self::MESSENGER_WORKER_STOPPED          => ['onWorkerStopped', -4096],
            self::CONSOLE_COMMAND                   => ['onConsoleCommand', 4096],
            self::CONSOLE_TERMINATE                 => ['onConsoleTerminate', -4096],
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
     * Start of a Messenger message: the profiles start from their defaults.
     */
    public function onMessageReceived(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = true;
        $this->resetTranslators();
    }

    /**
     * End of a Messenger message (handled or failed).
     */
    public function onMessageFinished(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
        $this->resetTranslators();
    }

    /**
     * The worker stopped (possibly after a skipped message): no message is in flight any more.
     */
    public function onWorkerStopped(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
    }

    /**
     * Start of a command: resets only when the outermost command starts outside a Messenger message.
     */
    public function onConsoleCommand(): void
    {
        // @igor-ignore - Nesting counter of the running commands (no request data).
        if (++$this->consoleDepth === 1 && !$this->messageInFlight) {
            $this->resetTranslators();
        }
    }

    /**
     * End of a command: resets only when the outermost command ends outside a Messenger message.
     */
    public function onConsoleTerminate(): void
    {
        // @igor-ignore - Nesting counter of the running commands (no request data).
        $this->consoleDepth = max(0, $this->consoleDepth - 1);
        if ($this->consoleDepth === 0 && !$this->messageInFlight) {
            $this->resetTranslators();
        }
    }

    /**
     * Resets every already-instantiated profile translator.
     */
    public function resetTranslators(): void
    {
        foreach ($this->translators as $translator) {
            $translator->reset();
        }
    }
}
