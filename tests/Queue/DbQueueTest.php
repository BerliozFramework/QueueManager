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

use Berlioz\QueueManager\Queue\DbQueue;
use Berlioz\QueueManager\Queue\QueueInterface;
use Hector\Connection\Connection;

class DbQueueTest extends QueueTestCase
{
    public static function newQueue(): QueueInterface
    {
        $connection = new Connection('sqlite::memory:');
        $connection->execute(file_get_contents(__DIR__ . '/schema-jobs-sqlite.sql'));

        return new DbQueue(connection: $connection, name: 'default');
    }
}
