<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\Initializer\Db;

class Mysql implements DbInterface
{
    private $_hostName;
    private $_port;
    private $_userName;
    private $_password;
    private $_schemaName;

    public function __construct($hostName, $userName, $password, $schemaName, $port = 3306)
    {
        $this->_hostName   = $hostName;
        $this->_port       = $port;
        $this->_userName   = $userName;
        $this->_password   = $password;
        $this->_schemaName = $schemaName;

    }

    public function createDump($fileName)
    {
        $dir = dirname($fileName);
        if (!is_writable($dir)) {
            throw new \InvalidArgumentException("Directory '$dir' is not writeable.");
        }

        if (!$this->_isMysqldumpAvailable()) {
            throw new \RuntimeException("Unable execute mysqldump; please check that it's in the path.");
        }

        exec(
            vsprintf(
            	'mysqldump %s --host=%s --port=%s --user=%s --password=%s > %s',
                array_map(
                	'escapeshellarg',
                    array(
                        $this->_schemaName,
                        $this->_hostName,
                        $this->_port,
                        $this->_userName,
                        $this->_password,
                        $fileName,
                    )
                )
            ),
            $output,
            $exitCode
        );

        if ($exitCode) {
            throw new \RuntimeException("Error occured on mysqldump call:\n" . substr(implode("\n", $output), 0, 150));
        }
    }

    public function restoreDump($fileName)
    {
        if (!file_exists($fileName)) {
            throw new \InvalidArgumentException("File '$fileName' does not exist.");
        }
        if (!is_readable($fileName)) {
            throw new \InvalidArgumentException("File '$fileName' is not readable.");
        }
        if (!$this->_isMysqlClientAvailable()) {
            throw new \RuntimeException("Unable execute mysql; please check that it's in the path.");
        }

        exec(
            vsprintf(
            	'mysql --host=%s --port=%s --database=%s --user=%s --password=%s < %s',
                array_map(
                	'escapeshellarg',
                    array(
                        $this->_hostName,
                        $this->_port,
                        $this->_schemaName,
                        $this->_userName,
                        $this->_password,
                        $fileName,
                    )
                )
            ),
            $output,
            $exitCode
        );

        if ($exitCode) {
            throw new \RuntimeException("Error occured on mysql call:\n" . substr(implode("\n", $output), 0, 150));
        }
    }

    private function _isMysqldumpAvailable()
    {
        exec('mysqldump --version', $output, $exitCode);

        return 0 == $exitCode;
    }

    private function _isMysqlClientAvailable()
    {
        exec('mysql --version', $output, $exitCode);

        return 0 == $exitCode;
    }
}