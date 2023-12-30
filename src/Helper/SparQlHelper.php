<?php declare(strict_types=1);

namespace EffectiveActivism\SchemaOrgApi\Helper;

use EffectiveActivism\SchemaOrgApi\Constant;
use EffectiveActivism\SchemaOrgApi\Exception\SchemaOrgApiException;
use DateTime;
use EffectiveActivism\SparQlClient\Client\SparQlClientInterface;
use EffectiveActivism\SparQlClient\Exception\SparQlException;
use EffectiveActivism\SparQlClient\Syntax\Order\Asc;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Filter;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\FilterNotExists;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Operator\Binary\Equal;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Constraint\Operator\Unary\Str;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Optionally\Optionally;
use EffectiveActivism\SparQlClient\Syntax\Pattern\Triple\Triple;
use EffectiveActivism\SparQlClient\Syntax\Statement\SelectStatementInterface;
use EffectiveActivism\SparQlClient\Syntax\Term\Iri\Iri;
use EffectiveActivism\SparQlClient\Syntax\Term\Iri\PrefixedIri;
use EffectiveActivism\SparQlClient\Syntax\Term\Literal\PlainLiteral;
use EffectiveActivism\SparQlClient\Syntax\Term\Literal\TypedLiteral;
use EffectiveActivism\SparQlClient\Syntax\Term\Path\InversePath;
use EffectiveActivism\SparQlClient\Syntax\Term\Path\ZeroOrMorePath;
use EffectiveActivism\SparQlClient\Syntax\Term\TermInterface;
use EffectiveActivism\SparQlClient\Syntax\Term\Variable\Variable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class SparQlHelper
{
    /** @var string[] */
    protected array $namespaces = [];

    public function __construct(
        protected CacheInterface $cache,
        protected LoggerInterface $logger,
        protected SparQlClientInterface $sparQlClient
    )
    {
        $this->namespaces = [
            'schema' => 'https://schema.org/',
        ];
    }

    /**
     * @throws SchemaOrgApiException
     */
    public function getClass(string $className): array
    {
        $class = [];
        try {
            $classTerm = new PrefixedIri($this->getNamespaceFromLocalPart($className), $className);
            $enumeratorClassVariable = new Variable('enumeratorClass');
            $commentVariable = new Variable('comment');
            $statement = $this->sparQlClient
                ->select([$commentVariable])
                ->where([
                    new Triple($classTerm, new PrefixedIri('rdf', 'type'), new PrefixedIri('rdfs', 'Class')),
                    // Do not include pending classes.
                    new FilterNotExists([
                        new Triple($classTerm, new PrefixedIri('schema', 'isPartOf'), new Iri(Constant::PENDING_ADDRESS)),
                    ]),
                    // Do not include enumerated values.
                    new FilterNotExists([
                        new Triple($classTerm, new PrefixedIri('rdf', 'type'), $enumeratorClassVariable),
                        new Triple($enumeratorClassVariable, new ZeroOrMorePath(new PrefixedIri('rdfs', 'subClassOf')), new PrefixedIri('schema', 'Enumeration'))
                    ]),
                    // Include comments where available.
                    new Optionally([
                        new Triple($classTerm, new PrefixedIri('rdfs', 'comment'), $commentVariable),
                    ]),
                ]);
            $setsClasses = $this->sparQlClient->execute($statement);
            if ($setsClasses !== false) {
                foreach ($setsClasses as $setClass) {
                    /** @var PlainLiteral $commentTerm */
                    $commentTerm = $setClass[$commentVariable->getVariableName()];
                    $class[$className] = $this->sanitizeComment($commentTerm);
                }
            }
        } catch (SparQlException $exception) {
            throw new SchemaOrgApiException('Failed to generate class', 0, $exception);
        }
        return $class;
    }

    /**
     * @throws SchemaOrgApiException
     */
    public function getClasses(): array
    {
        $classes = [];
        try {
            $classVariable = new Variable('class');
            $enumeratorClassVariable = new Variable('enumeratorClass');
            $commentVariable = new Variable('comment');
            $statement = $this->sparQlClient
                ->select([$classVariable, $commentVariable])
                ->orderBy([new Asc($classVariable)])
                ->where([
                    new Triple($classVariable, new PrefixedIri('rdf', 'type'), new PrefixedIri('rdfs', 'Class')),
                    // Do not include pending classes.
                    new FilterNotExists([
                        new Triple($classVariable, new PrefixedIri('schema', 'isPartOf'), new Iri(Constant::PENDING_ADDRESS)),
                    ]),
                    // Do not include enumerated values.
                    new FilterNotExists([
                        new Triple($classVariable, new PrefixedIri('rdf', 'type'), $enumeratorClassVariable),
                        new Triple($enumeratorClassVariable, new ZeroOrMorePath(new PrefixedIri('rdfs', 'subClassOf')), new PrefixedIri('schema', 'Enumeration'))
                    ]),
                    // Include comments where available.
                    new Optionally([
                        new Triple($classVariable, new PrefixedIri('rdfs', 'comment'), $commentVariable),
                    ]),
                ]);
            $setsClasses = $this->sparQlClient->execute($statement);
            if ($setsClasses !== false) {
                foreach ($setsClasses as $setClass) {
                    /** @var Iri $classTerm */
                    $classTerm = $setClass[$classVariable->getVariableName()];
                    /** @var PlainLiteral $commentTerm */
                    $commentTerm = $setClass[$commentVariable->getVariableName()];
                    $className = $this->getLocalPart($classTerm);
                    $classes[$className] = $this->sanitizeComment($commentTerm);
                }
            }
        } catch (SparQlException $exception) {
            throw new SchemaOrgApiException('Failed to generate classes', 0, $exception);
        }
        return $classes;
    }

    /**
     * @throws SchemaOrgApiException
     */
    public function getProperties(string $className): array
    {
        $properties = [];
        try {
            $propertyVariable = new Variable('property');
            $classesVariable = new Variable('classes');
            $commentVariable = new Variable('comment');
            $statement = $this->sparQlClient
                ->select([$propertyVariable, $commentVariable])
                ->where(
                    [
                        // Get this class and parent classes.
                        new Triple(
                            new PrefixedIri($this->getNamespaceFromLocalPart($className), $className),
                            new ZeroOrMorePath(new PrefixedIri('rdfs', 'subClassOf')),
                            $classesVariable
                        ),
                        // Get properties from each class in hierarchy.
                        new Triple(
                            $classesVariable,
                            new InversePath(new PrefixedIri('schema', 'domainIncludes')),
                            $propertyVariable
                        ),
                        // Include comments where available.
                        new Optionally(
                            [new Triple($propertyVariable, new PrefixedIri('rdfs', 'comment'), $commentVariable)]
                        ),
                        // Do not include pending properties.
                        new FilterNotExists(
                            [
                                new Triple(
                                    $propertyVariable,
                                    new PrefixedIri('schema', 'isPartOf'),
                                    new Iri(Constant::PENDING_ADDRESS)
                                ),
                            ]
                        ),
                    ]
                );
            $sets = $this->sparQlClient->execute($statement);
            if ($sets !== false) {
                foreach ($sets as $set) {
                    /** @var Iri $propertyTerm */
                    $propertyTerm = $set[$propertyVariable->getVariableName()];
                    /** @var PlainLiteral $commentTerm */
                    $commentTerm = $set[$commentVariable->getVariableName()];
                    $properties[$this->getLocalPart($propertyTerm)] = $this->sanitizeComment($commentTerm);
                }
            }
        } catch (SparQlException $exception) {
            throw new SchemaOrgApiException('Failed to generate classes', 0, $exception);
        }
        return $properties;
    }

    /**
     * @throws SchemaOrgApiException
     */
    public function getRanges(string $propertyName): array
    {
        $ranges = [];
        try {
            $classVariable = new Variable('class');
            $commentVariable = new Variable('comment');
            /** @var SelectStatementInterface $statement */
            $statement = $this->sparQlClient
                ->select([$classVariable, $commentVariable])
                ->where([
                    new Triple(new PrefixedIri($this->getNamespaceFromLocalPart($propertyName), $propertyName), new PrefixedIri('schema', 'rangeIncludes'), $classVariable),
                    new Triple($classVariable, new PrefixedIri('rdf', 'type'), new PrefixedIri('rdfs', 'Class')),
                    // Include comments where available.
                    new Optionally(
                        [new Triple($classVariable, new PrefixedIri('rdfs', 'comment'), $commentVariable)]
                    ),
                    // Do not include pending classes.
                    new FilterNotExists([
                        new Triple($classVariable, new PrefixedIri('schema', 'isPartOf'), new Iri(Constant::PENDING_ADDRESS)),
                    ]),
                ]);
            $sets = $this->sparQlClient->execute($statement);
            if ($sets !== false) {
                foreach ($sets as $set) {
                    /** @var Iri $typeTerm */
                    $typeTerm = $set[$classVariable->getVariableName()];
                    /** @var PlainLiteral $commentTerm */
                    $commentTerm = $set[$commentVariable->getVariableName()];
                    $ranges[$this->getLocalPart($typeTerm)] = $this->sanitizeComment($commentTerm);
                }
            }
            ksort($ranges);
        } catch (SparQlException $exception) {
            throw new SchemaOrgApiException('Failed to generate classes', 0, $exception);
        }
        return $ranges;
    }

    public static function safe(string $unsafeString): string
    {
        return Constant::SCHEMA_ORG_DATA_TYPES[$unsafeString] ?? $unsafeString;
    }

    public static function unsafe(string $safeString): string
    {
        return array_search($safeString, Constant::SCHEMA_ORG_DATA_TYPES) ?: $safeString;
    }

    /**
     * @throws SchemaOrgApiException
     */
    public function getLocalPart(Iri $iri): string
    {
        $localPart = str_replace(array_values($this->namespaces), '', $iri->getRawValue());
        if ($localPart === $iri->getRawValue()) {
            throw new SchemaOrgApiException(sprintf('"%s" has an unknown namespace', $iri->getRawValue()));
        }
        return $localPart;
    }

    /**
     * @throws SchemaOrgApiException
     */
    public function getNamespace(Iri $iri): string
    {
        foreach ($this->namespaces as $namespace => $url) {
            if (str_starts_with($iri->getRawValue(), $url)) {
                return $namespace;
            }
        }
        throw new SchemaOrgApiException(sprintf('"%s" has an unknown namespace', $iri->getRawValue()));
    }

    /**
     * @throws SchemaOrgApiException
     */
    // TODO: This is inefficient. Instead consider maintaining a translation table as an associative array.
    public function getNamespaceFromLocalPart(string $localPart): string
    {
        try {
            $variables = [];
            $conditions = [];
            $typeVariable = new Variable('type');
            foreach ($this->namespaces as $namespace => $url) {
                $suggestedSolution = sprintf('%s%s', $url, $localPart);
                $variable = new Variable($namespace);
                $variables[] = $variable;
                $conditions[] = new Optionally([
                    new Triple($variable, new PrefixedIri('rdf', 'type'), $typeVariable),
                    new Filter(new Equal(new Str($variable), new PlainLiteral($suggestedSolution))),
                ]);
            }
            $statement = $this->sparQlClient
                ->select($variables)
                ->where($conditions);
            $sets = $this->sparQlClient->execute($statement);
            if ($sets !== false) {
                foreach ($sets as $set) {
                    foreach ($variables as $variable) {
                        if (isset($set[$variable->getVariableName()])) {
                            return $variable->getVariableName();
                        }
                    }
                }
            }
            throw new SchemaOrgApiException(sprintf('"%s" has an unknown namespace', $localPart));
        } catch (SparQlException $exception) {
            throw new SchemaOrgApiException(sprintf('Failed to retrieve namespace for "%s"', $localPart), 0, $exception);
        }
    }

    public function sanitizeComment(PlainLiteral $plainLiteral): string
    {
        return trim(preg_replace('!\s+!', ' ', str_replace(['\n', "\n"], ' ', strip_tags($plainLiteral->getRawValue()))));
    }

    /**
     * @throws SchemaOrgApiException
     */
    public function resolveScalar(string $type, bool|int|float|string $scalar): TermInterface
    {
        try {
            switch ($type) {
                case Constant::SCHEMA_ORG_DATA_TYPE_BOOLEAN:
                    return new TypedLiteral($scalar, new PrefixedIri('xsd', 'boolean'));

                case Constant::SCHEMA_ORG_DATA_TYPE_DATE:
                    $date = DateTime::createFromFormat('Y-m-d', $scalar);
                    if ($date === false) {
                        $date = DateTime::createFromFormat('Y-m-dp', $scalar);
                    }
                    if ($date === false) {
                        $date = DateTime::createFromFormat('Y-m-dP', $scalar);
                    }
                    if ($date === false) {
                        throw new SchemaOrgApiException(sprintf('Date format of date %s is not valid ISO 8601', $scalar));
                    }
                    return new TypedLiteral(
                        $date->format(Constant::XSD_DATE_FORMATS['date']),
                        new PrefixedIri('xsd', 'date')
                    );

                case Constant::SCHEMA_ORG_DATA_TYPE_DATETIME:
                    $date = DateTime::createFromFormat('Y-m-d\TH:i:sP', $scalar);
                    if ($date === false) {
                        $date = DateTime::createFromFormat('Y-m-d\TH:i:sp', $scalar);
                    }
                    if ($date === false) {
                        // ISO 8601 Extended Format.
                        $date = DateTime::createFromFormat('Y-m-d\TH:i:s.vP', $scalar);
                    }
                    if ($date === false) {
                        // ISO 8601 Extended Format.
                        $date = DateTime::createFromFormat('Y-m-d\TH:i:s.vp', $scalar);
                    }
                    if ($date === false) {
                        throw new SchemaOrgApiException(sprintf('Date format of datetime %s is not valid ISO 8601', $scalar));
                    }
                    return new TypedLiteral(
                        $date->format(Constant::XSD_DATE_FORMATS['dateTime']),
                        new PrefixedIri('xsd', 'dateTime')
                    );

                case Constant::SCHEMA_ORG_DATA_TYPE_FLOAT:
                    return new TypedLiteral($scalar, new PrefixedIri('xsd', 'float'));

                case Constant::SCHEMA_ORG_DATA_TYPE_INTEGER:
                    return new TypedLiteral($scalar, new PrefixedIri('xsd', 'integer'));

                case Constant::SCHEMA_ORG_DATA_TYPE_NUMBER:
                    return new TypedLiteral($scalar, new PrefixedIri('xsd', 'decimal'));

                case Constant::SCHEMA_ORG_DATA_TYPE_TEXT:
                    return new PlainLiteral($scalar);

                case Constant::SCHEMA_ORG_DATA_TYPE_TIME:
                    $date = DateTime::createFromFormat('H:i:s', $scalar);
                    if ($date === false) {
                        $date = DateTime::createFromFormat('H:i:sP', $scalar);
                    }
                    if ($date === false) {
                        $date = DateTime::createFromFormat('H:i:sp', $scalar);
                    }
                    if ($date === false) {
                        throw new SchemaOrgApiException(sprintf('Time format of time %s is not valid ISO 8601', $scalar));
                    }
                    return new TypedLiteral(
                        $date->format(Constant::XSD_DATE_FORMATS['time']),
                        new PrefixedIri('xsd', 'time')
                    );

                case Constant::SCHEMA_ORG_DATA_TYPE_URL:
                    return new Iri($scalar);

                default:
                    throw new SchemaOrgApiException(sprintf('Unknown type "%s" for scalar', $type));
            }
        } catch (SparQlException $exception) {
            throw new SchemaOrgApiException(sprintf('Failed to resolve scalar of type "%s"', $type), 0, $exception);
        }
    }
}
