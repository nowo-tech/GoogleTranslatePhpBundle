<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * Messenger receiver of the worker test: hands out $count messages for two tenants in alternation (every 7th one
 * fails in the handler) and keeps no envelope, so memory reflects the bundle only.
 */
final class TenantReceiver implements ReceiverInterface
{
    private int $next = 0;

    public int $acknowledged = 0;

    public int $rejected = 0;

    public function __construct(
        private readonly int $count,
    ) {
    }

    public function get(): iterable
    {
        if ($this->next >= $this->count) {
            return [];
        }

        $index = $this->next++;

        return [new Envelope(new TenantTranslation($index % 2 === 0 ? 'A' : 'B', $index, $index % 7 === 3))];
    }

    public function ack(Envelope $envelope): void
    {
        ++$this->acknowledged;
    }

    public function reject(Envelope $envelope): void
    {
        ++$this->rejected;
    }
}
