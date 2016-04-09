<?php
namespace Ivory\Connection;

class ConnConfigTransactionWatcher implements ITransactionControlObserver
{
    const SCOPE_TRANSACTION = 'trans';
    const SCOPE_SESSION = 'session';

    const RESET_ALL = '';

    private static $emptyStruct = [
        'name' => null,
        self::SCOPE_TRANSACTION => [],
        self::SCOPE_SESSION => [],
    ];

    private $connConfig;

    /** @var bool whether currently in a transaction */
    private $inTrans = false;

    /**
     * @var array[] list: struct:
     *                'name' => savepoint name,
     *                'trans' => map: lower-cased names => original-cased names of properties the transaction-wide value
     *                           a change of which has been saved by this savepoint,
     *                'session' => similar for session-wide values;
     *              the list is sorted in the savepoint save order;
     *              the last struct always holds the currently unsaved values, its 'name' is null
     */
    private $bySavepoint;

    /** @var int index of the last struct in the $bySavepoint list */
    private $tailIdx;


    public function __construct(IObservableConnConfig $connConfig)
    {
        $this->connConfig = $connConfig;
    }


    public function handleSetForTransaction($propertyName)
    {
        if ($this->inTrans) {
            $this->bySavepoint[$this->tailIdx][self::SCOPE_TRANSACTION][strtolower($propertyName)] = $propertyName;
        }
    }

    public function handleSetForSession($propertyName)
    {
        if ($this->inTrans) {
            $this->bySavepoint[$this->tailIdx][self::SCOPE_SESSION][strtolower($propertyName)] = $propertyName;
        }
    }

    public function handleResetAll()
    {
        if ($this->inTrans) {
            $this->bySavepoint[$this->tailIdx][self::SCOPE_SESSION][self::RESET_ALL] = self::RESET_ALL;
        }
    }



    //region ITransactionControlObserver

    public function handleTransactionStart()
    {
        $this->inTrans = true;
        $this->tailIdx = 0;
        $this->bySavepoint = [$this->tailIdx => self::$emptyStruct];
    }

    public function handleTransactionCommit()
    {
        $props = [];
        foreach ($this->bySavepoint as $savepoint) {
            $props += $savepoint[self::SCOPE_TRANSACTION];
        }
        foreach ($props as $propName) {
            $this->connConfig->notifyPropertyChange($propName);
        }

        $this->inTrans = false;
        $this->bySavepoint = null;
        $this->tailIdx = null;
    }

    public function handleTransactionRollback()
    {
        $rolledBack = [];
        foreach ($this->bySavepoint as $savepoint) {
            $rolledBack += $savepoint[self::SCOPE_TRANSACTION];
            $rolledBack += $savepoint[self::SCOPE_SESSION];
        }

        if (isset($rolledBack[self::RESET_ALL])) {
            $this->connConfig->notifyPropertiesReset();
        }
        else {
            foreach ($rolledBack as $propName) {
                $this->connConfig->notifyPropertyChange($propName);
            }
        }

        $this->inTrans = false;
        $this->bySavepoint = null;
        $this->tailIdx = null;
    }

    public function handleSavepointSaved($name)
    {
        $this->bySavepoint[$this->tailIdx]['name'] = $name;
        $this->tailIdx++;
        $this->bySavepoint[$this->tailIdx] = self::$emptyStruct;
    }

    public function handleSavepointReleased($name)
    {
        $idx = $this->findSavepoint($name);
        if ($idx === null) {
            return;
        }

        $unsaved = self::$emptyStruct;
        for ($i = $idx; $i <= $this->tailIdx; $i++) {
            $unsaved[self::SCOPE_TRANSACTION] += $this->bySavepoint[$i][self::SCOPE_TRANSACTION];
            $unsaved[self::SCOPE_SESSION] += $this->bySavepoint[$i][self::SCOPE_SESSION];
        }
        array_splice($this->bySavepoint, $idx, count($this->bySavepoint), [$unsaved]);
        $this->tailIdx = $idx;
    }

    public function handleRollbackToSavepoint($name)
    {
        $idx = $this->findSavepoint($name);
        if ($idx === null) {
            return;
        }

        $rolledBack = [];
        for ($i = $idx + 1; $i <= $this->tailIdx; $i++) {
            $rolledBack += $this->bySavepoint[$i][self::SCOPE_TRANSACTION];
            $rolledBack += $this->bySavepoint[$i][self::SCOPE_SESSION];
        }
        array_splice($this->bySavepoint, $idx + 1, count($this->bySavepoint), [self::$emptyStruct]);
        $this->tailIdx = $idx + 1;

        if (isset($rolledBack[self::RESET_ALL])) {
            $this->connConfig->notifyPropertiesReset();
        }
        else {
            foreach ($rolledBack as $propName) {
                $this->connConfig->notifyPropertyChange($propName);
            }
        }
    }

    private function findSavepoint($name)
    {
        for ($idx = $this->tailIdx - 1; $idx >= 0; $idx--) {
            if ($this->bySavepoint[$idx]['name'] == $name) {
                return $idx;
            }
        }
        return null;
    }

    public function handleTransactionPrepared($name)
    {
        $this->handleTransactionCommit();
    }

    public function handlePreparedTransactionCommit($name)
    {
        // NOTE: does not affect the properties
    }

    public function handlePreparedTransactionRollback($name)
    {
        // NOTE: does not affect the properties
    }

    //endregion
}
