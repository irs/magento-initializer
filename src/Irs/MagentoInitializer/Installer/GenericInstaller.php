<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\Installer;

use Irs\Fso\Fso;

class GenericInstaller implements InstallerInterface
{
    const PARAMS_FILENAME = 'params.php';
    const ADMIN_USER_NAME = 'admin';
    const ADMIN_USER_PASSWORD = '123123qa';

    private $magentoDir;
    private $targetDir;
    private $configData;
    private $copier;

    /**
     * Constructs installer
     *
     * @param string $targetDir   Target directory path
     * @param string $magentoDir  Path to magento
     * @param string $dbHostName  Host name of test DB
     * @param string $dbUserName  User name of test DB
     * @param string $dbPassword  Pasword of test DB
     * @param string $dbName      Schema name of test DB
     * @throws \InvalidArgumentException On invalid arguments
     */
    public function __construct($targetDir, $magentoDir, $dbHostName, $dbUserName, $dbPassword, $dbName)
    {
        $this->magentoDir = realpath($magentoDir);
        if (!$this->magentoDir) {
            throw new \InvalidArgumentException("'$magentoDir' is incorrect path.");
        }
        if (!is_dir($this->magentoDir)) {
            throw new \InvalidArgumentException("'$magentoDir' is not a directory.");
        }
        if (!file_exists($this->magentoDir . '/app/Mage.php')) {
            throw new \InvalidArgumentException("'$magentoDir' is invalid Magento's directory.");
        }
        if (!is_readable($this->magentoDir)) {
            throw new \InvalidArgumentException("'$targetDir' is not readable.");
        }

        $this->targetDir = realpath($targetDir);
        if (!$this->targetDir) {
            throw new \InvalidArgumentException("'$targetDir' is incorrect path.");
        }
        if (!is_dir($this->targetDir)) {
            throw new \InvalidArgumentException("'$targetDir' is not a directory.");
        }
        if (!is_writeable($this->targetDir)) {
            throw new \InvalidArgumentException("'$targetDir' is not writeable.");
        }

        $this->configData = array(
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
             throw new \RuntimeException("Magento is already installed at '$this->targetDir'.");
        }
        try {
            $this->createDirectoryStructure();
            $this->createLocalXml();
            $this->createIndexPhp();
            $params = $this->createRunParams();
            $this->installMagento($params['code'], $params['type'], $params['options']);
        } catch (\Exception $e) {
            $this->cleanupTarget();
            throw $e;
        }
    }

    protected function cleanupTarget()
    {
    	foreach (new \DirectoryIterator($this->targetDir) as $item) {
    		if (!$item->isDot()) {
    			Fso::delete($item->getPathname());
    		}
    	}
    }

    public function isInstalled()
    {
        return file_exists($this->targetDir . DIRECTORY_SEPARATOR . self::PARAMS_FILENAME);
    }

    protected function installMagento($code, $type, array $options)
    {
        include $this->magentoDir . '/app/Mage.php';

        \Mage::app($code, $type, $options);
        \Mage_Core_Model_Resource_Setup::applyAllUpdates();
        \Mage_Core_Model_Resource_Setup::applyAllDataUpdates();

        // Enable configuration cache by default in order to improve tests performance
        \Mage::app()->getCacheInstance()->saveOptions(array('config' => 1));
        $this->updateLocalXmlWithCurrentDate();
        $this->createAdminUser(self::ADMIN_USER_NAME, self::ADMIN_USER_PASSWORD);
    }

    protected function createAdminUser($userName, $password)
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

    protected function createIndexPhp()
    {
        $index = file_get_contents($this->magentoDir . DIRECTORY_SEPARATOR . 'index.php.sample');

        $index = str_replace(
        	"\$compilerConfig = 'includes/config.php';",
            "\$compilerConfig = '{$this->magentoDir}/includes/config.php';",
            $index
        );
        $index = str_replace(
            "\$mageFilename = 'app/Mage.php';",
            "\$mageFilename = '{$this->magentoDir}/app/Mage.php';",
            $index
        );

        $pattern = '/umask\(0\);.*/s';
        $targetCode = <<<TARGET
umask(0);

\$paramsFilename = 'params.php';
if (file_exists(\$paramsFilename)) {
    \$params = include \$paramsFilename;
    Mage::run(\$params['code'], \$params['type'], \$params['options']);
} else {
    die("Cannot run test instance without run params.");
}
TARGET;
        $index = preg_replace($pattern, $targetCode, $index);
        file_put_contents($this->targetDir . DIRECTORY_SEPARATOR . 'index.php', $index);
    }

    protected function createRunParams()
    {
        $config = array(
            'code'    => '',
            'type'    => 'store',
            'options' => array(
                'etc_dir'     => $this->targetDir . DIRECTORY_SEPARATOR . 'etc',
                'var_dir'     => $this->targetDir . DIRECTORY_SEPARATOR . 'var',
                'tmp_dir'     => $this->targetDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'tmp',
                'cache_dir'   => $this->targetDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache',
                'log_dir'     => $this->targetDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log',
                'session_dir' => $this->targetDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'session',
                'media_dir'   => $this->targetDir . DIRECTORY_SEPARATOR . 'media',
                'public_dir'  => $this->targetDir,
                'skin_dir'    => $this->targetDir . DIRECTORY_SEPARATOR . 'skin',
                'upload_dir'  => $this->targetDir . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'upload',
            ),
        );

        file_put_contents(
            $this->targetDir . DIRECTORY_SEPARATOR . self::PARAMS_FILENAME,
            '<?php return ' . var_export($config, true) . ';'
        );

        return $config;
    }

    protected function createDirectoryStructure()
    {
        $sourceEtc = $this->magentoDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'etc';
        $targetEtc = $this->targetDir . DIRECTORY_SEPARATOR . 'etc';

        mkdir($targetEtc);
        mkdir($this->targetDir . DIRECTORY_SEPARATOR . 'var');
        mkdir($this->targetDir . DIRECTORY_SEPARATOR . 'media');
        mkdir($this->targetDir . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'upload');

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
            $this->magentoDir . DIRECTORY_SEPARATOR . 'js',
            $this->targetDir . DIRECTORY_SEPARATOR . 'js',
            false
        );
        Fso::copy(
            $this->magentoDir . DIRECTORY_SEPARATOR . 'skin',
            $this->targetDir . DIRECTORY_SEPARATOR . 'skin',
            false
        );
    }

    protected function createLocalXml()
    {
        $templatePathname = $this->magentoDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'local.xml.template';
        $targetPathname = $this->targetDir . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'local.xml';

        $target = str_replace(
            array_map(
                function ($key) {return '{{' . $key . '}}';},
                array_keys($this->configData)
            ),
            array_values($this->configData),
            file_get_contents($templatePathname)
        );

        file_put_contents($targetPathname, $target);
    }

    protected function updateLocalXmlWithCurrentDate()
    {
        $localXmlFilename = $this->targetDir . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'local.xml';
        $localXml = file_get_contents($localXmlFilename);
        $localXml = str_replace('{{date}}', date('r'), $localXml);
        file_put_contents($localXmlFilename, $localXml, LOCK_EX);
    }
}
