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
use EffectiveActivism\SparQlClient\Syntax\Pattern\Triple\Triple;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Triple\TripleInterface;
use EffectiveActivism\SparQlClient\Syntax\Statement\ConditionalStatementInterface;
use EffectiveActivism\SparQlClient\Syntax\Term\Iri\PrefixedIri;
use EffectiveActivism\SparQlClient\Syntax\Term\Variable\Variable;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class MutationHelper
{
    /** @var TripleInterface[][] */
    protected array $conditionals = [];

    /** @var ConditionalStatementInterface[] */
    protected array $inlineMutationStatements = [];

    public function __construct(
        protected LoggerInterface $logger,
        protected SparQlClientInterface $sparQlClient,
        protected SparQlHelper $sparQlHelper
    ) {
    }

    /**
     * Build conditionals to match GraphQl mutation.
     * @throws SchemaOrgApiException
     */
    public function condenseMutation(string $uniqueIdentifier, array $arguments, Variable $incomingVariable = null, string $incomingPath = ''): void
    {
        try {
            $index = 0;
            foreach ($arguments as $field => $fieldArguments) {
                // Check if field is a class/type.
                if (preg_match('~^\p{Lu}~u', $field)) {
                    $objectVariable = new Variable($incomingPath);
                    $unsafeClass = $this->sparQlHelper->unsafe($field);
                    if (in_array($unsafeClass, array_keys(Constant::SCHEMA_ORG_DATA_TYPES))) {
                        foreach ($fieldArguments as $type => $value) {
                            $scalarTerm = $this->sparQlHelper->resolveScalar($unsafeClass, $value);
                            switch ($type) {
                                case Constant::ARGUMENT_CONTAINS:
                                    $this->conditionals[$uniqueIdentifier][] = new Filter(new Regex($scalarTerm, $objectVariable));
                                    break;

                                case Constant::ARGUMENT_EQUAL_TO:
                                    $this->conditionals[$uniqueIdentifier][] = new Filter(new Equal($scalarTerm, $objectVariable));
                                    break;

                                case Constant::ARGUMENT_GREATER_THAN:
                                    $this->conditionals[$uniqueIdentifier][] = new Filter(new GreaterThan($scalarTerm, $objectVariable));
                                    break;

                                case Constant::ARGUMENT_LESS_THAN:
                                    $this->conditionals[$uniqueIdentifier][] = new Filter(new LessThan($scalarTerm, $objectVariable));
                                    break;

                                case Constant::ARGUMENT_NOT_EQUAL_TO:
                                    $this->conditionals[$uniqueIdentifier][] = new Filter(new NotEqual($scalarTerm, $objectVariable));
                                    break;
                            }
                        }
                    }
                    else {
                        $this->conditionals[$uniqueIdentifier][] = new Triple($objectVariable, new PrefixedIri('rdf', 'type'), new PrefixedIri($this->sparQlHelper->getNamespaceFromLocalPart($unsafeClass), $unsafeClass));
                        $this->condenseMutation($uniqueIdentifier, $fieldArguments, $objectVariable, $incomingPath);
                    }
                }
                elseif (in_array($field, Constant::MUTATION_ARGUMENTS)) {
                    // Skip inline mutations for now. They will be dealt with when treating properties
                    // to ensure that the full context of the inline mutation is available.
                    continue;
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
                        $this->conditionals[$uniqueIdentifier][] = new Triple($incomingVariable, new PrefixedIri('rdf', 'type'), new PrefixedIri($this->sparQlHelper->getNamespaceFromLocalPart($incomingPath), $incomingPath));
                    }
                    $innerPath = sprintf('%s__%s', $path, $field);
                    $objectVariable = new Variable($innerPath);
                    $propertyTerm = new PrefixedIri($this->sparQlHelper->getNamespaceFromLocalPart($field), $field);
                    $this->conditionals[$uniqueIdentifier][] = new Triple($incomingVariable, $propertyTerm, $objectVariable);
                    $this->condenseMutation($uniqueIdentifier, $fieldArguments, $objectVariable, $innerPath);
                    // Next, look for inline mutations.
                    $subIndex = 0;
                    foreach ($fieldArguments as $subField => $subArgument) {
                        if (in_array($subField, Constant::MUTATION_ARGUMENTS)) {
                            $this->processInlineMutation($uniqueIdentifier, $subField, $subArgument, $incomingVariable, $propertyTerm, new Triple($incomingVariable, $propertyTerm, $objectVariable));
                        }
                        $subIndex++;
                    }
                }
                $index++;
            }
        } catch (SparQlException $exception) {
            throw new SchemaOrgApiException('Failed to extract data', 0, $exception);
        }
    }

    /**
     * @throws SchemaOrgApiException
     * @throws SparQlException
     */
    protected function processInlineMutation(string $uniqueIdentifier, string $operation, array $input, Variable $incomingVariable, PrefixedIri $incomingPropertyTerm, TripleInterface $tripleInput = null)
    {
        switch ($operation) {
            case Constant::ARGUMENT_DETACH:
                $this->inlineMutationStatements[$uniqueIdentifier][] = $this->sparQlClient
                    ->delete([$tripleInput]);
                break;

            case Constant::ARGUMENT_DETACH_ALL:
                // Replace object of triple with a unique variable to break connection with conditional triples.
                // This ensures that all objects matches, thus detaching all.
                $tripleInput->setObject(new Variable(str_replace('-', '_', Uuid::uuid4()->toString())));
                $this->inlineMutationStatements[$uniqueIdentifier][] = $this->sparQlClient
                    ->delete([$tripleInput])
                    ->where([$tripleInput]);
                break;

            case Constant::ARGUMENT_INSERT:
                // Look for data types.
                foreach ($input as $type => $data) {
                    $unsafeClass = $this->sparQlHelper->unsafe($type);
                    // If a data type is found, build the insertion triple from that.
                    if (in_array($unsafeClass, array_keys(Constant::SCHEMA_ORG_DATA_TYPES))) {
                        foreach ($data as $argument => $value) {
                            $scalarTerm = $this->sparQlHelper->resolveScalar($unsafeClass, $value);
                            switch ($argument) {
                                case Constant::ARGUMENT_VALUE:
                                    $this->inlineMutationStatements[$uniqueIdentifier][] = $this->sparQlClient
                                        ->insert([new Triple($incomingVariable, $incomingPropertyTerm, $scalarTerm)]);
                                    break;
                            }
                        }
                    }
                    // Otherwise, call condenseMutation.
                    else {
                        $inlineVariable = new Variable($unsafeClass);
                        $inlineUniqueIdentifier = Uuid::uuid4()->toString();
                        $this->condenseMutation($inlineUniqueIdentifier, $input, $inlineVariable, $unsafeClass);
                        $this->inlineMutationStatements[$uniqueIdentifier][] = $this->sparQlClient
                            ->insert([new Triple($incomingVariable, $incomingPropertyTerm, $inlineVariable)])
                            ->where($this->conditionals[$inlineUniqueIdentifier]);
                    }
                }
                break;

            case Constant::ARGUMENT_REPLACE:
                // TODO: It may be necessary to collect triples first, and then do a replacement to ensure that
                // TODO: replacements are not done one at a time, thereby overwriting each other.
                // Look for data types.
                foreach ($input as $type => $data) {
                    $unsafeClass = $this->sparQlHelper->unsafe($type);
                    // If a data type is found, build the insertion triple from that.
                    if (in_array($unsafeClass, array_keys(Constant::SCHEMA_ORG_DATA_TYPES))) {
                        foreach ($data as $argument => $value) {
                            $scalarTerm = $this->sparQlHelper->resolveScalar($unsafeClass, $value);
                            switch ($argument) {
                                case Constant::ARGUMENT_VALUE:
                                    $this->inlineMutationStatements[$uniqueIdentifier][] = $this->sparQlClient
                                        ->replace([$tripleInput])
                                        ->with([new Triple($incomingVariable, $incomingPropertyTerm, $scalarTerm)]);
                                    break;
                            }
                        }
                    }
                    // Otherwise, call condenseMutation.
                    else {
                        $inlineVariable = new Variable($unsafeClass);
                        $inlineUniqueIdentifier = Uuid::uuid4()->toString();
                        $this->condenseMutation($inlineUniqueIdentifier, $input, $inlineVariable, $unsafeClass);
                        $this->inlineMutationStatements[$uniqueIdentifier][] = $this->sparQlClient
                            ->replace([$tripleInput])
                            ->with([new Triple($incomingVariable, $incomingPropertyTerm, $inlineVariable)])
                            ->where($this->conditionals[$inlineUniqueIdentifier]);
                    }
                }
                break;

            case Constant::ARGUMENT_REPLACE_ALL:
                // Replace object of triple with a unique variable to break connection with conditional triples.
                // This ensures that all objects matches, thus replacing all.
                $tripleInput->setObject(new Variable(str_replace('-', '_', Uuid::uuid4()->toString())));
                // TODO: It may be necessary to collect triples first, and then do a replacement to ensure that
                // TODO: replacements are not done one at a time, thereby overwriting each other.
                // Look for data types.
                foreach ($input as $type => $data) {
                    $unsafeClass = $this->sparQlHelper->unsafe($type);
                    // If a data type is found, build the insertion triple from that.
                    if (in_array($unsafeClass, array_keys(Constant::SCHEMA_ORG_DATA_TYPES))) {
                        foreach ($data as $argument => $value) {
                            $scalarTerm = $this->sparQlHelper->resolveScalar($unsafeClass, $value);
                            switch ($argument) {
                                case Constant::ARGUMENT_VALUE:
                                    $this->inlineMutationStatements[$uniqueIdentifier][] = $this->sparQlClient
                                        ->replace([$tripleInput])
                                        ->with([new Triple($incomingVariable, $incomingPropertyTerm, $scalarTerm)])
                                        ->where([$tripleInput]);
                                    break;
                            }
                        }
                    }
                    // Otherwise, call condenseMutation.
                    else {
                        $inlineVariable = new Variable($unsafeClass);
                        $inlineUniqueIdentifier = Uuid::uuid4()->toString();
                        $this->condenseMutation($inlineUniqueIdentifier, $input, $inlineVariable, $unsafeClass);
                        $this->inlineMutationStatements[$uniqueIdentifier][] = $this->sparQlClient
                            ->replace([$tripleInput])
                            ->with([new Triple($incomingVariable, $incomingPropertyTerm, $inlineVariable)])
                            ->where(array_merge([$tripleInput], $this->conditionals[$inlineUniqueIdentifier]));
                    }
                }
                break;
        }
    }

    public function getIndexedCondensedConditionals(): array
    {
        return $this->conditionals;
    }

    public function getInlineMutationStatements(): array
    {
        return $this->inlineMutationStatements;
    }
}
