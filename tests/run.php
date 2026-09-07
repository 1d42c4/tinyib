<?php

declare(strict_types=1);

use TinyIB\BoardMessage;
use TinyIB\Captcha;
use TinyIB\Config;
use TinyIB\Database;
use TinyIB\Passwords;
use TinyIB\Process;
use TinyIB\Request;
use TinyIB\Role;
use TinyIB\Session;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

final class Checks
{
    public int $passed = 0;

    public function check(bool $condition, string $description): void
    {
        if (!$condition) {
            throw new RuntimeException('FAIL: ' . $description);
        }
        $this->passed++;
        echo 'PASS: ' . $description . PHP_EOL;
    }

    public function rejects(Closure $operation, string $exceptionClass, string $description): void
    {
        try {
            $result = $operation();
        } catch (Throwable $exception) {
            $this->check($exception instanceof $exceptionClass, $description);
            return;
        }
        $this->check(false, $description);
    }
}

$checks = new Checks();
$values = require dirname(__DIR__) . '/settings.default.php';
$values['tripseed'] = bin2hex(random_bytes(32));
$config = new Config(...$values);
$database = new Database($config->with(['dbpath' => ':memory:']));
$table = $database->identifier($config->dbaccounts);
$checks->check($database->count('SELECT COUNT(*) FROM ' . $table) === 0, 'A fresh SQLite schema is initialized');
$password = Passwords::hash('test-only-password');
$id = $database->insert($config->dbaccounts, ['username' => "test' OR 1=1 --", 'password' => $password, 'role' => Role::Moderator->value, 'lastactive' => 0]);
$row = $database->row('SELECT * FROM ' . $table . ' WHERE id = ?', [$id]);
$checks->check($row['id'] === $id && $row['role'] === 3, 'PDO rows retain native integer types');
$checks->check($row['username'] === "test' OR 1=1 --", 'SQL metacharacters are stored as data');
$checks->check($database->row('SELECT * FROM ' . $table . ' WHERE username = ?', ["' OR 1=1 --"]) === [], 'Prepared lookup cannot be changed by injected SQL');
$database->update($config->dbaccounts, $id, ['lastactive' => 123]);
$checks->check($database->row('SELECT * FROM ' . $table . ' WHERE id = ?', [$id])['lastactive'] === 123, 'Prepared updates target the selected row');
$checks->rejects(fn(): string => $database->identifier('accounts; DROP TABLE accounts'), InvalidArgumentException::class, 'Unsafe SQL identifiers are rejected');
$checks->check(Passwords::verify('test-only-password', $row['password']) && !Passwords::verify('wrong', $row['password']), 'Passwords are hashed and verified');
$checks->check(Role::Administrator->canAdminister() && !Role::Moderator->canAdminister(), 'Roles enforce administrator privileges');
$checks->check($config->with(['boardtitle' => 'Test board'])->title === 'Test board', 'Configuration cloning and the computed title work');
$checks->check(Request::integer('123') === 123 && Request::ids('1,2,3') === [1, 2, 3], 'Request identifiers normalize to integers');
foreach (['-1', '1.5', '1e5', '1 OR 1=1', ['1']] as $invalid) {
    $checks->rejects(fn(): int => Request::integer($invalid), BoardMessage::class, 'Malformed identifiers are rejected');
}
$_GET = [];
$_POST = ['name' => ['nested']];
$_FILES = [];
$checks->rejects(Request::capture(...), BoardMessage::class, 'Nested request fields are rejected');
$_POST = ['delete' => ['1', '2']];
$checks->check(Request::capture()->form['delete'] === ['1', '2'], 'Deletion lists remain supported');
$checks->check(strlen(Session::token()) === 64 && Session::token() === Session::token(), 'CSRF tokens are stable within a session');
$checks->check(str_contains(Session::decorate('<form method="post"></form>'), 'name="_csrf"'), 'Dynamic forms receive CSRF tokens');
$_SESSION['tinyibcaptcha'] = 'abc23';
$_SESSION['tinyibcaptcha_time'] = time();
Captcha::verify(' ABC23 ');
$checks->check(!isset($_SESSION['tinyibcaptcha']), 'CAPTCHA normalization consumes the challenge');
$checks->rejects(static function (): void {
    Captcha::verify('abc23');
}, BoardMessage::class, 'A CAPTCHA cannot be replayed');
$_SESSION['tinyibcaptcha'] = 'abc23';
$_SESSION['tinyibcaptcha_time'] = time() - 601;
$checks->rejects(static function (): void {
    Captcha::verify('abc23');
}, BoardMessage::class, 'Expired CAPTCHAs are rejected');
$checks->check(trim(Process::run([PHP_BINARY, '-r', 'echo $argv[1];', 'a & b'])) === 'a & b', 'External program arguments do not use shell interpretation');
$checks->rejects(static fn(): string => Process::run([PHP_BINARY, '-r', 'exit(2);']), BoardMessage::class, 'External program failures are handled');

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS));
$phpFiles = 0;
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    if (!preg_match('/^<\?php\s+declare\(strict_types=1\);/', $source)) {
        throw new RuntimeException('Missing strict types: ' . $file->getPathname());
    }
    $phpFiles++;
}
$checks->check($phpFiles > 15, 'Every PHP file declares strict types');
$methods = 0;
foreach (glob(dirname(__DIR__) . '/app/*.php') ?: [] as $file) {
    $name = 'TinyIB\\' . basename($file, '.php');
    if (!class_exists($name) && !trait_exists($name) && !enum_exists($name)) {
        throw new RuntimeException('Cannot load ' . $name);
    }
    foreach (new ReflectionClass($name)->getMethods() as $method) {
        if (!str_starts_with($method->getDeclaringClass()->getName(), 'TinyIB\\')) {
            continue;
        }
        foreach ($method->getParameters() as $parameter) {
            if (!$parameter->hasType()) {
                throw new RuntimeException('Untyped parameter: ' . $name . '::' . $method->name);
            }
        }
        if (!$method->isConstructor() && !$method->isDestructor() && !$method->hasReturnType()) {
            throw new RuntimeException('Untyped return: ' . $name . '::' . $method->name);
        }
        $methods++;
    }
}
$checks->check($methods > 100, 'All application methods have native parameter and return types');
echo $checks->passed . ' checks passed; ' . $phpFiles . ' PHP files audited.' . PHP_EOL;
