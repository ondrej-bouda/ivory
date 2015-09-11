<?php
namespace Ivory;

/**
 * Transaction configuration. To be used, e.g., for {@link Connection::startTransaction()}.
 *
 * There are several configurable parameters of transactions in PostgreSQL. For each of them, a group of constants,
 * representing the possible values for the respective parameter, is available. Constants from distinct groups may be
 * combined together using the binary <tt>|</tt> operator.
 */
class TxConfig
{
    /** The <tt>SERIALIZABLE</tt> isolation level. */
    const ISOLATION_SERIALIZABLE = 1;
    /** The <tt>REPEATABLE READ</tt> isolation level. */
    const ISOLATION_REPEATABLE_READ = 2;
    /** The <tt>READ COMMITTED</tt> isolation level. */
    const ISOLATION_READ_COMMITTED = 4;
    /** The <tt>READ UNCOMMITTED</tt> isolation level. */
    const ISOLATION_READ_UNCOMMITTED = 8;

    /** The <tt>READ WRITE</tt> access mode. */
    const ACCESS_READ_WRITE = 128;
    /** The <tt>READ ONLY</tt> access mode. */
    const ACCESS_READ_ONLY = 256;

    /**
     * The <tt>DEFERRABLE</tt> deferrable mode. Only effective in <tt>SERIALIZABLE</tt> <tt>READ ONLY</tt> transactions.
     */
    const DEFERRABLE = 4096;
    /** The <tt>NOT DEFERRABLE</tt> deferrable mode. */
    const NOT_DEFERRABLE = 8192;


    private $isolationLevel = null;
    private $readOnly = null;
    private $deferrable = null;


    /**
     * @param int $options one or more {@link TxConfig} constants combined together with the <tt>|</tt> operator
     */
    public function __construct($options = 0)
    {
        $isolationLevel = $options & (
            self::ISOLATION_SERIALIZABLE | self::ISOLATION_REPEATABLE_READ |
            self::ISOLATION_READ_COMMITTED | self::ISOLATION_READ_UNCOMMITTED
        );
        if ($isolationLevel != 0) {
            $this->setIsolationLevel($isolationLevel);
        }

        $this->setReadOnly(
            self::initBool($options, self::ACCESS_READ_ONLY, self::ACCESS_READ_WRITE, 'read/write and read-only access mode')
        );
        $this->setDeferrable(
            self::initBool($options, self::DEFERRABLE, self::NOT_DEFERRABLE, 'deferrable and non-deferrable mode')
        );
    }

    private static function initBool($options, $trueBit, $falseBit, $errorDesc)
    {
        if ($options & $trueBit) {
            if ($options & $falseBit) {
                throw new \InvalidArgumentException(__CLASS__ . " options specify both $errorDesc");
            }
            return true;
        }
        elseif ($options & $falseBit) {
            return false;
        }
        else {
            return null;
        }
    }

    /**
     * @return int|null one of the <tt>ISOLATION_*</tt> constants, or <tt>null</tt> if no isolation level is specified
     */
    public function getIsolationLevel()
    {
        return $this->isolationLevel;
    }

    /**
     * @param int|null $isolationLevel one of the <tt>ISOLATION_*</tt> constants, or <tt>null</tt> to clear specifying
     *                                   the isolation level
     * @return $this
     */
    public function setIsolationLevel($isolationLevel)
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
    public function isReadOnly()
    {
        return $this->readOnly;
    }

    /**
     * @param bool|null $readOnly
     * @return TxConfig
     */
    public function setReadOnly($readOnly)
    {
        $this->readOnly = $readOnly;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function isDeferrable()
    {
        return $this->deferrable;
    }

    /**
     * @param bool|null $deferrable
     * @return TxConfig
     */
    public function setDeferrable($deferrable)
    {
        $this->deferrable = $deferrable;
        return $this;
    }
}
