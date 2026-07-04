<?php

namespace App\Services\Insights\Demo;

use Illuminate\Support\Facades\File;
use RuntimeException;

class DemoDataRepository
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * Load a demo module dataset from database/demo/{module}.json.
     *
     * @return array<string, mixed>
     */
    public function load(string $module): array
    {
        if (! isset(self::$cache[$module])) {
            $path = database_path("demo/{$module}.json");

            if (! File::exists($path)) {
                throw new RuntimeException("Demo dataset missing: {$path}");
            }

            $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            self::$cache[$module] = $decoded;
        }

        return self::$cache[$module];
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
