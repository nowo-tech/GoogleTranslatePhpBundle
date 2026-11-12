<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\EventSubscriber;

use Nowo\GoogleTranslatePhpBundle\Worker\UnitOfWorkFrames;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

use const PHP_INT_MAX;

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
 *   must not reset the profiles the outer unit configured.
 *
 * Which unit is outermost is tracked by {@see UnitOfWorkFrames} (1.4.1, audits NM / NL2-v / NL2-v2), fed at priority
 * PHP_INT_MAX so another listener that throws or stops propagation cannot unbalance it: one frame per console run
 * (Command + input) that saves and restores the outer Messenger state, a frame left failing by a throwing console.error
 * listener healed at the next request, message, command or worker stop (before the handler writes its own flag). The
 * outermost command resets right after its frame is pushed and at -4096 on terminate (verdict kept per event).
 *
 * The Messenger and console events are subscribed by name, so symfony/messenger and symfony/console
 * stay optional. Only already-instantiated profiles are reset (the iterator holds
 * {@code IGNORE_ON_UNINITIALIZED_REFERENCE} references), so nothing is built at a boundary.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ResetTranslatorsOnRequestSubscriber implements EventSubscriberInterface, ResetInterface
{
    /** Messenger event names (class names), subscribed without requiring symfony/messenger. */
    public const MESSENGER_WORKER_MESSAGE_RECEIVED = 'Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent';
    public const MESSENGER_WORKER_MESSAGE_HANDLED  = 'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent';
    public const MESSENGER_WORKER_MESSAGE_FAILED   = 'Symfony\Component\Messenger\Event\WorkerMessageFailedEvent';

    /** Messenger skip event (only dispatched by `messenger:failed:retry`): the message is no longer in flight. */
    public const MESSENGER_WORKER_MESSAGE_SKIPPED = 'Symfony\Component\Messenger\Event\WorkerMessageSkipEvent';

    /** Console event names (ConsoleEvents::COMMAND / ERROR / TERMINATE), subscribed without requiring symfony/console. */
    public const CONSOLE_COMMAND   = 'console.command';
    public const CONSOLE_ERROR     = 'console.error';
    public const CONSOLE_TERMINATE = 'console.terminate';

    /** Messenger event name of a stopped worker (a listener may skip a received message: no handled / failed follows). */
    public const MESSENGER_WORKER_STOPPED = 'Symfony\Component\Messenger\Event\WorkerStoppedEvent';

    /** Units of work running in this process (deliberately not tagged kernel.reset; the service is not autoconfigured). */
    private readonly UnitOfWorkFrames $frames;

    /**
     * @param iterable<ResetInterface> $translators
     */
    public function __construct(
        private readonly iterable $translators,
    ) {
        $this->frames = new UnitOfWorkFrames();
    }

    /**
     * Subscribes to kernel.request, the Messenger worker events and the console command events.
     *
     * @return array<string, array{0: string, 1: int}|list<array{0: string, 1: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                   => [['onKernelRequestStarted', PHP_INT_MAX], ['onKernelRequest', 4096]],
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => [['onMessageReceivedStarted', PHP_INT_MAX], ['onMessageReceived', 4096]],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => [['onMessageFinishedStarted', PHP_INT_MAX], ['onMessageFinished', -4096]],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => [['onMessageFinishedStarted', PHP_INT_MAX], ['onMessageFinished', -4096]],
            self::MESSENGER_WORKER_MESSAGE_SKIPPED  => ['onMessageFinishedStarted', PHP_INT_MAX],
            self::MESSENGER_WORKER_STOPPED          => ['onWorkerStopped', PHP_INT_MAX],
            // Bookkeeping first (PHP_INT_MAX), the end-of-command reset late: a listener in between cannot skip it.
            self::CONSOLE_COMMAND   => ['onConsoleCommand', PHP_INT_MAX],
            self::CONSOLE_ERROR     => ['onConsoleError', PHP_INT_MAX],
            self::CONSOLE_TERMINATE => [['onConsoleTerminateStarted', PHP_INT_MAX], ['onConsoleTerminate', -4096]],
        ];
    }

    /**
     * Main request starts (bookkeeping): heals a run left failing. The main-request depth is deliberately not kept
     * (requestFinished() at once): the request reset is unconditional and a command run from a controller keeps its
     * pre-1.4.1 behaviour (it is its own outermost unit).
     */
    public function onKernelRequestStarted(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->frames->requestStarted();
            $this->frames->requestFinished();
        }
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
     * A Messenger message is received (bookkeeping).
     */
    public function onMessageReceivedStarted(): void
    {
        $this->frames->messageReceived();
    }

    /**
     * Start of a Messenger message: the profiles start from their defaults.
     */
    public function onMessageReceived(): void
    {
        $this->resetTranslators();
    }

    /**
     * A Messenger message is handled, failed or skipped (bookkeeping).
     */
    public function onMessageFinishedStarted(): void
    {
        $this->frames->messageFinished();
    }

    /**
     * End of a Messenger message (handled or failed).
     */
    public function onMessageFinished(): void
    {
        $this->resetTranslators();
    }

    /**
     * The worker stopped (possibly after a skipped message): no message is in flight any more.
     */
    public function onWorkerStopped(): void
    {
        $this->frames->workerStopped();
    }

    /**
     * A console run starts: pushes its frame; resets only when it is the outermost unit (outside a Messenger message).
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        if ($this->frames->commandStarted($event)) {
            $this->resetTranslators();
        }
    }

    /**
     * A console run failed (bookkeeping: its frame is marked failing until its own terminate).
     */
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->frames->commandFailed($event);
    }

    /**
     * A console run ends (bookkeeping: its frame is removed, the outermost verdict kept for the late reset).
     */
    public function onConsoleTerminateStarted(ConsoleTerminateEvent $event): void
    {
        $this->frames->commandTerminated($event);
    }

    /**
     * End of a command: resets only when the outermost command ends outside a Messenger message.
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ($this->frames->isOutermost($event)) {
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

    /**
     * Forgets the unit-of-work bookkeeping (not the translators: see resetTranslators()). Not tagged kernel.reset and
     * not relied on in worker mode.
     */
    public function reset(): void
    {
        $this->frames->reset();
    }
}
