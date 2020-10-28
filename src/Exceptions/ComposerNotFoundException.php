<?php

namespace App\Exceptions;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class ComposerNotFoundException extends FileNotFoundException
{
	private string $composerSearchPath;

	public function __construct(string $composerSearchPath, string $message = null, int $code = 0, \Throwable $previous = null, string $path = null)
	{
		parent::__construct($message, $code, $previous, $path);
		$this->composerSearchPath = $composerSearchPath;
	}

	public function getComposerSearchPath(): string
	{
		return $this->composerSearchPath;
	}
}
