<?php

namespace Charcoal\Conductor\Command;

use Charcoal\App\App;
use Charcoal\App\AppConfig;
use Charcoal\App\AppContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

abstract class AbstractCommand extends Command
{
    public static int $SUCCESS = 0;
    public static int $FAILURE = 1;
    public static int $INVALID = 2;

    protected $project_dir;
    protected AppConfig $appConfig;
    protected AppContainer $appContainer;
    protected App $projectApp;

    public function getProjectDir(): string
    {
        if (!isset($this->project_dir)) {
            $this->project_dir = getcwd();
        }
        return $this->project_dir;
    }

    public function writeError(string $message, OutputInterface $output)
    {
        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $section = $formatter->formatSection(
            'Error',
            sprintf('<fg=red>%s</>', $message),
            'error'
        );
        $output->writeln($section);
    }

    /**
     * Validates that the project is a Charcoal project.
     */
    public function validateProject(): bool
    {
        $filesystem = new Filesystem();
        $exists = $filesystem->exists($this->getProjectDir() . '/metadata');
        return $exists;
    }

    /**
     * Get Project App.
     * @return App
     */
    public function getProjectApp(bool $new = false)
    {
        if ($new || !isset($this->projectApp)) {
            $container = $this->getAppContainer();
            $this->projectApp = App::instance($container);
            $this->projectApp->run();
        }

        return $this->projectApp;
    }

    public function getAppConfig(bool $new = false)
    {
        if ($new || !isset($this->appConfig)) {
            $baseDir = $this->getProjectDir();
            $confFile = $baseDir . '/config/config.php';
            $config = new AppConfig([
                'base_path' => $baseDir,
            ]);
            $config->addFile($confFile);
            $config->merge([
                'service_providers' => [
                    'charcoal/admin/service-provider/admin' => []
                ]
            ]);

            if ($this->isDdevSupported()) {
               $ddevDbHostname = $this->getDdevDbHostname();
                $config->merge([
                    'databases' => [
                        'default' => [
                            'hostname' => $ddevDbHostname,
                        ]
                    ]
                ]);
            }

            $this->appConfig = $config;
        }

        return $this->appConfig;
    }

    /**
     * Get Project App.
     * @param boolean $new Create a new app container.
     * @return AppContainer
     */
    public function getAppContainer(bool $new = false): AppContainer
    {
        if ($new || !isset($this->appContainer)) {
            // Mute deprecation warnings.
            set_error_handler(function ($errno, $errstr) {
                if ($errno == E_DEPRECATED) {
                    return true;
                }
            });

            // Find Composer autoloader
            $autoloaderPath = $this->getProjectDir() . '/vendor/autoload.php';

            if (file_exists($autoloaderPath)) {
                include $autoloaderPath;
            } else {
                die('Composer autoloader not found.' . "\n");
            }

            // Create container and configure it (with charcoal-config)
            $this->appContainer = new AppContainer([
                'config' => $this->getAppConfig($new),
            ]);

            // Convert HTTP 404 Not Found to CLI-friendly error
            $this->appContainer['notFoundHandler'] = function ($container) {
                return function ($request, $response) use ($container) {
                    return $container['response'];
                };
            };

            // Convert HTTP 500 Server Error to CLI-friendly error
            $this->appContainer['errorHandler'] = function ($container) {
                return function ($request, $response, $exception) use ($container) {
                    return $container['response']
                        ->withStatus(500)
                        ->write(sprintf(
                            'Something went wrong! [%s]' . "\n",
                            $exception->getMessage()
                        ));
                };
            };
        }
        return $this->appContainer;
    }

    /**
     * Run a bash command.
     * @param string $command Command to run.
     * @param OutputInterface $output Output.
     * @param callable|boolean $callback Callback.
     * @param callable|boolean $errorCallback Error Callback.
     * @return void
     */
    protected function runScript(
        string $command,
        OutputInterface $output,
        $callback = null,
        $errorCallback = null
    ) {
        if ($callback === null || $callback === true) {
            $callback = function ($type, $buffer) use ($output) {
                $output->write($buffer);
            };
        }

        if ($errorCallback === null || $errorCallback === true) {
            $errorCallback = function ($type, $buffer) use ($output) {
                if (Process::ERR === $type) {
                    $output->write('<error> ' . $buffer . '</error>');
                }
            };
        }

        $process = new Process($command);
        $process->run(function ($type, $buffer) use ($callback, $errorCallback) {
            if (is_callable($errorCallback) && Process::ERR === $type) {
                $errorCallback($type, $buffer);
            }

            if (is_callable($callback)) {
                $callback($type, $buffer);
            }
        });
    }

    private function getPhpBinaryFromScript(string $script, OutputInterface $output): string
    {
        $phpBinary = PHP_BINARY;

        $this->runScript($script, $output, function ($type, $buffer) use (&$phpBinary) {
            if (strpos($buffer, '/php') !== false) {
                $phpBinary = str_replace(array("\r", "\n"), '', $buffer);
            }
        });

        return $phpBinary;
    }

    public function getPhpBinaryForCharcoal(OutputInterface $output)
    {
        $phpBinary = PHP_BINARY;

        if ($this->isValetSupported()) {
            $phpBinary = $this->getPhpBinaryFromScript('cd ' . __DIR__ . '/../../;valet link;valet which-php', $output);
        }

        if ($this->isDdevSupported()) {
            $phpBinary = 'ddev php';
        }

        return $phpBinary;
    }

    public function getPhpBinaryForProject(OutputInterface $output)
    {
        $phpBinary = PHP_BINARY;

        if ($this->isValetSupported()) {
            $projectDirectory = $this->getProjectDir();
            $phpBinary = $this->getPhpBinaryFromScript('cd ' . $projectDirectory . ';valet which-php', $output);
        }

        return $phpBinary;
    }

    public function isValetSupported()
    {
        return !empty(shell_exec(sprintf("which %s", escapeshellarg('valet'))));
    }

    public function isDdevSupported()
    {
        $path = $this->getProjectDir() . '/.ddev/.webimageBuild/Dockerfile';
        $dockerfile_exists   = file_exists($path);
        $ddev_command_exists = !empty(shell_exec(sprintf("which %s", escapeshellarg('ddev'))));

        return $dockerfile_exists && $ddev_command_exists;
    }

    function getDdevDbHostname(): ?string {
        try {
            $ddevJson = json_decode(trim(shell_exec('ddev describe -j')), true);

            $dbHost = $ddevJson['raw']['dbinfo']['host'];
            $dbHost = $dbHost === 'db' ? '127.0.0.1' : $dbHost;

            $dbPort = $ddevJson['raw']['dbinfo']['published_port'];

            return $dbHost . ':' . $dbPort;
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    protected function getQuestionHelper(): QuestionHelper
    {
        return $this->getHelper('question');
    }
}
