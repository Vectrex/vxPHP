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

use vxPHP\Database\DatabaseInterface;

/**
 * interface for custom ORM queries
 *
 * @author Gregor Kofler
 * @version 0.4.0 2021-04-28
 */
interface QueryInterface
{
    /**
     * 'AND' a where clause; $value can either be a single value or an array
     *
     * @param string $columnName
     * @param $value
     * @return QueryInterface
     */
	public function filter(string $columnName, $value): QueryInterface;

    /**
     * add a generic where clause
     *
     * @param string $whereClause
     * @param array $valuesToBind
     * @return QueryInterface
     */
	public function where(string $whereClause, array $valuesToBind): QueryInterface;

    /**
     * add an order by clause
     *
     * @param string $columnName
     * @param bool $asc
     * @return QueryInterface
     */
	public function sortBy(string $columnName, bool $asc): QueryInterface;

    /**
     * execute query and return record
     *
     * @return array
     */
	public function select(): array;

    /**
     * execute query and count of resulting records
     *
     * @return int
     */
	public function count(): int;

    /**
     * INNER JOIN table $table with $on condition
     *
     * @param string $table
     * @param string $on
     * @return QueryInterface
     */
    public function innerJoin(string $table, string $on): QueryInterface;

	/**
	 * dumps SQL string of current query
	 * (invokes building of SQL string if not already built)
	 *
	 * @return string sqlString
	 */
	public function dumpSql(): string;

	/**
	 * execute query and add a limit clause to retrieve only first records
     *
	 * @param int $count
	 * @return array
	 */
	public function selectFirst(int $count): array;

	/**
     * execute query and add a limit clause to retrieve a subset of records
	 *
	 * @param int $from
	 * @param int $to
	 * 
	 * @return array
	 */
	public function selectFromTo(int $from, int $to): array;

	/**
	 * @param DatabaseInterface $dbConnection
	 * @return QueryInterface
	 */
	public static function create(DatabaseInterface $dbConnection): QueryInterface;
}
