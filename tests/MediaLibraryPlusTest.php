<?php

namespace IdeasOnPurpose\WP;

use PHPUnit\Framework\TestCase;
use IdeasOnPurpose\WP\Test;

Test\Stubs::init();

/**
 * @covers \IdeasOnPurpose\WP\MediaLibraryPlus
 */
final class MediaLibraryPlusTest extends TestCase
{
    public $MLP;
    public function setUp(): void
    {
        global $attached_file, $attachment_metadata, $original_image_path, $actions, $filters;

        $attached_file = null;
        $attachment_metadata = null;
        $original_image_path = null;

        $actions = [];
        $filters = [];

        $this->MLP = $this->getMockBuilder(\IdeasOnPurpose\WP\MediaLibraryPlus::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    public function testConstructor()
    {
        new MediaLibraryPlus();
        $this->assertCount(9, all_added_actions());
        $this->assertCount(3, all_added_filters());
    }

    public function testAdminStyles()
    {
        global $current_screen, $inline_styles;
        $inline_styles = [];

        $current_screen = (object) ['id' => 'upload'];

        $this->MLP->adminStyles();

        $this->assertEquals('wp-admin', $inline_styles[0]['handle']);
        $this->assertStringContainsString('.media-library-plus', $inline_styles[0]['data']);
    }

    // public function
    // public function testIncludeFiles()
    // {
    //     global $current_screen;
    //     $current_screen = (object) ['id' => 'upload'];

    //     ob_start();
    //     $this->MLP->includeFiles();
    //     ob_end_clean();

    //     $includes = get_included_files();
    //     $actual = [];
    //     foreach ($includes as $inc) {
    //         if (strpos($inc, 'phar://') === false) {
    //             $actual[] = basename($inc);
    //         }
    //     }
    //     d($actual);
    //     $this->assertContains('scripts.html', $actual);
    //     $this->assertContains('dynamic-styles.php', $actual);
    // }

    public function testGetCombinedFileSize()
    {
        global $attached_file, $attachment_metadata;
        $placeholder = __DIR__ . '/Fixtures/24-byte-placeholder.txt';
        $attached_file = realpath($placeholder);
        $attachment_metadata = ['sizes' => ['xl' => ['file' => '48-byte-placeholder.txt']]];

        $actual = $this->MLP->get_combined_filesize(1);
        $this->assertEquals($actual, 72);
    }

    public function testGetCombinedFileSizeWithOriginalImage()
    {
        global $attached_file, $attachment_metadata;
        $placeholder = __DIR__ . '/Fixtures/24-byte-placeholder.txt';
        $attached_file = realpath($placeholder);
        $attachment_metadata = false;

        $actual = $this->MLP->get_combined_filesize(1);
        $this->assertEquals($actual, 24);
    }

    public function testGetCombinedFileSizeNoMetadata()
    {
        global $attached_file, $attachment_metadata, $original_image_path;
        $placeholder = __DIR__ . '/Fixtures/24-byte-placeholder.txt';
        $placeholder3 = __DIR__ . '/Fixtures/96-byte-placeholder.txt';
        $attached_file = realpath($placeholder);
        $original_image_path = realpath($placeholder3);
        $attachment_metadata = false;

        $actual = $this->MLP->get_combined_filesize(1);
        $this->assertEquals($actual, 120);
    }

    public function testHideCols()
    {
        $screen = new \stdClass();
        $screen->id = 'upload';
        $actual = $this->MLP->hide_columns_default([], $screen);
        $this->assertArrayHasKey('width', $actual);
        $this->assertArrayHasKey('height', $actual);
        $this->assertArrayNotHasKey('hesight', $actual);
    }
}
