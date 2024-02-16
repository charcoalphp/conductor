<?php

namespace Charcoal\Conductor\Command\Scripts;

use Charcoal\Conductor\Traits\ModelAwareTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Charcoal\Conductor\Command\AbstractCommand;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Slim\Http\Environment as SlimEnvironment;

class RunScript extends AbstractCommand implements CompletionAwareInterface
{
    use ModelAwareTrait;

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('scripts:run')
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

        $scripts = array_map(function ($script) {
            return $script['ident'];
        }, $this->getProjectScripts());

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
            return self::$FAILURE;
        }

        $container = $this->getAppContainer();
        $scriptInput = $input->getArgument('script');

        // Create a fake HTTP environment from the first CLI argument
        $container['environment'] = function ($container) use ($scriptInput) {
            $path = '/' . ltrim($scriptInput, '/');
            return SlimEnvironment::mock([
                'PATH_INFO'   => $path,
                'REQUEST_URI' => $path,
            ]);
        };

        $this->getProjectApp();

        return self::$SUCCESS;
    }
}
