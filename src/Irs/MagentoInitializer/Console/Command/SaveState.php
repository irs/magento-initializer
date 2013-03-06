<?php
/**
 * This file is part of the Magento initialization framework.
 * (c) 2013 Vadim Kusakin <vadim.irbis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Irs\MagentoInitializer\Console\Command;

use Irs\MagentoInitializer\Initializer\GenericInitializer as MagentoInitializer;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;

class SaveState extends Command
{
    const OPTION_CONFIG = 'config';
    const OPTION_CONFIG_PROFILE = 'config-profile';
    const OPTION_STATE_FILENAME = 'name';

    private $behatConfig;

    private $currentInput;

    protected function configure()
    {
        $this->setName('save-state')
            ->setDescription('Saves state of Magento instance in states directory')
            ->addOption(self::OPTION_CONFIG, 'c', InputArgument::OPTIONAL, "Path to Behat's config", 'behat.yml')
            ->addOption(self::OPTION_CONFIG_PROFILE, 'p', InputArgument::OPTIONAL, "Profile of Behat's config", 'default')
            ->addOption(self::OPTION_STATE_FILENAME, 's', InputArgument::OPTIONAL, "State name", $this->getDefaultStateName());
    }

    protected function getDefaultStateName()
    {
        $now = new \DateTime;

        return 'states/' . $now->format('Y-m-d-h-i-s') . '.state';
    }

    /**
     * @return InputInterface Currect input interface
     */
    protected function getCurrentInput()
    {
        if (!$this->currentInput) {
            throw new LogicException("Current input is undefined.");
        }

        return $this->currentInput;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->currentInput = $input;
        $formatter = $this->getHelper('formatter');

        try {
            $initializer = new MagentoInitializer($this->getTargetPath());
            $stateFilename = $this->getStateFileName();
            $initializer->saveState($stateFilename);
            $output->writeln($formatter->formatBlock('Magento state has been successfully saved to:', 'info'));
            $output->writeln($formatter->formatBlock($stateFilename, 'comment'));
        } catch (\InvalidArgumentException $e) {
            $output->writeln($formatter->formatBlock($e->getMessage(), 'error'));
            return;
        }
    }

    protected function getTargetPath()
    {
        $config = $this->getBehatConfig();
        if (!isset($config['extensions']['Irs\BehatMagentoExtension\Extension']['target'])) {
            throw new \InvalidArgumentException('Target is not defined in config.');
        }

        return $config['extensions']['Irs\BehatMagentoExtension\Extension']['target'];
    }

    protected function getConfigFileName()
    {
        return $this->getCurrentInput()->getOption(self::OPTION_CONFIG);
    }

    protected function getConfigProfile()
    {
        return $this->getCurrentInput()->getOption(self::OPTION_CONFIG_PROFILE);
    }

    protected function getStateFileName()
    {
        return $this->getCurrentInput()->getOption(self::OPTION_STATE_FILENAME);
    }

    /**
     * Returns parsed profile from behat.yml
     *
     * @param InputInterface $input
     * @return array
     * @throws \InvalidArgumentException If cannot readt behat.yml or
     *                                   it doesn't contain required profile
     */
    protected function getBehatConfig()
    {
        if (!$this->behatConfig) {
            $configPath = $this->getConfigFileName();
            if (!file_exists($configPath)) {
                throw new \InvalidArgumentException("Cannot open config file '$configPath'.");
            }
            $this->behatConfig = Yaml::parse($configPath);
        }

        $profileName = $this->getConfigProfile();
        if (!isset($this->behatConfig[$profileName])) {
            throw new \InvalidArgumentException("Profile '$profileName' is undefined in '$configPath'.");
        }

        return $this->behatConfig[$profileName];
    }
}
