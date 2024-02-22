<?php

namespace Charcoal\Conductor\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Charcoal\App\App;
use Charcoal\App\AppConfig;
use Charcoal\App\AppContainer;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\FormatterHelper;
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

    public function getProjectScripts(): array
    {
        $container = $this->getAppContainer();

        /** @var AppConfig $config */
        $routes = $container['config']['routes'] ?? [];
        $scripts = !empty($routes['scripts']) ? $routes['scripts'] : [];

        foreach ($scripts as &$script) {
            $ident = $script['ident'];
            $script['ident'] = str_replace('/script', '', $ident);
        }

        $admin_routes = $container['admin/config']['routes'] ?? [];
        $admin_scripts = !empty($admin_routes['scripts']) ? $admin_routes['scripts'] : [];

        foreach ($admin_scripts as &$script) {
            $ident = $script['ident'];
            $script['ident'] = str_replace('charcoal/', '', $ident);
            $script['ident'] = str_replace('/script', '', $ident);
        }

        $all_scripts = array_values(array_merge($scripts, $admin_scripts));

        sort($all_scripts);

        return $all_scripts;
    }

    /**
     * Get Project App.
     * @return App
     */
    public function getProjectApp()
    {
        if (!isset($this->projectApp)) {
            $container = $this->getAppContainer();
            $this->projectApp = App::instance($container);
            $this->projectApp->run();
        }

        return $this->projectApp;
    }

    public function getAppConfig()
    {
        if (!isset($this->appConfig)) {
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

            $this->appConfig = $config;
        }

        return $this->appConfig;
    }

    /**
     * Get Project App.
     * @return AppContainer
     */
    public function getAppContainer(): AppContainer
    {
        if (!isset($this->appContainer)) {
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
                'config' => $this->getAppConfig(),
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

    private function getPhpBinaryFromScript(string $script, OutputInterface $output): string
    {
        $phpBinary = PHP_BINARY;
        $process = new Process($script);
        $process->run(function ($type, $buffer) use ($output, &$success, &$phpBinary) {
            if (Process::ERR === $type) {
                $success = false;
                $output->write('<error> ' . $buffer . '</error>');
            } else {
                if (strpos($buffer, '/php') !== false) {
                    $phpBinary = str_replace(array("\r", "\n"), '', $buffer);
                }
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
}
