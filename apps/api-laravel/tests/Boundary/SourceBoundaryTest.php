<?php

namespace Tests\Boundary;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class SourceBoundaryTest extends BoundaryTestCase
{
    public function test_code_outside_mapping_service_cannot_access_mapping_connection_directly(): void
    {
        $violations = [];
        $allowedPaths = [
            app_path('Models/Privacy/SubjectMapping.php'),
            app_path('Services/Privacy/MappingService.php'),
            app_path('Console/Commands/ElyoMigrateFresh.php'),
        ];
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $nodeFinder = new NodeFinder;

        foreach ($this->phpFiles(app_path()) as $file) {
            if (in_array($file->getPathname(), $allowedPaths, true)) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if ($source === false) {
                $this->fail("Could not read {$file->getPathname()}.");
            }

            $ast = $parser->parse($source);
            $mappingReference = $nodeFinder->findFirst(
                $ast ?? [],
                fn (Node $node): bool => $this->isMappingConnectionReference($node),
            );

            if ($mappingReference !== null) {
                $violations[] = "{$file->getPathname()}: accesses the mapping connection directly";
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Code outside MappingService must not access the mapping connection directly:\n".implode("\n", $violations),
        );
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFiles(string $directory): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }

    private function resolvedString(Node $node): ?string
    {
        if ($node instanceof String_) {
            return $node->value;
        }

        if ($node instanceof Node\Expr\BinaryOp\Concat) {
            $left = $this->resolvedString($node->left);
            $right = $this->resolvedString($node->right);

            return $left !== null && $right !== null ? $left.$right : null;
        }

        return null;
    }

    private function isMappingConnectionReference(Node $node): bool
    {
        if ($node instanceof Property) {
            foreach ($node->props as $property) {
                if (
                    $property->name->toString() === 'connection'
                    && $property->default instanceof Node
                    && $this->resolvedString($property->default) === 'mapping'
                ) {
                    return true;
                }
            }
        }

        if ($node instanceof StaticCall || $node instanceof MethodCall) {
            return $node->name instanceof Identifier
                && $node->name->toString() === 'connection'
                && isset($node->args[0])
                && $this->resolvedString($node->args[0]->value) === 'mapping';
        }

        return $node instanceof String_
            && $node->value === 'database.connections.mapping';
    }
}
