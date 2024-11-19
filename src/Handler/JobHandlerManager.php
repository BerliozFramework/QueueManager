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

namespace Berlioz\QueueManager\Handler;

use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Job\JobInterface;
use Psr\Container\ContainerInterface;

class JobHandlerManager implements JobHandlerInterface
{
    private array $handlers = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?JobHandlerInterface $defaultJobHandler = null,
    ) {
    }

    public function __debugInfo(): ?array
    {
        return [
            'handlers' => $this->handlers,
            'container' => '**CONTAINER**',
        ];
    }

    /**
     * Add job handler.
     *
     * @param string $jobName
     * @param class-string<JobHandlerInterface> $jobHandlerClass
     *
     * @return self
     * @throws QueueManagerException
     */
    public function addHandler(string $jobName, string $jobHandlerClass): self
    {
        if (array_key_exists($jobName, $this->handlers)) {
            throw new QueueManagerException('A job handler already exists for job "' . $jobName . '".');
        }

        $this->handlers[$jobName] = $jobHandlerClass;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function handle(JobInterface $job): void
    {
        // No handler for job
        if (!array_key_exists($job->getName(), $this->handlers)) {
            if ($this->defaultJobHandler) {
                $this->defaultJobHandler->handle($job);
                return;
            }

            throw QueueManagerException::invalidJobHandler($job->getName());
        }

        // Get handler from service container
        $handler = $this->container->get($this->handlers[$job->getName()]);

        if (!$handler instanceof JobHandlerInterface) {
            throw QueueManagerException::invalidJobHandler($job->getName());
        }

        $handler->handle($job);
    }
}
