<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Exception\InternalException;

/**
 * Transaction configuration. To be used, e.g., for {@link Connection::startTransaction()}.
 *
 * There are several configurable parameters of transactions in PostgreSQL. For each of them, a group of constants,
 * representing the possible values for the respective parameter, is available. Constants from distinct groups may be
 * combined together using the binary `|` operator.
 */
class TxConfig
{
    /** The `SERIALIZABLE` isolation level. */
    const ISOLATION_SERIALIZABLE = 1;
    /** The `REPEATABLE READ` isolation level. */
    const ISOLATION_REPEATABLE_READ = 2;
    /** The `READ COMMITTED` isolation level. */
    const ISOLATION_READ_COMMITTED = 4;
    /** The `READ UNCOMMITTED` isolation level. */
    const ISOLATION_READ_UNCOMMITTED = 8;

    /** The `READ WRITE` access mode. */
    const ACCESS_READ_WRITE = 128;
    /** The `READ ONLY` access mode. */
    const ACCESS_READ_ONLY = 256;

    /** The `DEFERRABLE` deferrable mode. Only effective in `SERIALIZABLE` `READ ONLY` transactions. */
    const DEFERRABLE = 4096;
    /** The `NOT DEFERRABLE` deferrable mode. */
    const NOT_DEFERRABLE = 8192;


    private $isolationLevel = null;
    private $readOnly = null;
    private $deferrable = null;


    public static function create($transactionOptions): TxConfig
    {
        if ($transactionOptions instanceof TxConfig) {
            return $transactionOptions;
        } else {
            return new TxConfig($transactionOptions);
        }
    }

    public static function createFromParams(?string $isolationLevel, ?bool $readOnly, ?bool $deferrable): TxConfig
    {
        $txConf = new TxConfig();
        $txConf->setReadOnly($readOnly);
        $txConf->setDeferrable($deferrable);

        if ($isolationLevel !== null) {
            static $isolationLevels = [
                'serializable' => TxConfig::ISOLATION_SERIALIZABLE,
                'repeatable read' => TxConfig::ISOLATION_REPEATABLE_READ,
                'read committed' => TxConfig::ISOLATION_READ_COMMITTED,
                'read uncommitted' => TxConfig::ISOLATION_READ_UNCOMMITTED,
            ];
            if (!isset($isolationLevels[$isolationLevel])) {
                throw new \InvalidArgumentException("Unrecognized transaction isolation level: '$isolationLevel'");
            }
            $txConf->setIsolationLevel($isolationLevels[$isolationLevel]);
        }

        return $txConf;
    }

    /**
     * @param int $options one or more {@link TxConfig} constants combined together with the <tt>|</tt> operator
     */
    public function __construct(int $options = 0)
    {
        $isolationLevel = $options & (
                self::ISOLATION_SERIALIZABLE | self::ISOLATION_REPEATABLE_READ |
                self::ISOLATION_READ_COMMITTED | self::ISOLATION_READ_UNCOMMITTED
            );
        if ($isolationLevel != 0) {
            $this->setIsolationLevel($isolationLevel);
        }

        $this->setReadOnly(
            self::initBool(
                $options, self::ACCESS_READ_ONLY, self::ACCESS_READ_WRITE, 'read/write and read-only access mode'
            )
        );
        $this->setDeferrable(
            self::initBool(
                $options, self::DEFERRABLE, self::NOT_DEFERRABLE, 'deferrable and non-deferrable mode'
            )
        );
    }

    private static function initBool(int $options, int $trueBit, int $falseBit, string $errorDesc): ?bool
    {
        if ($options & $trueBit) {
            if ($options & $falseBit) {
                throw new \InvalidArgumentException(__CLASS__ . " options specify both $errorDesc");
            }
            return true;
        } elseif ($options & $falseBit) {
            return false;
        } else {
            return null;
        }
    }

    /**
     * @return int|null one of the <tt>ISOLATION_*</tt> constants, or <tt>null</tt> if no isolation level is specified
     */
    public function getIsolationLevel(): ?int
    {
        return $this->isolationLevel;
    }

    /**
     * @param int|null $isolationLevel one of the <tt>ISOLATION_*</tt> constants, or <tt>null</tt> to clear specifying
     *                                   the isolation level
     * @return $this
     */
    public function setIsolationLevel(?int $isolationLevel): TxConfig
    {
        static $levels = [
            null,
            self::ISOLATION_SERIALIZABLE,
            self::ISOLATION_REPEATABLE_READ,
            self::ISOLATION_READ_COMMITTED,
            self::ISOLATION_READ_UNCOMMITTED,
        ];
        if (!in_array($isolationLevel, $levels)) {
            throw new \InvalidArgumentException('Invalid isolation level specification');
        }

        $this->isolationLevel = $isolationLevel;
        return $this;
    }

    /**
     * @return bool|null whether the access mode is specified as read-only, or <tt>null</tt> if no access mode is
     *                     specified
     */
    public function isReadOnly(): ?bool
    {
        return $this->readOnly;
    }

    public function setReadOnly(?bool $readOnly): TxConfig
    {
        $this->readOnly = $readOnly;
        return $this;
    }

    public function isDeferrable(): ?bool
    {
        return $this->deferrable;
    }

    public function setDeferrable(?bool $deferrable): TxConfig
    {
        $this->deferrable = $deferrable;
        return $this;
    }

    public function toSql(): string
    {
        $clauses = [];

        $il = $this->getIsolationLevel();
        if ($il !== null) {
            switch ($il) {
                case self::ISOLATION_SERIALIZABLE:
                    $clauses[] = 'ISOLATION LEVEL SERIALIZABLE';
                    break;
                case self::ISOLATION_REPEATABLE_READ:
                    $clauses[] = 'ISOLATION LEVEL REPEATABLE READ';
                    break;
                case self::ISOLATION_READ_COMMITTED:
                    $clauses[] = 'ISOLATION LEVEL READ COMMITTED';
                    break;
                case self::ISOLATION_READ_UNCOMMITTED:
                    $clauses[] = 'ISOLATION LEVEL READ UNCOMMITTED';
                    break;
                default:
                    throw new InternalException('Undefined isolation level');
            }
        }

        $ro = $this->isReadOnly();
        if ($ro !== null) {
            $clauses[] = ($ro ? 'READ ONLY' : 'READ WRITE');
        }

        $d = $this->isDeferrable();
        if ($d !== null) {
            $clauses[] = ($d ? 'DEFERRABLE' : 'NOT DEFERRABLE');
        }

        return implode(' ', $clauses);
    }
}
