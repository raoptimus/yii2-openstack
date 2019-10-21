<?php

namespace raoptimus\openstack;

class HttpHelper
{
    public static function buildUrl(array $url): string
    {
        $q = empty($url['query']) ? '' : ('?' . $url['query']);
        $p = isset($url['port']) ? ':' . $url['port'] : '';

        return sprintf(
            '%s://%s%s%s%s',
            $url['scheme'],
            $url['host'],
            $p,
            $url['path'],
            $q
        );
    }

    public static function checkStatusCode(
        int $statusCode,
        array $errMap,
        string $exceptionClass = SwiftException::class
    ): bool {
        if (isset($errMap[$statusCode])) {
            throw new $exceptionClass($errMap[$statusCode], $statusCode);
        }
        if ($statusCode < HttpCode::OK || $statusCode >= HttpCode::MULTIPLE_CHOICES) {
            throw new $exceptionClass(sprintf('HTTP Error: %d', $statusCode), $statusCode);
        }

        return true;
    }
}
