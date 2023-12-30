<?php declare(strict_types=1);

namespace EffectiveActivism\SchemaOrgApi\Resolver;

use EffectiveActivism\SchemaOrgApi\Constant;
use EffectiveActivism\SchemaOrgApi\Helper\MutationHelper;
use EffectiveActivism\SchemaOrgApi\Helper\QueryHelper;
use EffectiveActivism\SchemaOrgApi\Helper\SparQlHelper;
use EffectiveActivism\SchemaOrgApi\Registry\TypeRegistry;
use EffectiveActivism\SparQlClient\Client\SparQlClientInterface;
use EffectiveActivism\SparQlClient\Syntax\Term\TermInterface;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class FieldResolver
{
    protected static bool $hasRun = false;

    protected static array $resultTree = [];

    public function __construct(
        protected MutationHelper $mutationHelper,
        protected QueryHelper $queryHelper,
        protected LoggerInterface $logger,
        protected SparQlClientInterface $sparQlClient,
        protected SparQlHelper $sparQlHelper,
        protected TypeRegistry $typeRegistry
    )
    {
    }

    public function __invoke($objectValue, array $arguments, $context, ResolveInfo $info): Deferred
    {
        $parentTypeName = $info->parentType->name;
        return new Deferred(function () use ($objectValue, $arguments, $parentTypeName, $info, $context): mixed
        {
            if (self::$hasRun === false) {
                switch ($parentTypeName) {
                    case Constant::GRAPHQL_MUTATION:
                        $uniqueIdentifier = Uuid::uuid4()->toString();
                        $this->mutationHelper->condenseMutation($uniqueIdentifier, $arguments, null, $info->fieldName);
                        $condensedConditionals = $this->mutationHelper->getIndexedCondensedConditionals();
                        // Inline mutations are carried out first, in the order they have been discovered, with nested
                        // inline mutations being carried out before their parent mutations.
                        foreach ($this->mutationHelper->getInlineMutationStatements() as $identifier => $statements) {
                            if (isset($condensedConditionals[$identifier])) {
                                foreach ($statements as $statement) {
                                    $statement->where(array_merge($statement->getConditions(), $condensedConditionals[$identifier]));
                                    $this->sparQlClient->execute($statement);
                                }
                            }
                        }
                        // Combine the main mutation query conditionals with the hydrated conditionals.
                        $conditionals = array_merge($condensedConditionals[$uniqueIdentifier], $this->queryHelper->hydrateQuery($info->fieldNodes->getArrayCopy()));
                        $statement = $this->sparQlClient
                            ->select($this->queryHelper->getHydratedVariables())
                            ->where($conditionals);
                        self::$resultTree = $this->buildResultTree($this->sparQlClient->execute($statement));
                        break;

                    case Constant::GRAPHQL_QUERY:
                        $this->queryHelper->condenseQuery($arguments, null, $info->fieldName);
                        $conditionals = array_merge(
                            $this->queryHelper->getCondensedConditionals(),
                            $this->queryHelper->hydrateQuery($info->fieldNodes->getArrayCopy())
                        );
                        $statement = $this->sparQlClient
                            ->select($this->queryHelper->getHydratedVariables())
                            ->where($conditionals);
                        self::$resultTree = $this->buildResultTree($this->sparQlClient->execute($statement));
                        break;
                }
            }
            return $this->resolveField($info);
        });
    }

    // TODO: Clean up?
    protected function buildResultTree(array $sets): array
    {
        $input = [];
        $index = 0;
        foreach ($sets as $set) {
            $input[$index] = [];
            /** @var TermInterface $term */
            foreach ($set as $path => $term) {
                // Sets contain duplicate values which must be filtered out.
                foreach ($input as $key => $value) {
                    foreach ($value as $inputPath => $inputValue) {
                        if (
                            $inputPath === (string) $path &&
                            $inputValue === $term->getRawValue()
                        ) {
                            continue 3;
                        }
                    }
                }
                $input[$index][(string) $path] = $term->getRawValue();
            }
            $index++;
        }
        $resultTree = [];
        foreach ($input as $row) {
            foreach ($row as $path => $value) {
                $resultTree = $this->innerBuildResultTree($resultTree, $path, $value);
            }
        }
        return $resultTree;
    }

    // TODO: Clean up?
    protected function innerBuildResultTree(array $source, string $path, string $value)
    {
        $current =& $source;
        $parts = explode('_', $path);
        $index = 1;
        foreach ($parts as $part) {
            if (
                is_numeric($part) &&
                count($parts) === $index + 1 &&
                preg_match('~^\p{Lu}~u', $parts[$index])
            ) {
                if (!isset($current[$value])) {
                    $current[$value] = [$parts[$index] => []];
                }
                break;
            }
            elseif (
                is_numeric($part) &&
                !in_array($parts[$index], array_values(Constant::SCHEMA_ORG_DATA_TYPES))
            ) {
                $current =& $current[array_key_last($current)];
            }
            elseif (is_numeric($part)) {
                $current[] = [];
                $current =& $current[count($current) - 1];
            }
            elseif (isset($current[$part])) {
                $current =& $current[$part];
            }
            else {
                if (count($parts) === $index) {
                    $current[$part] = $value;
                }
                else {
                    $current[$part] = [];
                    $current =& $current[$part];
                }
            }
            $index++;
        }
        return $source;
    }

    // TODO: Clean up?
    protected function resolveField(ResolveInfo $info): mixed
    {
        $path = $info->path;
        // Do not include GraphQL function name.
        array_shift($path);
        if (empty($path)) {
            return array_values(self::$resultTree);
        }
        $current = array_values(self::$resultTree);
        foreach ($path as $fragment) {
            $current = str_starts_with((string) array_key_first($current), 'urn:uuid:') ? array_values($current) : $current;
            if (is_numeric($fragment)) {
                if (isset($current[$fragment])) {
                    $current = $current[$fragment][array_key_first($current[$fragment])];
                }
                else {
                    return [];
                }
            }
            else {
                if (isset($current[$fragment])) {
                    $current = $current[$fragment];
                }
                else {
                    return [];
                }
            }
        }
        return $current;
    }
}
