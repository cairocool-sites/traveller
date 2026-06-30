<?php

use Symfony\Component\Process\Process;

it('keeps all migration files syntactically valid', function (): void {
    foreach (glob(database_path('migrations/*.php')) as $migration) {
        $process = new Process([PHP_BINARY, '-l', $migration]);
        $process->run();

        expect($process->isSuccessful())
            ->toBeTrue($migration.PHP_EOL.$process->getErrorOutput().$process->getOutput());
    }
});

it('keeps generated migration index names within mysql identifier limits', function (): void {
    foreach (glob(database_path('migrations/*.php')) as $migration) {
        $contents = file_get_contents($migration);
        $tables = migrationTableBlocks($contents);

        foreach ($tables as $table => $body) {
            foreach (generatedIndexNames($table, $body) as $line => $name) {
                expect(strlen($name))
                    ->toBeLessThanOrEqual(64, basename($migration).':'.$line.' generated '.$name);
            }

            foreach (explicitIdentifierNames($body) as $line => $name) {
                expect(strlen($name))
                    ->toBeLessThanOrEqual(64, basename($migration).':'.$line.' explicit '.$name);
            }
        }
    }
});

it('keeps mysql strict mode timestamp columns safe in migrations', function (): void {
    foreach (glob(database_path('migrations/*.php')) as $migration) {
        foreach (file($migration) as $lineNumber => $line) {
            if (! str_contains($line, "->timestamp('")) {
                continue;
            }

            $safe = str_contains($line, '->nullable()')
                || str_contains($line, '->useCurrent()')
                || str_contains($line, '->useCurrentOnUpdate()');

            expect($safe)->toBeTrue(basename($migration).':'.($lineNumber + 1).' '.$line);
        }
    }
});

it('keeps env example staging safe and free of duplicate keys', function (): void {
    $keys = [];

    foreach (file(base_path('.env.example')) as $lineNumber => $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key] = explode('=', $line, 2);

        expect($keys)->not->toHaveKey($key, 'Duplicate .env.example key '.$key.' on line '.($lineNumber + 1));
        $keys[$key] = true;
    }

    $env = parse_ini_file(base_path('.env.example'), false, INI_SCANNER_RAW);

    expect($env['APP_ENV'])->toBe('staging')
        ->and($env['APP_DEBUG'])->toBe('false')
        ->and($env['APP_URL'])->toBe('https://travel.cairocool.com')
        ->and($env['SESSION_DRIVER'])->toBe('file')
        ->and($env['SESSION_ENCRYPT'])->toBe('true')
        ->and($env['SESSION_SECURE_COOKIE'])->toBe('true')
        ->and($env['CACHE_STORE'])->toBe('file')
        ->and($env['QUEUE_CONNECTION'])->toBe('database')
        ->and($env['HBX_ENABLED'])->toBe('false')
        ->and($env['HBX_INTEGRATION_TESTS'])->toBe('false')
        ->and($env['HBX_SANDBOX_BOOKING_ENABLED'])->toBe('false')
        ->and($env['HBX_PRODUCTION_ENABLED'])->toBe('false')
        ->and($env['TRAVEL_PAYMENT_LIVE_ENABLED'])->toBe('false')
        ->and($env['TRAVEL_ACTUAL_SUPPLIER_CANCELLATION_ENABLED'])->toBe('false');
});

it('keeps deployment script safe for staging', function (): void {
    $script = file_get_contents(base_path('scripts/deploy-staging.sh'));

    expect($script)->toContain('/usr/local/php85/bin/php')
        ->and($script)->toContain('8.4.1')
        ->and($script)->toContain('install --no-dev --optimize-autoloader')
        ->and($script)->toContain('artisan migrate --force')
        ->and($script)->not->toContain('migrate:fresh')
        ->and($script)->toContain("grep -q '^HBX_SANDBOX_BOOKING_ENABLED=true'")
        ->and($script)->toContain("grep -q '^HBX_PRODUCTION_ENABLED=true'");
});

function migrationTableBlocks(string $contents): array
{
    preg_match_all('/Schema::(?:create|table)\(\'([^\']+)\'\s*,\s*function \(Blueprint \$table\): void \{(.*?)\n\s*\}\);/s', $contents, $matches, PREG_SET_ORDER);

    $tables = [];

    foreach ($matches as $match) {
        $tables[$match[1]] = $match[2];
    }

    return $tables;
}

function generatedIndexNames(string $table, string $body): array
{
    $names = [];
    $lines = explode("\n", $body);

    foreach ($lines as $index => $line) {
        foreach (['index', 'unique'] as $type) {
            if (preg_match('/\$table->'.$type.'\(\[([^\]]+)\]\s*\)/', $line, $match)) {
                $columns = migrationColumns($match[1]);
                $names[$index + 1] = $table.'_'.implode('_', $columns).'_'.$type;
            }

            if (preg_match('/\$table->\w+\(\'([^\']+)\'[^)]*\)->'.$type.'\(\)/', $line, $match)) {
                $names[$index + 1] = $table.'_'.$match[1].'_'.$type;
            }
        }

        if (preg_match('/\$table->foreignId\(\'([^\']+)\'\).*->constrained\(/', $line, $match)) {
            $names[$index + 1] = $table.'_'.$match[1].'_foreign';
        }
    }

    return $names;
}

function explicitIdentifierNames(string $body): array
{
    $names = [];
    $lines = explode("\n", $body);

    foreach ($lines as $index => $line) {
        if (preg_match('/\$table->(?:index|unique)\([^;]+,\s*\'([^\']+)\'/', $line, $match)) {
            $names[$index + 1] = $match[1];
        }
    }

    return $names;
}

function migrationColumns(string $columns): array
{
    return array_map(
        fn (string $column): string => trim($column, " \t\n\r\0\x0B'\""),
        explode(',', $columns),
    );
}
