<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\Initializer\Db;

interface DbInterface
{
    /**
     * Creates dump with certain file name
     *
     * @param string $fileName
     */
    public function createDump($fileName);

    /**
     * Uploads dump with certain file name to database
     *
     * @param string $fileName
     */
    public function restoreDump($fileName);
}
