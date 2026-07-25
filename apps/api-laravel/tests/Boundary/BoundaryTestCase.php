<?php

namespace Tests\Boundary;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class BoundaryTestCase extends TestCase
{
    protected $connectionsToTransact = ['identity', 'mapping', 'health'];

    /** @var array<int, string> */
    private array $boundaryConnections = [];

    protected function tearDown(): void
    {
        foreach ($this->boundaryConnections as $connection) {
            DB::purge($connection);
            config()->offsetUnset("database.connections.{$connection}");
        }

        parent::tearDown();
    }

    protected function boundaryConnection(
        string $name,
        string $databaseConnection,
        string $username,
        string $password,
    ): Connection {
        $this->assertNotSame('', $password, "Password for {$username} is missing from the test environment.");

        $connection = "boundary_{$name}";
        $configuration = config("database.connections.{$databaseConnection}");
        $configuration['username'] = $username;
        $configuration['password'] = $password;

        config()->set("database.connections.{$connection}", $configuration);
        DB::purge($connection);
        $this->boundaryConnections[] = $connection;

        return DB::connection($connection);
    }

    protected function assertCurrentUser(Connection $connection, string $expectedRole): void
    {
        $this->assertSame(
            $expectedRole,
            $connection->scalar('SELECT current_user'),
            'Boundary query did not use the expected runtime role.',
        );
    }

    protected function assertDatabaseOperationDenied(
        callable $operation,
        string $expectedDatabaseMessage,
        string $message,
    ): void {
        try {
            $operation();
        } catch (QueryException $exception) {
            $this->assertStringContainsString($expectedDatabaseMessage, $exception->getMessage(), $message);

            return;
        }

        $this->fail($message);
    }
}
