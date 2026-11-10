<?php

namespace App\Support;

/**
 * Which proxies ReserveBot trusts to pass on a visitor's real address (the X-Forwarded-For header).
 *
 * On your own PC nothing sits in front of the site, so nothing is trusted and every request counts as coming from
 * the address that connected. Once the site is opened through a tunnel or a proxy (ngrok, a campus reverse proxy),
 * every visitor would look like that one proxy, and the login lockout would then lock everyone out together.
 * Listing the proxy's own address in TRUSTED_PROXIES (.env) lets Laravel read the real visitor behind it.
 *
 * Only exact addresses and ranges are accepted. "Trust everyone" (*, **, REMOTE_ADDR) is refused on purpose:
 * with it, anyone could send a made-up X-Forwarded-For and walk around the login lockout.
 */
final class TrustedProxies
{
    /**
     * "127.0.0.1, 172.18.0.0/16" -> ['127.0.0.1', '172.18.0.0/16']. Anything that isn't an IP address or a CIDR
     * range is dropped. Returns null when nothing valid is listed, which means: trust no proxy.
     *
     * @return list<string>|null
     */
    public static function parse(?string $value): ?array
    {
        $entries = [];
        foreach (explode(',', (string) $value) as $entry) {
            $entry = trim($entry);
            if ($entry !== '' && self::isAddressOrRange($entry) && ! in_array($entry, $entries, true)) {
                $entries[] = $entry;
            }
        }

        return $entries ?: null;
    }

    /** An IPv4/IPv6 address, or one followed by /bits (but never a range that covers every address) */
    public static function isAddressOrRange(string $entry): bool
    {
        [$address, $bits] = array_pad(explode('/', $entry, 2), 2, null);
        $v4 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $v6 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        if (! $v4 && ! $v6) {
            return false;
        }
        if ($bits === null) {
            return true;
        }

        // /0 would be "everyone"; the smallest range allowed is /8 for IPv4 and /16 for IPv6
        return ctype_digit($bits) && (int) $bits >= ($v4 ? 8 : 16) && (int) $bits <= ($v4 ? 32 : 128);
    }
}
