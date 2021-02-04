<?php
declare(strict_types=1);

namespace App\Command;

use App\Exception\ComposerNotFoundException;
use App\Exception\InvalidComposerException;
use App\Model\Package;
use App\Service\JsonService;
use App\Service\PackagistService;
use Chindit\Collection\Collection;
use Exception;
use JsonException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\HttpClient\HttpClient;

class CheckUpdatesCommand extends Command
{
	public static $defaultName = 'app:check-updates';

	private JsonService $json;
	private PackagistService $packagistService;
	/** @var Collection<int, Package> */
	private Collection $packages;
	/** @var array<int, string> */
	private array $errors = [];

	public function __construct(string $name = null)
	{
		parent::__construct($name);

		$this->packages = new Collection();
		$this->packagistService = new PackagistService(HttpClient::create());
	}

	protected function configure(): void
	{
		parent::configure();

		$this->addOption('composer', '-c', InputOption::VALUE_OPTIONAL, 'The directory where your composer.json is located', getcwd());
		$this->addOption('no-dev', null, InputOption::VALUE_OPTIONAL, 'Ignore require-dev section', false);
		$this->addOption('update', '-u', InputOption::VALUE_OPTIONAL, 'Update composer.json file', false);
		$this->addOption('interactive', '-i', InputOption::VALUE_OPTIONAL, 'Set mode to interactive', false);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		// 1) Create JsonService
		try {
			$this->json = new JsonService($input->getOption('composer'));
		} catch (ComposerNotFoundException $exception) {
			$output->writeln('<error>Unable to find a composer.json file in «' . $exception->getComposerSearchPath() . '»</error>');
			$output->writeln('<comment>Try using «-c /path/to/my/project» to specify correct location to your composer.json file</comment>');

			return Command::FAILURE;
		} catch (InvalidComposerException $exception) {
			$output->writeln('<error>Your composer.json does not contains «require» nor «require-dev» sections</error>');

			return Command::FAILURE;
		} catch (JsonException $exception) {
			$output->writeln('<error>Your composer.json does not contains valid JSON</error>');

			return Command::FAILURE;
		}

		// 2) Get dependencies
		$dependencies = $this->json->getDependencies();
		$output->writeln(sprintf('<info>Found %s packages in «require» section.  Scanning…</info>', $dependencies->count()));
		$this->scanDependencies($output, $dependencies);

		// 3) Get dev dependencies
		if ($input->getOption('no-dev') !== null)
		{
			$devDependencies = $this->json->getDevDependencies();
			$output->writeln(sprintf('<info>Found %s packages in «require-dev» section.  Scanning…</info>', $devDependencies->count()));
			$this->scanDependencies($output, $devDependencies);
		}


		foreach ($this->errors as $error) {
			$output->writeln($error);
		}

		$updatablePackages = $this->packages->filter(fn(Package $package) => $package->isUpdatable());

		if ($updatablePackages->isNotEmpty())
		{
			$versionTable = new Table($output);
			$versionTable->setHeaders(
				[
					'Package',
					'Current version',
					'New version'
				]
			)
				->addRows($updatablePackages
                    ->map(function(Package $package) {
                        return $package->toTableArray();
                    })
                    ->toArray()
                )
			;

			$versionTable->render();

			$output->writeln(sprintf('<info>There are %s packages to update.</info>', $updatablePackages->count()));
		} else {
			$output->writeln('<info>All packages are up to date</info>');
		}

        $packagesToUpdate = new Collection();
        if ($input->getOption('interactive') !== false) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Do you want to update all packages, minors and patch, patch only or mothing ?',
                // choices can also be PHP objects that implement __toString() method
                ['all (default)', 'minor', 'patch', 'none'],
                0
            );

            $question->setErrorMessage('Value %s is invalid.');

            $upgradeType = $helper->ask($input, $output, $question);

            $packagesToUpdate = $this->packages->filter(function(Package $package) use ($upgradeType) {
                return match ($upgradeType) {
                    'minor' => $package->isMinorUpdate() || $package->isPatchUpdate(),
                    'patch' => $package->isPatchUpdate(),
                    'none'  => false,
                    default => $package->isUpdatable(),
                };
            })->keyBy(fn(Package $package) => $package->getName());
        } elseif ($input->getOption('update') !== false) {
			$packagesToUpdate = $this->packages;
		} else {
            $output->writeln('<info>Tip: Re-run the command with «-u» to update your composer.json</info>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>%d packages will be updated</info>', $packagesToUpdate->count()));

        if ($packagesToUpdate->isEmpty()) {
            return Command::SUCCESS;
        }

        if (!$this->json->isWritable()) {
            $output->writeln('<error>Your composer.json is not writable</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Updating composer.json</info>');
        if ($this->json->updateComposer($packagesToUpdate)) {
            $output->writeln('<info>Composer.json updated.  You can now run «composer update»</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>An error has occurred during composer.json update</error>');

        return Command::FAILURE;
	}

    /**
     * @param Collection<string, string> $dependencies
     */
	private function scanDependencies(OutputInterface $output, Collection $dependencies): void
	{
		$progress = new ProgressBar($output, $dependencies->count());

		$dependencies->each(function(string $version, string $dependency) use ($progress)
        {
            try
            {
                $this->packages->push(
                    new Package($dependency, $version, $this->packagistService->checkPackage($dependency))
                );
            } catch (Exception $exception) {
                $this->errors[] = sprintf('<error>%s</error>', $exception->getMessage());
            } finally {
                $progress->advance();
            }
        });

		$progress->finish();
		$output->writeln('');
	}
}
