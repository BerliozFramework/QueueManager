<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\QueueManager\Tests\Queue;

use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\RedisJob;
use Berlioz\QueueManager\Queue\RedisQueue;
use PHPUnit\Framework\TestCase;
use Redis;

class RedisQueueTest extends TestCase
{
    private Redis $redisMock;
    private RedisQueue $queue;

    protected function setUp(): void
    {
        $this->redisMock = $this->createMock(Redis::class);
        $this->queue = new RedisQueue($this->redisMock, 'testQueue');
    }

    public function testGetName()
    {
        $this->assertSame('testQueue', $this->queue->getName());
    }

    public function testSize()
    {
        $this->redisMock
            ->method('llen')
            ->with('testQueue')
            ->willReturn(5);

        $this->assertSame(5, $this->queue->size());
    }

    public function testConsumeReturnsJob()
    {
        $jobData = json_encode(['jobId' => '123', 'payload' => '{"key":"value"}', 'attempts' => 0]);

        $this->redisMock
            ->method('lpop')
            ->with('testQueue')
            ->willReturn($jobData);

        $job = $this->queue->consume();
        $this->assertInstanceOf(RedisJob::class, $job);
        $this->assertSame('123', $job->getId());
    }

    public function testConsumeReturnsNullWhenEmpty()
    {
        $this->redisMock
            ->method('lpop')
            ->with('testQueue')
            ->willReturn(false);

        $this->assertNull($this->queue->consume());
    }

    public function testPushReturnsJobId()
    {
        $jobDescriptorMock = $this->createMock(JobDescriptorInterface::class);
        $jobDescriptorMock->method('jsonSerialize')->willReturn(['key' => 'value']);

        $this->redisMock
            ->expects($this->once())
            ->method('rPush')
            ->with(
                'testQueue',
                $this->callback(function ($jobData) {
                    $decoded = json_decode($jobData, true);
                    return isset($decoded['jobId'], $decoded['payload']) && $decoded['payload'] === '{"key":"value"}';
                })
            );

        $jobId = $this->queue->push($jobDescriptorMock);
        $this->assertNotEmpty($jobId);
    }

    public function testPushWithDelayAddsToDelayedQueue()
    {
        $jobDescriptorMock = $this->createMock(JobDescriptorInterface::class);
        $jobDescriptorMock->method('jsonSerialize')->willReturn(['key' => 'value']);

        $this->redisMock
            ->expects($this->once())
            ->method('zadd')
            ->with('testQueue:delayed', ['NX'], $this->greaterThan(time()), $this->callback(function ($jobData) {
                $decoded = json_decode($jobData, true);
                return isset($decoded['jobId'], $decoded['payload']) && $decoded['payload'] === '{"key":"value"}';
            }));

        $jobId = $this->queue->push($jobDescriptorMock, 10);
        $this->assertNotEmpty($jobId);
    }

    public function testFreeDelayedJobs()
    {
        $jobData = json_encode(['jobId' => '123', 'payload' => '{"key":"value"}', 'attempts' => 0]);

        $this->redisMock
            ->expects($this->once())
            ->method('set')
            ->with('testQueue:delayed:lock', '1', ['nx', 'ex' => 10])
            ->willReturn(true);

        $this->redisMock
            ->expects($this->once())
            ->method('zrangebyscore')
            ->with('testQueue:delayed', '-inf', (string)time())
            ->willReturn([$jobData]);

        $this->redisMock
            ->expects($this->once())
            ->method('zrem')
            ->with('testQueue:delayed', $jobData);

        $this->redisMock
            ->expects($this->once())
            ->method('rpush')
            ->with('testQueue', $jobData);

        $this->queue->freeDelayedJobs();
    }
}
