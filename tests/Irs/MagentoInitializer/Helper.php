<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer;

abstract class Helper extends \PHPUnit_Framework_Assert
{
    public static function createTempDir()
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::_getRandomName();
        mkdir($path);

        return $path;
    }

    public static function delete($path)
    {
        if (is_dir($path)) {
            if (!is_link($path)) {
                $directory = dir($path);
                while (false !== ($readdirectory = $directory->read())) {
                    if ($readdirectory == '.' || $readdirectory == '..') {
                        continue;
                    }
                    self::delete($path . DIRECTORY_SEPARATOR . $readdirectory);
                }
                $directory->close();
                rmdir($path);
            } else {
                unlink($path);
            }
        } else if (is_file($path)) {
            unlink($path);
        } else {
            // broken symlink
            @unlink($path) || @rmdir($path);
        }
    }

    private static function _getRandomName()
    {
        return uniqid('btf');
    }

    public static function assertCorrectIndexPhpFile($indexFileName, $magento)
    {
        // prepare
        self::assertFileExists($indexFileName);
        $index = file_get_contents($indexFileName);
        self::assertGreaterThan(0, preg_match("#\\\$compilerConfig = '(.*)/includes/config.php';#", $index, $m));
        self::assertEquals(realpath($magento), realpath($m[1]));

        self::assertGreaterThan(0, preg_match("#\\\$mageFilename = '(.*)/app/Mage.php';#", $index, $m));
        self::assertEquals(realpath($magento), realpath($m[1]));

        self::assertTrue(false !== strpos($index, <<<STMT
\$paramsFilename = 'params.php';
if (file_exists(\$paramsFilename)) {
    \$params = include \$paramsFilename;
    Mage::run(\$params['code'], \$params['type'], \$params['options']);
} else {
    die("Cannot run test instance without run params.");
}
STMT
        ));
    }

    public static function assertFileStructureIsCorrect($target)
    {
        self::assertFileExists($target . '/etc');
        self::assertFileExists($target . '/etc/config.xml');
        self::assertFileExists($target . '/etc/local.xml.additional');
        self::assertFileExists($target . '/etc/local.xml.template');
        self::assertFileExists($target . '/etc/enterprise.xml');
        self::assertFileExists($target . '/etc/');
        self::assertFileExists($target . '/etc/modules');
        self::assertFileExists($target . '/etc/modules/module.xml');
        self::assertFileExists($target . '/js');
        self::assertFileExists($target . '/skin');
        self::assertFileExists($target . '/js/js.test');
        self::assertFileExists($target . '/skin/skin.test');
        self::assertFileExists($target . '/var');
        self::assertFileExists($target . '/media');
        self::assertFileNotExists($target . '/var/var.test');
        self::assertFileNotExists($target . '/media/media.test');
    }

    public static function assertRunParametersIsCorrect($target, $magento, $expectedStore = '', $expectedScope = 'store')
    {
        $target = realpath($target);
        $magento = realpath($magento);
        $runParamsPathname = $target . '/params.php';
        self::assertFileExists($runParamsPathname);
        $params = include $runParamsPathname;
        self::assertEquals(
            array(
                'code' => $expectedStore,
                'type' => $expectedScope,
                'options' =>
                    array(
                        'etc_dir' => $target . DIRECTORY_SEPARATOR . 'etc',
                        'var_dir' => $target . DIRECTORY_SEPARATOR . 'var',
                        'tmp_dir' => $target . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'tmp',
                        'cache_dir' => $target . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache',
                        'log_dir' => $target . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log',
                        'session_dir' => $target . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'session',
                        'media_dir' => $target . DIRECTORY_SEPARATOR . 'media',
                        'public_dir' => $target,
                        'skin_dir' => $target . DIRECTORY_SEPARATOR . 'skin',
                        'upload_dir' => $target . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'upload',
                    ),
            ),
            $params
        );
    }

    public static function emulateMagentoFileStructure($target, $version = '1.9')
    {
        mkdir($target . '/app');
        mkdir($target . '/var');
        mkdir($target . '/skin');
        mkdir($target. '/media');
        mkdir($target. '/js');
        mkdir($target. '/app/code');
        mkdir($target. '/app/etc');
        mkdir($target. '/app/etc/modules');

        file_put_contents($target. '/app/Mage.php', 'Mage.php');
        file_put_contents($target. '/app/etc/local.xml', 'local.xml');
        file_put_contents($target. '/app/etc/config.xml', 'config.xml');
        file_put_contents($target. '/app/etc/local.xml.additional', 'local.xml.additional');
        file_put_contents($target. '/app/etc/local.xml.template', self::LOCAL_XML_TEMPLATE);
        file_put_contents($target. '/app/etc/enterprise.xml', 'enterprise.xml');
        file_put_contents($target. '/app/etc/modules/module.xml', 'module.xml');
        file_put_contents($target. '/var/var.test', 'var.test');
        file_put_contents($target. '/media/media.test', 'media.test');
        file_put_contents($target. '/js/js.test', 'js.test');
        file_put_contents($target. '/skin/skin.test', 'skin.test');
        file_put_contents($target. '/index.php.sample', self::$indexPhpSample[$version]);
    }

//    private $_localXmlTemplateContent = <<<XML
    const LOCAL_XML_TEMPLATE = <<<XML
<?xml version="1.0"?>
<!--
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE_AFL.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2008 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
-->
<config>
    <global>
        <install>
            <date>{{date}}</date>
        </install>
        <crypt>
            <key>{{key}}</key>
        </crypt>
        <disable_local_modules>false</disable_local_modules>
        <resources>
            <db>
                <table_prefix>{{db_prefix}}</table_prefix>
            </db>
            <default_setup>
                <connection>
                    <host>{{db_host}}</host>
                    <username>{{db_user}}</username>
                    <password>{{db_pass}}</password>
                    <dbname>{{db_name}}</dbname>
                    <initStatements>{{db_init_statemants}}</initStatements>
                    <model>{{db_model}}</model>
                    <type>{{db_type}}</type>
                    <pdoType>{{db_pdo_type}}</pdoType>
                    <active>1</active>
                </connection>
            </default_setup>
        </resources>
        <session_save>{{session_save}}</session_save>
    </global>
    <admin>
        <routers>
            <adminhtml>
                <args>
                    <frontName>{{admin_frontname}}</frontName>
                </args>
            </adminhtml>
        </routers>
    </admin>
</config>
XML;

    private static $indexPhpSample = array(
        '1.9' => <<<INDEX
<?php
/**
 * Magento Enterprise Edition
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Magento Enterprise Edition License
 * that is bundled with this package in the file LICENSE_EE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.magentocommerce.com/license/enterprise-edition
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage
 * @copyright   Copyright (c) 2011 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://www.magentocommerce.com/license/enterprise-edition
 */

if (version_compare(phpversion(), '5.2.0', '<')===true) {
    echo  '<div style="font:12px/1.35em arial, helvetica, sans-serif;"><div style="margin:0 0 25px 0; border-bottom:1px solid #ccc;"><h3 style="margin:0; font-size:1.7em; font-weight:normal; text-transform:none; text-align:left; color:#2f2f2f;">Whoops, it looks like you have an invalid PHP version.</h3></div><p>Magento supports PHP 5.2.0 or newer. <a href="http://www.magentocommerce.com/install" target="">Find out</a> how to install</a> Magento using PHP-CGI as a work-around.</p></div>';
    exit;
}

/**
 * Error reporting
 */
error_reporting(E_ALL | E_STRICT);

/**
 * Compilation includes configuration file
 */
\$compilerConfig = 'includes/config.php';
if (file_exists(\$compilerConfig)) {
    include \$compilerConfig;
}

\$mageFilename = 'app/Mage.php';

if (!file_exists(\$mageFilename)) {
    if (is_dir('downloader')) {
        header("Location: downloader");
    } else {
        echo \$mageFilename." was not found";
    }
    exit;
}

require_once \$mageFilename;

#Varien_Profiler::enable();

if (isset(\$_SERVER['MAGE_IS_DEVELOPER_MODE'])) {
    Mage::setIsDeveloperMode(true);
}

#ini_set('display_errors', 1);

umask(0);

\$mageRunCode = isset(\$_SERVER['MAGE_RUN_CODE']) ? \$_SERVER['MAGE_RUN_CODE'] : '';
\$mageRunType = isset(\$_SERVER['MAGE_RUN_TYPE']) ? \$_SERVER['MAGE_RUN_TYPE'] : 'store';

Mage::run(\$mageRunCode, \$mageRunType);
INDEX
        ,
        '1.11' => <<<INDEX
        <?php
/**
 * Magento Enterprise Edition
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Magento Enterprise Edition License
 * that is bundled with this package in the file LICENSE_EE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.magentocommerce.com/license/enterprise-edition
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage
 * @copyright   Copyright (c) 2011 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://www.magentocommerce.com/license/enterprise-edition
 */

if (version_compare(phpversion(), '5.2.0', '<')===true) {
    echo  '<div style="font:12px/1.35em arial, helvetica, sans-serif;"><div style="margin:0 0 25px 0; border-bottom:1px solid #ccc;"><h3 style="margin:0; font-size:1.7em; font-weight:normal; text-transform:none; text-align:left; color:#2f2f2f;">Whoops, it looks like you have an invalid PHP version.</h3></div><p>Magento supports PHP 5.2.0 or newer. <a href="http://www.magentocommerce.com/install" target="">Find out</a> how to install</a> Magento using PHP-CGI as a work-around.</p></div>';
    exit;
}

/**
 * Error reporting
 */
error_reporting(E_ALL | E_STRICT);

/**
 * Compilation includes configuration file
 */
\$compilerConfig = 'includes/config.php';
if (file_exists(\$compilerConfig)) {
    include \$compilerConfig;
}

\$mageFilename = 'app/Mage.php';
\$maintenanceFile = 'maintenance.flag';

if (!file_exists(\$mageFilename)) {
    if (is_dir('downloader')) {
        header("Location: downloader");
    } else {
        echo \$mageFilename." was not found";
    }
    exit;
}

if (file_exists(\$maintenanceFile)) {
    include_once dirname(__FILE__) . '/errors/503.php';
    exit;
}

require_once \$mageFilename;

#Varien_Profiler::enable();

if (isset(\$_SERVER['MAGE_IS_DEVELOPER_MODE'])) {
    Mage::setIsDeveloperMode(true);
}

#ini_set('display_errors', 1);

umask(0);

/* Store or website code */
\$mageRunCode = isset(\$_SERVER['MAGE_RUN_CODE']) ? \$_SERVER['MAGE_RUN_CODE'] : '';

/* Run store or run website */
\$mageRunType = isset(\$_SERVER['MAGE_RUN_TYPE']) ? \$_SERVER['MAGE_RUN_TYPE'] : 'store';

Mage::run(\$mageRunCode, \$mageRunType);
INDEX
    );
}
