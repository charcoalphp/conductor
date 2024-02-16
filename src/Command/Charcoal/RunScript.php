<?php

namespace Charcoal\Conductor\Command\Charcoal;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Command\AbstractCommand;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;

class RunScript extends AbstractCommand implements CompletionAwareInterface
{
    use ModelAwareTrait;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('charcoal:run')
            ->setDescription('Run a charcoal script.')
            ->addArgument('script', InputArgument::REQUIRED, 'The charcoal script you want to execute.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays a list of all registered scripts within your Charcoal project
EOF
            );
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
        if (!$this->validateProject()) {
            return [];
        }

        $scripts = $this->getProjectScripts();
        $word    = $context->getCurrentWord();
        $suggestions = [];

        if (empty($word)) {
            return $scripts;
        }

        foreach ($scripts as $script) {
            if (strpos($script, $word) !== false) {
                $suggestions[] = $script;
            }
        }

        return $suggestions;
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
        return [];
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
