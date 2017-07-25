# ProcessManager

| `master` | `develop` |
|----------|-----------|
| [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Innmind/ProcessManager/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Innmind/ProcessManager/?branch=master) | [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Innmind/ProcessManager/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/Innmind/ProcessManager/?branch=develop) |
| [![Code Coverage](https://scrutinizer-ci.com/g/Innmind/ProcessManager/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Innmind/ProcessManager/?branch=master) | [![Code Coverage](https://scrutinizer-ci.com/g/Innmind/ProcessManager/badges/coverage.png?b=develop)](https://scrutinizer-ci.com/g/Innmind/ProcessManager/?branch=develop) |
| [![Build Status](https://scrutinizer-ci.com/g/Innmind/ProcessManager/badges/build.png?b=master)](https://scrutinizer-ci.com/g/Innmind/ProcessManager/build-status/master) | [![Build Status](https://scrutinizer-ci.com/g/Innmind/ProcessManager/badges/build.png?b=develop)](https://scrutinizer-ci.com/g/Innmind/ProcessManager/build-status/develop) |

Simple library to execute code in parallel thanks to `pcntl_fork`.

## Installation

```sh
composer require innmind/process-manager
```

## Usage

```php
use Innmind\ProcessManager\Manager\Parallel;
use Innmind\Immutable\Sequence;
use GuzzleHttp\Client;

$urls = new Sequence(
    'http://google.com',
    'http://github.com',
    'http://wikipedia.org'
);
$http = new Client;
$crawl = $urls->reduce(
    new Parallel,
    static function(Parallel $parallel, string $url) use ($http): Parallel {
        return $parallel->schedule(static function() use ($http, $url): void {
            file_put_contents(
                '/tmp/'.md5($url),
                (string) $http->get($url)->getBody()
            );
        });
    }
);
$crawl = $crawl();
echo 'These urls are being crawled in parallel: '.$urls->join(', ');
$crawl->wait();
```

This sample will crawl the 3 urls in parallel via sub processes.

**Important**: with this code you cannot return values, if you want to return content to the parent process you need to implement IPC over socket or shared memory (this may be implemented in future versions).

As you may have noticed `Parallel::__invoke()` return a new instance, this means that this code `$crawl() && $crawl->wait()` will not wait for the sub processes to finish. This behaviour is implemented so you can safely re-run a set a jobs, in other words you can do `$crawl()->wait() && $crawl()->wait()` (which will crawl each url twice).
