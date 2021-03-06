<?php
/**
 * @file
 * lightningsdk\core\Tools\Database
 */

namespace lightningsdk\core\Tools;

use Exception;
use PDO;
use PDOException;
use PDOStatement;
use ReflectionClass;

/**
 * A database abstraction layer.
 *
 * @package lightningsdk\core\Tools
 *
 * A query array is a processable array built as a query. This array is converted
 * into SQL at execution time. The following is an example query with available options:
 *
 * [
 *   'select' => [
 *     // SELECT `table`.*, `field`, `another_table`.`another_field`
 *     'table.*', 'field', 'another_table.another_field',
 *   ],
 *   'select' => [
 *     // SELECT `field` as `alias`
 *     'field' => 'alias',
 *   ],
 *   'from' => 'table_name',
 *   'join' => [
 *     'left_join' => 'join_table',
 *     'on' => [
 *       // field=value
 *       'field' => 'value',
 *       // field={literal_expression}
 *       'field' => ['expression' => '{literal_expression}']
 *       // {some_field=some_value}
 *       ['expression' => '{some_field=some_value}'],
 *     ]
 *   ],
 *   // Max number of results
 *   'limit' => 12,
 *   // Built in pagination
 *   'page' => 2,
 * ]
 */
class Database extends Singleton {
    /**
     * The mysql connection.
     *
     * @var PDO
     */
    public $connection;

    protected $maxHistorySize = 500;

    /**
     * Determines if queries and errors should be collected and output.
     *
     * @var boolean
     */
    protected $verbose = false;

    /**
     * An array of all queries called in this page request.
     *
     * @var array
     */
    protected $history = [];

    /**
     * The result of the last query.
     *
     * @var PDOStatement
     */
    protected $result;

    /**
     * The timer start time.
     *
     * @var float
     */
    protected $start;

    /**
     * The mysql execution end time.
     *
     * @var float
     */
    protected $end_mysql;

    /**
     * The php execution end time.
     *
     * @var float
     */
    protected $end_php;

    /**
     * The last query executed. If it's the same it does not need to be re-prepared.
     *
     * @var string
     */
    protected $last_query;

    /**
     * The total number of queries executed.
     *
     * @var integer
     */
    protected $query_count = 0;

    /**
     * The total time to execute mysql queries.
     *
     * @var integer
     */
    protected $mysql_time = 0;

    /**
     * The total time to execute the php post processing of mysql queries data.
     *
     * @var integer
     */
    protected $php_time = 0;

    /**
     * Whether this is in read only mode.
     *
     * @var boolean
     */
    protected $readOnly = FALSE;

    /**
     * Whether the current connection is in the transaction state.
     *
     * @var boolean
     */
    protected $inTransaction = FALSE;

    const FETCH_ASSOC = PDO::FETCH_ASSOC;

    const WILDCARD_NONE = 0;
    const WILDCARD_BEFORE = 1;
    const WILDCARD_AFTER = 2;
    const WILDCARD_EITHER = 3;

    protected $URL;
    protected $username;
    protected $password;

    /**
     * Construct this object.
     *
     * @param string $url
     *   Database URL.
     */
    public function __construct($url='') {
        $this->verbose = Configuration::get('debug');

        try {
            // Extract user data.
            $results = NULL;
            $this->url = $url;
            preg_match('|user=(.*)[;$]|U', $url, $results);
            $this->username = !empty($results[1]) ? $results[1] : '';
            preg_match('|password=(.*)[;$]|U', $url, $results);
            $this->password = !empty($results[1]) ? $results[1] : '';
        } catch (PDOException $e) {
            // Error handling.
            Logger::error('Connection failed: ' . $e->getMessage());
            Output::error('Connection failed: ' . $e->getMessage());
        }
    }

    public function connect() {
        if (empty($this->connection)) {
            $this->connection = new PDO($this->url, $this->username, $this->password);
        }
    }

    public function disconnect() {
        if (!empty($this->connection)) {
            $this->connection = null;
        }
    }

    /**
     * Get the default database instance.
     *
     * @return Database
     *   The singleton Database object.
     */
    public static function getInstance($create = true) {
        $instance = parent::getInstance($create);
        $instance->connect();
        return $instance;
    }

    /**
     * Create a database instance with the default database.
     *
     * @return Database
     *   The database object.
     */
    public static function createInstance() {
        return new static(Configuration::get('database'));
    }

    /**
     * Set the controller to only execute select queries.
     *
     * @param boolean $value
     *   Whether readOnly should be on or off.
     *
     * @notice
     *   This has no effect on direct query functions like query() and assoc()
     */
    public function readOnly($value = TRUE) {
        $this->readOnly = $value;
    }

    /**
     * Whether to enable verbose messages in output.
     *
     * @param boolean $value
     *   Whether to switch to verbose mode.
     */
    public function verbose($value = TRUE) {
        $this->verbose = $value;
    }

    /**
     * Outputs a list of queries that have been called during this page request.
     *
     * @return array
     *   A list of executed queries.
     */
    public function getQueries() {
        return $this->history;
    }

    public function flush() {
        $this->history = [];
    }

    /**
     * Called whenever mysql returns an error executing a query.
     *
     * @param array $error
     *   The PDO error.
     * @param string $sql
     *   The original query.
     *
     * @throws Exception
     *   When a mysql error occurs.
     */
    public function errorHandler($error, $sql) {
        $error_string = "MYSQL ERROR ($error[0]:$error[1]): $error[2] @ $sql";
        if ($this->verbose) {
            $exception = new Exception($error_string);
        } else {
            $exception = new Exception("***** MYSQL ERROR *****");
            Logger::error($error_string);
        }

        Logger::exception($exception);

        // Throw a general xception for all users.
        throw $exception;
    }

    /**
     * Saves a query to the history and should be called on each query.
     *
     * @param $sql
     *   Add a query to the sql log.
     */
    public function log($sql, $vars, $time) {
        $this->history[] = [
            'query' => $sql,
            'vars' => $vars,
            'time' => $time,
        ];

        if (count($this->history) > $this->maxHistorySize) {
            array_shift($this->history);
        }
    }

    /**
     * Start a query.
     */
    public function timerStart() {
        $this->start = microtime(TRUE);
    }

    /**
     * A query is done, add up the times.
     */
    public function timerQueryEnd() {
        $this->end_mysql = microtime(TRUE);
    }

    /**
     * Stop the timer and add up the times.
     */
    public function timerEnd() {
        $this->end_php = microtime(TRUE);
        $this->mysql_time += $this->end_mysql - $this->start;
        $this->php_time += $this->end_php - $this->start;
    }

    /**
     * Reset the clock.
     */
    public function timerReset() {
        $this->query_count = 0;
        $this->mysql_time = 0;
        $this->php_time = 0;
    }

    /**
     * Output a time report
     */
    public function timeReport() {
        return [
            'Total MySQL Queries' => $this->query_count,
            'Total MySQL Time' => number_format($this->mysql_time, 4),
            'Total MySQL PHP Time' => number_format($this->php_time, 4),
        ];
    }

    /**
     * Raw query handler.
     *
     * @param string $query
     *   The rendered query.
     * @param array $vars
     *   A list of replacement variables.
     *
     * @throws Exception
     */
    protected function _query($query, $vars = []) {
        if ($this->readOnly) {
            if (!preg_match("/^SELECT /i", $query)) {
                return;
            }
        }
        $this->query_count ++;
        $this->timerStart();
        $this->__query_execute($query, $vars);
        $this->timerQueryEnd();
        if ($this->verbose) {
            $this->log($query, $vars, $this->end_mysql - $this->start);
        }
        if (!$this->result) {
            $this->errorHandler($this->connection->errorInfo(), $query);
        }
        elseif ($this->result->errorCode() != "00000") {
            $this->errorHandler($this->result->errorInfo(), $query);
        }
    }

    /**
     * Execute query and pull results object.
     *
     * @param string $query
     *   The rendered query.
     * @param array $vars
     *   A list of replacement variables.
     */
    protected function __query_execute($query, $vars) {
        // If the query has changed, we need to prepare a new one.
        if ($this->last_query != $query) {
            $this->last_query = $query;
            $this->result = $this->connection->prepare($query);
        }
        // Execute the query with substitutions.
        $this->result->execute($vars);
    }

    /**
     * Simple query execution.
     *
     * @param string $query
     *   The rendered query.
     * @param array $vars
     *   A list of replacement variables.
     *
     * @return PDOStatement
     *
     * @throws Exception
     */
    public function query($query, $vars = []) {
        $this->_query($query, $vars);
        $this->timerEnd();
        return $this->result;
    }

    public function queryArray($query) {
        $values = [];
        $parsed = $this->parseQuery($query, $values);
        $this->query($parsed, $values);
        $this->timerEnd();
        return $this->result;
    }

    /**
     * Checks if at least one entry exists.
     *
     * @param array|string $table
     *   The table and optionally joins.
     * @param array $where
     *   A list of conditions for the query.
     *
     * @return boolean
     *   Whether there is at least one matching entry.
     */
    public function check($table, $where = []) {
        $query = [
            'from' => $table,
            'where' => $where,
        ];
        $this->queryArray($query);
        return $this->result->rowCount() > 0;
    }

    /**
     * Counts total number of matching rows.
     *
     * @param array|string $table
     *   The table and optionally joins.
     * @param array $where
     *   A list of conditions for the query.
     *
     * @return integer
     *   How many matching rows were found.
     *
     * @throws Exception
     */
    public function count($table, $where = [], $count_field = '*', $final = '') {
        if (!empty($table['limit'])) {
            unset($table['limit']);
        }
        return (integer) $this->selectField(['count' => ['expression' => 'COUNT(' . $count_field . ')']], $table, $where, $final);
    }

    public function countQuery($query, $subquery = false) {
        if ($subquery) {
            // This performs slower, but might be necessary for some queries.
            $query = [
                'select' => ['count' => ['expression' => 'COUNT(*)']],
                'from' => $query,
            ];
        } elseif (empty($query['select']['count'])) {
            // Only set this if the developer has not already set a count column.
            $query['select']['count'] = ['expression' => 'COUNT(*)'];
        }
        return (integer) $this->selectFieldQuery($query, 'count');
    }

    /**
     * Get a list of counted groups, keyed by an index.
     *
     * @param string $table
     *   The table to search.
     * @param string $key
     *   The table to use as the key.
     * @param array $where
     *   A list of conditions for the query.
     * @param string $order
     *   Additional order information.
     *
     * @return array
     *   A list of counts keyed by the $key column.
     *
     * @throws Exception
     */
    public function countKeyed($table, $key, $where = [], $order = '') {
        $this->_select($table, $where, ['count' => ['expression' => 'COUNT(*)'], $key], NULL, 'GROUP BY `' . $key . '` ' . $order);
        $results = [];
        // TODO: This is built in to PDO.
        while ($row = $this->result->fetch(PDO::FETCH_ASSOC)) {
            $results[$row[$key]] = $row['count'];
        }
        $this->timerEnd();
        return $results;
    }

    public function countKeyedQuery($query, $key) {
        $values = [];
        $query['select'] = [
            $key,
            'count' => ['expression' => 'COUNT(*)'],
        ];
        $query['group_by'] = $key;
        $parsed = $this->parseQuery($query, $values);
        $this->query($parsed, $values);
        $result = $this->result->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->timerEnd();
        return $result;
    }

    /**
     * Update a row.
     *
     * @param string $table
     *   The table to update.
     * @param array $data
     *   A list of new values keyed by the column.
     * @param array $where
     *   A list of conditions on which rows to update.
     *
     * @return integer
     *   The number of rows updated.
     *
     * @throws Exception
     */
    public function update($table, $data, $where) {
        $vars = [];
        $query = 'UPDATE ' . $this->parseTable($table, $vars) . ' SET ' . $this->sqlImplode($data, $vars, ', ', true);
        if (!empty($where)) {
            $query .= ' WHERE ' . $this->sqlImplode($where, $vars, ' AND ');
        }
        $this->query($query, $vars);
        $this->timerEnd();
        return $this->result->rowCount() == 0 ? false : $this->result->rowCount();
    }

    /**
     * Insert a new row into a table.
     *
     * @param string $table
     *   The table to insert into.
     * @param array $data
     *   An array of columns and values to set.
     * @param boolean|array $existing
     *   TRUE to ignore, an array to update.
     * @param boolean $return_count
     *   Whether to return the insert count instead of the last ID.
     *   This is useful when there is no auto increment, or if you are
     *   testing for whether a row was inserted (1) or updated (2) or
     *   no changes were made (-1)
     *
     * @return integer
     *   The last inserted id.
     *
     * @throws Exception
     */
    public function insert($table, $data, $existing = false, $return_count = false) {
        $vars = [];
        $table = $this->parseTable($table, $vars);
        $ignore = $existing === TRUE ? 'IGNORE' : '';
        $set = $this->sqlImplode($data, $vars, ', ', true);
        $duplicate = is_array($existing) ? ' ON DUPLICATE KEY UPDATE ' . $this->sqlImplode($existing, $vars, ', ', true) : '';
        $this->query('INSERT ' . $ignore . ' INTO ' . $table . ' SET ' . $set . $duplicate, $vars);
        $this->timerEnd();
        if ($return_count) {
            return $this->result->rowCount();
        } else {
            return $this->result->rowCount() === 0 ? false :
                // If there is no auto increment, just return true.
                ($this->connection->lastInsertId() ?: true);
        }
    }

    /**
     * Insert a list of values.
     *
     * @param string $table
     *   The table to insert into.
     * @param array $value_sets
     *   A list of column values. Each value should either be an array of the same length
     *   as all other arrays, or a string to have the same entry for each set.
     * @param boolean|array $existing
     *   Whether to ignore existing or an array of values to use for existing key entries.
     *
     * @return integer
     *   The number of entries submitted.
     *
     * @throws Exception
     */
    public function insertSets($table, $value_sets, $existing = FALSE) {
        $vars = [];
        $table = $this->parseTable($table, $vars);
        $ignore = $existing === TRUE ? 'IGNORE' : '';
        $fields = array_keys($value_sets);
        $field_string = '`' . implode('`,`', $fields) . '`';

        $values = '(' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $set_count = 0;
        foreach ($fields as $field) {
            $set_count = max($set_count, is_array($value_sets[$field]) ? count($value_sets[$field]) : 1);
        }
        $values = implode(',', array_fill(0, $set_count, $values));
        for ($i = 0; $i < $set_count; $i++) {
            foreach ($fields as $field) {
                $vars[] = is_array($value_sets[$field]) ? $value_sets[$field][$i] : $value_sets[$field];
            }
        }

        // TODO: Verify that this works.
        $duplicate = is_array($existing) ? ' ON DUPLICATE KEY UPDATE ' . $this->sqlImplode($existing, $vars) : '';
        $this->query('INSERT ' . $ignore . ' INTO ' . $table . ' ('  . $field_string . ') VALUES ' . $values . $duplicate, $vars);
        $this->timerEnd();
        return $this->result->rowCount() == 0 ? false :
            // If there is no auto increment, just return true.
            ($this->connection->lastInsertId() ?: true);
    }

    /**
     * Insert multiple values for each combination of the supplied data values.
     *
     * @param array $table
     *   The table to insert to.
     * @param array $data
     *   An array where each item corresponds to a column.
     *   The value may be a string, int or an array of multiple values.
     * @param array|boolean $existing
     *   TRUE to ignore, or an array of field names from which to copy
     *   update the value with the value from $data if the unique key exists.
     *
     * @return integer
     *   The last inserted id.
     *
     * @throws Exception
     */
    public function insertMultiple($table, $data, $existing = FALSE) {
        $last_insert = false;

        // Set up the constant variables.
        $start_vars = [];
        $table = $this->parseTable($table, $start_vars);
        $ignore = $existing === TRUE ? 'IGNORE' : '';

        // This passes $data as individual params to the __construct() function.
        $reflect = new ReflectionClass('lightningsdk\core\Tools\CombinationIterator');
        $combinator = $reflect->newInstanceArgs($data);

        $fields = $this->implodeFields(array_keys($data));
        $placeholder_set = '(' . implode(',', array_fill(0, count($data), '?')) . ')';

        // Add the update on existing key.
        $duplicate = '';
        if (is_array($existing)) {
            $duplicate .= ' ON DUPLICATE KEY UPDATE ';
            $feilds = [];
            foreach ($existing as $field) {
                $feilds[] = '`' . $field . '`=VALUES(`' . $field . '`)';
            }
            $duplicate .= implode(',', $fields);
        }

        // Initialize data.
        $vars = [];
        $i = 0;
        $iterations_per_query = 100;

        // Iterate over each value combination.
        foreach ($combinator as $combination) {
            $i++;
            // If ($iterations_per_query) have already been inserted, reset to a new query.
            if ($i > $iterations_per_query) {
                if (empty($values)) {
                    $values = implode(',', array_fill(0, $iterations_per_query, $placeholder_set));
                }
                $this->query('INSERT ' . $ignore . ' INTO ' . $table . '(' . $fields . ') VALUES ' . $values . $duplicate, $vars);
                $last_insert = $this->result->rowCount() == 0 ? $last_insert : $this->connection->lastInsertId();
                // Reset the data.
                $i = 1;
                $vars = [];
            }
            $vars = array_merge($vars, $combination);
        }

        if (!empty($vars)) {
            // The placeholder count might be different.
            if (empty($values) || (count($vars) / count($data) != $iterations_per_query)) {
                $values = implode(',', array_fill(0, count($vars) / count($data), $placeholder_set));
            }

            // Run the insert query for remaining sets.
            $this->query('INSERT ' . $ignore . ' INTO ' . $table . '(' . $fields . ') VALUES ' . $values . $duplicate, $vars);

            // Return the last insert ID.
            return $this->result->rowCount() == 0 ? $last_insert : $this->connection->lastInsertId();
        }

        return 0;
    }

    public function duplicateRows($table, $where = [], $update = []) {
        $this->duplicateRowsQuery([
            'from' => $table,
            'where' => $where,
            'set' => $update,
        ]);
    }

    /**
     * @param array $query
     *
     * @throws Exception
     */
    public function duplicateRowsQuery($query = []) {

        // Copy the data to the temp table.
        $select_query = $query;
        unset($select_query['set']);
        $values = [];
        $this->query('CREATE TEMPORARY TABLE `temporary_table` AS ' . $this->parseQuery($select_query, $values), $values);

        // Update the values
        $values = [];
        $update_query = [
            'update' => 'temporary_table',
            'set' => $query['set']
        ];
        $this->query($this->parseQuery($update_query, $values), $values);

        // Copy the new values back.
        $this->query('INSERT INTO `' . $query['from'] . '` SELECT * FROM `temporary_table`');

        $this->query('DROP TEMPORARY TABLE `temporary_table`');
    }

    /**
     * Delete rows from the database.
     *
     * @param string $table
     *   The table to delete from.
     * @param array $where
     *   The condition for the query.
     *
     * @return integer
     *   The number of rows deleted.
     *
     * @throws Exception
     */
    public function delete($table, $where) {
        $values = [];
        $this->query($this->parseQuery(['from' => $table, 'where' => $where], $values, 'DELETE'), $values);

        return $this->result->rowCount() ?: false;
    }

    /**
     * Universal select function.
     *
     * @param array|string $table
     *   The table and optionally joins.
     * @param array $where
     *   A list of conditions for the query.
     * @param array $fields
     *   A list of fields to select.
     * @param array|integer $limit
     *   A limited number of rows.
     * @param string $final
     *   A final string to append to the query, such as limit and sort.
     *
     * @throws Exception
     */
    protected function _select($table, $where = [], $fields = [], $limit = NULL, $final = '') {
        $fields = $this->implodeFields($fields);
        $values = [];
        if (!empty($where) && $where = $this->sqlImplode($where, $values, ' AND ')) {
            $where = ' WHERE ' . $where;
        } else {
            $where = '';
        }
        $limit = is_array($limit) ? ' LIMIT ' . $limit[0] . ', ' . $limit[1] . ' '
            : (!empty($limit) ? ' LIMIT ' . intval($limit) : '');
        $table_values = [];
        $table = $this->parseTable($table, $table_values);
        $this->query('SELECT ' . $fields . ' FROM ' . $table . $where . ' ' . $final . $limit, array_merge($table_values, $values));
    }

    /**
     * Experimental:
     * This should parse the entire query provided as an array.
     *
     * @param array $query
     *   The query to run.
     * @param array $values
     *   Empty array for new values.
     *
     * @return string
     *   The built query.
     *
     * @throws Exception
     *
     * @todo This should be protected.
     */
    public function parseQuery($query, &$values, $type = 'SELECT') {
        // Update can be implicit if doing UPDATE SELECT.
        if (!empty($query['update'])) {
            $type = 'UPDATE';
        }
        $output = $type . ' ';

        if ($type == 'UPDATE') {
            $output .= ' ' . $this->parseTable($query['update'], $values);
        }
        if ($type == 'SELECT') {
            $output .= $this->implodeFields(!empty($query['select']) ? $query['select'] : '*');
        }
        if (!empty($query['from'])) {
            $output .= ' FROM ' . $this->parseTable($query['from'], $values);
        }
        if (!empty($query['join'])) {
            $output .= $this->parseJoin($query['join'], $values);
        }
        if (!empty($query['set'])) {
            // For INSERT and UPDATE queries.
            $output .= ' SET ' . $this->sqlImplode($query['set'], $values, ', ', true);
        }
        if (!empty($query['where'])) {
            // For ALL queries.
            $output .= ' WHERE ' . $this->sqlImplode($query['where'], $values, ' AND ');
        }
        if (!empty($query['group_by'])) {
            $output .= ' GROUP BY ' . $this->implodeFields($query['group_by'], false);
        }
        if (!empty($query['having'])) {
            $output .= ' HAVING ' . $this->sqlImplode($query['having'], $values, ' AND ');
        }
        if (!empty($query['order_by'])) {
            $output .= ' ORDER BY ' . $this->parseOrder($query['order_by'], $values);
        }
        if (!empty($query['limit'])) {
            if (is_array($query['limit'])) {
                $output .= ' LIMIT ' . implode($query['limit']);
            } else {
                $output .= ' LIMIT ';
                if (!empty($query['page'])) {
                    $output .= (($query['page'] - 1) * $query['limit']) . ', ';
                }
                $output .= $query['limit'];
            }
        }

        return $output;
    }

    protected function parseOrder($orderBy, &$values) {
        // Ensure the order clause is an array
        if (is_string($orderBy)) {
            $orderBy = [$orderBy => 'ASC'];
        }

        $orders = [];
        foreach ($orderBy as $field => $order) {
            if (is_array($order) && !empty($order['expression'])) {
                // This order is an expression
                $orders[] = $order['expression'];
            } elseif (is_array($order)) {
                // This order is an array of IDs in a specific order
                $orders[] = 'field(' . implode(',', array_merge(['`' . $field . '`'], array_fill(0, count($order), '?'))) . ')';
                $values = array_merge($values, $order);
            } else {
                // The array is a simple field and direction
                $orders[] = $this->formatField($field) . ' ' . $order;
            }
        }
        return implode(',', $orders);
    }

    /**
     * Parse join data into a query string.
     *
     * @param array $joins
     *   The join data.
     * @param array $values
     *   The array to add variables to.
     *
     * @return string
     *   The rendered query portion.
     *
     * @throws Exception
     *   On parse error.
     */
    protected function parseJoin($joins, &$values) {
        // If the first element of join is not an array, it's an actual join.
        if (!is_array(current($joins))) {
            // Wrap it in an array so we can loop over it.
            $joins = [$joins];
        }
        // Foreach join.
        $output = '';
        foreach ($joins as $alias => $join) {
            // This format is deprecated.
            if (is_numeric(key($join))) {
                $output .= $this->implodeJoin($join[0], $join[1], !empty($join[2]) ? $join[2] : '', $values, is_string($alias) ? $alias : null);
                // Add any extra replacement variables.
                if (isset($join[3])) {
                    $values = array_merge($values, $join[3]);
                }
            }
            else {
                // Parse joins
                foreach ([
                             'left_join' => ' LEFT JOIN ',
                             'right_join' => ' RIGHT JOIN ',
                             'join' => ' JOIN ',
                             'inner_join' => ' INNER JOIN ',
                         ] as $type => $format) {
                    // Rewrite old queries for backwards compatibility.
                    if (!empty($join[$format]) && is_array($join[$format])) {
                        $output .= $this->parseJoin($join, $values);
                    }
                    if (!empty($join[$type])) {
                        $output .= $format . $this->parseTable($join[$type], $values, $join['as'] ?? (is_string($alias) ? $alias : null));
                        break;
                    }
                }

                if (!empty($join['on'])) {
                    if (!is_array($join['on'])) {
                        throw new Exception('Expecting array!');
                    }
                    $output .= ' ON ' . $this->sqlImplode($join['on'], $values, ' AND ');
                }
                elseif (!empty($join['using'])) {
                    $output .= ' USING(' . $this->implodeFields($join['using']) . ')';
                }
            }
        }
        return $output;
    }

    /**
     * Create a query-ready string for a table and it's joins.
     *
     * @param string|array $table
     *   The table name or table with join data.
     * @param array $values
     *   The PDO replacement variables.
     *
     * @return string
     *   The query-ready string for the table and it's joins.
     *
     * @throws Exception
     */
    protected function parseTable($table, &$values, $alias = null) {
        if (is_string($table)) {
            // A simple table as alias.
            $output = '`' . $table . '`';
            if (!empty($alias)) {
                $output .= 'AS `' . $alias . '`';
            }
        }
        else {
            if (empty($alias) && !empty($table['as'])) {
                $alias = $table['as'];
            }

            // If this is a 1 item array with an alias as a key:
            if (count($table) == 1 && empty($table['from'])) {
                $alias = key($table);
                $table = current($table);
                if (is_array($table)) {
                    // This must be the new experimental format.
                    $output = '(' . $this->parseQuery($table, $values) . ') AS `' . $alias . '`';
                } else {
                    $output = '`' . $table . '` AS `' . $alias . '`';
                }
            }

            // If there is an alias, then it's a subquery that needs to be wrapped.
            elseif (!empty($alias)) {
                $output = $this->parseQuery($table, $values);
                $output = '(' . $output . ') AS `' . $alias . '`';
            }

            // If this is a full array without an alais.
            else {
                $output = $this->parseTable($table['from'], $values);
                if (!empty($table['join'])) {
                    $output .= ' ' . $this->parseJoin($table['join'], $values);
                }
            }
        }
        return $output;
    }

    /**
     * Run a select query and return a result object.
     *
     * @param array|string $table
     *   The table and optionally joins.
     * @param array $where
     *   A list of conditions for the query.
     * @param array $fields
     *   A list of fields to select.
     * @param string $final
     *   A final string to append to the query, such as limit and sort.
     *
     * @return PDOStatement
     *   The query results.
     *
     * @throws Exception
     */
    public function select($table, $where = [], $fields = [], $final = '') {
        $this->_select($table, $where, $fields, null, $final);
        $this->timerEnd();
        return $this->result;
    }

    public function selectQuery($query) {
        $values = [];
        $parsed = $this->parseQuery($query, $values);
        $this->query($parsed, $values);
        $this->timerEnd();
        return $this->result;
    }

    /**
     * Run a select query and return a result array.
     *
     * @param array|string $table
     *   The table and optionally joins.
     * @param array $where
     *   A list of conditions for the query.
     * @param array $fields
     *   A list of fields to select.
     * @param string $final
     *   A final string to append to the query, such as limit and sort.
     *
     * @return array
     *   The query results.
     *
     * @throws Exception
     */
    public function selectAll($table, $where = [], $fields = [], $final = '') {
        $this->_select($table, $where, $fields, null, $final);
        $result = $this->result->fetchAll(PDO::FETCH_ASSOC);
        $this->timerEnd();
        return $result;
    }

    public function selectAllQuery($query) {
        $values = [];
        $parsed = $this->parseQuery($query, $values);
        $this->query($parsed, $values);
        if (!empty($query['indexed_by'])) {
            $result = [];
            while ($row = $this->result->fetch(PDO::FETCH_ASSOC)) {
                $result[$row[$query['indexed_by']]] = $row;
            }
        } else {
            $result = $this->result->fetchAll(PDO::FETCH_ASSOC);
        }
        $this->timerEnd();
        return $result;
    }

    /**
     * Run a select query and return the rows indexed by a key.
     *
     * @param array|string $table
     *   The table and optionally joins.
     * @param string $key
     *   The column to use as the array index.
     * @param array $where
     *   A list of conditions for the query.
     * @param array $fields
     *   A list of fields to select.
     * @param string $final
     *   A final string to append to the query, such as limit and sort.
     *
     * @return array
     *   The query results keyed by $key.
     *
     * @throws Exception
     */
    public function selectIndexed($table, $key, $where = [], $fields = [], $final = '') {
        $this->_select($table, $where, $fields, NULL, $final);
        $results = [];
        // TODO: This is built in to PDO.
        while ($row = $this->result->fetch(PDO::FETCH_ASSOC)) {
            $results[$row[$key]] = $row;
        }
        $this->timerEnd();
        return $results;
    }

    /**
     * Select just a single row.
     *
     * @param array|string $table
     *   The table and optionally joins.
     * @param array $where
     *   A list of conditions for the query.
     * @param array $fields
     *   A list of fields to select.
     * @param string $final
     *   A final string to append to the query, such as limit and sort.
     *
     * @return array
     *   A single row from the database.
     *
     * @throws Exception
     */
    public function selectRow($table, $where = [], $fields = [], $final = '') {
        $this->_select($table, $where, $fields, 1, $final);
        $this->timerEnd();
        return $this->result->fetch(PDO::FETCH_ASSOC);
    }

    public function selectRowQuery($query) {
        $values = [];
        $query['limit'] = 1;
        $parsed = $this->parseQuery($query, $values);
        $this->query($parsed, $values);
        $this->timerEnd();
        return $this->result->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Select a single column.
     *
     * @param string $table
     *   The main table to select from.
     * @param string $column
     *   The column to select.
     * @param array $where
     *   Conditions.
     * @param string $key
     *   A field to index the column.
     * @param string $final
     *   Additional query data.
     *
     * @return array
     *   All values from the column.
     *
     * @throws Exception
     */
    public function selectColumn($table, $column, $where = [], $key = NULL, $final = '') {
        $fields = [$column];
        if ($key) {
            array_unshift($fields, $key);
        }
        $this->_select($table, $where, $fields, NULL, $final);
        if ($key) {
            $output = $this->result->fetchAll(PDO::FETCH_KEY_PAIR);
        } else {
            $output = $this->result->fetchAll(PDO::FETCH_COLUMN);
        }
        $this->timerEnd();
        return $output;
    }

    public function selectColumnQuery($query) {
        $values = [];
        $parsed = $this->parseQuery($query, $values);
        $this->query($parsed, $values);
        if (is_array($query['select']) && count($query['select']) == 2) {
            $output = $this->result->fetchAll(PDO::FETCH_KEY_PAIR);
        } else {
            $output = $this->result->fetchAll(PDO::FETCH_COLUMN);
        }
        $this->timerEnd();
        return $output;
    }

    /**
     * Select a single column from the first row.
     *
     * @param string|array $field
     *   The column to select from.
     * @param array|string $table
     *   The table and optionally joins.
     * @param array $where
     *   A list of conditions for the query.
     * @param string $final
     *   A final string to append to the query, such as limit and sort.
     *
     * @return mixed
     *   A single field value.
     *
     * @throws Exception
     */
    public function selectField($field, $table, $where = [], $final = '') {
        if (!is_array($field)) {
            $field = [$field => $field];
        }
        $row = $this->selectRow($table, $where, $field, $final);

        if (!empty($row)) {
            reset($field);
            return $row[key($field)];
        }

        return null;
    }

    public function selectFieldQuery($query, $field) {
        $row = $this->selectRowQuery($query);
        if (!empty($row)) {
            reset($row);
            return $row[$field];
        }

        return null;
    }

    /**
     * Gets the number of affected rows from the last query.
     *
     * @return integer
     *   The number of rows that were affected.
     */
    public function affectedRows() {
        return $this->result->rowCount();
    }

    /**
     * Stars a db transaction.
     */
    public function startTransaction() {
        $this->query("BEGIN");
        $this->query("SET autocommit=0");
        $this->inTransaction = true;
    }

    /**
     * Ends a db transaction.
     */
    public function commitTransaction() {
        $this->query("COMMIT");
        $this->query("SET autocommit=1");
        $this->inTransaction = false;
    }

    /**
     * Terminates a transaction and rolls back to the previous state.
     */
    public function killTransaction() {
        $this->query("ROLLBACK");
        $this->query("SET autocommit=1");
        $this->inTransaction = false;
    }

    /**
     * Determine if the connection is currently in a transactional state.
     *
     * @return boolean
     *   Whther the current connection is in a transaction.
     */
    public function inTransaction() {
        return $this->inTransaction;
    }

    /**
     * Convert an order array into a query string.
     *
     * @param array $order
     *   A list of fields and their order.
     *
     * @return string
     *   SQL ready string.
     */
    protected function implodeOrder($order) {
        $output = ' ORDER BY ';
        foreach ($order as $field => $direction) {
            $output .= '`' . $field . '` ' . $direction;
        }
        return $output;
    }

    /**
     * Implode a join from the name, table, condition, etc.
     *
     * @param string $joinType
     *   LEFT JOIN, JOIN, RIGHT JOIN, INNER JOIN
     * @param string|array $table
     *   The table criteria
     * @param string $condition
     *   Including USING or ON
     * @param array $values
     *   The PDO replacement variables.
     *
     * @return string
     *   The SQL query segment.
     *
     * @throws Exception
     */
    protected function implodeJoin($joinType, $table, $condition, &$values, $alias = null) {
        return ' ' . $joinType . ' ' . $this->parseTable($table, $values, $alias) . ' ' . $condition;
    }

    /**
     * Convert a list of fields into a string.
     *
     * @param array $fields
     *   A list of fields and their aliases to retrieve.
     * @param boolean $use_alias
     *   Whether to use an alias. This should be disabled in the event of expressions in an ORDER BY query.
     *
     * @return string
     *   The SQL query segment.
     */
    protected function implodeFields($fields, $use_alias = true) {
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        foreach ($fields as $alias => &$field) {
            $current = null;
            if (is_array($field)) {
                $current = current($field);
            }
            if (!empty($current) && !empty($field['expression'])) {
                // Format of ['count' => ['expression' => 'COUNT(*)'))
                $field = $field['expression'];
                if ($use_alias) {
                    $field .= ' AS ' . $this->formatField($alias);
                }
            }
            elseif (!empty($field) && is_array($field)) {
                // Format of ['table' => ['column1', 'column2'))
                // Or ['table' => ['alias' => 'column'))
                // Or [0 => ['table' => ['column1', 'alias' => 'column2')))
                $table = $alias;
                $table_field_list = $this->implodeTableFields($table, $field);
                $field = implode(', ', $table_field_list);
            }
            else {
                if (!empty($current)) {
                    $alias = key($field);
                    $field = $current;
                }
                $field = $this->formatField($field);

                if (!empty($alias) && !is_numeric($alias) && $use_alias) {
                    // Format of ['alias' => 'column') to column as `alias`.
                    $field .= ' AS ' . $this->formatField($alias);
                }
            }
        }
        return empty($fields) ? '*' : implode(', ', $fields);
    }

    /**
     * Implode tables and fields wrapped in an array.
     *
     * @param string $first
     *   The first param, either the table name or an unused index.
     * @param array $seconds
     *   The sub fields of the table.
     *
     * @return array
     *   A list of formatted fields.
     */
    protected function implodeTableFields($first, $seconds) {
        $output = [];
        foreach ($seconds as $second => $third) {
            if (is_array($third)) {
                // [0 => ['table' => ['column1', 'alias' => 'column2')))
                $output = array_merge($output, $this->implodeTableFields($second, $third));
            } elseif (is_numeric($second)) {
                // Format of ['table' => ['column'))
                if ($third == '*') {
                    $output[] = "`{$first}`.*";
                } else {
                    $output[] = "`{$first}`.`{$third}`";
                }
            } else {
                // Format of ['table' => ['alias' => 'column'))
                $output[] = "`{$first}`.`{$third}` AS `{$second}`";
            }
        }
        return $output;
    }

    /**
     * Convert a field to a valid SQL reference.
     *
     * @param string $field
     *   The field as submitted in the query.
     *
     * @return string
     *   The field ready for SQL.
     */
    protected function formatField($field) {
        $table = '';

        // Add the table if there is one.
        $field = explode('.', $field);
        if (count($field) == 1) {
            $field = $field[0];
        } elseif (count($field)  == 2) {
            $table = '`' . $field[0] . '`.';
            $field = $field[1];
        }

        // Add the field.
        if ($field == '*') {
            return $table . '*';
        } else {
            return $table . '`' . $field . '`';
        }
    }

    /**
     * Build a list of values by imploding an array.
     *
     * @param $array
     *   The field => value pairs.
     * @param $values
     *   The current list of replacement values.
     * @param string $glue
     *   The string used to concatenate (usually , or AND or OR)
     * @param boolean $setting
     *   If we are setting variables. (Helps in determining what to do with null values)
     *
     * @return string
     *   The query string segment.
     *
     * @throws Exception
     */
    public function sqlImplode($array, &$values, $glue = ', ', $setting = false) {
        $a2 = [];
        if (!is_array($array)) {
            $array = [$array];
        }
        foreach ($array as $field => $v) {
            if (is_numeric($field) && empty($v['expression'])) {
                if ($subImplode = $this->sqlImplode($v, $values, ' AND ')) {
                    $a2[] = $subImplode;
                }
            }

            // This might change from an and to an or.
            if ($field === '#operator') {
                $glue = $v;
                continue;
            }
            // This is if and AND/OR is explicitly grouped.
            elseif (($field === '#OR' || $field === '#AND') && !empty($v)) {
                if ($subImplode = $this->sqlImplode($v, $values, ' ' . str_replace('#', '', $field) . ' ')) {
                    $a2[] = '(' . $subImplode . ')';
                }
                continue;
            }

            if (!is_numeric($field)) {
                $field = $this->formatField($field);
            }

            // If the value is an array.
            if (is_array($v)) {
                // Value is an expression.
                if (!empty($v['expression'])) {
                    $expression_values = !empty($v['vars']) ? $v['vars'] : [];
                    $expression = is_array($v['expression']) ? $this->parseQuery($v['expression'], $expression_values) : $v['expression'];
                    if (is_numeric($field)) {
                        // There is no name, this expression should contain it's own equations.
                        $a2[] = $expression;
                    } else {
                        // Check a field equal to an expression.
                        $a2[] = "{$field} = {$expression}";
                    }
                    // Add any vars.
                    if (!empty($expression_values)) {
                        if (is_array($expression_values)) {
                            $values = array_merge($values, $expression_values);
                        } else {
                            $values[] = $expression_values;
                        }
                    }
                }
                // If this is inserting a bit column.
                if (!empty($v['bit'])) {
                    $a2[] = "{$field} = b?";
                    $values[] = $v['bit'];
                }
                // IN operator.
                elseif (!empty($v[0]) && is_string($v[0])) {
                    switch (strtoupper($v[0])) {
                        case 'IN':
                            // The IN list is empty, so the set should be empty.
                            if (empty($v[1])) {
                                $a2[] = 'false';
                                break;
                            }
                        case 'NOT IN':
                            // The NOT IN list is empty, all results apply.
                            // Add the IN or NOT IN query.
                            if (empty($v[1])) {
                                break;
                            }
                            $values = array_merge($values, array_values($v[1]));
                            $a2[] = "{$field} {$v[0]} (" . implode(array_fill(0, count($v[1]), '?'), ",") . ")";
                            break;
                        case 'BETWEEN':
                            $a2[] = "{$field} BETWEEN ? AND ? ";
                            $values[] = $v[1];
                            $values[] = $v[2];
                            break;
                        case 'IS NULL':
                        case 'IS NOT NULL':
                            $a2[] = "{$field} {$v[0]} ";
                            break;
                        case '!=':
                        case '<':
                        case '<=':
                        case '>':
                        case '>=':
                        case 'LIKE':
                            $a2[] = "{$field} {$v[0]} ? ";
                            $values[] = $v[1];
                            break;
                        // Where a specific bit us set.
                        case '&':
                            $a2[] = "{$field} & ? = ?";
                            $values[] = $v[1];
                            $values[] = isset($v[2]) ? $v[2] : $v[1];
                            break;
                        // Where a specific bit is not set.
                        case '!&':
                            $a2[] = "{$field} & ? != ?";
                            $values[] = $v[1];
                            $values[] = $v[1];
                            break;
                        case '-=':
                            $v[1] = -$v[1];
                        case '+=':
                            $a2[] = "{$field} = {$field} + ?";
                            $values[] = $v[1];
                            break;
                        default:
                            $a2[] = $field . '=' . $this->formatField($v[0]);
                    }
                }
            }
            elseif ($v === null) {
                if ($setting) {
                    $a2[] = "{$field} = NULL ";
                } else {
                    $a2[] = "{$field} IS NULL ";
                }
            }
            else {
                // Standard key/value column = value.
                $values[] = $v;
                $a2[] = "{$field} = ? ";
            }
        }
        return implode($glue, $a2);
    }

    /**
     * Create a new table.
     *
     * @param string $table
     *   The table name.
     * @param array $columns
     *   The columns to add.
     * @param array $indexes
     *   The indexes to add.
     *
     * @throws Exception
     */
    public function createTable($table, $columns, $indexes) {
        $primary_added = false;

        // Find the primary column if there is only 1.
        $primary_column = null;
        if (empty($indexes['primary'])) {
            $primary_column = null;
        }
        if (!empty($indexes['primary']) && is_string($indexes['primary'])) {
            $primary_column = $indexes['primary'];
        }
        elseif (!empty($indexes['primary']['columns'])) {
            if (count($indexes['primary']['columns']) == 1
                && (!isset($indexes['primary']['auto_increment']) || empty($indexes['primary']['auto_increment']))) {
                $primary_column = $indexes['primary']['columns'][0];
            }
        }

        $definitions = [];
        foreach ($columns as $column => $settings) {
            $definitions[] = $this->getColumnDefinition($column, $settings, $primary_column == $column);
            if ($primary_column == $column) {
                $primary_added = true;
            }
        }

        foreach ($indexes as $index => $settings) {
            if ($primary_added && $index == 'primary') {
                // The primary key was already added with the column.
                continue;
            }
            $definitions[] = $this->getIndexDefinition($index, $settings);
        }

        $query = "CREATE TABLE {$table} (" . implode(',', $definitions) . ') ENGINE=InnoDB;';

        $this->query($query);
    }

    /**
     * Add a new column to an existing table.
     *
     * @param string $table
     *   The existing table.
     * @param string $column
     *   The name of the new column.
     * @param array $settings
     *   The column definition.
     * @param null|string|boolean $position
     *   TRUE to add the column to the beginning of the table,
     *   Column name to add the column after another column,
     *   FALSE or NULL to add the column to the end of the table.
     *
     * @throws Exception
     */
    public function addColumn($table, $column, $settings, $position = null) {
        $query = 'ALTER TABLE ' . $this->parseTable($table);
        $query .= ' ADD COLUMN ' . $this->getColumnDefinition($column, $settings);
        if ($position === true) {
            $query .= ' FIRST';
        } elseif (!empty($position)) {
            $query .= ' AFTER `' . $position . '`';
        }
        $this->query($query);
    }

    /**
     * Create a column definition for adding to a table.
     *
     * @param string $name
     *   The name of the column.
     * @param array $settings
     *   The definition of the column.
     * @param boolean $primary
     *   Whether this column should be the primary key.
     *
     * @return string
     *   The column definition.
     */
    protected function getColumnDefinition($name, $settings, $primary = false) {
        $definition = "`{$name}` ";

        $definition .= $settings['type'];
        if (!empty($settings['size'])) {
            $definition .= "({$settings['size']})";
        }

        if (!empty($settings['unsigned'])) {
            $definition .= ' UNSIGNED ';
        }

        if (empty($settings['null'])) {
            $definition .= ' NOT NULL ';
        } else {
            $definition .= ' NULL ';
        }

        if (!empty($settings['auto_increment']) || $primary) {
            $definition .= ' PRIMARY KEY ';

            if (!empty($settings['auto_increment'])) {
                $definition .= 'AUTO_INCREMENT';
            }
        }

        return $definition;
    }

    /**
     * Create an index definition to add to a table.
     *
     * @param string $name
     *   The index name.
     * @param array $settings
     *   The index definition.
     *
     * @return string
     *   The index definition.
     */
    protected function getIndexDefinition($name, $settings) {
        // Figure out the columns.
        if (is_array($settings['columns'])) {
            $columns = $settings['columns'];
        }
        elseif (is_string($settings['columns'])) {
            $columns = [$settings['columns']];
        }
        else {
            $columns = [$name];
        }

        if ($name == 'primary') {
            $definition = 'PRIMARY KEY ';
        } else {
            $definition = (empty($settings['unique']) ? 'INDEX ' : 'UNIQUE INDEX ') . '`' . $name . '`';
        }
        $definition .= ' (`' . implode('`,`', $columns) . '`)' ;
        if (!empty($settings['size'])) {
            $definition .= ' KEY_BLOCK_SIZE = ' . intval($settings['size']);
        }
        return $definition;
    }

    /**
     * Check if a table exists.
     *
     * @param string $table
     *   The name of the table.
     *
     * @return boolean
     *
     * @throws Exception
     */
    public function tableExists($table) {
        return $this->query('SHOW TABLES LIKE ?', [$table])->rowCount() == 1;
    }

    /**
     * Create a search condition that has all of the values in at least one of the fields.
     *
     * @param $fields
     *   The fields to search.
     * @param $values
     *   The values to look for.
     * @param int $wildcard
     *   Whether to add wildcards and where.
     *
     * @return array
     *   A where condition.
     */
    public static function getMultiFieldSearch($fields, $values, $wildcard = self::WILDCARD_AFTER) {
        $where = [];
        // where field_1 like a or field_2 like a or field_3 like a
        // AND field_1 like b or field_2 like b or field_3 like b
        foreach ($values as $v) {
            $wv = self::addWildCards($v, $wildcard);
            $set = ['#OR' => []];
            foreach ($fields as $f) {
                $set['#OR'][$f] = ['LIKE', $wv];
            }
            $where[] = $set;
        }
        return $where;
    }

    public static function addWildCards($value, $wildcard = self::WILDCARD_AFTER) {
        switch ($wildcard) {
            case self::WILDCARD_NONE:
                return $value;
            case self::WILDCARD_BEFORE:
                return '%' . $value;
            case self::WILDCARD_AFTER:
                return $value . '%';
            case self::WILDCARD_EITHER:
                return '%' . $value . '%';
        }
    }

    /**
     * Merges filters from a second query into a first.
     * TODO: This is implemented in the Filter class. does it need to be updated or referenced?
     *
     * @param array $start_query
     * @param array $filter_query
     */
    public static function filterQuery(&$start_query, $filter_query) {
        // Merge checking for duplicates.
        if (!empty($filter_query['join'])) {
            // Make sure both joins are wrapped in arrays.
            reset($start_query['join']);
            if (!is_numeric(key($start_query['join']))) {
                $start_query['join'] = [$start_query['join']];
            }
            reset($filter_query['join']);
            if (!is_numeric(key($filter_query['join']))) {
                $filter_query['join'] = [$filter_query['join']];
            }

            foreach ($filter_query['join'] as $join) {
                $match = false;
                foreach ($start_query['join'] as $j) {
                    if ($join == $j) {
                        $match = true;
                    }
                }
                if (!$match) {
                    $start_query['join'][] = $join;
                }
            }
        }

        // Merge where queries.
        if (!empty($filter_query['where'])) {
            if (empty($start_query['where'])) {
                $start_query['where'] = [];
            }
            $start_query['where'] = array_merge($start_query['where'], $filter_query['where']);
        }

        // Make sure the correct fields are added.
        if (!empty($filter_query['select'])) {
            if (empty($start_query['select'])) {
                $start_query['select'] = [];
            }
            if (!is_array($filter_query['select'])) {
                $filter_query['select'] = [$filter_query['select']];
            }
            $final_select = [];
            foreach ([$start_query['select'], $filter_query['select']] as $query) {
                foreach ($query as $table => $fields) {
                    // If this is a numeric key, it should not be an array.
                    if (is_numeric($table) && $fields == '*') {
                        $final_select = ['*'];
                        break 2;
                    }
                    if (!is_numeric($table)) {
                        foreach ($fields as $field) {
                            if ($field == '*') {
                                $final_select[$table] = ['*'];
                            } elseif (!in_array('*', $final_select[$table]) && !in_array($field, $final_select[$table])) {
                                $final_select[$table] = $field;
                            }
                        }
                    }
                }
            }
            $start_query['select'] = $final_select;
        }
    }
}
