<?php declare(strict_types=1);

namespace EffectiveActivism\SchemaOrgApi\Helper;

use EffectiveActivism\SchemaOrgApi\Constant;
use EffectiveActivism\SchemaOrgApi\Exception\SchemaOrgApiException;
use EffectiveActivism\SparQlClient\Client\SparQlClientInterface;
use EffectiveActivism\SparQlClient\Exception\SparQlException;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Filter;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Operator\Binary\Equal;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Operator\Binary\GreaterThan;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Operator\Binary\LessThan;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Operator\Binary\NotEqual;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Operator\Trinary\Regex;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Operator\Unary\Datatype;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Operator\Unary\IsIri;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Optionally\Optionally;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Triple\Triple;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Triple\TripleInterface;
use EffectiveActivism\SparQlClient\Syntax\Term\Iri\PrefixedIri;
use EffectiveActivism\SparQlClient\Syntax\Term\Variable\Variable;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\SelectionNode;
use Psr\Log\LoggerInterface;

class QueryHelper
{
    /** @var TripleInterface[] */
    protected array $conditionals = [];

    /** @var Variable[] */
    protected array $variables = [];

    public function __construct(
        protected LoggerInterface $logger,
        protected SparQlClientInterface $sparQlClient,
        protected SparQlHelper $sparQlHelper
    )
    {}

    /**
     * Build conditionals to match GraphQl query.
     * @throws SchemaOrgApiException
     */
    public function condenseQuery(array $arguments, Variable $incomingVariable = null, string $incomingPath = ''): void
    {
        try {
            $index = 0;
            ksort($arguments);
            foreach ($arguments as $field => $fieldArguments) {
                if (preg_match('~^\p{Lu}~u', $field)) {
                    $objectVariable = new Variable($incomingPath);
                    $unsafeClass = $this->sparQlHelper->unsafe($field);
                    if (in_array($unsafeClass, array_keys(Constant::SCHEMA_ORG_DATA_TYPES))) {
                        foreach ($fieldArguments as $type => $value) {
                            $scalarTerm = $this->sparQlHelper->resolveScalar($unsafeClass, $value);
                            switch ($type) {
                                case Constant::ARGUMENT_CONTAINS:
                                    $this->conditionals[] = new Filter(new Regex($scalarTerm, $objectVariable));
                                    break;

                                case Constant::ARGUMENT_EQUAL_TO:
                                    $this->conditionals[] = new Filter(new Equal($scalarTerm, $objectVariable));
                                    break;

                                case Constant::ARGUMENT_GREATER_THAN:
                                    $this->conditionals[] = new Filter(new GreaterThan($scalarTerm, $objectVariable));
                                    break;

                                case Constant::ARGUMENT_LESS_THAN:
                                    $this->conditionals[] = new Filter(new LessThan($scalarTerm, $objectVariable));
                                    break;

                                case Constant::ARGUMENT_NOT_EQUAL_TO:
                                    $this->conditionals[] = new Filter(new NotEqual($scalarTerm, $objectVariable));
                                    break;
                            }
                        }
                    }
                    else {
                        $this->conditionals[] = new Triple($objectVariable, new PrefixedIri('rdf', 'type'), new PrefixedIri($this->sparQlHelper->getNamespaceFromLocalPart($unsafeClass), $unsafeClass));
                        $this->condenseQuery($fieldArguments, $objectVariable, $incomingPath);
                    }
                }
                else {
                    // Use two underscores to avoid colliding paths with hydrated query.
                    if (str_contains($incomingPath, '__')) {
                        $path = sprintf('%s__%d', $incomingPath, $index);
                        $incomingVariable = $incomingVariable ?? new Variable($path);
                    }
                    // TODO: Hackish solution, should be cleaned up.
                    // If this is the base entity, prefix index to base entity class name
                    // with just one underscore to connect it to the hydration query.
                    // Also add type for the base entity.
                    else {
                        $path = sprintf('%d_%s', $index, $incomingPath);
                        $incomingVariable = $incomingVariable ?? new Variable($path);
                        $this->conditionals[] = new Triple($incomingVariable, new PrefixedIri('rdf', 'type'), new PrefixedIri($this->sparQlHelper->getNamespaceFromLocalPart($incomingPath), $incomingPath));
                    }
                    $innerPath = sprintf('%s__%s', $path, $field);
                    $objectVariable = new Variable($innerPath);
                    $propertyTerm = new PrefixedIri($this->sparQlHelper->getNamespaceFromLocalPart($field), $field);
                    $this->conditionals[] = new Triple($incomingVariable, $propertyTerm, $objectVariable);
                    $this->condenseQuery($fieldArguments, $objectVariable, $innerPath);
                }
                $index++;
            }
        } catch (SparQlException $exception) {
            throw new SchemaOrgApiException('Failed to extract data', 0, $exception);
        }
    }

    /**
     * Return the conditionals created during condensation.
     */
    public function getCondensedConditionals(): array
    {
        return $this->conditionals;
    }

    /**
     * Return the variables created during hydration.
     */
    public function getHydratedVariables(): array
    {
        // GraphQl queries with multiple instances of the same field are valid.
        // This will result in duplicate variables, which SPARQL doesn't allow.
        // To avoid corrupt SPARQL queries, ensure that the variables are unique.
        return array_unique($this->variables, SORT_REGULAR);
    }

    /**
     * Build conditionals to retrieve results.
     * @throws SchemaOrgApiException
     */
    public function hydrateQuery(array $nodes, Variable $incomingVariable = null, string $incomingPath = ''): array
    {
        $conditionals = [];
        $index = 0;
        /** @var SelectionNode[] $nodes */
        foreach ($nodes as $node) {
            try {
                $path = empty($incomingPath) ? sprintf('%d', $index) : $incomingPath;
                // The base entity will match this condition. Subsequent entities will always be inline fragments, because every property is a union field.
                if ($node instanceof FieldNode) {
                    $field = $node->name->value;
                    $innerPath = sprintf('%s_%s', $path, $field);
                    $incomingVariable = $incomingVariable ?? new Variable($innerPath);
                    // Include this variable to get the base entity urn. This ensures that multiple matches are distinguishable.
                    $this->variables[] = $incomingVariable;
                    if (isset($node->selectionSet)) {
                        foreach ((array) $node->selectionSet->selections as $selection) {
                            $conditionals = array_merge($conditionals, $this->innerHydrateQuery($selection, $incomingVariable, $innerPath));
                        }
                    }
                }
                elseif ($node instanceof InlineFragmentNode) {
                    $incomingVariable = $incomingVariable ?? new Variable($path);
                    if (isset($node->selectionSet)) {
                        foreach ((array) $node->selectionSet->selections as $selection) {
                            $conditionals = array_merge($conditionals, $this->innerHydrateQuery($selection, $incomingVariable, $path));
                        }
                    }
                }
            } catch (SparQlException $exception) {
                throw new SchemaOrgApiException(sprintf('Invalid path %s', $path));
            }
        }
        return $conditionals;
    }

    /**
     * Build conditionals to retrieve results.
     * @throws SchemaOrgApiException
     */
    protected function innerHydrateQuery(array $fieldNodes, Variable $incomingVariable = null, string $incomingPath = ''): array
    {
        $conditionals = [];
        $index = 0;
        /** @var FieldNode[] $fieldNodes */
        foreach ($fieldNodes as $fieldNode) {
            try {
                $path = empty($incomingPath) ? (string) $index : $incomingPath;
                $incomingVariable = $incomingVariable ?? new Variable($path);
                $field = $fieldNode->name->value;
                $innerPath = sprintf('%s_%s', $path, $field);
                if (isset($fieldNode->selectionSet)) {
                    /** @var InlineFragmentNode $inlineFragmentNode */
                    foreach ($fieldNode->selectionSet->selections as $inlineFragmentNode) {
                        $optionalConditionals = [];
                        $type = $inlineFragmentNode->typeCondition->name->value;
                        if (in_array($type, array_values(Constant::SCHEMA_ORG_DATA_TYPES))) {
                            $objectVariable = new Variable($innerPath . sprintf('_%d_%s_value', $index, $type));
                            $this->variables[] = $objectVariable;
                            $optionalConditionals[] = new Triple($incomingVariable, new PrefixedIri($this->sparQlHelper->getNamespaceFromLocalPart($field), $field), $objectVariable);
                            $unsafeType = $this->sparQlHelper->unsafe($type);
                            $filter = match ($unsafeType) {
                                Constant::SCHEMA_ORG_DATA_TYPE_BOOLEAN => new Filter(new Equal(new Datatype($objectVariable), new PrefixedIri('xsd', 'boolean'))),
                                Constant::SCHEMA_ORG_DATA_TYPE_DATE => new Filter(new Equal(new Datatype($objectVariable), new PrefixedIri('xsd', 'date'))),
                                Constant::SCHEMA_ORG_DATA_TYPE_DATETIME => new Filter(new Equal(new Datatype($objectVariable), new PrefixedIri('xsd', 'dateTime'))),
                                Constant::SCHEMA_ORG_DATA_TYPE_FLOAT => new Filter(new Equal(new Datatype($objectVariable), new PrefixedIri('xsd', 'float'))),
                                Constant::SCHEMA_ORG_DATA_TYPE_INTEGER => new Filter(new Equal(new Datatype($objectVariable), new PrefixedIri('xsd', 'integer'))),
                                Constant::SCHEMA_ORG_DATA_TYPE_NUMBER => new Filter(new Equal(new Datatype($objectVariable), new PrefixedIri('xsd', 'decimal'))),
                                Constant::SCHEMA_ORG_DATA_TYPE_TEXT => new Filter(new Equal(new Datatype($objectVariable), new PrefixedIri('xsd', 'string'))),
                                Constant::SCHEMA_ORG_DATA_TYPE_TIME => new Filter(new Equal(new Datatype($objectVariable), new PrefixedIri('xsd', 'time'))),
                                Constant::SCHEMA_ORG_DATA_TYPE_URL => new Filter(new IsIri($objectVariable)),
                                default => throw new SchemaOrgApiException(sprintf('Invalid type %s', $type)),
                            };
                            $optionalConditionals[] = $filter;
                        }
                        else {
                            $objectVariable = new Variable($innerPath . sprintf('_%d_%s', $index, $type));
                            $this->variables[] = $objectVariable;
                            $optionalConditionals[] = new Triple($incomingVariable, new PrefixedIri($this->sparQlHelper->getNamespaceFromLocalPart($field), $field), $objectVariable);
                            $unsafeType = $this->sparQlHelper->unsafe($type);
                            $optionalConditionals[] = new Triple($objectVariable, new PrefixedIri('rdf', 'type'), new PrefixedIri($this->sparQlHelper->getNamespaceFromLocalPart($unsafeType), $unsafeType));
                            $optionalConditionals = array_merge($optionalConditionals, $this->hydrateQuery([$inlineFragmentNode], $objectVariable, $innerPath . sprintf('_%d_%s', $index, $type)));
                        }
                        $conditionals[] = new Optionally($optionalConditionals);
                    }
                }
            } catch (SparQlException $exception) {
                throw new SchemaOrgApiException(sprintf('Invalid path %s', $path));
            }
        }
        return $conditionals;
    }
}
