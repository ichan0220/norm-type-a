<?php
/**
 * Norm - (not) ORM Framework
 *
 * MIT LICENSE
 *
 * Copyright (c) 2013 PT Sagara Xinix Solusitama
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author      Ganesha <reekoheek@gmail.com>
 * @copyright   2013 PT Sagara Xinix Solusitama
 * @link        http://xinix.co.id/products/norm
 * @license     https://raw.github.com/xinix-technology/norm/master/LICENSE
 * @package     Norm\PDO
 *
 */
namespace Norm\Cursor;

use Norm\Collection;

// FIXME reekoheek: see OCICursor
/**
 * Wrapper to PDO statement to produce cursor for Norm
 * @author Ganesha <reekoheek@gmail.com>
 */
class PDOCursor implements ICursor
{

    // FIXME reekoheek cursor cannot reset statement result to foreach multiple
    // times
    protected $criteria;

    /**
     * PDO statement
     * @var \PDOStatement
     */
    protected $statement;

    /**
     * Single fetch of current row from PDO statement
     * @var array
     */
    protected $current;

    /**
     * Limit fetched data from database
     *
     * @var int
     */
    protected $limit;

    /**
     * Skip the cursor by n number
     *
     * @var int
     */
    protected $skip;

    /**
     * Construct cursor for particular statement
     * @param \PDOStatement $statement PDO statement
     */
    public function __construct(Collection $collection)
    {
        $this->collection = $collection;

        $this->criteria = $this->prepareCriteria($collection->criteria ?: array());

        $this->row = 0;
    }

    /**
     * Get valid next row if available
     * @return array NULL if not available
     */
    public function getNext()
    {
        if ($this->valid()) {
            return $this->current();
        }
    }

    /**
     * Get current row
     * @return array
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * Move to next row
     */
    public function next()
    {
        $this->row++;
    }

    /**
     * Get current key for row
     * @return int Current row key
     */
    public function key()
    {
        return $this->row;
    }

    /**
     * Check if current row is available
     * @return bool
     */
    public function valid()
    {
        $this->current = $this->getStatement()->fetch(\PDO::FETCH_ASSOC);

        $valid = false;
        if ($this->current !== false) {
            $valid = true;

            $this->current = array_change_key_case($this->current, CASE_LOWER);
        }

        return $valid;
    }

    public function prepareCriteria($criteria)
    {
        if (isset($criteria['$id'])) {
            $criteria['id'] = $criteria['$id'];
            unset($criteria['$id']);
        }

        return $criteria;
    }

    public function getStatement()
    {
        if (is_null($this->statement)) {

            $sql = 'SELECT * FROM '. $this->collection->name;

            $wheres = array();
            $data = array();

            foreach ($this->criteria as $key => $value) {
                $wheres[] = $a= $this->collection->connection->getDialect()->grammarExpression(
                    $key,
                    $value,
                    $this->collection,
                    $data
                );
            }

            if (count($wheres)) {
                $sql .= ' WHERE '.implode(' AND ', $wheres);
            }

            if ($this->limit) {
                $sql .= ' LIMIT '.$this->limit;
            }

            if ($this->skip) {
                if (! $this->limit) {
                    $sql .= ' LIMIT '.$this->count();
                }
                $sql .= ' OFFSET '.$this->skip;
            }

            // var_dump($sql);
            $this->statement = $this->collection->connection->getRaw()->prepare($sql);

            $this->statement->execute($data);
        }

        return $this->statement;
    }

    /**
     * Rewind to the first row
     * Do nothing because PDOStatement cannot be rewinded
     */
    public function rewind()
    {
        // noop
    }

    public function sort(array $fields = array())
    {
        if (!empty($fields)) {
            throw new \Exception('Not implemented yet!');
        }

        return $this;
    }

    public function count($foundOnly = false)
    {

        $sql = 'SELECT COUNT(1) AS c FROM '. $this->collection->name;

        $wheres = array();
        $data = array();
        foreach ($this->criteria as $key => $value) {
            $wheres[] = $this->collection->connection->getDialect()->grammarExpression($key, $value, $this->collection, $data);
        }

        if (count($wheres)) {
            $sql .= ' WHERE '.implode(' AND ', $wheres);
        }

        $statement = $this->collection->connection->getRaw()->prepare($sql);

        $statement->execute($data);

        return $statement->fetch(\PDO::FETCH_OBJ)->c;
    }

    public function limit($num = null)
    {
        if (func_num_args() === 0) {
            return $this->limit;
        }

        $this->limit = (int) $num;

        return $this;
    }

    public function match($q)
    {
        if (is_null($q)) {
            return $this;
        }

        throw new \Exception('Unimplemented yet!');

        // $orCriteria = array();

        // $schema = $this->collection->schema();
        // foreach ($schema as $key => $value) {
        //     $orCriteria[] = array($key => array('$regex' => new \MongoRegex("/$q/i")));
        // }
        // $this->criteria = array('$or' => $orCriteria);

        // return $this;
    }

    public function skip($num = null)
    {
        if (func_num_args() === 0) {
            return $this->skip;
        }
        $this->skip = (int) $num;

        return $this;
    }
}
