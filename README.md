# Berlioz Queue Manager

[![Latest Version](https://img.shields.io/packagist/v/berlioz/queue-manager.svg?style=flat-square)](https://github.com/BerliozFramework/QueueManager/releases)
[![Software license](https://img.shields.io/github/license/BerliozFramework/QueueManager.svg?style=flat-square)](https://github.com/BerliozFramework/QueueManager/blob/1.x/LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/BerliozFramework/QueueManager/tests.yml?branch=main&style=flat-square)](https://github.com/BerliozFramework/QueueManager/actions/workflows/tests.yml?query=branch%3Amain)
[![Quality Grade](https://img.shields.io/codacy/grade/701df41ea7644c3abe548e93f0412d87/main.svg?style=flat-square)](https://www.codacy.com/manual/BerliozFramework/QueueManager)
[![Total Downloads](https://img.shields.io/packagist/dt/berlioz/queue-manager.svg?style=flat-square)](https://packagist.org/packages/berlioz/queue-manager)

**Berlioz Queue Manager** is responsible for processing jobs from a queue using a job handler. It supports advanced
features like memory and time limits, signal handling, and customizable worker options.

For more information, and use of Berlioz Framework, go to website and online documentation :
https://getberlioz.com

## Installation

### Composer

You can install **Berlioz Queue Manager** with [Composer](https://getcomposer.org/), it's the recommended installation.

```bash
$ composer require berlioz/queue-manager
```

### Dependencies

* **PHP** ^8.2
* Packages:
    * **berlioz/helpers**
    * **psr/clock**
    * **psr/container**
    * **psr/log**
    * **psr/event-dispatcher**

## Usage

### Jobs

#### `JobDescriptorInterface`

- **Definition**: Represents jobs that are ready to be pushed into a queue.
- **Example**: Defining the structure and payload of a task before queueing it.
- **Note**: A generic `JobDescriptor` class is available for creating new messages quickly. However, you can extend or override this class to provide additional control, such as custom payload validation or specific job behaviors.

#### `JobInterface`

- **Definition**: Manages jobs that have been consumed from a queue.
- **Example**: Handling retries, deleting jobs after processing, or releasing jobs back into the queue.

#### `JobForQueue`

- **Definition**: Ensures specific jobs are routed to designated queues.
- **Example**: Assigning priority tasks to a high-priority queue.


### Jobs handlers

The `JobHandlerManager` is a central component for managing multiple job handlers in the **Berlioz Queue Manager**. It implements the `JobHandlerInterface` and acts as a dispatcher, delegating job processing to the appropriate handler based on the job's name.

```php
use Berlioz\QueueManager\Job\JobHandlerManager;

$manager = new JobHandlerManager($container, $defaultHandler);
$manager->addHandler('email', EmailJobHandler::class);
$manager->addHandler('report', ReportJobHandler::class);

$job = new Job('email'); // Example job with name 'email'
$manager->handle($job); // Delegates to EmailJobHandler
```

The `JobHandlerInterface` defines the contract for handling jobs in the **Berlioz Queue Manager**. Implementing this interface allows you to define how specific jobs should be processed.


Below is an example implementation of a `JobHandlerInterface` for consuming and processing a job named `"foo"`:

```php
use Berlioz\QueueManager\Handler\JobHandlerInterface;
use Berlioz\QueueManager\Job\JobInterface;
use Berlioz\QueueManager\Exception\QueueManagerException;

class FooJobHandler implements JobHandlerInterface
{
    public function handle(JobInterface $job): void
    {
        if ($job->getName() !== 'foo') {
            throw new QueueManagerException('Invalid job name');
        }

        // Process the job
        $payload = $job->getPayload();
        echo "Processing job 'foo' with payload: " . json_encode($payload);
    }
}
```

TIP: `JobHandlerManager` accept a wildcard "*" at the end of job name.

### Worker

The `Worker` class is the main part of the **Berlioz Queue Manager** and is responsible for processing jobs from a queue
using a job handler.

```php
use Berlioz\QueueManager\Queue\MemoryQueue;
use Berlioz\QueueManager\Worker;
use Berlioz\QueueManager\WorkerOptions;
use Berlioz\QueueManager\Handler\JobHandlerManager;
use Psr\Log\NullLogger;

// Create a Job Handler Manager
$jobHandler = new JobHandlerManager($container);

// Initialize the Worker
$worker = new Worker($jobHandler);

// Optionally, set a logger
$worker->setLogger(new NullLogger());

// Configure worker options
$options = new WorkerOptions(
    name: 'worker',   // Worker name
    limit: 10,        // Max jobs to execute
    memoryLimit: 128, // Memory limit in MB
    timeLimit: 60,    // Time limit in seconds
    killFilePath: 10, // File to kill process
    stopNoJob: true,  // Stop if no job
    sleep: 2          // Sleep time between jobs in seconds
);

// Create a queue instance
$queue = new MemoryQueue();

// Run the worker
$exitCode = $worker->run($queue, $options);
```

### Queues

#### DbQueue

The `DbQueue` is a durable implementation of a queue that uses a database to store jobs persistently. This ensures that jobs remain available even in the event of application or server restarts. By leveraging a database, the `DbQueue` provides reliability and durability, making it suitable for production environments where job data must not be lost.

**Key Characteristics**:

- **Durable Storage**: Jobs are stored in a relational or NoSQL database, ensuring persistence and fault tolerance.
- **Transactional Guarantees**: Can leverage database transactions to ensure that job insertion, processing, and deletion are atomic operations.
- **Scalability**: With proper indexing and optimization, the `DbQueue` can handle large volumes of jobs efficiently.
- **Use Cases**:
  - Applications that require guaranteed delivery and processing of jobs.
  - Scenarios where jobs must survive server or application crashes.
  - Environments where job metadata (e.g., retries, priorities) must be tracked over time.

While `DbQueue` offers durability and reliability, its performance may be impacted by database latency compared to in-memory queues. It is best suited for scenarios where persistence and fault tolerance are prioritized over low-latency operations.

```php
use Berlioz\QueueManager\Queue\DbQueue;
use Hector\Connection\Connection;

$dbConnection = new Connection('mysql://localhost:3306');
$queue = new DbQueue(
    connection: $dbConnection, // Database connection
    name: 'default',           // Queue name
    tableName: 'queue_jobs',   // Name of MySQL table
    retryTime: 30,             // Time to wait after failed job
    maxAttempts: 5,            // Maximum attempts of a job
);
```

Example of schema for MySQL:

```mysql
CREATE TABLE `queue_jobs` (
  `job_id` int unsigned NOT NULL AUTO_INCREMENT,
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `queue` varchar(128) NOT NULL DEFAULT 'default',
  `availability_time` timestamp NOT NULL,
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `lock_time` timestamp NULL DEFAULT NULL,
  `payload` json NOT NULL,
  PRIMARY KEY (`job_id`),
  KEY `INDEX_job` (`queue`,`availability_time`,`lock_time`,`attempts`)
) ENGINE=InnoDB;
```

If you want to keep the jobs treated:

```mysql
CREATE TABLE `queue_jobs_done` (
  `job_id` int unsigned NOT NULL,
  `create_time` timestamp NOT NULL,
  `queue` varchar(128) NOT NULL,
  `availability_time` timestamp NOT NULL,
  `attempts` int unsigned NOT NULL,
  `lock_time` timestamp NOT NULL,
  `payload` json NOT NULL,
  PRIMARY KEY (`job_id`),
  KEY `INDEX_job` (`queue`,`lock_time`)
) ENGINE=InnoDB;

-- Trigger to make automatic insert into  `queue_jobs_done`
-- the deleted done jobs into `queue_jobs`.

DELIMITER $$
CREATE
  TRIGGER `queue_jobs_AFTER_DELETE` AFTER DELETE ON `queue_jobs`
  FOR EACH ROW BEGIN
    INSERT INTO `queue_jobs_done` (`job_id`, `create_time`, `queue`, `availability_time`, `attempts`, `lock_time`, `payload`)
    VALUES (OLD.`job_id`, OLD.`create_time`, OLD.`queue`, OLD.`availability_time`, OLD.`attempts`, IFNULL(OLD.`lock_time`, CURRENT_TIMESTAMP), OLD.`payload`);
  END;$$
DELIMITER ;
```

#### Memory queue

The `MemoryQueue` is a lightweight, ephemeral implementation of a queue that stores jobs in memory for the duration of the script's execution. This queue is particularly useful for testing, development, or scenarios where persistent storage is not required. Since the jobs are stored in memory, they are lost when the script ends, making it unsuitable for production environments where job persistence is critical.

**Key Characteristics**:

- **Ephemeral Nature**: Jobs exist only during the script's runtime.
- **Fast and Lightweight**: No external dependencies or storage overhead.
- **Use Cases**:
  - Unit testing or local development.
  - Short-lived tasks that do not require durability.
  - Simulating job execution flows without external systems.

The `MemoryQueue` provides all the standard operations of a queue, such as pushing jobs, consuming jobs, and checking the size of the queue, while maintaining a simple in-memory data structure to manage these operations. However, since it lacks durability, it should be used with caution and only in scenarios where the transient nature of the data is acceptable.

```php
use Berlioz\QueueManager\Queue\MemoryQueue;

$queue = new MemoryQueue(
    name: 'default', // Queue name
    retryTime: 30,   // Time to wait after failed job
);
```

#### AwsSqsQueue

The `AwsSqsQueue` is an implementation of a queue that integrates with Amazon Simple Queue Service (SQS), a fully managed message queuing service provided by AWS. This queue leverages the scalability, durability, and distributed nature of SQS to handle job storage and delivery in a reliable and fault-tolerant manner.

**Key Characteristics**:

- **Fully Managed**: Offloads the operational complexity of managing infrastructure, scaling, and maintenance.
- **Highly Durable**: Messages are redundantly stored across multiple data centers, ensuring data durability and availability.
- **Scalable**: Capable of handling an unlimited number of messages and automatically scaling to meet demand.
- **Low Overhead**: Removes the need for a dedicated queue server or database.
- **Use Cases**:
  - Distributed systems requiring reliable asynchronous communication.
  - Scenarios with high message throughput or unpredictable traffic spikes.
  - Cloud-native applications leveraging other AWS services like Lambda or EC2.

With features like visibility timeouts, message delays, and dead-letter queues, `AwsSqsQueue` provides robust mechanisms for handling complex workflows and ensuring job delivery. However, since it is a cloud-based service, its performance depends on network latency and AWS's regional availability.

```php
use Aws\Sqs\SqsClient;
use Berlioz\QueueManager\Queue\AwsSqsQueue;

$queue = new AwsSqsQueue(
    sqsClient: new SqsClient(...), // Database connection
    name: 'default',               // Queue name
    queueUrl: '...',               // AWS queue URL
    retryTime: 30,                 // Time to wait after failed job
);
```
