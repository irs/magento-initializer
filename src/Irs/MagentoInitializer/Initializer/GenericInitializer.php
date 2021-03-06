<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\Initializer;

use Irs\Fso\Fso;
use Irs\MagentoInitializer\Initializer\Db\Mysql as MysqlDbInitializer;
use Irs\MagentoInitializer\State\GenericState as MagentoState;
use Irs\MagentoInitializer\Installer\GenericInstaller as MagentoInstaller;

class GenericInitializer implements InitializerInterface
{
    private $magentoRoot;
    private $paramsPathname;
    private $store;
    private $scope;
    private $connectionTypeToDb = array();

    /**
     * Constructs initializer
     *
     * @param string $magentoRootDir  Path to Magento installed by GenericInstaller
     * @param string $storeCode       Magento store code
     * @param string $scopeCode       Magento scope code
     */
    public function __construct($magentoRootDir, $storeCode = '', $scopeCode = 'store')
    {
        $magentoRootDir = rtrim($magentoRootDir, '\/');
        $this->paramsPathname = $magentoRootDir . DIRECTORY_SEPARATOR . MagentoInstaller::PARAMS_FILENAME;
        $this->magentoRoot = $magentoRootDir;
        $this->scope = $scopeCode;
        $this->store = $storeCode;
    }

    /**
     * Sets store and scope code into index.php generated by GenericInstaller
     *
     * @see \Irs\MagentoInitializer\Initializer\InitializerInterface::initialize()
     */
    public function initialize()
    {
        if (!file_exists($this->paramsPathname)) {
            throw new \InvalidArgumentException("Invalid Magento root '$this->magentoRoot'.");
        }

        $params = $this->getParams();
        $params['code'] = $this->store;
        $params['type'] = $this->scope;
        $this->saveParams($params);
    }

    /**
     * @return array
     */
    protected function getParams()
    {
        if (!file_exists($this->paramsPathname)) {
            throw new \InvalidArgumentException('System under test is not initialized; run behat first.');
        }

        try {
            $params = include $this->paramsPathname;
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

            throw new \InvalidArgumentException("Inconsistent params file ({$this->paramsPathname}).");
        }

        return $params;
    }

    protected function saveParams(array $params)
    {
        file_put_contents($this->paramsPathname, '<?php return ' . var_export($params, true) . ';');
    }

    public function saveState($fileName)
    {
        $state = new MagentoState($fileName);

        $dumpFileName = tempnam(null, 'btd');
        $this->getDb()->createDump($dumpFileName);
        $state->setDump($dumpFileName);

        $params = $this->getParams();
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
        $tempDir = $this->createTempDir();

        // restore dump
        $dumpFile = $tempDir . DIRECTORY_SEPARATOR . 'dump';
        $state->extractDump($tempDir);
        $this->getDb()->restoreDump($tempDir . DIRECTORY_SEPARATOR . 'dump');

        // restore var and media
        $params = $this->getParams();
        file_exists($params['options']['var_dir']) && $this->delete($params['options']['var_dir']);
        file_exists($params['options']['media_dir']) && $this->delete($params['options']['media_dir']);
        $state->extractVar($tempDir);
        $state->extractMedia($tempDir);
        Fso::move($tempDir . DIRECTORY_SEPARATOR . 'var', $params['options']['var_dir']);
        Fso::move($tempDir . DIRECTORY_SEPARATOR . 'media', $params['options']['media_dir']);

        $this->delete($tempDir);
    }

    protected function createTempDir()
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->getRandomName();
        mkdir($path);

        return $path;
    }

    protected function delete($path)
    {
        Fso::delete($path);
    }

    private function getRandomName()
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
    protected function getDb()
    {
        $type = $this->getConfig()->global->resources->default_setup->connection->type;

        return $this->getDbByConnectionType($type);
    }

    /**
     *
     * @param string $type
     * @return \Btf\Bootstrap\Initializer\Db\DbInterface
     * @throws InvalidArgumentException
     */
    protected function getDbByConnectionType($type)
    {
        $type = (string)$type;
        if (!isset($this->connectionTypeToDb[$type])) {
            switch ($type) {
                case 'pdo_mysql':
                    $db = $this->getMysqlDbInitializer();
                    break;

                default:
                    throw new \InvalidArgumentException("DB '$type' is not supported.");
            }
            $this->connectionTypeToDb[$type] = $db;
        }

        return $this->connectionTypeToDb[$type];
    }

    /**
     * @return \Btf\Bootstrap\Initializer\Db\Mysql
     */
    protected function getMysqlDbInitializer()
    {
        $config = $this->getConfig()->global->resources->default_setup->connection;

        return new MysqlDbInitializer($config->host, $config->username, $config->password, $config->dbname);
    }

    /**
     * @return \SimpleXMLElement
     * @throws \RuntimeException
     */
    protected function getConfig()
    {
        $params = $this->getParams();
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
