<?php

namespace App\Support\CoreHub\Tests\Unit;

use App\Support\Testing\PackageTestCase;


use App\Support\CoreHub\HubSignature;
use PHPUnit\Framework\Attributes\Test;

/**
 * One canonical signature both directions agree on.
 *
 * The hub signs, a consumer verifies, and a single field added on one side would
 * make every push a 401 in production while both halves stay green in their own
 * suite. These assertions pin the parts a change would touch: method and path are
 * case-normalised, the body is hashed (not compared literally), and a timestamp
 * outside the window is refused even with a perfect signature.
 */
class HubSignatureTest extends PackageTestCase
{
    private const SECRET = 'consumer-secret';

    #[Test]
    public function a_signed_payload_verifies(): void
    {
        $body = '{"external_id":"user-1"}';
        $ts = time();

        $signature = HubSignature::sign(self::SECRET, $ts, 'post', '/internal/v1/identity/sync', $body);

        $this->assertTrue(HubSignature::verify(
            self::SECRET,
            $ts,
            'POST',
            '/internal/v1/identity/sync',
            $body,
            $signature,
        ));
    }

    #[Test]
    public function method_case_does_not_change_the_signature(): void
    {
        $ts = time();

        $this->assertSame(
            HubSignature::sign(self::SECRET, $ts, 'POST', '/x', ''),
            HubSignature::sign(self::SECRET, $ts, 'post', '/x', ''),
        );
    }

    #[Test]
    public function a_tampered_body_is_refused(): void
    {
        $ts = time();
        $signature = HubSignature::sign(self::SECRET, $ts, 'POST', '/internal/v1/entitlement/change', '{"tier":"paid"}');

        $this->assertFalse(HubSignature::verify(
            self::SECRET,
            $ts,
            'POST',
            '/internal/v1/entitlement/change',
            '{"tier":"free"}',
            $signature,
        ));
    }

    #[Test]
    public function another_secret_is_refused(): void
    {
        $ts = time();
        $signature = HubSignature::sign(self::SECRET, $ts, 'POST', '/x', 'body');

        $this->assertFalse(HubSignature::verify('other-secret', $ts, 'POST', '/x', 'body', $signature));
    }

    #[Test]
    public function a_replayed_timestamp_outside_the_window_is_refused(): void
    {
        $stale = time() - (HubSignature::TOLERANCE_SECONDS + 60);
        $signature = HubSignature::sign(self::SECRET, $stale, 'POST', '/x', 'body');

        $this->assertFalse(HubSignature::verify(self::SECRET, $stale, 'POST', '/x', 'body', $signature));
    }

    #[Test]
    public function an_empty_secret_never_verifies(): void
    {
        $ts = time();

        $this->assertFalse(HubSignature::verify('', $ts, 'POST', '/x', 'body', HubSignature::sign('', $ts, 'POST', '/x', 'body')));
    }
}
