<?php

namespace Charcoal\Conductor\Command;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Charcoal extends AbstractCommand
{
    use ModelAwareTrait;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('charcoal')
            ->setDescription('Run a charcoal script.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays a list of all registered scripts within your Charcoal project
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->validateProject()) {
            $output->write('Your project is not a valid Charcoal project');
            exit();
        }

        $scripts = $this->getProjectScripts();

        foreach ($scripts as $script) {
            $output->writeln(get_class($script));
        }

        return 0;
    }
}
