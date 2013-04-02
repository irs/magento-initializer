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
            self::DB_NAME
        );
        $this->assertInstanceOf('Irs\MagentoInitializer\Installer\InstallerInterface', $installer);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorShouldThrowInvalidArgumentExceptionOnIncorrectMagentoDir()
    {
        $installer = new MagentoWithMockedRun($this->_target, $this->_magento, 'asd', 'asdas', 'asdas', 'asqwed');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorShouldThrowInvalidArgumentExceptionOnIncorrectMagentoPath()
    {
        $installer = new MagentoWithMockedRun($this->_target, $this->_temp . 'aaaa', 'asd', 'asdas', 'asdas', 'asqwed');
    }


    public function testInstallMethodShouldCreateAppropriateFileStructure()
    {
        // prepare
        Helper::emulateMagentoFileStructure($this->_magento);
        $installer = new MagentoWithMockedRun(
            $this->_target,
            $this->_magento,
            self::DB_HOSTNAME,
            self::DB_USERNAME,
            self::DB_PASSWORD,
            self::DB_NAME
        );

        // act
        $installer->install();

        // assert
        Helper::assertFileStructureIsCorrect($this->_target);
        $this->_assertLocalXmlIsCorrect();
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
            self::DB_NAME
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
            self::DB_NAME
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
            self::DB_NAME
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

    protected function _assertLocalXmlIsCorrect()
    {
        $showErrors = libxml_use_internal_errors(false);
        $local = simplexml_load_file($this->_target . '/etc/local.xml');
        libxml_use_internal_errors($showErrors);
        $this->assertNotEquals(false, $local, 'local.xml was copied from Magento instance.');

        $this->assertTrue(isset($local->global->install->date));
        $this->assertTrue(isset($local->global->crypt->key));
        $this->assertTrue(isset($local->global->resources->db->table_prefix));
        $this->assertTrue(isset($local->global->resources->default_setup->connection->host));
        $this->assertTrue(isset($local->global->resources->default_setup->connection->username));
        $this->assertTrue(isset($local->global->resources->default_setup->connection->password));
        $this->assertTrue(isset($local->global->resources->default_setup->connection->dbname));
        $this->assertTrue(isset($local->global->resources->default_setup->connection->initStatements));
        $this->assertTrue(isset($local->global->resources->default_setup->connection->model));
        $this->assertTrue(isset($local->global->resources->default_setup->connection->type));
        $this->assertTrue(isset($local->global->resources->default_setup->connection->pdoType));
        $this->assertTrue(isset($local->global->resources->default_setup->connection->active));
        $this->assertTrue(isset($local->global->session_save));
        $this->assertTrue(isset($local->admin->routers->adminhtml->args->frontName));

        $this->assertEmpty((string)$local->global->resources->db->table_prefix);
        $this->assertEmpty((string)$local->global->resources->default_setup->connection->pdoType);
        $this->assertEquals('{{date}}',        (string)$local->global->install->date);
        $this->assertEquals('{{key}}',         (string)$local->global->crypt->key);
        $this->assertEquals(self::DB_HOSTNAME, (string)$local->global->resources->default_setup->connection->host);
        $this->assertEquals(self::DB_USERNAME, (string)$local->global->resources->default_setup->connection->username);
        $this->assertEquals(self::DB_PASSWORD, (string)$local->global->resources->default_setup->connection->password);
        $this->assertEquals(self::DB_NAME,     (string)$local->global->resources->default_setup->connection->dbname);
        $this->assertEquals('SET NAMES utf8',  (string)$local->global->resources->default_setup->connection->initStatements);
        $this->assertEquals('mysql4',          (string)$local->global->resources->default_setup->connection->model);
        $this->assertEquals('pdo_mysql',       (string)$local->global->resources->default_setup->connection->type);
        $this->assertEquals(1,                 (string)$local->global->resources->default_setup->connection->active);
        $this->assertEquals('files',           (string)$local->global->session_save);
        $this->assertEquals('admin',           (string)$local->admin->routers->adminhtml->args->frontName);
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
