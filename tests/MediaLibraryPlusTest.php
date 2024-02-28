<?php

namespace IdeasOnPurpose\WP;

use PHPUnit\Framework\TestCase;
use IdeasOnPurpose\WP\Test;

Test\Stubs::init();

/**
 * @covers \IdeasOnPurpose\WP\MediaLibraryPlus\MediaLibraryPlus
 */
final class MediaLibraryPlusTest extends TestCase
{
    public $MLP;
    public function setUp(): void
    {
    }

    public function beforeEach()
    {
        $this->MLP = $this->getMockBuilder(
            \IdeasOnPurpose\WP\MediaLibraryPlus\MediaLibraryPlus::class
        )
    // $this->MLP=   $this->getMockBuilder('\IdeasOnPurpose\WP\MediaLibraryPlus')
            ->disableOriginalConstructor()
            ->onlyMethods(['includeFiles'])
            ->getMock();
    }

    public function testPlaceholder()
    {
        d($this->MLP);
        $this->assertTrue(true);
    }
    public function testGetCombinedFileSize()
    {
        $this->assertTrue($this->MLP->get_combined_filesize(''));
    }

    public function testHideCols()
    {
        $screen = new stdClass();
        $screen->id = 'upload';
        $actual = $this->MLP->hide_columns_default([], $screen);
        $this->assertArrayHasKey('width', $actual);
    }
}
