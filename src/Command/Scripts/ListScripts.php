<?php

namespace Charcoal\Conductor\Command\Scripts;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ListScripts extends AbstractScriptCommand
{
    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('scripts:list')
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
            return self::$FAILURE;
        }

        $scripts = $this->getProjectScripts();

        $table = new Table($output);
        $table->setHeaders([
            'Command',
            'Description',
        ]);

        foreach ($scripts as $script) {
            $table->addRow([
                $script['ident'],
                $script['description'] ?? ''
            ]);
        }

        $table->render();

        return self::$SUCCESS;
    }
}
