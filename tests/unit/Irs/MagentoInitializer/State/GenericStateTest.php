<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\State;

use Irs\MagentoInitializer\Helper;

class GenericStateTest extends \PHPUnit_Framework_TestCase
{
    private $_target;
    private $_source;
    private $_structure = array(
        'var',
        'var/cache',
        'media',
        'media/upload',
        array('var/var.test', 'var.test'),
        array('var/cache/cache.test', 'cache.test'),
        array('media/media.test', 'media.test'),
        array('media/upload/upload.test', 'upload.test'),
        array('dump', 'dump'),
    );

    protected function _source($relativePath = '')
    {
        return "$this->_source/$relativePath";
    }

    protected function _target($relativePath = '')
    {
        return "$this->_target/$relativePath";
    }

    /**
     * @return Btf\Bootstrap\State\Magento
     */
    protected function _getState()
    {
        $state = new GenericState($this->_source('magento.state'));
        $state->setVar($this->_source('var'));
        $state->setMedia($this->_source('media'));
        $state->setDump($this->_source('dump'));

        return $state;
    }

    protected function setUp()
    {
        $this->_target = Helper::createTempDir();
        $this->_source = Helper::createTempDir();
        $this->_initializeSource();
    }

    protected function tearDown()
    {
        Helper::delete($this->_target);
        Helper::delete($this->_source);
    }

    protected function _initializeSource()
    {
        foreach ($this->_structure as $item) {
            if (is_array($item)) {
                list ($fileName, $content) = $item;
                file_put_contents($this->_source($fileName), $content);
            } else {
                mkdir($this->_source($item));
            }
        }
    }

    protected function _assertCorrectTarget()
    {
        foreach ($this->_structure as $item) {
            if (is_array($item)) {
                list ($fileName, $content) = $item;
                $this->assertFileExists($this->_target($fileName));
                $this->assertEquals($content, file_get_contents($this->_target($fileName)));
            } else {
                $this->assertFileExists($this->_target($item));
                $this->assertTrue(is_dir($this->_target($item)));
            }
        }
    }

    public function testShouldImplementStateInterface()
    {
        $this->assertInstanceOf('Irs\MagentoInitializer\State\StateInterface', new GenericState($this->_source('magento.state')));
    }

    public function testSetVarMediaDumpAndSaveItToFile()
    {
        $state = $this->_getState();
        $state->save();

        $this->assertFileExists($this->_source('magento.state'));
    }

    public function testShouldImplementFluentInterface()
    {
        $state = new GenericState($this->_target('magento.state'));
        $this->assertSame($state, $state->setVar($this->_source('var')));
        $this->assertSame($state, $state->setMedia($this->_source('media')));
        $this->assertSame($state, $state->setDump($this->_source('dump')));
        $this->assertSame($state, $state->save());
        $this->assertSame($state, $state->extractVar($this->_target()));
        $this->assertSame($state, $state->extractMedia($this->_target()));
        $this->assertSame($state, $state->extractDump($this->_target()));
    }

    public function testLoadFromCorrectFile()
    {
        $state = $this->_getState();
        $state->save();

        $state->extractVar($this->_target());
        $state->extractMedia($this->_target());
        $state->extractDump($this->_target());

        $this->_assertCorrectTarget();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testLoadFromIncorrectFileShouldThrowInvalidArgumentException()
    {
        $fileName = $this->_target('bad.state');
        file_put_contents($fileName, 'bueeeeeee');
        $state = new GenericState($fileName);
        $state->save();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testIfStateFileHasIncorrectCommentItShouldThrowInvalidArgumentExceptionOnLoadFromFileMethod()
    {
        $stateFileName = $this->_source('magento.state-');
        $stateFile = new \ZipArchive();
        $stateFile->open($stateFileName, GenericState::MODE_CREATE);
        $stateFile->setArchiveComment('Blah-blah');
        $stateFile->addEmptyDir('test');
        $stateFile->close();

        $state = new GenericState($stateFileName);
    }
}