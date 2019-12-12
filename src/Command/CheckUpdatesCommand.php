<?php
declare(strict_types=1);

namespace App\Command;

use App\Exceptions\ComposerNotFoundException;
use App\Exceptions\InvalidComposerException;
use App\Exceptions\InvalidPackageException;
use App\Service\JsonService;
use App\Service\PackagistService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

class CheckUpdatesCommand extends Command
{
	public static $defaultName = 'app:check-updates';

	/**
	 * @var JsonService
	 */
	private $json;

	/**
	 * @var PackagistService
	 */
	private $packagistService;
	private $updates = [];
	private $errors = [];

	public function __construct(string $name = null)
	{
		parent::__construct($name);
		$this->packagistService = new PackagistService(HttpClient::create());
	}

	protected function configure()
	{
		parent::configure();

		$this->addOption('composer', '-c', InputOption::VALUE_OPTIONAL, 'The directory where your composer.json is located', getcwd());
		$this->addOption('no-dev', null, InputOption::VALUE_OPTIONAL, 'Ignore require-dev section', false);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		// 1) Create JsonService
		try {
			$this->json = new JsonService($input->getOption('composer'));
		} catch (ComposerNotFoundException $exception) {
			$output->writeln('<error>Unable to find a composer.json file in «' . $exception->getComposerSearchPath() . '»</error>');
			$output->writeln('<comment>Try using «-c /path/to/my/project» to specify correct location to your composer.json file</comment>');
		} catch (InvalidComposerException $exception) {
			$output->writeln('<error>Your composer.json does not contains «require» nor «require-dev» sections</error>');
		}

		// 2) Get dependencies
		$dependencies = $this->json->getDependencies();
		$output->writeln(sprintf('<info>Found %s packages in «require» section.  Scanning…</info>', count($dependencies)));
		$this->scanDependencies($output, $dependencies);

		// 3) Get dev dependencies
		if ($input->getOption('no-dev') !== null)
		{
			$devDependencies = $this->json->getDevDependencies();
			$output->writeln(sprintf('<info>Found %s packages in «require-dev» section.  Scanning…</info>', count($devDependencies)));
			$this->scanDependencies($output, $devDependencies);
		}


		foreach ($this->errors as $error) {
			$output->writeln($error);
		}

		if (!empty($this->updates))
		{
			$versionTable = new Table($output);
			$versionTable->setHeaders(
				[
					'Package',
					'Current version',
					'Last version'
				]
			)
				->addRows($this->updates)
			;

			$versionTable->render();

			$output->writeln(sprintf('<info>There are %s packages to update.</info>', count($this->updates)));
		} else {
			$output->writeln('<info>All packages are up to date</info>');
		}

		return 1;
	}

	private function scanDependencies(OutputInterface $output, array $dependencies): void
	{
		$progress = new ProgressBar($output, count($dependencies));

		foreach ($dependencies as $dependency => $version) {
			try
			{
				if ($update = $this->packagistService->needsUpdate($this->packagistService->checkUpdate($dependency), $version)) {
					$this->updates[] = [$dependency, $version, $update];
				}
			} catch (\Exception $exception) {
				$this->errors[] = sprintf('<error>%s</error>', $exception->getMessage());
			} finally {
				$progress->advance();
			}
		}
		$progress->finish();
		$output->writeln('');
	}
}
