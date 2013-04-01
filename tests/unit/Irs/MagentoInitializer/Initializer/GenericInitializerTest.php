<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\Initializer;

use Irs\MagentoInitializer\State\GenericState as MagentoState;
use Irs\MagentoInitializer\Installer\GenericInstaller as MagentoInstaller;
use Irs\MagentoInitializer\Initializer\GenericInitializer as MagentoInitializer;
use Irs\MagentoInitializer\Initializer\Db\DbInterface;
use Irs\MagentoInitializer\Helper;

class GenericInitializerTest extends \PHPUnit_Framework_TestCase
{
    private $_magento;
    private $_target;
    private $_temp;

    protected function setUp()
    {
        $this->_magento = Helper::createTempDir();
        $this->_target = Helper::createTempDir();
        $this->_temp = Helper::createTempDir();
        Helper::emulateMagentoFileStructure($this->_magento);
        $this->_installMagento();
    }

    protected function tearDown()
    {
        Helper::delete($this->_magento);
        Helper::delete($this->_target);
        Helper::delete($this->_temp);
    }

    protected function _target($relativePath = '')
    {
        return "$this->_target/$relativePath";
    }

    protected function _getInitializerWithMockedDb(DbInterface $mockedDb, $magentoRoot, $store = '', $scope = 'store')
    {
        $initializer = new InitializerWithMockedDb($magentoRoot, $store, $scope);
        $initializer->setDb($mockedDb);

        return $initializer;
    }

    protected function _installMagento()
    {
        $installer = new MagentoInstallerWithMockedRun($this->_target, $this->_magento, 'host', 'user', 'pwd', 'name');
        $installer->install();
        var_dump($this->_target, `ls $this->_target`);
    }

    public function testShouldImplementInitializerInterface()
    {

        $initializer = new MagentoInitializer($this->_target);
        $this->assertInstanceOf('Irs\MagentoInitializer\Initializer\InitializerInterface', $initializer);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testShouldThrowInvalidArgumentExceptionOnIncorrectMagentoLocation()
    {
        $initializer = new MagentoInitializer($this->_magento);
        $initializer->initialize();
    }

    /**
     * @dataProvider providerStoreScope
     */
    public function testShouldInitializeMagentoByPathForParticularStoreAndScope($store, $scope)
    {
        // prepare
        $initializer = new MagentoInitializer($this->_target, $store, $scope);

        // act
        $initializer->initialize();

        // assert
        Helper::assertRunParametersIsCorrect($this->_target, $this->_magento, $store, $scope);
    }

    public function providerStoreScope()
    {
        return array(
            array('assadasd', 'asdqw'),
            array('as', 'asqwe'),
            array('q[wjd', 'asqwpo'),
        );
    }

    public function testContructorOfInitializerShouldSupportDefaultStoreAndScope()
    {
        // prepare
        $initializer = new MagentoInitializer($this->_target);

        // act
        $initializer->initialize();

        // assert
        Helper::assertRunParametersIsCorrect($this->_target, $this->_magento);
    }

    public function testShouldSaveStateOfMagentoAndReturnIt()
    {
        // preapare

        $db = $this->getMock(
            'Irs\MagentoInitializer\Initializer\Db\Mysql',
            array('createDump'),
            array('host', 'user', 'pwd', 'db')
        );

        $dbDump = 'dump';
        $db->expects($this->once())
            ->method('createDump')
            ->will($this->returnCallback(
                function ($fileName) use ($dbDump) {
                    file_put_contents($fileName, $dbDump);
                }
            ));

        $initializer = $this->_getInitializerWithMockedDb($db, $this->_target);

        $modSet = array(
            'var/cache',
            array('var/cache/my.cache', 'my_cache'),
            array('media/upload/image.jpg', 'my_image'),
            array('media/foto.jpg', 'my_foto'),
        );

        $this->_modifyTarget($modSet);

        // act
        $stateFileName = $this->_target('magento.state');
        $state = $initializer->saveState($stateFileName);

        // assert
        $this->assertInstanceOf('Irs\MagentoInitializer\State\GenericState', $state);
        $this->assertFileExists($stateFileName);
        $this->_assertStateContent($state, $modSet, $dbDump);
    }

    public function testShouldRestoreStateOfMagentoAndReturnIt()
    {
        // preapare
        $dbDump = 'dump';
        $stateFileName = $this->_target('magento.state');

        $db = $this->getMock(
            'Irs\MagentoInitializer\Initializer\Db\Mysql',
            array('createDump', 'restoreDump'),
            array('host', 'user', 'pwd', 'db')
        );

        $db->expects($this->once())
            ->method('createDump')
            ->will($this->returnCallback(
                function ($fileName) use ($dbDump) {
                    file_put_contents($fileName, $dbDump);
                }
            ));
        $db->expects($this->once())
            ->method('restoreDump')
            ->will($this->returnCallback(
                function ($fileName) use ($dbDump) {
                    $this->assertFileExists($fileName);
                    $this->assertEquals($dbDump, file_get_contents($fileName));
                }
            ));

        $initializer = $this->_getInitializerWithMockedDb($db, $this->_target);

        $modSet = array(
            'var/cache',
            array('var/cache/my.cache', 'my_cache'),
            array('media/upload/image.jpg', 'my_image'),
            array('media/foto.jpg', 'my_foto'),
        );
        $anotherModSet = array(
            'var/cache',
            array('var/cache/my.cache', 'another_cache'),
            array('var/cache/their.cache', 'another_cache'),
            array('media/upload/image.jpg', 'another_image'),
            array('media/foto.jpg', 'another_foto'),
            array('media/another_foto.jpg', 'another_foto'),
        );

        $this->_modifyTarget($modSet);

        // act
        $initializer->saveState($stateFileName);
        $this->_modifyTarget($anotherModSet);
        $initializer->restoreState($stateFileName);

        // assert
        $this->_assertStateContent(new MagentoState($stateFileName), $modSet, $dbDump);
    }

    protected function _assertStateContent(MagentoState $state, array $modSet, $dbDump)
    {
        $state->extractDump($this->_temp)
            ->extractMedia($this->_temp)
            ->extractVar($this->_temp);

        foreach ($modSet as $file) {
            if (is_array($file)) {
                list ($name, $content) = $file;
                $name = "$this->_temp/$name";
                $this->assertFileExists($name);
                $this->assertEquals($content, file_get_contents($name));
            } else {
                $this->assertTrue(is_dir("$this->_temp/$file"));
            }
        }

        $this->assertEquals($dbDump, file_get_contents("$this->_temp/dump"));
    }

    protected function _modifyTarget(array $modSet)
    {
        foreach ($modSet as $file) {
            if (is_array($file)) {
                list ($name, $content) = $file;
                file_put_contents($this->_target($name), $content);
            } else {
                if (!is_dir($this->_target($file))) {
                    mkdir($this->_target($file));
                }
            }
        }
    }

    protected function _assertModificationsApplied(array $modSet)
    {
        foreach ($modSet as $file) {
            if (is_array($file)) {
                list ($name, $content) = $file;
                $name = $this->_target($name);
                $this->assertFileExists($name);
                $this->assertEquals($content, file_get_contents($name));
            } else {
                $this->assertTrue(is_dir($this->_target($file)));
            }
        }
    }
}

class InitializerWithMockedDb extends MagentoInitializer
{
    private $_db;

    public function setDb(DbInterface $db)
    {
        $this->_db = $db;
    }

    protected function _getDbByConnectionType($type)
    {
        return $this->_db;
    }
}

class MagentoInstallerWithMockedRun extends MagentoInstaller
{
    protected function _installMagento($code, $type, array $options)
    {}
}