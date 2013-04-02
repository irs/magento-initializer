<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer;

require_once __DIR__ . '/Helper.php';

class FsoTest extends \PHPUnit_Framework_TestCase
{
    private $_temp;

    protected function _isOsSupportLinks()
    {
        return (php_uname('s') == 'Windows NT')
            ? version_compare(php_uname('r'), '6.0', '>=')
            : true;
    }

    protected function setUp()
    {
        $this->_temp = Helper::createTempDir();
        mkdir($this->_temp . '/source');
        mkdir($this->_temp . '/source/directory');
        file_put_contents($this->_temp . '/source/directory/file.txt', 'My File');
        mkdir($this->_temp . '/target');
    }

    protected function tearDown()
    {
        Helper::delete($this->_temp);
    }

    public function testCopyOfFileShouldCreateCopyOfFile()
    {
        // prepare
        $from =     $this->_temp . '/source/directory/file.txt';
        $to =       $this->_temp . '/target/file.txt';

        // act
        Fso::copy($from, $to);

        // assert
        unlink($from);
        $this->assertFileExists($to);
    }

    public function testCopyWithDeepFalseShouldCreateSymbolicLinkIfSystemSupports()
    {
        // prepare
        $from =     $this->_temp . '/source/directory/file.txt';
        $to =       $this->_temp . '/target/file.txt';

        // act
        Fso::copy($from, $to, false);

        // assert
        $this->assertFileExists($to);
        if ($this->_isOsSupportLinks()) {
            $this->assertTrue(is_link($to));
        }
    }

    public function testCopyOfDirectoryShouldCreateCopyOfDirectory()
    {
        // prepare
        $from =     $this->_temp . '/source/directory';
        $to =       $this->_temp . '/target/directory';

        // act
        Fso::copy($from, $to);

        // assert
        Helper::delete($from);
        $this->assertFileExists($to);
        $this->assertTrue(is_dir($to));
        $this->assertFileExists($this->_temp . '/target/directory/file.txt');
    }

    public function testCopyOfDirectoryWithDeepFalseShouldCreateSymbolicLinkIfSystemSupports()
    {
        // prepare
        $from =     $this->_temp . '/source/directory';
        $to =       $this->_temp . '/target/directory';

        // act
        Fso::copy($from, $to, false);

        // assert
        $this->assertFileExists($to);
        $this->assertTrue(is_dir($to));
        $this->assertFileExists($this->_temp . '/target/directory/file.txt');

        if ($this->_isOsSupportLinks()) {
            $this->assertTrue(is_link($to));
        }
    }

    public function testMoveMethodShouldRenameFile()
    {
        // prepare
        $from     = $this->_temp . '/source/directory/file.txt';
        $to       = $this->_temp . '/target/file.txt';

        // act
        Fso::move($from, $to);

        // assert
        $this->assertFileExists($to);
        $this->assertFileNotExists($from);
    }

    public function testMoveMethodShouldRenameDirectory()
    {
        // prepare
        $from = $this->_temp . '/source/directory';
        $to   = $this->_temp . '/target/directory';

        // act
        Fso::move($from, $to);

        // assert
        $this->assertFileNotExists($from);
        $this->assertFileExists($to);
        $this->assertTrue(is_dir($to));
        $this->assertFileExists($this->_temp . '/target/directory/file.txt');
    }

    public function testDeleteMethodShouldDeleteFile()
    {
        // prepare
        $file = $this->_temp . '/source/directory/file.txt';

        // act
        Fso::delete($file);

        // assert
        $this->assertFileNotExists($file);
    }

    public function testDeleteMethodShouldDeleteDirectory()
    {
        // prepare
        $directory = $this->_temp . '/source/directory';

        // act
        Fso::delete($directory);

        // assert
        $this->assertFileNotExists($directory);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testDeleteOnNotExistentFilesShouldThrowInvalidArgumentException()
    {
    	Fso::delete(uniqid());
    }
}