<?php
declare(strict_types=1);
namespace Ivory\Type;

use Ivory\Lang\Sql\Types;

abstract class TypeBase implements IType
{
    private $schemaName;
    private $name;
    private $typeModifier;

    public function __construct(string $schemaName, string $name, string $typeModifier = '')
    {
        $this->schemaName = $schemaName;
        $this->name = $name;
        $this->typeModifier = $typeModifier;
    }

    /**
     * @return string name of schema this object is defined in
     */
    final public function getSchemaName(): string
    {
        return $this->schemaName;
    }

    /**
     * @return string name of this object
     */
    final public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string modifier of the type name, i.e., name suffix; e.g., for arrays, the type modifier is `[]`
     */
    final public function getTypeModifier(): string
    {
        return $this->typeModifier;
    }

    protected function typeCastExpr(bool $useTypeCast, string $sqlExpr): string
    {
        if ($useTypeCast) {
            $plainName = (
                $this->typeModifier === '' ?
                $this->getName() :
                substr($this->getName(), 0, -strlen($this->typeModifier))
            );
            return sprintf(
                '%s::%s.%s%s',
                $sqlExpr,
                Types::serializeIdent($this->getSchemaName()),
                Types::serializeIdent($plainName),
                $this->getTypeModifier()
            );
        } else {
            return $sqlExpr;
        }
    }

    protected function indicateType(bool $useTypeIndication, string $sqlExpr): string
    {
        if ($useTypeIndication) {
            $plainName = (
                $this->typeModifier === '' ?
                $this->getName() :
                substr($this->getName(), 0, -strlen($this->typeModifier))
            );
            return sprintf(
                '%s.%s%s %s',
                Types::serializeIdent($this->getSchemaName()),
                Types::serializeIdent($plainName),
                $this->getTypeModifier(),
                $sqlExpr
            );
        } else {
            return $sqlExpr;
        }
    }

    protected function invalidValueException($val, ?\Exception $cause = null): \InvalidArgumentException
    {
        $message = "Value '$val' is not valid for type {$this->schemaName}.{$this->name}";
        return new \InvalidArgumentException($message, 0, $cause);
    }
}
