# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [1.0.0-beta5] - In progress

### Added

- New method `PayloadInterface::getOrFail(string $path): mixed`

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