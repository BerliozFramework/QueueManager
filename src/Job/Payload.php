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

namespace Berlioz\QueueManager\Job;

readonly class Payload implements PayloadInterface
{
    public function __construct(private array $payload)
    {
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * Get array copy.
     *
     * @return array
     */
    public function getArrayCopy(): array
    {
        return $this->payload;
    }

    /**
     * Get value in payload.
     *
     * @param string $path
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $payload = $this->payload;
        return b_array_traverse_get($payload, $path, $default);
    }
}
