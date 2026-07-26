<?php

namespace Tests\Boundary;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;

class SourceBoundaryTest extends BoundaryTestCase
{
    /**
     * ADR-001 §2.4 / ADR-003 D7: company and admin HTTP paths cannot read the
     * health domain. Runtime grants are the final barrier; this source check
     * keeps forbidden dependencies from entering those namespaces at all.
     */
    public function test_company_and_admin_http_paths_have_no_health_read_dependency(): void
    {
        $violations = [];
        $directories = [
            app_path('Http/Controllers/Company'),
            app_path('Http/Controllers/Admin'),
            app_path('Http/Resources/Company'),
            app_path('Http/Resources/Admin'),
            app_path('Services/Company'),
        ];
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $nodeFinder = new NodeFinder;
        $applicationFiles = $this->applicationClassFiles();
        $dependencyGraph = $this->applicationDependencyGraph(
            $applicationFiles,
            $parser,
            $nodeFinder,
        );

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach ($this->phpFiles($directory) as $file) {
                $dependencyPath = $this->healthReadDependencyPath(
                    $file->getPathname(),
                    $dependencyGraph,
                );

                if ($dependencyPath !== null) {
                    $relativePath = array_map(
                        fn (string $path): string => str_replace(base_path().DIRECTORY_SEPARATOR, '', $path),
                        $dependencyPath,
                    );
                    $violations[] = implode(' -> ', $relativePath);
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Company/Admin HTTP paths must not read the health domain:\n".implode("\n", $violations),
        );
    }

    /**
     * @param  array<string, array{dependencies: list<string>, readsHealth: bool}>  $dependencyGraph
     * @param  array<string, true>  $visited
     * @return list<string>|null
     */
    private function healthReadDependencyPath(
        string $filePath,
        array $dependencyGraph,
        array $visited = [],
    ): ?array {
        if (isset($visited[$filePath])) {
            return null;
        }

        $visited[$filePath] = true;
        $dependency = $dependencyGraph[$filePath] ?? null;

        if ($dependency === null) {
            return null;
        }

        if ($dependency['readsHealth']) {
            return [$filePath];
        }

        foreach ($dependency['dependencies'] as $dependencyFile) {
            $healthDependencyPath = $this->healthReadDependencyPath(
                $dependencyFile,
                $dependencyGraph,
                $visited,
            );

            if ($healthDependencyPath !== null) {
                return [$filePath, ...$healthDependencyPath];
            }
        }

        return null;
    }

    /**
     * @param  array<class-string, string>  $applicationFiles
     * @return array<string, array{dependencies: list<string>, readsHealth: bool}>
     */
    private function applicationDependencyGraph(
        array $applicationFiles,
        Parser $parser,
        NodeFinder $nodeFinder,
    ): array {
        $graph = [];

        foreach ($applicationFiles as $filePath) {
            $source = file_get_contents($filePath);

            if ($source === false) {
                $this->fail("Could not read {$filePath}.");
            }

            $ast = $parser->parse($source) ?? [];
            $traverser = new NodeTraverser;
            $traverser->addVisitor(new NameResolver);
            $ast = $traverser->traverse($ast);
            $dependencies = [];

            /** @var list<Node\Name> $names */
            $names = $nodeFinder->findInstanceOf($ast, Node\Name::class);

            foreach ($names as $name) {
                $class = ltrim($name->toString(), '\\');

                if (isset($applicationFiles[$class]) && $applicationFiles[$class] !== $filePath) {
                    $dependencies[] = $applicationFiles[$class];
                }
            }

            $graph[$filePath] = [
                'dependencies' => array_values(array_unique($dependencies)),
                'readsHealth' => $nodeFinder->findFirst(
                    $ast,
                    fn (Node $node): bool => $this->isCompanyHealthReadReference($node),
                ) !== null,
            ];
        }

        return $graph;
    }

    /**
     * @return array<class-string, string>
     */
    private function applicationClassFiles(): array
    {
        $files = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            $relative = str_replace([app_path().DIRECTORY_SEPARATOR, '.php'], '', $file->getPathname());
            $class = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
            $files[$class] = $file->getPathname();
        }

        return $files;
    }

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
     * ADR-003 D3/D8: no identity model may relate to a health model. Reflection
     * rather than grep, so a relation added under any name is caught.
     */
    public function test_identity_models_have_no_relation_to_health_domain_models(): void
    {
        $violations = [];

        foreach ($this->identityModelClasses() as $class) {
            $model = new $class;

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $returnType = $method->getReturnType();

                if ($method->getNumberOfParameters() > 0 || ! $returnType instanceof ReflectionNamedType) {
                    continue;
                }

                if ($returnType->isBuiltin() || ! is_a($returnType->getName(), Relation::class, true)) {
                    continue;
                }

                $relatedClass = $method->invoke($model)->getRelated()::class;

                if (str_starts_with($relatedClass, 'App\\Models\\Health\\')) {
                    $violations[] = "{$class}::{$method->getName()}() relates to {$relatedClass}";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Identity models must not relate to health models:\n".implode("\n", $violations),
        );
    }

    /**
     * @return iterable<class-string<Model>>
     */
    private function identityModelClasses(): iterable
    {
        foreach ($this->phpFiles(app_path('Models')) as $file) {
            $relative = str_replace([app_path('Models').DIRECTORY_SEPARATOR, '.php'], '', $file->getPathname());
            $class = 'App\\Models\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if (str_starts_with($class, 'App\\Models\\Health\\') || str_starts_with($class, 'App\\Models\\Privacy\\')) {
                continue;
            }

            if (is_subclass_of($class, Model::class)) {
                yield $class;
            }
        }
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

    private function isCompanyHealthReadReference(Node $node): bool
    {
        if ($node instanceof Node\Name) {
            $name = ltrim($node->toString(), '\\');

            return str_starts_with($name, 'App\\Models\\Health\\')
                || str_starts_with($name, 'App\\Services\\Health\\');
        }

        if ($node instanceof StaticCall || $node instanceof MethodCall) {
            return $node->name instanceof Identifier
                && $node->name->toString() === 'connection'
                && isset($node->args[0])
                && $this->resolvedString($node->args[0]->value) === 'health';
        }

        return $node instanceof String_
            && $node->value === 'database.connections.health';
    }
}
