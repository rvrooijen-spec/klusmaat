<?php
declare(strict_types=1);

// Testrunner zonder externe pakketten. Gebruik: php tests/run.php [filter]
error_reporting(E_ALL);
set_error_handler(function (int $nr, string $msg, string $file, int $line): bool {
    throw new \ErrorException($msg, 0, $nr, $file, $line);
});

spl_autoload_register(function (string $klasse): void {
    $kaarten = [
        'Klusmaat\\Core\\Tests\\' => __DIR__ . '/',
        'Klusmaat\\Core\\' => __DIR__ . '/../plugins/klusmaat-core/src/',
    ];
    foreach ($kaarten as $voorvoegsel => $map) {
        if (str_starts_with($klasse, $voorvoegsel)) {
            $pad = $map . str_replace('\\', '/', substr($klasse, strlen($voorvoegsel))) . '.php';
            if (is_file($pad)) {
                require $pad;
            }
            return;
        }
    }
});

$filter = $argv[1] ?? '';
$totaal = 0;
$gefaald = 0;
$asserties = 0;

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $bestand) {
    $klasse = 'Klusmaat\\Core\\Tests\\' . basename($bestand, '.php');
    foreach (get_class_methods($klasse) as $methode) {
        if (!str_starts_with($methode, 'test') || ($filter !== '' && !str_contains($klasse . '::' . $methode, $filter))) {
            continue;
        }
        $totaal++;
        $test = new $klasse();
        $methodeRun = new \ReflectionMethod($test, 'run');
        try {
            $methodeRun->invoke($test, $methode);
            echo "  ok    " . basename($bestand, '.php') . "::$methode\n";
        } catch (\Throwable $fout) {
            $gefaald++;
            echo "  FOUT  " . basename($bestand, '.php') . "::$methode\n        " . get_class($fout) . ': ' . $fout->getMessage() . "\n        " . basename($fout->getFile()) . ':' . $fout->getLine() . "\n";
        }
        $asserties += $test->asserties;
    }
}

echo "\n$totaal tests, $asserties controles, $gefaald mislukt.\n";
exit($gefaald === 0 ? 0 : 1);
