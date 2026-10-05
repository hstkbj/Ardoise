<?php

namespace Tests\Unit;

use App\Support\AccessCode;
use PHPUnit\Framework\TestCase;

class AccessCodeTest extends TestCase
{
    public function test_generated_codes_are_well_formed_and_formatted(): void
    {
        $code = AccessCode::generate();

        $this->assertSame(12, strlen($code));
        $this->assertTrue(AccessCode::isWellFormed($code));
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', AccessCode::format($code));
    }

    public function test_free_input_is_normalized_before_hashing(): void
    {
        $code = AccessCode::generate();
        $typed = ' '.strtolower(AccessCode::format($code)).' ';

        $this->assertSame(AccessCode::hash($code, 'secret'), AccessCode::hash($typed, 'secret'));
    }

    public function test_ambiguous_characters_are_rejected(): void
    {
        $this->assertFalse(AccessCode::isWellFormed('OOOO-1111-IIII'));
    }

    public function test_no_collisions_on_a_large_sample(): void
    {
        $seen = [];
        for ($i = 0; $i < 20000; $i++) {
            $seen[AccessCode::generate()] = true;
        }

        $this->assertCount(20000, $seen);
    }
}
