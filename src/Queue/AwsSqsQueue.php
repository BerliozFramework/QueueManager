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

use Aws\Sqs\SqsClient;
use Berlioz\QueueManager\Exception\QueueException;
use Berlioz\QueueManager\Exception\QueueManagerException;
use Berlioz\QueueManager\Job\JobDescriptorInterface;
use Berlioz\QueueManager\Job\SqsJob;

class_exists(SqsClient::class) || throw QueueManagerException::missingPackage('aws/aws-sdk-php');

readonly class AwsSqsQueue extends AbstractQueue implements PurgeableQueueInterface
{
    public function __construct(
        private SqsClient $sqsClient,
        private string $queueUrl,
        string $name = 'default',
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function size(): int
    {
        $response = $this->sqsClient->getQueueAttributes([
            'QueueUrl' => $this->queueUrl,
            'AttributeNames' => ['ApproximateNumberOfMessages'],
        ]);

        return (int)$response->get('Attributes')['ApproximateNumberOfMessages'] ?? 0;
    }

    /**
     * @inheritDoc
     *
     * Result syntax:
     *
     * [
     *     'Messages' => [
     *         [
     *             'Attributes' => ['<string>', ...],
     *             'Body' => '<string>',
     *             'MD5OfBody' => '<string>',
     *             'MD5OfMessageAttributes' => '<string>',
     *             'MessageAttributes' => [
     *                 '<String>' => [
     *                     'BinaryListValues' => [<string || resource || Psr\Http\Message\StreamInterface>, ...],
     *                     'BinaryValue' => <string || resource || Psr\Http\Message\StreamInterface>,
     *                     'DataType' => '<string>',
     *                     'StringListValues' => ['<string>', ...],
     *                     'StringValue' => '<string>',
     *                 ],
     *                 // ...
     *             ],
     *             'MessageId' => '<string>',
     *             'ReceiptHandle' => '<string>',
     *         ],
     *         // ...
     *     ]
     * ]
     */
    public function consume(): ?SqsJob
    {
        $result = $this->sqsClient->receiveMessage([
            'QueueUrl' => $this->queueUrl,
            'AttributeNames' => ['ApproximateReceiveCount'],
        ])->toArray()['Messages'][0] ?? null;

        if (null === $result) {
            return null;
        }

        // Checksum
        if ($result['MD5OfBody'] !== md5($result['Body'])) {
            throw QueueException::checksum();
        }

        return new SqsJob($result, $this);
    }

    /**
     * @inheritDoc
     */
    public function push(JobDescriptorInterface $jobDescriptor, int $delay = 0): string
    {
        return $this->pushRaw($jobDescriptor, $delay);
    }

    /**
     * @inheritDoc
     */
    public function pushRaw(mixed $payload, int $delay = 0): string
    {
        $result = $this->sqsClient->sendMessage([
            'DelaySeconds' => $delay,
            'MessageBody' => json_encode($payload),
            'QueueUrl' => $this->queueUrl,
        ]);

        return (string)$result->get('MessageId');
    }

    /**
     * @inheritDoc
     */
    public function purge(): void
    {
        $this->sqsClient->purgeQueue([
            'QueueUrl' => $this->queueUrl,
        ]);
    }

    /**
     * Release job.
     *
     * @param SqsJob $job
     * @param int $delay
     *
     * @return void
     */
    public function release(SqsJob $job, int $delay = 0): void
    {
        $this->sqsClient->changeMessageVisibility([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $job->getAwsResult()['ReceiptHandle'],
            'VisibilityTimeout' => $delay,
        ]);
    }

    /**
     * Delete job.
     *
     * @param SqsJob $job
     *
     * @return void
     */
    public function delete(SqsJob $job): void
    {
        $this->sqsClient->deleteMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageId' => $job->getId(),
        ]);
    }
}
