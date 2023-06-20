<?php

namespace IdeasOnPurpose\WP;

use PHPUnit\Framework\TestCase;
use IdeasOnPurpose\WP\Test;

Test\Stubs::init();

/**
 * @covers \IdeasOnPurpose\WP\
 */
final class MediaLibraryPlusTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function testPlaceholder()
    {
        $this->assertTrue(true);
    }
}
