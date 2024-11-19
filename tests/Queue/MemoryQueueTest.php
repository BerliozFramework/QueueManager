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

use Berlioz\QueueManager\Queue\MemoryQueue;
use Berlioz\QueueManager\Queue\QueueInterface;

class MemoryQueueTest extends QueueTestCase
{
    public static function newQueue(): QueueInterface
    {
        return new MemoryQueue(name: 'default');
    }
}
