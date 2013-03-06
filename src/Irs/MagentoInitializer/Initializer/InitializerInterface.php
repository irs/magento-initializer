<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\Initializer;

interface InitializerInterface
{
    public function initialize();

    public function saveState($stateFileName);

    public function restoreState($stateFileName);
}

