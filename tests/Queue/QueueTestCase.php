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

namespace Berlioz\QueueManager\Tests\Queue;

use Berlioz\QueueManager\Job\JobDescriptor;
use Berlioz\QueueManager\Queue\PurgeableQueueInterface;
use Berlioz\QueueManager\Queue\QueueInterface;
use PHPUnit\Framework\TestCase;

abstract class QueueTestCase extends TestCase
{
    abstract public static function newQueue(): QueueInterface;

    public function testSize()
    {
        $queue = static::newQueue();

        $this->assertEquals(0, $queue->size());

        $queue->push(new JobDescriptor('test', ['foo' => 'value']));

        $this->assertEquals(1, $queue->size());

        $queue->push(new JobDescriptor('test', ['foo' => 'value']), 2);

        $this->assertEquals(1, $queue->size());

        sleep(3);

        $this->assertEquals(2, $queue->size());

    }

    public function testConsume()
    {
        $queue = static::newQueue();
        $jobId = $queue->push(new JobDescriptor('test', ['foo' => 'value']));

        $job = $queue->consume();

        $this->assertEquals($jobId, $job?->getId());
        $this->assertEquals(0, $queue->size());
    }

    public function testPush()
    {
        $queue = static::newQueue();
        $queue->push(new JobDescriptor('test', ['foo' => 'value']));

        $this->assertEquals(1, $queue->size());

        $job = $queue->consume();

        $this->assertEquals('test', $job->getName());
    }

    public function testPushRaw()
    {
        $queue = static::newQueue();
        $queue->pushRaw(new JobDescriptor('test', ['foo' => 'value']));

        $this->assertEquals(1, $queue->size());
    }

    public function testPurge()
    {
        $queue = static::newQueue();

        if (!$queue instanceof PurgeableQueueInterface) {
            $this->markTestSkipped('Queue does not implement PurgeableQueueInterface');
        }

        $queue->push(new JobDescriptor('test', ['foo' => 'value']));
        $queue->purge();

        $this->assertEquals(0, $queue->size());
    }

    public function testReleaseJob()
    {
        $queue = static::newQueue();
        $queue->push(new JobDescriptor('test', ['foo' => 'value']));

        $this->assertEquals(1, $queue->size());

        $job = $queue->consume();

        $this->assertEquals(1, $job->getAttempts());
        $this->assertEquals(0, $queue->size());
        $this->assertFalse($job->isReleased());

        $job->release();

        $this->assertEquals(1, $queue->size());
        $this->assertTrue($job->isReleased());

        $job = $queue->consume();

        $this->assertEquals(0, $queue->size());
        $this->assertEquals(2, $job->getAttempts());
    }
}
