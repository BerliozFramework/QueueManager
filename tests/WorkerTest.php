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

namespace Berlioz\QueueManager\Tests;

use Berlioz\QueueManager\Handler\JobHandlerInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Queue\QueueInterface;
use Berlioz\QueueManager\Worker;
use Berlioz\QueueManager\WorkerExit;
use Berlioz\QueueManager\WorkerOptions;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

class WorkerTest extends TestCase
{
    private JobHandlerInterface $jobHandlerMock;
    private QueueInterface $queueMock;
    private Worker $worker;

    protected function setUp(): void
    {
        $this->jobHandlerMock = $this->createMock(JobHandlerInterface::class);
        $this->queueMock = $this->createMock(QueueInterface::class);

        $this->worker = new Worker($this->jobHandlerMock);
    }

    public function testMemoryLimitExceeded()
    {
        $workerOptions = new WorkerOptions(memoryLimit: 10); // 10 MB

        $workerStub = $this->getMockBuilder(Worker::class)
            ->setConstructorArgs([$this->jobHandlerMock])
            ->onlyMethods(['getMemoryUsage'])
            ->getMock();

        $workerStub->method('getMemoryUsage')->willReturn(15); // Simulate 15 MB usage

        $this->assertTrue($workerStub->memoryLimitExceeded($workerOptions));
    }

    public function testTimeLimitExceeded()
    {
        $workerOptions = new WorkerOptions(timeLimit: 1); // 1 second
        $startTime = 0;

        $workerStub = $this->getMockBuilder(Worker::class)
            ->setConstructorArgs([$this->jobHandlerMock])
            ->onlyMethods(['hrtime'])
            ->getMock();

        $workerStub->method('hrtime')->willReturn(2_000_000_000); // Simulate 2 seconds in nanoseconds

        $this->assertTrue($workerStub->timeLimitExceeded($startTime, $workerOptions));
    }

    public function testKillFileExists()
    {
        $killFile = tempnam(sys_get_temp_dir(), 'berlioz');
        $workerOptions = new WorkerOptions(killFilePath: $killFile);

        $this->assertTrue($this->worker->killFileExists($workerOptions));

        unlink($workerOptions->killFilePath);
    }

    public function testContinueShouldTerminate()
    {
        $workerStub = $this->getMockBuilder(Worker::class)
            ->setConstructorArgs([$this->jobHandlerMock])
            ->onlyMethods([])
            ->getMock();

        $reflection = new ReflectionClass(Worker::class);
        $property = $reflection->getProperty('shouldTerminate');
        $property->setValue($workerStub, true);

        $workerExit = $workerStub->continue(new WorkerOptions(), 0, null, 0);

        $this->assertSame(WorkerExit::SHOULD_TERMINATE, $workerExit);
    }

    public function testExecuteJobCallsJobHandler()
    {
        $jobMock = $this->createMock(JobInterface::class);

        $this->jobHandlerMock
            ->expects($this->once())
            ->method('handle')
            ->with($jobMock);

        $this->worker->executeJob($jobMock);
    }

    public function testRunProcessesJobSuccessfully()
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $jobMock = $this->createMock(JobInterface::class);

        $this->worker->setLogger($loggerMock);

        $this->queueMock->method('consume')->willReturnOnConsecutiveCalls($jobMock, null);
        $jobMock->expects($this->once())->method('delete');
        $jobMock->method('getId')->willReturn('Job123');
        $jobMock->method('getQueue')->willReturn($this->queueMock);

        $loggerMock
            ->expects($this->exactly(4))
            ->method('info')
            ->willReturnOnConsecutiveCalls(
                [$this->stringContains('Start worker on queue(s):')],
                [$this->stringContains('Job Job123 consumed from queue')],
                [$this->stringContains('Job Job123 executed from queue')],
                [$this->stringContains('Exit')]
            );

        $exitCode = $this->worker->run($this->queueMock, new WorkerOptions(limit: 1));

        $this->assertSame(WorkerExit::LIMIT_EXCEEDED->code(), $exitCode);
    }

    public function testRunHandlesJobException()
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $jobMock = $this->createMock(JobInterface::class);

        $this->worker->setLogger($loggerMock);

        $this->queueMock->method('consume')->willReturn($jobMock);
        $jobMock->method('getId')->willReturn('Job123');
        $jobMock->method('getQueue')->willReturn($this->queueMock);

        $this->jobHandlerMock->method('handle')->willThrowException(new \Exception('Job failed'));

        $jobMock->expects($this->once())->method('release');
        $loggerMock->expects($this->once())->method('error');

        $exitCode = $this->worker->run($this->queueMock, new WorkerOptions(limit: 1));
        $this->assertSame(WorkerExit::LIMIT_EXCEEDED->code(), $exitCode);
    }

    public function testRunExitsWhenKillFileExists()
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->worker->setLogger($loggerMock);

        $killFile = tempnam(sys_get_temp_dir(), 'berlioz');

        $workerOptions = new WorkerOptions(killFilePath: $killFile);
        $exitCode = $this->worker->run($this->queueMock, $workerOptions);

        $this->assertSame(WorkerExit::SHOULD_TERMINATE->code(), $exitCode);

        unlink($killFile); // Cleanup
    }
}
