<?php

declare(strict_types=1);

namespace Omegaalfa\HttpPromise\Http;

use InvalidArgumentException;

/**
 * Validates URLs to prevent SSRF (Server-Side Request Forgery) attacks.
 * 
 * Blocks access to private IP ranges, reserved addresses, and non-HTTP(S) protocols.
 */
final class UrlValidator
{
    /** @var array<string> Allowed URL protocols */
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Validates a URL to prevent SSRF attacks.
     *
     * @param string $url URL to validate
     * @return void
     * @throws InvalidArgumentException If URL is invalid or potentially unsafe
     */
    public function validate(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new InvalidArgumentException('Invalid URL format');
        }

        $this->validateScheme($parsed['scheme']);
        $this->validateHost($parsed['host']);
    }

    /**
     * Validates the URL scheme against allowed protocols.
     *
     * @param string $scheme URL scheme (protocol)
     * @return void
     * @throws InvalidArgumentException If scheme is not allowed
     */
    private function validateScheme(string $scheme): void
    {
        if (!in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    "Protocol '%s' not allowed. Only HTTP/HTTPS are permitted.",
                    $scheme
                )
            );
        }
    }

    /**
     * Validates the host to prevent access to private/reserved IP addresses.
     *
     * @param string $host Hostname or IP address
     * @return void
     * @throws InvalidArgumentException If host resolves to private/reserved IP
     */
    private function validateHost(string $host): void
    {
        // Resolve DNS
        $ip = @gethostbyname($host);

        // If resolution failed or host is literal IP, use it directly
        if (($ip === $host) && filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        }

        // Validate that IP is not private/reserved/loopback
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->validateIpAddress($ip);
        }
    }

    /**
     * Validates an IP address is not in private or reserved ranges.
     *
     * @param string $ip IP address to validate
     * @return void
     * @throws InvalidArgumentException If IP is in private/reserved range
     */
    private function validateIpAddress(string $ip): void
    {
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if (!$isPublic) {
            throw new InvalidArgumentException(
                sprintf('Access to private/reserved IP addresses is forbidden: %s', $ip)
            );
        }
    }

    /**
     * Checks if a URL is valid without throwing exceptions.
     *
     * @param string $url URL to check
     * @return bool True if URL is valid and safe
     */
    public function isValid(string $url): bool
    {
        try {
            $this->validate($url);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Returns the list of allowed URL schemes.
     *
     * @return array<string> Allowed schemes
     */
    public function getAllowedSchemes(): array
    {
        return self::ALLOWED_SCHEMES;
    }
}
