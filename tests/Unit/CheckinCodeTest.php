<?php

namespace Tests\Unit;

use App\Support\CheckinCode;
use Tests\TestCase;

class CheckinCodeTest extends TestCase
{
    public function test_code_is_valid_for_current_window(): void
    {
        $meetingId = 42;
        $t = 1700000000; // Fixed timestamp

        $code = CheckinCode::generate($meetingId, $t);

        $this->assertTrue(CheckinCode::isValid($meetingId, $code, $t));
        // Still valid 15 seconds later within the same 30s window or edge
        $this->assertTrue(CheckinCode::isValid($meetingId, $code, $t + 15));
    }

    public function test_code_is_valid_for_immediately_preceding_window(): void
    {
        $meetingId = 42;
        $t = 1700000000;

        $code = CheckinCode::generate($meetingId, $t);

        // 35 seconds later (in the immediately next window)
        $tNextWindow = $t + 35;
        $this->assertTrue(CheckinCode::isValid($meetingId, $code, $tNextWindow));
    }

    public function test_code_is_rejected_for_older_windows(): void
    {
        $meetingId = 42;
        $t = 1700000000;

        $code = CheckinCode::generate($meetingId, $t);

        // 65 seconds later (two windows later)
        $tTwoWindowsLater = $t + 65;
        $this->assertFalse(CheckinCode::isValid($meetingId, $code, $tTwoWindowsLater));

        // 120 seconds later
        $this->assertFalse(CheckinCode::isValid($meetingId, $code, $t + 120));
    }

    public function test_code_is_rejected_for_future_windows(): void
    {
        $meetingId = 42;
        $t = 1700000000;

        $code = CheckinCode::generate($meetingId, $t);

        // Checking before the code was supposedly generated (server timestamp is 60 seconds earlier)
        $this->assertFalse(CheckinCode::isValid($meetingId, $code, $t - 60));
    }

    public function test_code_is_rejected_for_different_meeting_id(): void
    {
        $t = 1700000000;
        $code = CheckinCode::generate(10, $t);

        $this->assertFalse(CheckinCode::isValid(11, $code, $t));
    }

    public function test_code_is_rejected_if_tampered_or_empty(): void
    {
        $this->assertFalse(CheckinCode::isValid(10, ''));
        $this->assertFalse(CheckinCode::isValid(10, null));
        $this->assertFalse(CheckinCode::isValid(10, 'tampered-code'));
    }

    public function test_seconds_remaining_calculation(): void
    {
        // 1700000010 is a multiple of 30.
        // If timestamp is 1700000020, 10 seconds into window, remaining is 20.
        $t = 1700000020;
        $this->assertEquals(20, CheckinCode::secondsRemaining($t));

        // If timestamp is 1700000039, 29 seconds into window, remaining is 1.
        $t2 = 1700000039;
        $this->assertEquals(1, CheckinCode::secondsRemaining($t2));
    }
}
