<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\Installer;

use Irs\MagentoInitializer\Fso;

class GenericInstaller implements InstallerInterface
{
    const PARAMS_FILENAME = 'params.php';
    const ADMIN_USER_NAME = 'admin';
    const ADMIN_USER_PASSWORD = '123123qa';

    private $_magentoDir;
    private $_targetDir;
    private $_configData;
    private $_copier;

    public function __construct($targetDir, $magentoDir, $dbHostName, $dbUserName, $dbPassword, $dbName)
    {
        $this->_magentoDir = realpath($magentoDir);
        if (!$this->_magentoDir) {
            throw new \InvalidArgumentException("'$magentoDir' is incorrect path.");
        }
        if (!is_dir($this->_magentoDir)) {
            throw new \InvalidArgumentException("'$magentoDir' is not a directory.");
        }
        if (!file_exists($this->_magentoDir . '/app/Mage.php')) {
            throw new \InvalidArgumentException("'$magentoDir' is invalid Magento's directory.");
        }
        if (!is_readable($this->_magentoDir)) {
            throw new \InvalidArgumentException("'$targetDir' is not readable.");
        }

        $this->_targetDir = realpath($targetDir);
        if (!$this->_targetDir) {
            throw new \InvalidArgumentException("'$targetDir' is incorrect path.");
        }
        if (!is_dir($this->_targetDir)) {
            throw new \InvalidArgumentException("'$targetDir' is not a directory.");
        }
        if (!is_writeable($this->_targetDir)) {
            throw new \InvalidArgumentException("'$targetDir' is not writeable.");
        }

        $this->_configData = array(
            'db_host'            => $dbHostName,
            'db_user'            => $dbUserName,
            'db_pass'            => $dbPassword,
            'db_name'            => $dbName,
            'db_prefix'          => '',
            'db_pdo_type'        => '',
            'db_init_statemants' => 'SET NAMES utf8',
            'db_model'           => 'mysql4',
            'db_type'            => 'pdo_mysql',
            'session_save'       => 'files',
            'admin_frontname'    => 'admin',
        );
    }

    public function install()
    {
        if ($this->isInstalled()) {
             throw new \RuntimeException("Magento is already installed at '$this->_targetDir'.");
        }
        try {
            $this->_createDirectoryStructure();
            $this->_createLocalXml();
            $this->_createIndexPhp();
            $params = $this->_createRunParams();
            $this->_installMagento($params['code'], $params['type'], $params['options']);
        } catch (\Exception $e) {
            $this->_cleanupTarget();
            throw $e;
        }
    }

    protected function _cleanupTarget()
    {
    	foreach (new \DirectoryIterator($this->_targetDir) as $item) {
    		if (!$item->isDot()) {
    			Fso::delete($item->getPathname());
    		}
    	}
    }

    public function isInstalled()
    {
        return file_exists($this->_targetDir . DIRECTORY_SEPARATOR . self::PARAMS_FILENAME);
    }

    protected function _installMagento($code, $type, array $options)
    {
        include $this->_magentoDir . '/app/Mage.php';

        \Mage::app($code, $type, $options);
        \Mage_Core_Model_Resource_Setup::applyAllUpdates();
        \Mage_Core_Model_Resource_Setup::applyAllDataUpdates();

        // Enable configuration cache by default in order to improve tests performance
        \Mage::app()->getCacheInstance()->saveOptions(array('config' => 1));
        $this->_updateLocalXmlWithCurrentDate();
        $this->_createAdminUser(self::ADMIN_USER_NAME, self::ADMIN_USER_PASSWORD);
    }

    protected function _createAdminUser($userName, $password)
    {
        $user = \Mage::getModel('admin/user')
            ->setFirstname('John')
            ->setLastname('Doe')
            ->setEmail('fake@magento.com')
            ->setUsername($userName)
            ->setPassword($password)
            ->setIsActive(true)
            ->save();
        $role = \Mage::getModel('admin/role')
            ->setParentId(1)
            ->setTreeLevel(2)
            ->setSortOrder(0)
            ->setRoleType('U')
            ->setUserId($user->getId())
            ->setRoleName('Role')
            ->save();
    }

    protected function _createIndexPhp()
    {
        $index = file_get_contents($this->_magentoDir . DIRECTORY_SEPARATOR . 'index.php.sample');

        $index = str_replace(
        	"\$compilerConfig = 'includes/config.php';",
            "\$compilerConfig = '{$this->_magentoDir}/includes/config.php';",
            $index
        );
        $index = str_replace(
            "\$mageFilename = 'app/Mage.php';",
            "\$mageFilename = '{$this->_magentoDir}/app/Mage.php';",
            $index
        );

        $nativeCode = <<<NATIVE
\$mageRunCode = isset(\$_SERVER['MAGE_RUN_CODE']) ? \$_SERVER['MAGE_RUN_CODE'] : '';
\$mageRunType = isset(\$_SERVER['MAGE_RUN_TYPE']) ? \$_SERVER['MAGE_RUN_TYPE'] : 'store';

Mage::run(\$mageRunCode, \$mageRunType);
NATIVE;
        $targetCode = <<<TARGET
\$paramsFilename = 'params.php';
if (file_exists(\$paramsFilename)) {
    \$params = include \$paramsFilename;
    Mage::run(\$params['code'], \$params['type'], \$params['options']);
} else {
    die("Cannot run test instance without run params.");
}
TARGET;
        $index = str_replace($nativeCode, $targetCode, $index);
        file_put_contents($this->_targetDir . DIRECTORY_SEPARATOR . 'index.php', $index);
    }

    protected function _createRunParams()
    {
        $config = array(
            'code'    => '',
            'type'    => 'store',
            'options' => array(
                'etc_dir'     => $this->_targetDir . DIRECTORY_SEPARATOR . 'etc',
                'var_dir'     => $this->_targetDir . DIRECTORY_SEPARATOR . 'var',
                'tmp_dir'     => $this->_targetDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'tmp',
                'cache_dir'   => $this->_targetDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache',
                'log_dir'     => $this->_targetDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log',
                'session_dir' => $this->_targetDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'session',
                'media_dir'   => $this->_targetDir . DIRECTORY_SEPARATOR . 'media',
                'public_dir'  => $this->_targetDir,
                'skin_dir'    => $this->_targetDir . DIRECTORY_SEPARATOR . 'skin',
                'upload_dir'  => $this->_targetDir . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'upload',
            ),
        );

        file_put_contents(
            $this->_targetDir . DIRECTORY_SEPARATOR . self::PARAMS_FILENAME,
            '<?php return ' . var_export($config, true) . ';'
        );

        return $config;
    }

    protected function _createDirectoryStructure()
    {
        $sourceEtc = $this->_magentoDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'etc';
        $targetEtc = $this->_targetDir . DIRECTORY_SEPARATOR . 'etc';

        mkdir($targetEtc);
        mkdir($this->_targetDir . DIRECTORY_SEPARATOR . 'var');
        mkdir($this->_targetDir . DIRECTORY_SEPARATOR . 'media');
        mkdir($this->_targetDir . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'upload');

        foreach (new \DirectoryIterator($sourceEtc) as $item) {
            if ($item->isFile() && $item->getBasename() != 'local.xml') {
                Fso::copy($item->getPathname(), $targetEtc . DIRECTORY_SEPARATOR . $item->getBasename(), false);
            }
        }
        Fso::copy(
            $sourceEtc . DIRECTORY_SEPARATOR . 'modules',
            $targetEtc . DIRECTORY_SEPARATOR . 'modules',
            false
        );
        Fso::copy(
            $this->_magentoDir . DIRECTORY_SEPARATOR . 'js',
            $this->_targetDir . DIRECTORY_SEPARATOR . 'js',
            false
        );
        Fso::copy(
            $this->_magentoDir . DIRECTORY_SEPARATOR . 'skin',
            $this->_targetDir . DIRECTORY_SEPARATOR . 'skin',
            false
        );
    }

    protected function _createLocalXml()
    {
        $templatePathname = $this->_magentoDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'local.xml.template';
        $targetPathname = $this->_targetDir . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'local.xml';

        $target = str_replace(
            array_map(
                function ($key) {return '{{' . $key . '}}';},
                array_keys($this->_configData)
            ),
            array_values($this->_configData),
            file_get_contents($templatePathname)
        );

        file_put_contents($targetPathname, $target);
    }

    protected function _updateLocalXmlWithCurrentDate()
    {
        $localXmlFilename = $this->_targetDir . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'local.xml';
        $localXml = file_get_contents($localXmlFilename);
        $localXml = str_replace('{{date}}', date('r'), $localXml);
        file_put_contents($localXmlFilename, $localXml, LOCK_EX);
    }
}
