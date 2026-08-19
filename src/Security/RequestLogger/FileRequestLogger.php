<?php

namespace Supamask\Security\RequestLogger;

use Supamask\Contracts\RequestLoggerInterface;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Throwable;

/** Appends one JSON event per request to bouncer.log. */
final class FileRequestLogger implements RequestLoggerInterface
{
    private string $directory;
    private bool $includeQueryString;

    public function __construct(string $directory, bool $includeQueryString = false, ?string $basePath = null)
    {
        $this->directory = self::resolveDirectory($directory, $basePath);
        $this->includeQueryString = $includeQueryString;
    }

    public function log(Context $context, Decision $decision): void
    {
        try {
            if ($this->directory === '') {
                return;
            }
            if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
                return;
            }

            $requestContext = $context->requestContext();
            $uri = $requestContext->path();
            if ($this->includeQueryString && $requestContext->query() !== '') {
                $uri .= '?' . $requestContext->query();
            }

            $event = [
                'timestamp' => date(DATE_ATOM),
                'ip' => $requestContext->ip(),
                'method' => $requestContext->method(),
                'uri' => $uri,
                'decision' => $decision->name,
                'reason' => $context->decisionReason() ?? self::defaultReason($decision),
                'user_agent' => $requestContext->userAgent(),
                'referrer' => $requestContext->referrer(),
            ];

            $intelligence = $context->ipIntelligence();
            if ($intelligence !== null) {
                $event['asn'] = $intelligence->asn();
                $event['organization'] = $intelligence->organization();
                $event['is_vpn'] = $intelligence->isVpn();
                $event['is_proxy'] = $intelligence->isProxy();
                $event['is_tor'] = $intelligence->isTor();
                $event['is_relay'] = $intelligence->isRelay();
            }

            $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($line === false) {
                return;
            }

            @file_put_contents(
                $this->directory . DIRECTORY_SEPARATOR . 'bouncer.log',
                $line . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (Throwable) {
            // Logging must never alter security decisions or application flow.
        }
    }

    private static function defaultReason(Decision $decision): string
    {
        return match ($decision) {
            Decision::ALLOW => 'allowed',
            Decision::CHALLENGE => 'challenge_required',
            Decision::DENY => 'denied',
        };
    }

    private static function resolveDirectory(string $directory, ?string $basePath): string
    {
        $directory = trim($directory);
        if ($directory === '') {
            return '';
        }
        if (self::isAbsolutePath($directory)) {
            return rtrim($directory, DIRECTORY_SEPARATOR);
        }

        $base = $basePath;
        if ($base === null || trim($base) === '') {
            $base = $_SERVER['DOCUMENT_ROOT'] ?? null;
        }
        if (!is_string($base) || trim($base) === '') {
            $script = $_SERVER['SCRIPT_FILENAME'] ?? null;
            $base = is_string($script) && $script !== '' ? dirname($script) : getcwd();
        }

        return rtrim((string) $base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($directory, DIRECTORY_SEPARATOR);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1
            || str_starts_with($path, '\\\\');
    }
}
