<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\Installer;

use Irs\MagentoInitializer\Helper;

class GenericInstallerTest extends \PHPUnit_Framework_TestCase
{
    const DB_HOSTNAME = 'aaa';
    const DB_USERNAME = 'user';
    const DB_PASSWORD = 'pwd';
    const DB_NAME = 'name';

    private $_temp;
    private $_magento;
    private $_target;

    public function providerVersions()
    {
        return array(
            array('1.9'),
            array('1.11'),
        );
    }

    protected function setUp()
    {
        $this->_temp = Helper::createTempDir();
        $this->_magento = $this->_temp . DIRECTORY_SEPARATOR . 'magento';
        $this->_target = $this->_temp . DIRECTORY_SEPARATOR . 'target';
        mkdir($this->_target);
        mkdir($this->_magento);
    }

    protected function tearDown()
    {
        Helper::delete($this->_temp);
    }

    public function testShouldImplementInstallerInterface()
    {
        Helper::emulateMagentoFileStructure($this->_magento);
        $installer = new MagentoWithMockedRun(
            $this->_target,
            $this->_magento,
            self::DB_HOSTNAME,
            self::DB_USERNAME,
            self::DB_PASSWORD,
            self::DB_NAME,
            'url'
        );
        $this->assertInstanceOf('Irs\MagentoInitializer\Installer\InstallerInterface', $installer);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorShouldThrowInvalidArgumentExceptionOnIncorrectMagentoDir()
    {
        $installer = new MagentoWithMockedRun($this->_target, $this->_magento, 'asd', 'asdas', 'asdas', 'asqwed', 'url');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorShouldThrowInvalidArgumentExceptionOnIncorrectMagentoPath()
    {
        $installer = new MagentoWithMockedRun($this->_target, $this->_temp . 'aaaa', 'asd', 'asdas', 'asdas', 'asqwed', 'url');
    }

    /**
     * @dataProvider providerVersions
     */
    public function testInstallMethodShouldCreateAppropriateFileStructure($version)
    {
        // prepare
        Helper::emulateMagentoFileStructure($this->_magento, $version);
        $installer = new MagentoWithMockedRun(
            $this->_target,
            $this->_magento,
            self::DB_HOSTNAME,
            self::DB_USERNAME,
            self::DB_PASSWORD,
            self::DB_NAME,
            'url'
        );

        // act
        $installer->install();

        // assert
        Helper::assertFileStructureIsCorrect($this->_target);
        Helper::assertRunParametersIsCorrect($this->_target, $this->_magento);
        Helper::assertCorrectIndexPhpFile($this->_target . '/index.php', $this->_magento);
    }

    public function testOnNotInstalledMagentoMethodIsInstalledShouldReturnFalse()
    {
        // prepare
        Helper::emulateMagentoFileStructure($this->_magento);
        $installer = new MagentoWithMockedRun(
            $this->_target,
            $this->_magento,
            self::DB_HOSTNAME,
            self::DB_USERNAME,
            self::DB_PASSWORD,
            self::DB_NAME,
            'url'
        );

        // act & assert
        $this->assertFalse($installer->isInstalled());
    }

    public function testOnInstalledMagentoMethodIsInstalledShouldReturnTrue()
    {
        // prepare
        Helper::emulateMagentoFileStructure($this->_magento);
        $installer = new MagentoWithMockedRun(
            $this->_target,
            $this->_magento,
            self::DB_HOSTNAME,
            self::DB_USERNAME,
            self::DB_PASSWORD,
            self::DB_NAME,
            'url'
        );

        // act
        $installer->install();

        // assert
        $this->assertTrue($installer->isInstalled());
    }

    public function testShouldCleanupTargetIfErrorOccuredDuringInstallation()
    {
        // prepare
        Helper::emulateMagentoFileStructure($this->_magento);
        $installer = new MagentoWithMockedRun(
            $this->_target,
            $this->_magento,
            self::DB_HOSTNAME,
            self::DB_USERNAME,
            self::DB_PASSWORD,
            self::DB_NAME,
            'url'
        );
        $installer->setThrowExceptionFromInstall(true);

        // act
        try {
            $installer->install();
        } catch (MyException $e) {}

        // assert
        $this->assertFalse($installer->isInstalled());
        foreach (new \DirectoryIterator($this->_target) as $item) {
            if (!$item->isDot()) {
                $this->fail('Target is not empty.');
            }
        }
    }
}

class MagentoWithMockedRun extends GenericInstaller
{
    private $_throwException = false;

    public function setThrowExceptionFromInstall($throw)
    {
        $this->_throwException = $throw;
    }

    protected function installMagento($code, $type, array $options)
    {
        if ($this->_throwException) {
            throw new MyException('O_o');
        }
    }
}

class MyException extends \Exception
{}
