<?php

class System {
	public function __construct(string $dir) {
		$this->setDir($dir);
	}

	protected string $dir;

	protected array $options = [];

	public function setDir(string $dir): self {
		if (!is_dir($dir)) {
			throw new \InvalidArgumentException("$dir is not a directory");
		}
		$this->dir = $dir;
		if (\is_file($this->dir . DIRECTORY_SEPARATOR . 'ske.ini')) {
			$options = parse_ini_file($this->dir . DIRECTORY_SEPARATOR . 'ske.ini', true, INI_SCANNER_TYPED);
			foreach ($options as $key => $value) {
				if (\is_array($value)) {
					foreach ($value as $k => $v) {
						$k = strtoupper("{$key}_{$k}");
						$this->setEnv($k, $v);
					}
				} else {
					$key = strtotupper($key);
					$this->setEnv($key, $value);
				}
			}
		}
		return $this;
	}

	public function getDir(): string {
		return $this->dir;
	}

	public function getEnv(?string $name = null, mixed $default = null): mixed {
		if (!isset($name)) {
			return $_SERVER + $_ENV + $this->options;
		}
		return $this->options[$name] ?? getenv($name) ?: $_ENV[$name] ?? $_SERVER[$name] ?? $default;
	}

	public function setEnv(string $name, mixed $value): self {
		$this->options[$name] = $_ENV[$name] = $_SERVER[$name] = $value;
		if (is_scalar($value)) {
			$value = (string) $value;
			putenv("$name=$value");
		}
		return $this;
	}

	protected array $modules = [];

	public function getModule(string $name): Module {
		if (!isset($this->modules[$name])) {
			$this->addModule($name);
		}
		return $this->modules[$name];
	}

	public function addModule(string $name): self {
		$this->modules[$name] = new Module($this->getDir() . DIRECTORY_SEPARATOR . $name);
		return $this;
	}

	public function getModules(): array {
		return $this->modules;
	}

	public function runNewApp(string $name, string $mode = 'dev', string $directory = __DIR__, string $namespace = 'App', string $extension = 'php'): self {
		return $this->runApp($this->newApp($name, $mode, $directory, $namespace, $extension));
	}

	public function runApp(App $app): self {
		$app->run();
		return $this;
	}

	public function newApp(string $name, string $mode = 'dev', string $directory = __DIR__, string $namespace = 'App', string $extension = 'php'): App {
		return new App($this, $name, $mode, $directory, $namespace, $extension);
	}
}

class Module {
	public function __construct(string $path) {
		$this->setPath($path);
	}

	protected string $path;

	public function setPath(string $path): self {
		if (is_dir($path)) {
			throw new \InvalidArgumentException("$path is a directory");
		}
		$this->path = $path;
		return $this;
	}

	public function getPath(): string {
		return $this->path;
	}

	public function getRealPath(): string {
		return realpath($this->getPath());
	}

	public function getName(): string {
		return basename($this->getPath());
	}

	public function getDir(): string {
		return dirname($this->getPath());
	}

	public function getPackage(): Package {
		return new Package($this->getDir());
	}

	public function exists(): bool {
		return \is_file($this->getPath());
	}

	public function create(): self {
		if ($this->exists()) {
			throw new \RuntimeException("Module {$this->getName()} already exists");
		}
		touch($this->getPath());
	}

	public function remove(): self {
		if (!$this->exists()) {
			throw new \RuntimeException("Module does not exist");
		}
		unlink($this->getPath());
	}

	protected bool $once = false;

	public function once(): self {
		$this->once = true;
		return $this;
	}

	public function isOnce(): bool {
		return $this->once;
	}

	protected bool $required = false;

	public function required(): self {
		$this->required = true;
		return $this;
	}

	public function isRequired(): bool {
		return $this->required;
	}

	protected array $inputs = [];

	public function with(string|array $input, mixed $value = null): self {
		return is_array($input) ? $this->setInputs($input, $value) : $this->setInput($input, $value);
	}

	public function setInput(string $input, mixed $value): self {
		$this->inputs[$input] = $value;
		return $this;
	}

	public function setInputs(array $inputs, mixed $value = null): self {
		foreach ($inputs as $i => $v) {
			isset($value) ? $this->setInput($v, $value) : $this->setInput($i, $v);
		}
		return $this;
	}

	public function getInput(string $input): mixed {
		return $this->inputs[$input];
	}

	public function getInputs(): array {
		return $this->inputs;
	}

	protected bool $imported = false;

	public function import(): self {
		if ($this->isOnce() && $this->isImported()) {
			throw new \RuntimeException("Module {$this->getName()} already imported");
		}

		if (!$this->exists()) {
			if ($this->isRequired()) {
				throw new \RuntimeException("Module {$this->getName()} is required but does not exist");
			}
		}
		$this->imported = true;
		$module = $this;
		extract($this->getInputs());
		$this->setOutput('default', $this->isOnce() ? ($this->isRequired() ? require_once $this->getPath() : include_once $this->getPath()) : ($this->isRequired() ? require $this->getPath() : include $this->getPath()));
		$this->imported = false;
		return $this;
	}

	public function isImported(): bool {
		return $this->imported;
	}

	protected array $outputs = [];

	public function export(string|array $output, mixed $value = null): self {
		return is_array($output) ? $this->setOutputs($output, $value) : $this->setOutput($output, $value);
	}

	public function setOutput(string $output, mixed $value): self {
		if (!$this->isImported()) {
			throw new \RuntimeException("Module {$this->getName()} is not imported");
		}
		$this->outputs[$output] = $value;
		return $this;
	}

	public function setOutputs(array $outputs, mixed $value = null): self {
		foreach ($outputs as $i => $v) {
			isset($value) ? $this->setOutput($v, $value) : $this->setOutput($i, $v);
		}
		return $this;
	}

	public function getOutput(string $output): mixed {
		return $this->outputs[$output];
	}

	public function getOutputs(): array {
		return $this->outputs;
	}

	public function into(&$var, &...$vars): self {
		if (empty($this->getOutputs())) {
			throw new \RuntimeException("Module {$this->getName()} has no outputs");
		}

		$vars = [&$var, ...$vars];
		$outputs = $this->getOutputs();
		foreach ($vars as &$v) {
			$var = array_shift($outputs);
		}

		return $this;
	}

	public function getContents(): string {
		return file_get_contents($this->getPath());
	}

	public function setContents(string $contents, int $flags = LOCK_EX): self {
		file_put_contents($this->getPath(), $contents, $flags);
		return $this;
	}

	public function appendContents(string $contents, int $flags = LOCK_EX): self {
		return $this->setContents($this->getContents() . $contents, $flags | FILE_APPEND);
	}

	public function prependContents(string $contents, int $flags = LOCK_EX): self {
		return $this->setContents($contents . $this->getContents(), $flags | FILE_APPEND);
	}

	public function getLines(): array {
		return file($this->getPath());
	}

	public function setLines(array $lines, int $flags = LOCK_EX): self {
		return $this->setContents(implode(PHP_EOL, $lines), $flags);
	}
}

class Package {
	public function __construct(string $path) {
		$this->setPath($path);
	}

	protected string $path;

	public function getPath(): string {
		return $this->path;
	}

	public function getRealPath(): string {
		return realpath($this->getPath());
	}

	public function setPath(string $path): self {
		if (\is_file($path)) {
			throw new \RuntimeException("Package path must be a directory");
		}
		$this->path = $path;
		return $this;
	}

	public function getName(): string {
		return basename($this->getPath());
	}

	public function getModules(): array {
		$modules = [];
		foreach (glob($this->getPath() . '/*.php') as $path) {
			$modules[] = new Module($path);
		}
		return $modules;
	}

	public function getModule(string $name): Module {
		$modules = $this->getModules();
		foreach ($modules as $module) {
			if ($module->getName() === $name) {
				return $module;
			}
		}
		throw new \RuntimeException("Module {$name} does not exist");
	}

	public function getModuleByPath(string $path): Module {
		$modules = $this->getModules();
		foreach ($modules as $module) {
			if ($module->getPath() === $path) {
				return $module;
			}
		}
		throw new \RuntimeException("Module {$path} does not exist");
	}
}

class App {
	public function __construct(protected System $system, string $name, string $mode = 'dev', string $directory = __DIR__, string $namespace = 'App', string $extension = 'php') {
		$this->setName($name);
		$this->setMode($mode);
		$this->setDirectory($directory);
		$this->setNamespace($namespace);
		$this->setExtension($extension);
	}

	protected string $name;

	public function getName(): string {
		return $this->name;
	}

	public function setName(string $name): self {
		$this->name = $name;
		return $this;
	}

	protected string $mode;

	public function getMode(): string {
		return $this->mode;
	}

	public function setMode(string $mode): self {
		$this->mode = $mode;
		return $this;
	}

	protected string $directory;

	public function getDirectory(): string {
		return $this->directory;
	}

	public function setDirectory(string $directory): self {
		$this->directory = $directory;
		return $this;
	}

	protected string $namespace;

	public function getNamespace(): string {
		return $this->namespace;
	}

	public function setNamespace(string $namespace): self {
		$this->namespace = $namespace;
		return $this;
	}

	protected string $extension;

	public function getExtension(): string {
		return $this->extension;
	}

	public function setExtension(string $extension): self {
		$this->extension = $extension;
		return $this;
	}

	public function run(): self {
		$sys = $this->system;
		if ('cli' === PHP_SAPI || 'phpdbg' === PHP_SAPI) {
			$argv = $sys->getEnv('argv', []);
			$argc = $sys->getEnv('argc', 0);
			if ($argc !== count($argv) || !$argc) {
				return self;
			}
			else {
				$name = array_shift($argv);
				--$argc;
				if (realpath($name) !== $this->getName() && basename($name) !== basename($this->getName())) {
					throw new \RuntimeException("Cannot run {$this->getName()} from $name");
				}

				if ($argc) {
					$sys->setDir($this->getDirectory());
					$module = $sys->getModule("$argv[0].{$this->getExtension()}")->once()->with('sys', $sys)->required()->import()->into($result);
				}
			}
		}
		else {
			$name = $sys->getEnv('SERVER_NAME', 'localhost');
			if (!preg_match("/^(?P<base>.*)$name$/i", $sys->getEnv('HTTP_HOST'), $matches)) {
				throw new \RuntimeException("Cannot access to $name from {$sys->getEnv('HTTP_HOST')}");
			}
			$path = parse_url($sys->getEnv('REQUEST_URI'), PHP_URL_PATH);
			$base = str_replace('.', '/', $matches['base'] ?? '');
			$path = "$base/$path";
			$path = preg_replace('/\/+/', '/', $path);
			$path = trim($path, '/');
			$path = str_replace('/', DIRECTORY_SEPARATOR, $path);

			if (empty($path)) {
				return $this;
			}

			$path .= ".{$this->getExtension()}";
			$sys->setDir($this->getDirectory());
			$sys->getModule($path)->with('sys', $sys)->required()->once()->import()->into($response);
		}
		return $this;
	}
}

function ske_init(string $dir): System {
	static $systems = [];
	if (!isset($systems[$dir])) {
		$systems[$dir] = new System($dir);
	}
	return $systems[$dir];
}
