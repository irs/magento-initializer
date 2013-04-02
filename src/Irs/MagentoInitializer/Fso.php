<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer;

abstract class Fso
{
    public static function copy($from, $to, $deep = true)
    {
        if ($deep || !self::_isOsSupportsLinks()) {
            self::_copy($from, $to);
        } else {
            symlink($from, $to);
        }
    }

    protected static function _copy($from, $to)
    {
        if (is_dir($from)) {
            @mkdir($to);
            $directory = dir($from);
            while (false !== ($readdirectory = $directory->read())) {
                if ($readdirectory == '.' || $readdirectory == '..') {
                    continue;
                }
                self::_copy($from . DIRECTORY_SEPARATOR . $readdirectory, $to . DIRECTORY_SEPARATOR . $readdirectory);
            }
            $directory->close();
        } else {
            copy($from, $to);
        }
    }

    public static function move($from, $to)
    {
        return @rename($from, $to);
    }

    public static function delete($filename)
    {
        if (!file_exists($filename)){
            throw new \InvalidArgumentException("File '$filename' does not exist.");
        }

        if (is_file($filename)) {
            return unlink($filename);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($filename),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ('.' == $item->getFilename() || '..' == $item->getFilename()) {
                continue;
            }
            if ($item->isDir()) {
                if ($item->isLink() && !self::_isOsWindowsNt()) {
                    unlink((string)$item);
                } else {
                    rmdir((string)$item);
            	}
            } else {
                unlink((string)$item);
            }
        }

        unset($iterator);
        if (is_link($filename) && !self::_isOsWindowsNt()) {
            unlink($filename);
        } else {
            rmdir($filename);
    	}
    }

    protected static function _isOsSupportsLinks()
    {
        return self::_isOsWindowsNt()
            ? version_compare(php_uname('r'), '6.0', '>=')
            : true;
    }

    protected static function _isOsWindowsNt()
    {
        return php_uname('s') == 'Windows NT';
    }
}
