<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\Initializer;

use Irs\MagentoInitializer\Fso;
use Irs\MagentoInitializer\Initializer\Db\Mysql as MysqlDbInitializer;
use Irs\MagentoInitializer\State\GenericState as MagentoState;
use Irs\MagentoInitializer\Installer\GenericInstaller as MagentoInstaller;

class GenericInitializer implements InitializerInterface
{
    private $_magentoRoot;
    private $_paramsPathname;
    private $_store;
    private $_scope;
    private $_connectionTypeToDb = array();

    public function __construct($magentoRootDir, $storeCode = '', $scopeCode = 'store')
    {
        $magentoRootDir = rtrim($magentoRootDir, '\/');
        $this->_paramsPathname = $magentoRootDir . DIRECTORY_SEPARATOR . MagentoInstaller::PARAMS_FILENAME;
        $this->_magentoRoot = $magentoRootDir;
        $this->_scope = $scopeCode;
        $this->_store = $storeCode;
    }

    public function initialize()
    {
        if (!file_exists($this->_paramsPathname)) {
            throw new \InvalidArgumentException("Invalid Magento root '$this->_magentoRoot'.");
        }

        $params = $this->_getParams();
        $params['code'] = $this->_store;
        $params['type'] = $this->_scope;
        $this->_saveParams($params);
    }

    /**
     * @return array
     */
    protected function _getParams()
    {
        if (!file_exists($this->_paramsPathname)) {
            throw new \InvalidArgumentException('System under test is not initialized; run behat first.');
        }

        try {
            $params = include $this->_paramsPathname;
        } catch (\RuntimeException $e) {
            throw new \InvalidArgumentException(
                'Inconsistent state of system under tests; cleaunp sut folder, database and run behat again.', 0, $e
            );
        }

        if (!is_array($params) ||
            !isset($params['code']) ||
            !isset($params['type']) ||
            !isset($params['options']) ||
            !isset($params['options']['etc_dir']) ||
            !isset($params['options']['var_dir']) ||
            !isset($params['options']['media_dir'])) {

            throw new \InvalidArgumentException("Inconsistent params file ({$this->_paramsPathname}).");
        }

        return $params;
    }

    protected function _saveParams(array $params)
    {
        file_put_contents($this->_paramsPathname, '<?php return ' . var_export($params, true) . ';');
    }

    public function saveState($fileName)
    {
        $state = new MagentoState($fileName);

        $dumpFileName = tempnam(null, 'btd');
        $this->_getDb()->createDump($dumpFileName);
        $state->setDump($dumpFileName);

        $params = $this->_getParams();
        if (is_dir($params['options']['var_dir'])) {
            $state->setVar($params['options']['var_dir']);
        }
        if (is_dir($params['options']['media_dir'])) {
            $state->setMedia($params['options']['media_dir']);
        }

        $state->save();
        unlink($dumpFileName);

        return $state;
    }

    public function restoreState($stateFileName)
    {
    	$state = new MagentoState($stateFileName);
        $tempDir = $this->_createTempDir();

        // restore dump
        $dumpFile = $tempDir . DIRECTORY_SEPARATOR . 'dump';
        $state->extractDump($tempDir);
        $this->_getDb()->restoreDump($tempDir . DIRECTORY_SEPARATOR . 'dump');

        // restore var and media
        $params = $this->_getParams();
        file_exists($params['options']['var_dir']) && $this->_delete($params['options']['var_dir']);
        file_exists($params['options']['media_dir']) && $this->_delete($params['options']['media_dir']);
        $state->extractVar($tempDir);
        $state->extractMedia($tempDir);
        Fso::move($tempDir . DIRECTORY_SEPARATOR . 'var', $params['options']['var_dir']);
        Fso::move($tempDir . DIRECTORY_SEPARATOR . 'media', $params['options']['media_dir']);

        $this->_delete($tempDir);
    }

    protected function _createTempDir()
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->_getRandomName();
        mkdir($path);

        return $path;
    }

    protected function _delete($path)
    {
        Fso::delete($path);
    }

    private function _getRandomName()
    {
        $chars = '0123456789ABCDEF';
        $name = 'btd';
        for($i = 0; $i < 8; $i++) {
            $name .= substr($chars, rand(0, strlen($chars) -1 ), 1);
        }

        return $name;
    }

    /**
     *
     * @param string $type
     * @return \Btf\Bootstrap\Initializer\Db\DbInterface
     * @throws InvalidArgumentException
     */
    protected function _getDb()
    {
        $type = $this->_getConfig()->global->resources->default_setup->connection->type;

        return $this->_getDbByConnectionType($type);
    }

    /**
     *
     * @param string $type
     * @return \Btf\Bootstrap\Initializer\Db\DbInterface
     * @throws InvalidArgumentException
     */
    protected function _getDbByConnectionType($type)
    {
        $type = (string)$type;
        if (!isset($this->_connectionTypeToDb[$type])) {
            switch ($type) {
                case 'pdo_mysql':
                    $db = $this->_getMysqlDbInitializer();
                    break;

                default:
                    throw new \InvalidArgumentException("DB '$type' is not supported.");
            }
           $this->_connectionTypeToDb[$type] = $db;
        }

        return $this->_connectionTypeToDb[$type];
    }

    /**
     * @return \Btf\Bootstrap\Initializer\Db\Mysql
     */
    protected function _getMysqlDbInitializer()
    {
        $config = $this->_getConfig()->global->resources->default_setup->connection;

        return new MysqlDbInitializer($config->host, $config->username, $config->password, $config->dbname);
    }

    /**
     * @return \SimpleXMLElement
     * @throws \RuntimeException
     */
    protected function _getConfig()
    {
        $params = $this->_getParams();
        $configPath = $params['options']['etc_dir'] . DIRECTORY_SEPARATOR . 'local.xml';
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Magento instance is inconsistent; unable to read config ({$params['options']['etc_dir']}).");
        }

        $config = simplexml_load_file($configPath);
        if (!$config) {
            throw new \RuntimeException("Magento instance is inconsistent; unable to load config ({$params['options']['etc_dir']}).");
        }

        return $config;
    }
}
