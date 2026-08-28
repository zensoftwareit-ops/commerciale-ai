<?php

namespace App\Services\Auth;

use App\Models\User;

class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = 'Daria';
        $label = $issuer.':'.$user->email;

        return 'otpauth://totp/'.rawurlencode($label).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function code(string $secret, ?int $timestamp = null, int $digits = 6): string
    {
        $counter = intdiv($timestamp ?? time(), 30);
        $binaryCounter = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $hash = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }

        $timestamp ??= time();
        foreach ([-30, 0, 30] as $offset) {
            if (hash_equals($this->code($secret, $timestamp + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function recoveryCodes(int $count = 8): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return array_map(function () use ($alphabet): string {
            $raw = '';
            for ($i = 0; $i < 10; $i++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            return substr($raw, 0, 5).'-'.substr($raw, 5);
        }, range(1, $count));
    }

    public function hashRecoveryCode(string $code): string
    {
        return hash('sha256', strtoupper(str_replace(['-', ' '], '', trim($code))));
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $candidate = $this->hashRecoveryCode($code);
        $hashes = $user->two_factor_recovery_codes ?? [];
        foreach ($hashes as $index => $hash) {
            if (! hash_equals((string) $hash, $candidate)) {
                continue;
            }

            unset($hashes[$index]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
            return true;
        }

        return false;
    }

    private function base32Encode(string $binary): string
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';
        foreach (unpack('C*', $binary) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }
        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $buffer = 0;
        $bits = 0;
        $binary = '';
        foreach (str_split(strtoupper(rtrim($encoded, '='))) as $character) {
            $value = strpos(self::ALPHABET, $character);
            if ($value === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits < 8) {
                continue;
            }

            $bits -= 8;
            $binary .= chr(($buffer >> $bits) & 255);
            $buffer &= (1 << $bits) - 1;
        }

        return $binary;
    }
}
