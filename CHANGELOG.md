# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [1.0.0-beta10] - 2025-02-04

### Fixed

- Missing transaction during lock of row

## [1.0.0-beta9] - 2025-02-04

### Added

- New `RedisQueue` and `RedisJob`

### Changed

- `DbQueue` use lock for update from `hectororm/query` package instead of try another job

## [1.0.0-beta8] - 2025-02-03

### Added

- New option `WorkerOptions::$backoffTime` (default: 0) to wait before retry failed job

## [1.0.0-beta7] - 2025-01-27

### Added

- `$retryTime` property for `DbQueue` and `AwsSqsQueue`

### Changed

- Methods `QueueInterface::push()` and `QueueInterface::pushRaw()`, accept `\DateTimeInterface` or `\DateInterval` for `$delay` parameter

### Fixed

- Attempts value do not increased after consume
- `SqsJob` job name

## [1.0.0-beta6] - 2025-01-23

### Fixed

- Order of filtered queues with wildcard

## [1.0.0-beta5] - 2025-01-23

### Added

- New method `PayloadInterface::getOrFail(string $path): mixed`
- Support of wildcard "*" in job name, in `JobHandlerManager::addHandler()` method
- Support of wildcard "*" in queue name, in `QueueManager::filter()` method

## [1.0.0-beta4] - 2025-01-20

### Added

- New method `QueueManager::stats()` to get size of queues
- New option `WorkerOptions::$sleepNoJob` (default: 1 second) to wait before retry consumption in case of no job

### Changed

- Try another consumption of job, if no job found instead of increment job executed

## [1.0.0-beta3] - 2024-11-26

### Deleted

- Remove sleep before exit

### Fixed

- TypeError with float to usleep() method

## [1.0.0-beta2] - 2024-11-22

### Changed

- `WorkerOptions::$sleep` accept float value
- Add "LIMIT 1" in query for `DbQueue` to optimize performances

## [1.0.0-beta1] - 2024-11-19

Initial version