<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Orm;

use vxPHP\Database\RecordsetIteratorInterface;
use vxPHP\Database\DatabaseInterface;

/**
 * abstract class for ORM queries
 *
 * @author Gregor Kofler
 * @version 0.4.2 2025-01-13
 */
abstract class Query implements QueryInterface
{
    /**
     * @var DatabaseInterface
     */
    protected DatabaseInterface $dbConnection;

    /**
     * character for quoting identifiers
     *
     * @var string
     */
    protected string $quoteChar;

    protected array $columns = [];
    protected ?string $table;
    protected ?string $alias;
    protected array $innerJoins = [];
    protected array $whereClauses = [];
    protected array $columnSorts = [];
    protected array $valuesToBind = [];
    protected ?string $sql;
    protected ?string $lastQuerySql;

    /**
     * provide initial database connection
     *
     * @param DatabaseInterface $dbConnection
     */
    public function __construct(DatabaseInterface $dbConnection)
    {
        $this->dbConnection = $dbConnection;

        if (defined(get_class($dbConnection) . '::QUOTE_CHAR')) {
            $this->quoteChar = $dbConnection::QUOTE_CHAR;
        } else {
            $this->quoteChar = ' ';
        }
    }

    /**
     * add WHERE clause that filters articles where $columnName matches $value
     *
     * @param string $columnName
     * @param string|number|array $value
     *
     * @return \vxPHP\Orm\QueryInterface
     */
    public function filter(string $columnName, $value): QueryInterface
    {
        if (is_array($value)) {
            $this->addCondition($columnName, $value, 'IN');
        } else {
            $this->addCondition($columnName, $value, '=');
        }
        return $this;
    }

    /**
     * INNER JOIN table $table with $on condition
     *
     * @param string $table
     * @param string $on
     *
     * @return \vxPHP\Orm\QueryInterface
     */
    public function innerJoin(string $table, string $on): QueryInterface
    {
        $join = new \stdClass();
        $join->table = $table;
        $join->on = $on;

        $this->innerJoins[] = $join;

        return $this;
    }

    /**
     * add an "arbitrary" WHERE clause and values to bind
     *
     * @param string $whereClause
     * @param array|null $valuesToBind
     *
     * @return \vxPHP\Orm\QueryInterface
     */
    public function where(string $whereClause, ?array $valuesToBind = null): QueryInterface
    {
        $this->addCondition($whereClause, $valuesToBind);
        return $this;
    }

    /**
     * add ORDER BY clause
     *
     * @param string $columnName
     * @param boolean $asc
     *
     * @return \vxPHP\Orm\QueryInterface
     */
    public function sortBy(string $columnName, bool $asc = true): QueryInterface
    {
        $sort = new \stdClass();

        $sort->column = $columnName;
        $sort->asc = $asc;

        $this->columnSorts[] = $sort;

        return $this;
    }

    /**
     * static method for convenience reasons
     * avoids assigning ArticleQuery instance to variable before
     * specifying and executing query
     *
     * @param DatabaseInterface $dbConnection
     *
     * @return \vxPHP\Orm\QueryInterface
     */
    public static function create(DatabaseInterface $dbConnection): QueryInterface
    {
        return new static($dbConnection);
    }

    /**
     * executes query and returns number of rows
     *
     * @return int
     */
    abstract public function count(): int;

    /**
     * executes query and returns array of (custom) row instances
     *
     * @return array
     */
    abstract public function select(): array;

    /**
     * adds LIMIT clause, executes query and returns array of (custom) row instances
     *
     * @param int $count
     * @return array
     */
    abstract public function selectFirst(int $count = 1): array;

    /**
     * adds LIMIT clause with offset and count, executes query and returns array of (custom) row instances
     *
     * @see \vxPHP\Orm\QueryInterface::selectFromTo()
     */
    abstract public function selectFromTo(int $from, int $to): array;

    /**
     * @see \vxPHP\Orm\QueryInterface::dumpSql()
     */
    public function dumpSql(): string
    {
        if (!$this->sql) {
            $this->buildQueryString();
        }

        return $this->sql;
    }

    /**
     * stores WHERE clause and values which must be bound
     * when an operator is supplied, $conditionOrColumn will hold a column name,
     * otherwise a condition including comparison operator
     *
     * @param string $conditionOrColumn
     * @param string|number|array $value
     * @param string $operator
     */
    protected function addCondition(string $conditionOrColumn, mixed $value = null, string $operator = ''): void
    {
        $condition = new \stdClass();

        $condition->conditionOrColumn = $conditionOrColumn;
        $condition->value = $value;
        $condition->operator = strtoupper($operator);

        $this->whereClauses[] = $condition;
    }

    /**
     * builds query string by parsing WHERE and ORDER BY clauses
     * @todo incomplete masking (e.g. ON clauses)
     */
    protected function buildQueryString(): void
    {
        $w = [];
        $s = [];

        $qc = $this->quoteChar;

        // start SQL statement

        $this->sql = 'SELECT ';

        // add columns

        if (!$this->columns || !count($this->columns)) {
            $this->sql .= '*';
        } else {
            $this->sql .= $qc . str_replace('.', $qc . '.' . $qc, implode($qc . ',' . $qc, $this->columns)) . $qc;
        }

        // add table

        $this->sql .= ' FROM ' . $qc . preg_replace('/\s+/', $qc . ' ' . $qc, $this->table) . $qc;

        // add alias

        if ($this->alias) {
            $this->sql .= ' ' . $qc . $this->alias . $qc;
        }

        // add INNER JOINs

        foreach ($this->innerJoins as $join) {
            $this->sql .= sprintf(' INNER JOIN %1$s%2$s%1$s ON %3$s', $qc, preg_replace('/\s+/', $qc . ' ' . $qc, trim($join->table)), $join->on);
        }

        // build WHERE clause

        foreach ($this->whereClauses as $where) {

            // complete condition when no operator set

            if (!$where->operator) {
                $w[] = $where->conditionOrColumn;
            } // otherwise parse operator

            else if ($where->operator === 'IN') {
                $w[] = sprintf(
                    '%1$s%2$s%1$s IN (%3$s)',
                    $qc,
                    str_replace('.', $qc . '.' . $qc, $where->conditionOrColumn),
                    implode(', ', array_fill(0, count($where->value), '?'))
                );
            } else {
                $w[] = sprintf(
                    '%1$s%2$s%1$s %3$s ?',
                    $qc,
                    str_replace('.', $qc . '.' . $qc, $where->conditionOrColumn),
                    $where->operator
                );
            }
        }

        // build SORT clause

        foreach ($this->columnSorts as $sort) {
            $s[] = $sort->column . ($sort->asc ? '' : ' DESC');
        }

        if (count($w)) {
            $this->sql .= ' WHERE (' . implode(') AND (', $w) . ')';
        }
        if (count($s)) {
            $this->sql .= ' ORDER BY ' . implode(', ', $s);
        }
    }

    /**
     * prepares array containing values which must be bound to prepared statement
     */
    protected function buildValuesArray(): void
    {
        foreach ($this->whereClauses as $where) {

            if (is_null($where->value)) {
                continue;
            }
            if (is_array($where->value)) {
                array_push($this->valuesToBind, ...$where->value);
            } else {
                $this->valuesToBind[] = $where->value;
            }
        }
    }

    /**
     * bind values and execute the SQL statement
     * returns array of records
     *
     * @todo caching/do not prepare statement again, if query hasn't changed
     *
     * @return RecordsetIteratorInterface
     */
    protected function executeQuery(): RecordsetIteratorInterface
    {
        $this->lastQuerySql = $this->sql;
        return $this->dbConnection->doPreparedQuery($this->sql, $this->valuesToBind);
    }
}