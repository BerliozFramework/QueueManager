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

namespace Berlioz\QueueManager\Queue;

use Berlioz\QueueManager\Exception\JobException;
use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Job\DbJob;
use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\JobInterface;
use DateInterval;
use DateTimeImmutable;
use Hector\Connection\Connection;
use Hector\Query\Component\Order;
use Hector\Query\QueryBuilder;
use Psr\Clock\ClockInterface;

class_exists(QueryBuilder::class) || throw QueueManagerException::missingPackage('hectororm/query');

readonly class DbQueue extends AbstractQueue implements PurgeableQueueInterface, ClockInterface
{
    public function __construct(
        private Connection $connection,
        string $name = 'default',
        private string $tableName = 'queue_jobs',
        private int $maxAttempts = 5,
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $builder = new QueryBuilder($this->connection);
        $builder->from($this->tableName);
        $builder->where('queue', $this->getName());

        return $builder;
    }

    /**
     * @inheritDoc
     */
    public function size(): int
    {
        return $this->getQueryBuilder()
            ->whereLessThanOrEqual('availability_time', $this->now()->format('Y-m-d H:i:s'))
            ->whereNull('lock_time')
            ->whereLessThan('attempts', $this->maxAttempts)
            ->count();
    }

    /**
     * @inheritDoc
     */
    public function consume(): ?DbJob
    {
        $attempts = 0;

        do {
            $jobRaw = $this->getQueryBuilder()
                ->whereLessThanOrEqual('availability_time', $this->now()->format("Y-m-d H:i:s"))
                ->whereNull('lock_time')
                ->whereLessThan('attempts', $this->maxAttempts)
                ->orderBy('job_id', Order::ORDER_ASC)
                ->limit(1)
                ->fetchOne();

            if (null === $jobRaw) {
                return null;
            }

            // Lock
            $affected = $this->getQueryBuilder()
                ->whereEquals(
                    array_filter(
                        $jobRaw,
                        fn($k) => in_array(
                            $k,
                            ['job_id', 'queue', 'availability_time', 'attempts', 'lock_time']
                        ),
                        ARRAY_FILTER_USE_KEY
                    )
                )
                ->update($updatedData = [
                    'lock_time' => $this->now()->format("Y-m-d H:i:s"),
                    'attempts' => ($jobRaw['attempts'] ?? 0) + (null !== $jobRaw['lock_time'] ? 1 : 0),
                ]);

            if ($affected === 1) {
                return $this->createJob(array_replace($jobRaw, $updatedData));
            }

            $attempts++;
        } while ($attempts < 5);

        return null;
    }

    /**
     * Create job.
     *
     * @param array $raw
     *
     * @return DbJob
     */
    protected function createJob(array $raw): DbJob
    {
        $payload = json_decode($raw['payload'], true);
        $name = $payload['jobName'] ?? null;
        unset($payload['jobName']);

        return new DbJob(
            id: (string)$raw['job_id'],
            name: $name,
            attempts: $raw['attempts'] ?? 0,
            payload: $payload,
            queue: $this,
        );
    }

    /**
     * @inheritDoc
     */
    public function push(JobDescriptorInterface $jobDescriptor, int $delay = 0): string
    {
        $attempts = 0;
        if ($jobDescriptor instanceof JobInterface) {
            $attempts += $jobDescriptor->getAttempts() + 1;
        }

        return $this->pushRaw(
            payload: $jobDescriptor,
            delay: $delay,
            attempts: $attempts,
        );
    }

    /**
     * @inheritDoc
     */
    public function pushRaw(mixed $payload, int $delay = 0, int $attempts = 0): string
    {
        $now = $this->now();

        $this->getQueryBuilder()
            ->insert([
                'create_time' => $now->format('Y-m-d H:i:s'),
                'queue' => $this->name,
                'availability_time' => $now->add(new DateInterval('PT' . $delay . 'S'))->format('Y-m-d H:i:s'),
                'attempts' => $attempts,
                'payload' => json_encode($payload),
            ]);

        return $this->connection->getLastInsertId($this->tableName);
    }

    /**
     * @inheritDoc
     */
    public function purge(): void
    {
        $this->getQueryBuilder()->delete();
    }

    /**
     * Release job.
     *
     * @param DbJob $job
     * @param int $delay
     *
     * @return void
     * @throws QueueException
     */
    public function release(DbJob $job, int $delay = 0): void
    {
        $job->isReleased() && throw JobException::alreadyReleased($job);
        $job->isDeleted() && throw JobException::alreadyDeleted($job);

        $this->getQueryBuilder()
            ->where('job_id', $job->getId())
            ->update([
                'availability_time' => $this->now()->add(new DateInterval('PT' . $delay . 'S'))->format('Y-m-d H:i:s'),
                'attempts' => $job->getAttempts() + 1,
                'lock_time' => null,
            ]);
    }

    /**
     * Delete job.
     *
     * @param DbJob $job
     *
     * @return void
     * @throws JobException
     */
    public function delete(DbJob $job): void
    {
        $job->isReleased() && throw JobException::alreadyReleased($job);
        $job->isDeleted() && throw JobException::alreadyDeleted($job);

        $this->getQueryBuilder()
            ->where('job_id', $job->getId())
            ->delete();
    }
}
