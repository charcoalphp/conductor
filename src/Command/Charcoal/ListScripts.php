<?php

namespace Charcoal\Conductor\Command\Charcoal;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Command\AbstractCommand;

class ListScripts extends AbstractCommand
{
    use ModelAwareTrait;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('charcoal:list')
            ->setDescription('List all charcoal scripts.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays a list of all registered scripts within your Charcoal project
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->validateProject()) {
            $output->write('Your project is not a valid Charcoal project');
            exit();
        }

        $scripts = $this->getProjectScripts();

        foreach ($scripts as $scriptName) {
            $output->writeln($scriptName);
        }

        return 0;
    }
}
