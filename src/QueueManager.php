<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\QueueManager;

use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\JobForQueue;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Queue\PurgeableQueueInterface;
use Berlioz\QueueManager\Queue\QueueInterface;
use Countable;
use Generator;

readonly class QueueManager implements QueueInterface, PurgeableQueueInterface, Countable
{
    private array $queues;

    public function __construct(
        private QueueInterface $queue,
        QueueInterface ...$queues,
    ) {
        $this->queues = $queues;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return 1 + count($this->queues);
    }

    /**
     * Get queues.
     *
     * @return Generator<QueueInterface>
     */
    public function getQueues(): Generator
    {
        yield $this->queue;
        yield from $this->queues;
    }

    /**
     * Filter.
     *
     * @param string ...$queue
     *
     * @return static
     * @throws QueueManagerException
     */
    public function filter(string ...$queue): static
    {
        if (empty($queue)) {
            return $this;
        }

        $queues = iterator_to_array($this->getQueues(), false);
        $queues = array_filter($queues, fn(QueueInterface $q): bool => in_array($q->getName(), $queue));

        if (empty($queues)) {
            throw QueueManagerException::queueNotFound(...$queue);
        }

        usort(
            $queues,
            fn($a, $b) => array_search($a->getName(), $queue) <=> array_search($b->getName(), $queue)
        );

        return new self(...$queues);
    }

    /**
     * Get stats.
     *
     * @return Generator<string, int>
     * @throws QueueException
     */
    public function stats(): Generator
    {
        foreach ($this->getQueues() as $queue) {
            yield $queue->getName() => $queue->size();
        }
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return implode(
            ', ',
            array_map(
                fn(QueueInterface $q) => $q->getName(),
                iterator_to_array($this->getQueues(), false),
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function size(): int
    {
        $total = 0;

        foreach ($this->getQueues() as $queue) {
            $total += $queue->size();
        }

        return $total;
    }

    /**
     * @inheritDoc
     */
    public function consume(): ?JobInterface
    {
        /** @var QueueInterface $queue */
        foreach ($this->getQueues() as $queue) {
            if (null !== ($job = $queue->consume())) {
                return $job;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     * @param ?string $queue
     */
    public function push(JobDescriptorInterface $jobDescriptor, int $delay = 0, ?string $queue = null): string
    {
        if ($jobDescriptor instanceof JobForQueue) {
            $queue = $jobDescriptor->forQueue() ?: $queue;
        }

        if (null === $queue) {
            return $this->queue->push($jobDescriptor, $delay);
        }

        foreach ($this->getQueues() as $queueObj) {
            if ($queueObj->getName() === $queue) {
                return $queueObj->push($jobDescriptor, $delay);
            }
        }

        throw new QueueException('No queue found to push job');
    }

    /**
     * @inheritDoc
     * @param ?string $queue
     */
    public function pushRaw(mixed $payload, int $delay = 0, ?string $queue = null): string
    {
        if (null === $queue) {
            return $this->queue->pushRaw($payload);
        }

        foreach ($this->getQueues() as $queueObj) {
            if ($queueObj->getName() === $queue) {
                return $queueObj->pushRaw($payload, $delay);
            }
        }

        throw new QueueException('No queue found to push job');
    }

    /**
     * @inheritDoc
     */
    public function purge(): void
    {
        foreach ($this->getQueues() as $queue) {
            if (!$queue instanceof PurgeableQueueInterface) {
                continue;
            }

            $queue->purge();
        }
    }
}
