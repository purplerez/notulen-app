<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_normalizes_standard_08_prefix(): void
    {
        $this->assertEquals('628123456789', PhoneNumber::normalize('08123456789'));
        $this->assertEquals('6281234567890', PhoneNumber::normalize('081234567890'));
        $this->assertEquals('6285211223344', PhoneNumber::normalize('085211223344'));
    }

    public function test_normalizes_plus_628_prefix(): void
    {
        $this->assertEquals('628123456789', PhoneNumber::normalize('+628123456789'));
        $this->assertEquals('6285712345678', PhoneNumber::normalize('+6285712345678'));
    }

    public function test_normalizes_628_without_plus_prefix(): void
    {
        $this->assertEquals('628123456789', PhoneNumber::normalize('628123456789'));
    }

    public function test_normalizes_8_prefix_without_zero(): void
    {
        $this->assertEquals('628123456789', PhoneNumber::normalize('8123456789'));
        $this->assertEquals('6287812345678', PhoneNumber::normalize('87812345678'));
    }

    public function test_handles_spaces_dashes_dots_and_parentheses(): void
    {
        $this->assertEquals('628123456789', PhoneNumber::normalize('0812 3456 789'));
        $this->assertEquals('6281234567890', PhoneNumber::normalize('0812-3456-7890'));
        $this->assertEquals('6281234567890', PhoneNumber::normalize('(0812) 3456-7890'));
        $this->assertEquals('6281234567890', PhoneNumber::normalize('+62 812-3456-7890'));
        $this->assertEquals('6281234567890', PhoneNumber::normalize('0812.3456.7890'));
    }

    public function test_rejects_landline_numbers(): void
    {
        $this->assertNull(PhoneNumber::normalize('0211234567'));
        $this->assertNull(PhoneNumber::normalize('+62211234567'));
        $this->assertNull(PhoneNumber::normalize('0315551234'));
    }

    public function test_rejects_non_indonesian_numbers(): void
    {
        $this->assertNull(PhoneNumber::normalize('+12025550123'));
        $this->assertNull(PhoneNumber::normalize('+447911123456'));
        $this->assertNull(PhoneNumber::normalize('+60123456789'));
    }

    public function test_rejects_alphabetic_and_invalid_symbols(): void
    {
        $this->assertNull(PhoneNumber::normalize('0812abc3456'));
        $this->assertNull(PhoneNumber::normalize('+62812#3456'));
        $this->assertNull(PhoneNumber::normalize('not-a-number'));
    }

    public function test_rejects_too_short_or_too_long_numbers(): void
    {
        $this->assertNull(PhoneNumber::normalize('0812'));
        $this->assertNull(PhoneNumber::normalize('81234'));
        $this->assertNull(PhoneNumber::normalize('0812345678901234567'));
    }

    public function test_rejects_empty_and_null_inputs(): void
    {
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize('   '));
        $this->assertNull(PhoneNumber::normalize(null));
    }

    public function test_normalize_or_fail_throws_on_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PhoneNumber::normalizeOrFail('invalid-phone');
    }

    public function test_normalize_or_fail_succeeds_on_valid(): void
    {
        $this->assertEquals('628123456789', PhoneNumber::normalizeOrFail('08123456789'));
    }
}
