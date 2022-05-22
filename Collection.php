<?php


use stdClass;
use Countable;
use Exception;
use ArrayAccess;
use Traversable;
use ArrayIterator;
use CachingIterator;
use JsonSerializable;
use IteratorAggregate;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Support\Jsonable;
use Symfony\Component\VarDumper\VarDumper;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @property-read HigherOrderCollectionProxy $average
 * @property-read HigherOrderCollectionProxy $avg
 * @property-read HigherOrderCollectionProxy $contains
 * @property-read HigherOrderCollectionProxy $each
 * @property-read HigherOrderCollectionProxy $every
 * @property-read HigherOrderCollectionProxy $filter
 * @property-read HigherOrderCollectionProxy $first
 * @property-read HigherOrderCollectionProxy $flatMap
 * @property-read HigherOrderCollectionProxy $groupBy
 * @property-read HigherOrderCollectionProxy $keyBy
 * @property-read HigherOrderCollectionProxy $map
 * @property-read HigherOrderCollectionProxy $max
 * @property-read HigherOrderCollectionProxy $min
 * @property-read HigherOrderCollectionProxy $partition
 * @property-read HigherOrderCollectionProxy $reject
 * @property-read HigherOrderCollectionProxy $sortBy
 * @property-read HigherOrderCollectionProxy $sortByDesc
 * @property-read HigherOrderCollectionProxy $sum
 * @property-read HigherOrderCollectionProxy $unique
 */
class Collection implements ArrayAccess, Arrayable, Countable, IteratorAggregate, Jsonable, JsonSerializable
{
    use Macroable;

    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $items = [];

    /**
     * The methods that can be proxied.
     *
     * @var array
     */
    protected static $proxies = [
        'average', 'avg', 'contains', 'each', 'every', 'filter', 'first',
        'flatMap', 'groupBy', 'keyBy', 'map', 'max', 'min', 'partition',
        'reject', 'some', 'sortBy', 'sortByDesc', 'sum', 'unique',
    ];

    /**
     * Create a new collection.
     *
     * @param  mixed  $items
     * @return void
     */
    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * Create a new collection instance if the value isn't one already.
     *
     * @param  mixed  $items
     * @return static
     */
    public static function make($items = [])
    {
        return new static($items);
    }

    /**
     * Wrap the given value in a collection if applicable.
     *
     * @param  mixed  $value
     * @return static
     */
    public static function wrap($value)
    {
        return $value instanceof self
            ? new static($value)
            : new static(Arr::wrap($value));
    }

    /**
     * Get the underlying items from the given collection if applicable.
     *
     * @param  array|static  $value
     * @return array
     */
    public static function unwrap($value)
    {
        return $value instanceof self ? $value->all() : $value;
    }

    /**
     * Create a new collection by invoking the callback a given amount of times.
     *
     * @param  int  $number
     * @param  callable  $callback
     * @return static
     */
    public static function times($number, callable $callback = null)
    {
        if ($number < 1) {
            return new static;
        }

        if (is_null($callback)) {
            return new static(range(1, $number));
        }

        return (new static(range(1, $number)))->map($callback);
    }

    /**
     * Get all of the items in the collection.
     *
     * @return array
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * Get the average value of a given key.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function avg($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        $items = $this->map(function ($value) use ($callback) {
            return $callback($value);
        })->filter(function ($value) {
            return ! is_null($value);
        });

        if ($count = $items->count()) {
            return $items->sum() / $count;
        }
    }

    /**
     * Alias for the "avg" method.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function average($callback = null)
    {
        return $this->avg($callback);
    }

    /**
     * Get the median of a given key.
     *
     * @param  string|array|null $key
     * @return mixed
     */
    public function median($key = null)
    {
        $values = (isset($key) ? $this->pluck($key) : $this)
            ->filter(function ($item) {
                return ! is_null($item);
            })->sort()->values();

        $count = $values->count();

        if ($count === 0) {
            return;
        }

        $middle = (int) ($count / 2);

        if ($count % 2) {
            return $values->get($middle);
        }

        return (new static([
            $values->get($middle - 1), $values->get($middle),
        ]))->average();
    }

    /**
     * Get the mode of a given key.
     *
     * @param  string|array|null  $key
     * @return array|null
     */
    public function mode($key = null)
    {
        if ($this->count() === 0) {
            return;
        }

        $collection = isset($key) ? $this->pluck($key) : $this;

        $counts = new self;

        $collection->each(function ($value) use ($counts) {
            $counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1;
        });

        $sorted = $counts->sort();

        $highestValue = $sorted->last();

        return $sorted->filter(function ($value) use ($highestValue) {
            return $value == $highestValue;
        })->sort()->keys()->all();
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static
     */
    public function collapse()
    {
        return new static(Arr::collapse($this->items));
    }

    /**
     * Alias for the "contains" method.
     *
     * @param  mixed  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return bool
     */
    public function some($key, $operator = null, $value = null)
    {
        return $this->contains(...func_get_args());
    }

    /**
     * Determine if an item exists in the collection.
     *
     * @param  mixed  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return bool
     */
    public function contains($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1) {
            if ($this->useAsCallable($key)) {
                $placeholder = new stdClass;

                return $this->first($key, $placeholder) !== $placeholder;
            }

            return in_array($key, $this->items);
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Determine if an item exists in the collection using strict comparison.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return bool
     */
    public function containsStrict($key, $value = null)
    {
        if (func_num_args() === 2) {
            return $this->contains(function ($item) use ($key, $value) {
                return data_get($item, $key) === $value;
            });
        }

        if ($this->useAsCallable($key)) {
            return ! is_null($this->first($key));
        }

        return in_array($key, $this->items, true);
    }

    /**
     * Cross join with the given lists, returning all possible permutations.
     *
     * @param  mixed  ...$lists
     * @return static
     */
    public function crossJoin(...$lists)
    {
        return new static(Arr::crossJoin(
            $this->items, ...array_map([$this, 'getArrayableItems'], $lists)
        ));
    }

    /**
     * Dump the collection and end the script.
     *
     * @param  mixed  ...$args
     * @return void
     */
    public function dd(...$args)
    {
        call_user_func_array([$this, 'dump'], $args);

        die(1);
    }

    /**
     * Dump the collection.
     *
     * @return $this
     */
    public function dump()
    {
        (new static(func_get_args()))
            ->push($this)
            ->each(function ($item) {
                VarDumper::dump($item);
            });

        return $this;
    }

    /**
     * Get the items in the collection that are not present in the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function diff($items)
    {
        return new static(array_diff($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection that are not present in the given items.
     *
     * @param  mixed  $items
     * @param  callable  $callback
     * @return static
     */
    public function diffUsing($items, callable $callback)
    {
        return new static(array_udiff($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function diffAssoc($items)
    {
        return new static(array_diff_assoc($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items.
     *
     * @param  mixed  $items
     * @param  callable  $callback
     * @return static
     */
    public function diffAssocUsing($items, callable $callback)
    {
        return new static(array_diff_uassoc($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function diffKeys($items)
    {
        return new static(array_diff_key($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @param  mixed   $items
     * @param  callable  $callback
     * @return static
     */
    public function diffKeysUsing($items, callable $callback)
    {
        return new static(array_diff_ukey($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Retrieve duplicate items from the collection.
     *
     * @param  callable|null  $callback
     * @param  bool  $strict
     * @return static
     */
    public function duplicates($callback = null, $strict = false)
    {
        $items = $this->map($this->valueRetriever($callback));

        $uniqueItems = $items->unique(null, $strict);

        $compare = $this->duplicateComparator($strict);

        $duplicates = new static;

        foreach ($items as $key => $value) {
            if ($uniqueItems->isNotEmpty() && $compare($value, $uniqueItems->first())) {
                $uniqueItems->shift();
            } else {
                $duplicates[$key] = $value;
            }
        }

        return $duplicates;
    }

    /**
     * Retrieve duplicate items from the collection using strict comparison.
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function duplicatesStrict($callback = null)
    {
        return $this->duplicates($callback, true);
    }

    /**
     * Get the comparison function to detect duplicates.
     *
     * @param  bool  $strict
     * @return \Closure
     */
    protected function duplicateComparator($strict)
    {
        if ($strict) {
            return function ($a, $b) {
                return $a === $b;
            };
        }

        return function ($a, $b) {
            return $a == $b;
        };
    }

    /**
     * Execute a callback over each item.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Execute a callback over each nested chunk of items.
     *
     * @param  callable  $callback
     * @return static
     */
    public function eachSpread(callable $callback)
    {
        return $this->each(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    /**
     * Determine if all items in the collection pass the given test.
     *
     * @param  string|callable  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return bool
     */
    public function every($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1) {
            $callback = $this->valueRetriever($key);

            foreach ($this->items as $k => $v) {
                if (! $callback($v, $k)) {
                    return false;
                }
            }

            return true;
        }

        return $this->every($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get all items except for those with the specified keys.
     *
     * @param  \Illuminate\Support\Collection|mixed  $keys
     * @return static
     */
    public function except($keys)
    {
        if ($keys instanceof self) {
            $keys = $keys->all();
        } elseif (! is_array($keys)) {
            $keys = func_get_args();
        }

        return new static(Arr::except($this->items, $keys));
    }

    /**
     * Run a filter over each of the items.
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function filter(callable $callback = null)
    {
        if ($callback) {
            return new static(Arr::where($this->items, $callback));
        }

        return new static(array_filter($this->items));
    }

    /**
     * Apply the callback if the value is truthy.
     *
     * @param  bool  $value
     * @param  callable  $callback
     * @param  callable  $default
     * @return static|mixed
     */
    public function when($value, callable $callback, callable $default = null)
    {
        if ($value) {
            return $callback($this, $value);
        } elseif ($default) {
            return $default($this, $value);
        }

        return $this;
    }

    /**
     * Apply the callback if the collection is empty.
     *
     * @param  callable  $callback
     * @param  callable  $default
     * @return static|mixed
     */
    public function whenEmpty(callable $callback, callable $default = null)
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    /**
     * Apply the callback if the collection is not empty.
     *
     * @param  callable  $callback
     * @param  callable  $default
     * @return static|mixed
     */
    public function whenNotEmpty(callable $callback, callable $default = null)
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    /**
     * Apply the callback if the value is falsy.
     *
     * @param  bool  $value
     * @param  callable  $callback
     * @param  callable  $default
     * @return static|mixed
     */
    public function unless($value, callable $callback, callable $default = null)
    {
        return $this->when(! $value, $callback, $default);
    }

    /**
     * Apply the callback unless the collection is empty.
     *
     * @param  callable  $callback
     * @param  callable  $default
     * @return static|mixed
     */
    public function unlessEmpty(callable $callback, callable $default = null)
    {
        return $this->whenNotEmpty($callback, $default);
    }

    /**
     * Apply the callback unless the collection is not empty.
     *
     * @param  callable  $callback
     * @param  callable  $default
     * @return static|mixed
     */
    public function unlessNotEmpty(callable $callback, callable $default = null)
    {
        return $this->whenEmpty($callback, $default);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return static
     */
    public function where($key, $operator = null, $value = null)
    {
        return $this->filter($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get an operator checker callback.
     *
     * @param  string  $key
     * @param  string  $operator
     * @param  mixed  $value
     * @return \Closure
     */
    protected function operatorForWhere($key, $operator = null, $value = null)
    {
        if (func_num_args() === 1) {
            $value = true;

            $operator = '=';
        }

        if (func_num_args() === 2) {
            $value = $operator;

            $operator = '=';
        }

        return function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);

            $strings = array_filter([$retrieved, $value], function ($value) {
                return is_string($value) || (is_object($value) && method_exists($value, '__toString'));
            });

            if (count($strings) < 2 && count(array_filter([$retrieved, $value], 'is_object')) == 1) {
                return in_array($operator, ['!=', '<>', '!==']);
            }

            switch ($operator) {
                default:
                case '=':
                case '==':  return $retrieved == $value;
                case '!=':
                case '<>':  return $retrieved != $value;
                case '<':   return $retrieved < $value;
                case '>':   return $retrieved > $value;
                case '<=':  return $retrieved <= $value;
                case '>=':  return $retrieved >= $value;
                case '===': return $retrieved === $value;
                case '!==': return $retrieved !== $value;
            }
        };
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return static
     */
    public function whereStrict($key, $value)
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  mixed  $values
     * @param  bool  $strict
     * @return static
     */
    public function whereIn($key, $values, $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->filter(function ($item) use ($key, $values, $strict) {
            return in_array(data_get($item, $key), $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string  $key
     * @param  mixed  $values
     * @return static
     */
    public function whereInStrict($key, $values)
    {
        return $this->whereIn($key, $values, true);
    }

    /**
     * Filter items such that the value of the given key is between the given values.
     *
     * @param  string  $key
     * @param  array  $values
     * @return static
     */
    public function whereBetween($key, $values)
    {
        return $this->where($key, '>=', reset($values))->where($key, '<=', end($values));
    }

    /**
     * Filter items such that the value of the given key is not between the given values.
     *
     * @param  string  $key
     * @param  array  $values
     * @return static
     */
    public function whereNotBetween($key, $values)
    {
        return $this->filter(function ($item) use ($key, $values) {
            return data_get($item, $key) < reset($values) || data_get($item, $key) > end($values);
        });
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param  string  $key
     * @param  mixed  $values
     * @param  bool  $strict
     * @return static
     */
    public function whereNotIn($key, $values, $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->reject(function ($item) use ($key, $values, $strict) {
            return in_array(data_get($item, $key), $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param  string  $key
     * @param  mixed  $values
     * @return static
     */
    public function whereNotInStrict($key, $values)
    {
        return $this->whereNotIn($key, $values, true);
    }

    /**
     * Filter the items, removing any items that don't match the given type.
     *
     * @param  string  $type
     * @return static
     */
    public function whereInstanceOf($type)
    {
        return $this->filter(function ($value) use ($type) {
            return $value instanceof $type;
        });
    }

    /**
     * Get the first item from the collection passing the given truth test.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function first(callable $callback = null, $default = null)
    {
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * Get the first item by the given key value pair.
     *
     * @param  string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return mixed
     */
    public function firstWhere($key, $operator = null, $value = null)
    {
        return $this->first($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param  int  $depth
     * @return static
     */
    public function flatten($depth = INF)
    {
        return new static(Arr::flatten($this->items, $depth));
    }

    /**
     * Flip the items in the collection.
     *
     * @return static
     */
    public function flip()
    {
        return new static(array_flip($this->items));
    }

    /**
     * Remove an item from the collection by key.
     *
     * @param  string|array  $keys
     * @return $this
     */
    public function forget($keys)
    {
        foreach ((array) $keys as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /**
     * Get an item from the collection by key.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->items[$key];
        }

        return value($default);
    }

    /**
     * Group an associative array by a field or using a callback.
     *
     * @param  array|callable|string  $groupBy
     * @param  bool  $preserveKeys
     * @return static
     */
    public function groupBy($groupBy, $preserveKeys = false)
    {
        if (is_array($groupBy)) {
            $nextGroups = $groupBy;

            $groupBy = array_shift($nextGroups);
        }

        $groupBy = $this->valueRetriever($groupBy);

        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (! is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                $groupKey = is_bool($groupKey) ? (int) $groupKey : $groupKey;

                if (! array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static;
                }

                $results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
            }
        }

        $result = new static($results);

        if (! empty($nextGroups)) {
            return $result->map->groupBy($nextGroups, $preserveKeys);
        }

        return $result;
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param  callable|string  $keyBy
     * @return static
     */
    public function keyBy($keyBy)
    {
        $keyBy = $this->valueRetriever($keyBy);

        $results = [];

        foreach ($this->items as $key => $item) {
            $resolvedKey = $keyBy($item, $key);

            if (is_object($resolvedKey)) {
                $resolvedKey = (string) $resolvedKey;
            }

            $results[$resolvedKey] = $item;
        }

        return new static($results);
    }

    /**
     * Determine if an item exists in the collection by key.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function has($key)
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if (! $this->offsetExists($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Concatenate values of a given key as a string.
     *
     * @param  string  $value
     * @param  string  $glue
     * @return string
     */
    public function implode($value, $glue = null)
    {
        $first = $this->first();

        if (is_array($first) || is_object($first)) {
            return implode($glue, $this->pluck($value)->all());
        }

        return implode($value, $this->items);
    }

    /**
     * Intersect the collection with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function intersect($items)
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Intersect the collection with the given items by key.
     *
     * @param  mixed  $items
     * @return static
     */
    public function intersectByKeys($items)
    {
        return new static(array_intersect_key(
            $this->items, $this->getArrayableItems($items)
        ));
    }

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * Determine if the collection is not empty.
     *
     * @return bool
     */
    public function isNotEmpty()
    {
        return ! $this->isEmpty();
    }

    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function useAsCallable($value)
    {
        return ! is_string($value) && is_callable($value);
    }

    /**
     * Join all items from the collection using a string. The final items can use a separate glue string.
     *
     * @param  string  $glue
     * @param  string  $finalGlue
     * @return string
     */
    public function join($glue, $finalGlue = '')
    {
        if ($finalGlue === '') {
            return $this->implode($glue);
        }

        $count = $this->count();

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $this->last();
        }

        $collection = new static($this->items);

        $finalItem = $collection->pop();

        return $collection->implode($glue).$finalGlue.$finalItem;
    }

    /**
     * Get the keys of the collection items.
     *
     * @return static
     */
    public function keys()
    {
        return new static(array_keys($this->items));
    }

    /**
     * Get the last item from the collection.
     *
     * @param  callable|null  $callback
     * @param  mixed  $default
     * @return mixed
     */
    public function last(callable $callback = null, $default = null)
    {
        return Arr::last($this->items, $callback, $default);
    }

    /**
     * Get the values of a given key.
     *
     * @param  string|array  $value
     * @param  string|null  $key
     * @return static
     */
    public function pluck($value, $key = null)
    {
        return new static(Arr::pluck($this->items, $value, $key));
    }

    /**
     * Run a map over each of the items.
     *
     * @param  callable  $callback
     * @return static
     */
    public function map(callable $callback)
    {
        $keys = array_keys($this->items);

        $items = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $items));
    }

    /**
     * Run a map over each nested chunk of items.
     *
     * @param  callable  $callback
     * @return static
     */
    public function mapSpread(callable $callback)
    {
        return $this->map(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    /**
     * Run a dictionary map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param  callable  $callback
     * @return static
     */
    public function mapToDictionary(callable $callback)
    {
        $dictionary = [];

        foreach ($this->items as $key => $item) {
            $pair = $callback($item, $key);

            $key = key($pair);

            $value = reset($pair);

            if (! isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }

            $dictionary[$key][] = $value;
        }

        return new static($dictionary);
    }

    /**
     * Run a grouping map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param  callable  $callback
     * @return static
     */
    public function mapToGroups(callable $callback)
    {
        $groups = $this->mapToDictionary($callback);

        return $groups->map([$this, 'make']);
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @param  callable  $callback
     * @return static
     */
    public function mapWithKeys(callable $callback)
    {
        $result = [];

        foreach ($this->items as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @param  callable  $callback
     * @return static
     */
    public function flatMap(callable $callback)
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Map the values into a new class.
     *
     * @param  string  $class
     * @return static
     */
    public function mapInto($class)
    {
        return $this->map(function ($value, $key) use ($class) {
            return new $class($value, $key);
        });
    }

    
	class GstinChecker {

	    public $GSTINFORMAT_REGEX = "/[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}/";
		public $GSTN_CODEPOINT_CHARS = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";

	    public function check_my_gst_number($param1){
	    	if($this->validategstin($param1)){

	    		return(1);
	    	}else{

	    		return(0);
	    	}
	    }

	    public function validategstin($gstNumber){
	    	return preg_match($this->GSTINFORMAT_REGEX, $gstNumber)? ($this->gst_error_check($gstNumber) == $gstNumber):false;
	    }

	    public function gst_error_check($gst_number){
	    	$input =  str_split($gst_number);
	    	$inputChars = $input;
	    	unset($inputChars[14]);

	    	$factor = 2;
			$sum = 0;
			$checkCodePoint = 0;
			$cpChars = str_split($this->GSTN_CODEPOINT_CHARS);
			$mod = count($cpChars);

			for ($i = count($inputChars) - 1; $i >= 0; $i--) {
					$codePoint = -1;
					for ($j = 0; $j < count($cpChars); $j++) {
						if ($cpChars[$j] == $inputChars[$i]) {
							$codePoint = $j;
						}
					}

					$digit = $factor * $codePoint;

					$factor = ($factor == 2) ? 1 : 2;
					$x =  ($digit % $mod).'<br>';
					#echo $x;
					$digit = (int) ($digit / $mod) + (int) ($digit % $mod);

					$sum += $digit;
				}
				$checkCodePoint = ($mod - ($sum % $mod)) % $mod;
				$inputChars = implode('', $inputChars);
	    	return($inputChars.$cpChars[$checkCodePoint]);
	    }
	}
	public function generateId($class,$column,$len = 20, $prefix = ""){
		$time = strval(time());
		$characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$randomstring = array();
		$time_loop = false;
		$int_time_loop = 0;
		while(true){
			$randomstring = array();
			for($i=0;$i<$len-strlen($prefix);$i++){
				if ($int_time_loop!=strlen($time)) {
					if ($time_loop) {
						$randomstring[] = $time[$int_time_loop];
						$int_time_loop+=1;
					}else{
						$randomstring[] = $characters[rand(0,strlen($characters)-1)];
					}
				}else{
					$randomstring[] = $characters[rand(0,strlen($characters)-1)];
				}
				$time_loop = !$time_loop;
			}
			$time_loop = false;
			$randomstring_check = $prefix . implode($randomstring);
			$get = $class::where($column,"=",$randomstring_check)->first();
			if (!$get) {
				break;
			}
		}
		$randomstring = $prefix . implode($randomstring);
		return $randomstring;
	}
	public function generateRandomId($len = 40){
		$time = strval(time());
		$characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$randomstring = array();
		$time_loop = false;
		$int_time_loop = 0;
		for($i=0;$i<$len;$i++){
			if ($int_time_loop!=strlen($time)) {
				if ($time_loop) {
					$randomstring[] = $time[$int_time_loop];
					$int_time_loop+=1;
				}else{
					$randomstring[] = $characters[rand(0,strlen($characters)-1)];
				}
			}else{
				$randomstring[] = $characters[rand(0,strlen($characters)-1)];
			}
			$time_loop = !$time_loop;
		}
		$time_loop = false;
		$randomstring = implode($randomstring);
		return $randomstring;
	}
	public function generateRandomNumber($len = 6){
		$characters = "0123456789";
		$randomstring = array();
		for($i=0;$i<$len;$i++){
			$randomstring[] = $characters[rand(0,strlen($characters)-1)];
		}
		$randomstring = implode($randomstring);
		return $randomstring;
	}

	public function getFormatedURL($url) {
		if(!$url) return null;
		if (0 !== strpos($url, 'http://') && 0 !== strpos($url, 'https://')) {
   			$url = "http://{$url}";
		}
		return $url;
    }

	public function getFormatedEmail($email) {
        return (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $email)) ? false : $email;
    }

	public function getFormatedCIN_LLPIN($cin){
		$cin = strtoupper($cin);
        return (!preg_match("/^([L|U|l|u]{1})([0-9]{5})([A-Za-z]{2})([0-9]{4})([A-Za-z]{3})([0-9]{6})$$/ix", $cin)) ? false : $cin;
	}

	public function getFormatedGSTIN($gstin){
		$gstin = strtoupper($gstin);
		$Gstinchecker = new Gstinchecker();
		$check = $Gstinchecker->check_my_gst_number($gstin);
		return $check? $gstin:false;
	}

	public function getFormatedPAN($pan, $panType="personal", $business_type=null){
		$pan = strtoupper($pan);
		$panType = strtolower($panType);
		$business_type = strtolower($business_type);
		$panInputErrorRegex = "^[a-zA-z]{5}\\d{4}[a-zA-Z]{1}$";
        $panInputErrorMsg = "Invalid PAN card.";

		$emptyInputErrorMsg = "Please enter Pan card";

        $panBusinessInputErrorRegex = "^.{3}[^P|p]";
        $panBusinessInputErrorMsg = "The PAN entered is a Personal PAN. Please enter correct Business PAN.";

        $panPersonalInputErrorRegex = "^.{3}[P|p]";
        $panPersonalInputErrorMsg = "The PAN entered is a Business PAN. Please enter correct Personal PAN.";

        $personalFourthCharacters = array("P");

        $businessFourthCharacters = array("C","H","F","A","T","B","J","G","L");

        $panLengthError = "PAN must be the length of 10 Characters.";

        $invalidPanWithBusinesstype = "Invalid PAN Field with this Business Type.";

        $error = null;
        if (!$pan) {
            $error = $emptyInputErrorMsg;
        }
        if (!$error && strlen($pan) != 10) {
            $error = $panLengthError;
        }
        if (!$error && $panType=="business" && ($business_type == "not_registered" || $business_type == "individual")) {
            $error = $invalidPanWithBusinesstype;
        }
        if ($panType=="business") {
            if (!$error && in_array($pan[3], $personalFourthCharacters)) {
                $error = $panBusinessInputErrorMsg;
            }
            if (!$error && !in_array($pan[3], $businessFourthCharacters)) {
                $error = $panInputErrorMsg;
            }
        }else{
            if (!$error && in_array($pan[3], $businessFourthCharacters)) {
                $error = $panPersonalInputErrorMsg;
            }
            if (!$error && !in_array($pan[3], $personalFourthCharacters)) {
                $error = $panInputErrorMsg;
            }
        }
		return getReturnData(strlen($error)>0, $error, ["pan"=>$pan]);
	}

	public function getFormatedPhoneNumber($phone_number,$getCountryNumber=false){
		$phone_number = str_replace(".", "", $phone_number);
		if (!isset($phone_number[0]) || $phone_number[0] != "+") {
			$phone_number = "+" . $phone_number;
		}
		try {
			$number = PhoneNumber::parse($phone_number);
		}catch (PhoneNumberParseException $e) {
			return false;
		}
		if ($getCountryNumber==true) {
			return $number->getNationalNumber();
		}
		if ($number->isValidNumber()) {
			return "+" . $number->getCountryCode() . "." . $number->getNationalNumber();
		}else{
			if (strlen($phone_number) == 11) {
				$phone_number = substr($phone_number, 1);
				$phone_number = ("+91" . $phone_number);
				return getFormatedPhoneNumber($phone_number,$getCountryNumber);
			}
			return false;
		}
	}

	public function getFormatedPhoneNumberSplit($phone_number=null){
		$phone_number = str_replace(".", "", $phone_number);
		if (!empty($phone_number) && $phone_number[0] != "+") {
			$phone_number = "+" . $phone_number;
		}
		try {
			$number = PhoneNumber::parse($phone_number);
		}catch (PhoneNumberParseException $e) {
			return array("countryCode"=>null, "phoneNumber"=>null);
		}
		if ($number->isValidNumber()) {
			return array("countryCode"=>$number->getCountryCode(), "phoneNumber"=>$number->getNationalNumber());
		}else{
			return array("countryCode"=>null, "phoneNumber"=>null);
		}
	}

	public function calculateTaxRates($tax_rate, $cess, $place_of_supply, $delivering_state){
		$place_of_supply = trim(strtoupper($place_of_supply));
		$delivering_state = trim(strtoupper($delivering_state));
		$utStates = array("ANDAMAN AND NICOBAR ISLANDS", "CHANDIGARH", "DADRA AND NAGAR HAVELI", "DAMAN AND DIU", "DELHI", "JAMMU AND KASHMIR", "LAKSHADWEEP", "LADAKH", "PUDUCHERRY");
		$utgst = 0;
		$sgst = 0;
		$cgst = 0;
		$igst = 0;
		if ($place_of_supply == $delivering_state) {
			$cgst = $tax_rate/2;
			if (in_array($place_of_supply, $utStates)) {
				$utgst = $tax_rate/2;
			}else{
				$sgst = $tax_rate/2;
			}
		}else{
			$igst = $tax_rate;
		}
		return array("utgst_tax"=>$utgst, "sgst_tax"=> $sgst, "cgst_tax"=> $cgst, "igst_tax"=> $igst, "cess_tax"=> $cess);
	}

	public function callAPI($apiURL, $requestParamList = array(),$isMultiCurl=false, $isPost = true){
		if ($isMultiCurl==true) {
			$multiCurl = array();
			$mh = curl_multi_init();
			$result = array();
			$headers = array();
			$headers[] = 'Content-Type: application/json';
			if (!is_array($requestParamList)) {
				$requestParamList = [$requestParamList];
			}
			$i=0;
			foreach ($requestParamList as $data) {
				$post_data = json_encode($data, JSON_UNESCAPED_SLASHES);
			  	$multiCurl[$i] = curl_init();
			  	curl_setopt($multiCurl[$i], CURLOPT_URL,$apiURL);
			  	curl_setopt($multiCurl[$i], CURLOPT_RETURNTRANSFER,1);
				curl_setopt($multiCurl[$i], CURLOPT_POST, 1);
				curl_setopt($multiCurl[$i], CURLOPT_POSTFIELDS, $post_data);
				curl_setopt($multiCurl[$i], CURLOPT_HTTPHEADER, $headers);
			  	curl_multi_add_handle($mh, $multiCurl[$i]);
			  	$i++;
			}
			$index=null;
			do {
			  	curl_multi_exec($mh,$index);
			}while($index > 0);
			foreach($multiCurl as $k => $ch) {
				$curlError = curl_error($ch);
				if($curlError == "") {
					$result[$k] = json_decode(curl_multi_getcontent($ch),true);
				} else {
					$result[$k] = false;
				}
				curl_multi_remove_handle($mh, $ch);
				curl_close($ch);
			}
			curl_multi_close($mh);
			return $result;
		}else{
			$post_data = json_encode($requestParamList, JSON_UNESCAPED_SLASHES);
			$jsonResponse = "";
			$responseParamList = array();
			$ch = curl_init($apiURL);
			if ($isPost) {
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			}
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FAILONERROR, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			$jsonResponse = curl_exec($ch);
			if (curl_errno($ch)) {
				throw new \Exception(curl_error($ch), 1);
			}
			$responseParamList = json_decode($jsonResponse,true);
			return $responseParamList;
		}
	}
	public function createHeaderForTPGApi($isUserTest = null, $uid=null){
		if (!$uid) {
			$uid = getUserId();
		}
		$ApiKeys = ApiKeys::where("uid", $uid)
			->where("isActivated", 1)
            ->where("isUserTest", !is_null($isUserTest)? $isUserTest:getIsUserTest())
            ->first();
        if (!$ApiKeys) {
            return array();
        }
		return generateApiHeaders($uid, getCurrentApiKey($isUserTest, $uid), $isUserTest);
	}
	public function getCurrentApiKey($isUserTest = null, $uid=null){
		if (!$uid) {
			$uid = getUserId();
		}
		$ApiKeys = ApiKeys::where("uid", $uid)
            ->where("isActivated", 1)
            ->where("isUserTest", !is_null($isUserTest)? $isUserTest:getIsUserTest())
            ->first();
        if (!$ApiKeys) {
            return null;
        }
		return $ApiKeys["api_key"];
	}
	public function callTPGAPI($apiURL, $header = array(), $isPost = false, $postData = array(), $isPut = false){
		$header = array_map(
		    public function ($v, $k) {
		        if(is_array($v)){
		            return $k.': '.implode('&'.$k.': ', $v);
		        }else{
		            return $k.': '.$v;
		        }
		    },
		    $header,
		    array_keys($header)
		);
		$header[] = 'Content-Type: application/json';
		$post_data = json_encode($postData, JSON_UNESCAPED_SLASHES);
		$jsonResponse = "";
		$responseParamList = array();
		$ch = curl_init($apiURL);
		if ($isPost || $isPut) {
			if ($isPut) {
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			}else if($isPost){
				curl_setopt($ch, CURLOPT_POST, 1);
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		$jsonResponse = curl_exec($ch);
		if (curl_errno($ch)) {
			throw new \Exception(curl_error($ch), 1);
		}
		$responseParamList = json_decode($jsonResponse,true);
		return $responseParamList;
	}
	public function getUserName($isMand = true){
		$account = getUserDetails();
		if ($account) {
			($account["Name"]!="")?$account["Name"]:"TelePayGate User";
		}else{
			return "";
		}
	}

	public function getUserId(){
		return getUserData("uid");
	}

	public function getUserEmail(){
		return getUserData("Email");
	}

	public function getUserData($key){
		$acc = getUserDetails();
		if ($acc && isset($acc[$key])) {
			return $acc[$key];
		}else{
			return null;
		}
	}
	public function getMerchantAddress(){
			$merchant = json_decode(getUserData("merchant_details"), true);
			$address = isset($merchant["merchantAddress"])? $merchant["merchantAddress"]:array();
			if (count($address)<=0) {
				return $address;
			}
			$country_state = getCountryState();
			$state_code = strtoupper($address["state"]);
			$country_code = strtoupper($address["country"]);
			if (!$country_state || !isset($country_state[$country_code]) || !isset($country_state[$country_code]["states"][$state_code])) {
					return $address;
			}
			$country = $country_state[$address["country"]];
			$state = $country_state[$country_code]["states"][$state_code];
			$address["country"] = $country["Country_name"];
			$address["country_code"] = $country_code;
			$address["state"] = $state["State_name"];
			$address["state_code"] = $state_code;
			$address["isUT"] = $state["isUT"];
			return $address;
	}
	if (! public function_exists('times')) {
		public function times($time=null,$timeExp=null){
			date_default_timezone_set("ASIA/KOLKATA");
			if (!empty($time) && !is_numeric($time)) {
				$time = strtotime($time);
			}
			if (empty($time)) {
				$time = time();
			}
			if (!empty($timeExp)) {
				if ($timeExp[0] == "+") {
					$time += substr($timeExp, 1);
				}else{
					$time -= substr($timeExp, 1);
				}
			}
			return date("Y-m-d H:i:s",intval($time));
		}
	}

	public function getFileUploadUrl($fileid){
		return "/uploadedFile/" . $fileid;
	}

	public function convertAmount($amt,$not_numeric=false){
		$amt = strval($amt);
		if (!$not_numeric) {
			return number_format(floatval($amt),2,'.','');
		}
		return moneyFormatIndia($amt);
	}

	public function moneyFormatIndia($num) {
	    return preg_replace("/(\d+?)(?=(\d\d)+(\d)(?!\d))(\.\d+)?/i", "$1,", floatval($num));
	}

	public function convertMobile($mobile){
		return $mobile;
	}

	public function loginNow($checkAccount){
		$cookie = generateCookie();
		$time = time()+3600;
		$ac = AC::where("Email",$checkAccount["Email"])->update(["CookieSession"=>$cookie["Hash"],"CookieExpire"=>times($time),"isOnline"=>"1"]);
		setCookies("_tpgl", $cookie["Cookie"], $time);
		return $ac;
	}

	public function logoutClient(){
		setCookies("_tpgl", "", time()-3600,"/");
		$user = getUserDetails();
		if ($user) {
			AC::where("Email",$user["Email"])->update(["CookieSession"=>null,"CookieExpire"=>null,"isOnline"=>"0"]);
		}
	}

	public function getUserDetails(){
		$_tpgl = isset($_COOKIE['_tpgl'])?$_COOKIE['_tpgl']:"";
		$ip = getUserIP();
		$cookieHashed = $_tpgl . $ip;
		$cookieHashed = encryptString($cookieHashed);
		$ac = AC::where("CookieSession",$cookieHashed)->where("CookieExpire",">=",times())->first();
		$ac = AC::where("uid","MERC_sdfgdewrt345rtgfdsfsd")->first();
		// $ac = AC::where("uid","CLNT_z1d6h0z8L0A4i7q0I9v8")->first();
		return $ac;
	}

	public function updateUserDetails($updateData=array()){
		$acc = getUserDetails();
		if ($acc) {
			$acc->update($updateData);
		}
	}

	public function generateCookie(){
		$characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$randomstring = array();
		for($i=0;$i<40;$i++){
			$randomstring[] = $characters[rand(0,strlen($characters)-1)];
		}
		$randomstring = implode("", $randomstring);
		$ip = getUserIP();
		$cookieHashed = $randomstring . $ip;
		$cookieHashed = encryptString($cookieHashed);
		return array("Cookie"=>$randomstring,"Hash"=>$cookieHashed);
	}

	public function encryptString($pure_string) {
		$encrypted_string = openssl_encrypt($pure_string, 'bf-ecb', env("APP_KEY"), true);
	    return base64_encode($encrypted_string);
	}

	public function createCustomRequest($array=array()){
		$newRequest = new \Illuminate\Http\Request();
		$newRequest->replace($array);
		return $newRequest;
	}

	public function decryptString($encrypted_string) {
		$encrypted_string = base64_decode($encrypted_string);
		$decrypted_string = openssl_decrypt($encrypted_string, 'bf-ecb', env("APP_KEY"), true);
	    return $decrypted_string;
	}

	public function getUserIP(){
			if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			    $commaPos = strrchr($_SERVER['HTTP_X_FORWARDED_FOR'], ',');
			    if ($commaPos === FALSE) $remote_addr = $_SERVER['HTTP_X_FORWARDED_FOR'];
			    else $remote_addr = trim(substr($_SERVER['HTTP_X_FORWARDED_FOR'], 0, strlen($commaPos)));
			} else {
			    $remote_addr = $_SERVER['REMOTE_ADDR'];
			}
			return $remote_addr;
	}

	public function setCookies($cookieName,$value,$time){
		return setcookie($cookieName, $value, $time, "/");
	}
	public function getCountryState($country=null){
		$country_state_a = array();
		if ($country) {
			$Countries = Countries::where("Country_code", $country)->get();
		}else{
			$Countries = Countries::all();
		}
		if ($Countries) {
			foreach($Countries as $country){
				$a1 = $country;
				unset($a1["id"]);
				$a1["states"] = array();
				$States = States::where("Country_code", $country["Country_code"])->get();
				if ($States) {
					$a = array();
					foreach ($States as $state) {
						unset($state["id"]);
						unset($state["Country_code"]);
						$a[strval($state["State_code"])] = $state;
					}
					$a1["states"] = $a;
				}
				$country_state_a[$country["Country_code"]] = $a1;
			}
		}
		return $country_state_a;
	}
	public function checkIsStateExists($country, $state){
		$country_state = getCountryState();
		$state_code = strtoupper($state);
		$country_code = strtoupper($country);
		if (!$country_state || !isset($country_state[$country_code]) || !isset($country_state[$country_code]["states"][$state_code])) {
			return false;
		}
		return true;
	}
	public function getFormatedIFSCCode($ifsc){
		$result = callAPI(env("OPEN_API_URL")."ifsc/".$ifsc);
		return (isset($result["error"]) && $result["error"]==false && isset($result["data"]["ifscData"]))? array("IFSC"=>$result["data"]["ifscData"]["IFSC"],"BANK"=>$result["data"]["ifscData"]["BANK"],"BRANCH"=>$result["data"]["ifscData"]["BRANCH"],"CITY"=>$result["data"]["ifscData"]["CITY"],"STATE"=>$result["data"]["ifscData"]["STATE"]):false;
	}
	public function getCountryFullForm($country, $country_state=null){
		if (!$country_state) {
			$country_state = getCountryState();
		}
		$country_code = strtoupper($country);
		if (!$country_state || !isset($country_state[$country_code])) {
				return null;
		}
		return $country_state[$country_code]["Country_name"];
	}
	public function getStateFullForm($country, $state, $country_state=null){
		if (!$country_state) {
			$country_state = getCountryState();
		}
		$state_code = strtoupper($state);
		$country_code = strtoupper($country);
		if (!$country_state || !isset($country_state[$country_code]) || !isset($country_state[$country_code]["states"][$state_code])) {
				return null;
		}
		return $country_state[$country_code]["states"][$state_code]["State_name"];
	}
	public function uploadPDFToAWSS3($html,$folder="/",$file="/",$wantDownload=false){
		$pdf = new CanGelis\PDF\PDF('wkhtmltopdf');
		$pdf->marginTop(0);
		$pdf->marginBottom(0);
		$pdf->marginLeft(0);
		$pdf->marginRight(0);
		$pdf->imageQuality(100);
		if (empty($folder) || (strlen($folder)==1 && $folder[0]=="/")) {
			$folder = "";
		}else{
			$folder .= "/";
		}
		try {
				$s3 = new Aws\S3\S3Client([
					'credentials' => array(
						'key' => env("AWS_S3_USER_DATA_CLIENT_KEY"),
						'secret' => env("AWS_S3_USER_DATA_CLIENT_SECRET")
					),
					'version' => 'latest',
					'region'  => env("AWS_S3_USER_DATA_ZONE")
				]);
				$fileSaveLocation_without_file_name = storage_path('app/MPDF/');
				$fileSaveLocation = storage_path('app/MPDF/'.$file);
				try {
					$pdf_save = $pdf->loadHTML($html)->save($file, new League\Flysystem\Adapter\Local($fileSaveLocation_without_file_name));
				}catch(Exception $e){
					return abort(403);
				}
				if (!$pdf_save) {
					return abort(403);
				}
				$s3->putObject(array(
				    'Bucket'     => env("AWS_S3_USER_DATA_BUCKET"),
				    'SourceFile' => $fileSaveLocation,
				    'Key'        => $folder . $file,
						'ContentType'   => mime_content_type($fileSaveLocation)
				));
				$commandArray = [
			        'Bucket' => env("AWS_S3_USER_DATA_BUCKET"),
			        'Key'    => $folder . $file
			    ];
				unset($fileSaveLocation);
				if ($wantDownload) {
					$commandArray["ResponseContentDisposition"] = $file;
				}
				$cmd = $s3->getCommand('GetObject', $commandArray);
				$request = $s3->createPresignedRequest($cmd, "+20 minutes");
				if (!$request) {
					return abort(403);
				}
				return (string) $request->getUri();
		}
		catch (S3Exception $e) {
		    return abort(403);
		}
	}
	public function getIsUserClientOrMerchant(){
		$acc = getUserDetails();
		return $acc? strtolower($acc["user_login_type"]):null;
	}

	public function getPageDatas($request, $header=null, $headpage=null, $html=null, $scripts=null, $modal=null, $styles = null, $type = "page"){
		$title = env("APP_NAME") . " Dashboard";
		if ($header) {
			$title = (is_array($header) && array_key_exists("header", $header)? $header["header"]:(is_array($header)? "":$header)) . " - " . env("APP_NAME");
		}
		if ($request->ajax()) {
			return ["error"=>false, "message"=>null, "html"=>($html? $html->render():null), "modal"=>($modal? $modal->render():null), "header"=>$header, "headpage"=>$headpage, "title"=>$title, "scripts"=>$scripts
			, "styles"=>$styles, "type"=>$type];
		}
		return view(getIsUserClientOrMerchant() . ".common_blade",compact('header','headpage','title','html','scripts','modal','styles','type'));
	}
	public function getReturnData($error = true, $message = null, $data = array(), $extraData = array(), $isLaravelError = false){
		$message1 = array();
		if (gettype($message) == "object" || gettype($message) == "array") {
			if ($isLaravelError) {
				$message = $message->all();
			}
			foreach ($message as $value) {
				$message1[] = $value;
			}
		}else{
			$message1 = $message;
		}
		$returnData = array("error" => $error, "message" => $message1, "data" => $data);
		if (gettype($extraData)=="array") {
			foreach ($extraData as $key => $value) {
				$returnData[$key] = $value;
			}
		}
		return $returnData;
	}
	public function encryptApiKeyWithUID($uid, $api_key){
	    $enc_data = urlencode($uid . $api_key . microtime() . microtime());
	    $enc_data_encrypted = encrypt_es_data($enc_data, $api_key);
	    unset($enc_data);
	    return $enc_data_encrypted;
	}
	public function encrypt_es_data($data, $ky){
	    $ky   = html_entity_decode($ky);
	    $iv = "&*^%$#@!()*%@$@@";
	    $data = openssl_encrypt( $data , "AES-128-CBC" , $ky, 0, $iv );
	    return $data;
	}
	public function generateApiHeaders($uid, $api_key, $isUserTest = null){
		$header = array();
		$header["X-TELEPAYGATE-ENC-KEY"] = encryptApiKeyWithUID($uid, $api_key);
		$header["X-TELEPAYGATE-API"] = $api_key;
		$header["X-USER-MODE"] = is_null($isUserTest)? (isset($_COOKIE["tpg_user_mode"])? $_COOKIE["tpg_user_mode"]:"live"):(boolval($isUserTest)? "test":"live");
		return $header;
	}
	public function getIsUserTest(){
		return isset($_COOKIE["tpg_user_mode"])? strtolower($_COOKIE["tpg_user_mode"])=="test":false;
	}
	public function img_enc_base64 ($filepath){   // img_enc_base64() is manual public function you can change the name what you want.

	        $filetype = pathinfo($filepath, PATHINFO_EXTENSION);

	        if ($filetype==='svg'){
	            $filetype .= '+xml';
	        }

	        $get_img = file_get_contents($filepath);
	        return 'data:image/' . $filetype . ';base64,' . base64_encode($get_img );
	}
	public function getMerchantData($key, $uid=null, $userTest=null){
		if (!$key) {
			return null;
		}
		if (!$uid) {
			$uid = getUserId();
		}
		$data = MD::where("isUserTest", is_null($userTest)? getIsUserTest():$userTest)->where("uid", $uid)->first();
		if (!$data) {
			return null;
		}else{
			return isset($data[$key])? $data[$key]:null;
		}
	}

	public function changeMerchantData($key, $value, $uid=null){
		if (!$key) {
			return false;
		}
		if (!$uid) {
			$uid = getUserId();
		}
		$data = MD::where("isUserTest", getIsUserTest())->where("uid", $uid)->first();
		if (!$data) {
			return false;
		}else{
			$data->update([$key => $value]);
			return true;
		}
	}
	public function getHelperData($key=null){
		if (!$key) {
			return null;
		}
		$helper = HP::where("key_pair", $key)->first();
		if (!$helper) {
			return null;
		}else{
			return $helper["value_pair"];
		}
	}
	public function getThemeColor($isStaging=null){
		$theme_color = getMerchantData("theme_color", null, $isStaging);
		if (!$theme_color) {
			$theme_color = getHelperData("theme_color");
		}
		return $theme_color;
	}
	public function getUrlValue($key=null){
		$filterVal = request()->route('filter');
		$filterVal = preg_replace("~/+~", "/", $filterVal);
		$filterVal = explode("/", $filterVal);
		if (!$key) {
			return "";
		}
		foreach ($filterVal as $value) {
			$value = explode(":", $value);
			if (count($value)>1) {
				$key_val = $value[0];
				unset($value[0]);
				$value = implode(":", $value);
				if (strtolower($key_val)==strtolower($key)) {
					return $value;
				}
				continue;
			}else{
				$value = implode(":", $value);
				if (strtolower($value)==strtolower($key)) {
					return "";
				}
				continue;
			}
		}
		return "";
	}
	public function getMaskedCard($card_data){
		$first6 = $card_data["card_number__6_first"];
        $last4 = $card_data["card_number__4_last"];
        return $first6 . str_pad($last4, 10, 'x', STR_PAD_LEFT);
	}
	public function getPaymentMethodByID($transaction){
		if (isset($transaction["pg_type"]) && strtolower($transaction["pg_type"])=="card") {
			return (isset($transaction["details"]["brand"])? $transaction["details"]["brand"]:"") . " " . getMaskedCard(isset($transaction["details"])? $transaction["details"]:"") . " (" . (isset($transaction["pg_type"])? $transaction["pg_type"]:"") . ")";
		}else{
			return (isset($transaction["details"]["pg_name"])? $transaction["details"]["pg_name"]:"") . " (" . (isset($transaction["pg_type"])? $transaction["pg_type"]:"") . ")";
		}
	}
	public function getPaymentMethodByType($pg_type, $transaction_details){
		if (strtolower($pg_type)=="card") {
			return (isset($transaction_details["brand"])? $transaction_details["brand"]:("")) . " " . getMaskedCard(isset($transaction_details)? $transaction_details:"") . " (" . (isset($pg_type)? $pg_type:"") . ")";
		}else{
			return (isset($transaction_details["pg_name"])? $transaction_details["pg_name"]:"") . " (" . (isset($pg_type)? $pg_type:"") . ")";
		}
	}
	public function getCurrencySymbol($currency, $amount, $wantOnlyCurrency = false){
		$currency_array = array (
				  'AED' =>
				  array (
				    'currency_code' => 'AED',
				    'currency_name' => 'Emirati Dirham',
				    'currency_symbol' => '\\u062f.\\u0625',
				  ),
				  'ALL' =>
				  array (
				    'currency_code' => 'ALL',
				    'currency_name' => 'Albanian Lek',
				    'currency_symbol' => 'Lek',
				  ),
				  'AMD' =>
				  array (
				    'currency_code' => 'AMD',
				    'currency_name' => 'Armenian Dram',
				    'currency_symbol' => '\\u058f',
				  ),
				  'ARS' =>
				  array (
				    'currency_code' => 'ARS',
				    'currency_name' => 'Argentine Peso',
				    'currency_symbol' => 'ARS',
				  ),
				  'AUD' =>
				  array (
				    'currency_code' => 'AUD',
				    'currency_name' => 'Australian Dollar',
				    'currency_symbol' => 'A$',
				  ),
				  'AWG' =>
				  array (
				    'currency_code' => 'AWG',
				    'currency_name' => 'Aruban or Dutch Guilder',
				    'currency_symbol' => 'Afl.',
				  ),
				  'BBD' =>
				  array (
				    'currency_code' => 'BBD',
				    'currency_name' => 'Barbadian or Bajan Dollar',
				    'currency_symbol' => '$',
				  ),
				  'BDT' =>
				  array (
				    'currency_code' => 'BDT',
				    'currency_name' => 'Bangladeshi Taka',
				    'currency_symbol' => '\\u09f3',
				  ),
				  'BMD' =>
				  array (
				    'currency_code' => 'BMD',
				    'currency_name' => 'Bermudian Dollar',
				    'currency_symbol' => '$',
				  ),
				  'BND' =>
				  array (
				    'currency_code' => 'BND',
				    'currency_name' => 'Bruneian Dollar',
				    'currency_symbol' => 'BND',
				  ),
				  'BOB' =>
				  array (
				    'currency_code' => 'BOB',
				    'currency_name' => 'Bolivian Bol\\u00edviano',
				    'currency_symbol' => 'Bs',
				  ),
				  'BSD' =>
				  array (
				    'currency_code' => 'BSD',
				    'currency_name' => 'Bahamian Dollar',
				    'currency_symbol' => 'B$',
				  ),
				  'BWP' =>
				  array (
				    'currency_code' => 'BWP',
				    'currency_name' => 'Botswana Pula',
				    'currency_symbol' => 'P',
				  ),
				  'BZD' =>
				  array (
				    'currency_code' => 'BZD',
				    'currency_name' => 'Belizean Dollar',
				    'currency_symbol' => 'BZ$',
				  ),
				  'CAD' =>
				  array (
				    'currency_code' => 'CAD',
				    'currency_name' => 'Canadian Dollar',
				    'currency_symbol' => 'C$',
				  ),
				  'CHF' =>
				  array (
				    'currency_code' => 'CHF',
				    'currency_name' => 'Swiss Franc',
				    'currency_symbol' => 'CHf',
				  ),
				  'CNY' =>
				  array (
				    'currency_code' => 'CNY',
				    'currency_name' => 'Chinese Yuan Renminbi',
				    'currency_symbol' => '\\u00a5',
				  ),
				  'COP' =>
				  array (
				    'currency_code' => 'COP',
				    'currency_name' => 'Colombian Peso',
				    'currency_symbol' => 'COL$',
				  ),
				  'CRC' =>
				  array (
				    'currency_code' => 'CRC',
				    'currency_name' => 'Costa Rican Colon',
				    'currency_symbol' => '\\u20a1',
				  ),
				  'CUP' =>
				  array (
				    'currency_code' => 'CUP',
				    'currency_name' => 'Cuban Peso',
				    'currency_symbol' => '$MN',
				  ),
				  'CZK' =>
				  array (
				    'currency_code' => 'CZK',
				    'currency_name' => 'Czech Koruna',
				    'currency_symbol' => 'K\\u010d',
				  ),
				  'DKK' =>
				  array (
				    'currency_code' => 'DKK',
				    'currency_name' => 'Danish Krone',
				    'currency_symbol' => 'DKK',
				  ),
				  'DOP' =>
				  array (
				    'currency_code' => 'DOP',
				    'currency_name' => 'Dominican Peso',
				    'currency_symbol' => 'RD$',
				  ),
				  'DZD' =>
				  array (
				    'currency_code' => 'DZD',
				    'currency_name' => 'Algerian Dinar',
				    'currency_symbol' => '\\u062f.\\u062c',
				  ),
				  'EGP' =>
				  array (
				    'currency_code' => 'EGP',
				    'currency_name' => 'Egyptian Pound',
				    'currency_symbol' => 'E\\u00a3',
				  ),
				  'ETB' =>
				  array (
				    'currency_code' => 'ETB',
				    'currency_name' => 'Ethiopian Birr',
				    'currency_symbol' => '\\u1265\\u122d',
				  ),
				  'EUR' =>
				  array (
				    'currency_code' => 'EUR',
				    'currency_name' => 'Euro',
				    'currency_symbol' => '\\u20ac',
				  ),
				  'FJD' =>
				  array (
				    'currency_code' => 'FJD',
				    'currency_name' => 'Fijian Dollar',
				    'currency_symbol' => 'FJ$',
				  ),
				  'GBP' =>
				  array (
				    'currency_code' => 'GBP',
				    'currency_name' => 'British Pound',
				    'currency_symbol' => '\\u00a3',
				  ),
				  'GHS' =>
				  array (
				    'currency_code' => 'GHS',
				    'currency_name' => 'Ghanaian Cedi',
				    'currency_symbol' => 'GH\\u20b5',
				  ),
				  'GIP' =>
				  array (
				    'currency_code' => 'GIP',
				    'currency_name' => 'Gibraltar Pound',
				    'currency_symbol' => 'GIP',
				  ),
				  'GMD' =>
				  array (
				    'currency_code' => 'GMD',
				    'currency_name' => 'Gambian Dalasi',
				    'currency_symbol' => 'D',
				  ),
				  'GTQ' =>
				  array (
				    'currency_code' => 'GTQ',
				    'currency_name' => 'Guatemalan Quetzal',
				    'currency_symbol' => 'Q',
				  ),
				  'GYD' =>
				  array (
				    'currency_code' => 'GYD',
				    'currency_name' => 'Guyanese Dollar',
				    'currency_symbol' => 'G$',
				  ),
				  'HKD' =>
				  array (
				    'currency_code' => 'HKD',
				    'currency_name' => 'Hong Kong Dollar',
				    'currency_symbol' => 'HK$',
				  ),
				  'HNL' =>
				  array (
				    'currency_code' => 'HNL',
				    'currency_name' => 'Honduran Lempira',
				    'currency_symbol' => 'HNL',
				  ),
				  'HRK' =>
				  array (
				    'currency_code' => 'HRK',
				    'currency_name' => 'Croatian Kuna',
				    'currency_symbol' => 'kn',
				  ),
				  'HTG' =>
				  array (
				    'currency_code' => 'HTG',
				    'currency_name' => 'Haitian Gourde',
				    'currency_symbol' => 'G',
				  ),
				  'HUF' =>
				  array (
				    'currency_code' => 'HUF',
				    'currency_name' => 'Hungarian Forint',
				    'currency_symbol' => 'Ft',
				  ),
				  'IDR' =>
				  array (
				    'currency_code' => 'IDR',
				    'currency_name' => 'Indonesian Rupiah',
				    'currency_symbol' => 'Rp',
				  ),
				  'ILS' =>
				  array (
				    'currency_code' => 'ILS',
				    'currency_name' => 'Israeli Shekel',
				    'currency_symbol' => '\\u20aa',
				  ),
				  'INR' =>
				  array (
				    'currency_code' => 'INR',
				    'currency_name' => 'Indian Rupee',
				    'currency_symbol' => '\\u20b9',
				  ),
				  'JMD' =>
				  array (
				    'currency_code' => 'JMD',
				    'currency_name' => 'Jamaican Dollar',
				    'currency_symbol' => 'J$',
				  ),
				  'KES' =>
				  array (
				    'currency_code' => 'KES',
				    'currency_name' => 'Kenyan Shilling',
				    'currency_symbol' => 'Ksh',
				  ),
				  'KGS' =>
				  array (
				    'currency_code' => 'KGS',
				    'currency_name' => 'Kyrgyzstani Som',
				    'currency_symbol' => '\\u041b\\u0432',
				  ),
				  'KHR' =>
				  array (
				    'currency_code' => 'KHR',
				    'currency_name' => 'Cambodian Riel',
				    'currency_symbol' => '\\u17db',
				  ),
				  'KYD' =>
				  array (
				    'currency_code' => 'KYD',
				    'currency_name' => 'Caymanian Dollar',
				    'currency_symbol' => 'CI$',
				  ),
				  'KZT' =>
				  array (
				    'currency_code' => 'KZT',
				    'currency_name' => 'Kazakhstani Tenge',
				    'currency_symbol' => '\\u20b8',
				  ),
				  'LAK' =>
				  array (
				    'currency_code' => 'LAK',
				    'currency_name' => 'Lao Kip',
				    'currency_symbol' => '\\u20ad',
				  ),
				  'LBP' =>
				  array (
				    'currency_code' => 'LBP',
				    'currency_name' => 'Lebanese Pound',
				    'currency_symbol' => '\\u0644.\\u0644.\\u200e',
				  ),
				  'LKR' =>
				  array (
				    'currency_code' => 'LKR',
				    'currency_name' => 'Sri Lankan Rupee',
				    'currency_symbol' => '\\u0dbb\\u0dd4',
				  ),
				  'LRD' =>
				  array (
				    'currency_code' => 'LRD',
				    'currency_name' => 'Liberian Dollar',
				    'currency_symbol' => 'L$',
				  ),
				  'LSL' =>
				  array (
				    'currency_code' => 'LSL',
				    'currency_name' => 'Basotho Loti',
				    'currency_symbol' => 'LSL',
				  ),
				  'MAD' =>
				  array (
				    'currency_code' => 'MAD',
				    'currency_name' => 'Moroccan Dirham',
				    'currency_symbol' => '\\u062f.\\u0645.',
				  ),
				  'MDL' =>
				  array (
				    'currency_code' => 'MDL',
				    'currency_name' => 'Moldovan Leu',
				    'currency_symbol' => 'MDL',
				  ),
				  'MKD' =>
				  array (
				    'currency_code' => 'MKD',
				    'currency_name' => 'Macedonian Denar',
				    'currency_symbol' => '\\u0434\\u0435\\u043d',
				  ),
				  'MMK' =>
				  array (
				    'currency_code' => 'MMK',
				    'currency_name' => 'Burmese Kyat',
				    'currency_symbol' => 'MMK',
				  ),
				  'MNT' =>
				  array (
				    'currency_code' => 'MNT',
				    'currency_name' => 'Mongolian Tughrik',
				    'currency_symbol' => '\\u20ae',
				  ),
				  'MOP' =>
				  array (
				    'currency_code' => 'MOP',
				    'currency_name' => 'Macau Pataca',
				    'currency_symbol' => 'MOP$',
				  ),
				  'MUR' =>
				  array (
				    'currency_code' => 'MUR',
				    'currency_name' => 'Mauritian Rupee',
				    'currency_symbol' => '\\u20a8',
				  ),
				  'MVR' =>
				  array (
				    'currency_code' => 'MVR',
				    'currency_name' => 'Maldivian Rufiyaa',
				    'currency_symbol' => 'Rf',
				  ),
				  'MWK' =>
				  array (
				    'currency_code' => 'MWK',
				    'currency_name' => 'Malawian Kwacha',
				    'currency_symbol' => 'MK',
				  ),
				  'MXN' =>
				  array (
				    'currency_code' => 'MXN',
				    'currency_name' => 'Mexican Peso',
				    'currency_symbol' => 'Mex$',
				  ),
				  'MYR' =>
				  array (
				    'currency_code' => 'MYR',
				    'currency_name' => 'Malaysian Ringgit',
				    'currency_symbol' => 'RM',
				  ),
				  'NAD' =>
				  array (
				    'currency_code' => 'NAD',
				    'currency_name' => 'Namibian Dollar',
				    'currency_symbol' => 'N$',
				  ),
				  'NGN' =>
				  array (
				    'currency_code' => 'NGN',
				    'currency_name' => 'Nigerian Naira',
				    'currency_symbol' => '\\u20a6',
				  ),
				  'NIO' =>
				  array (
				    'currency_code' => 'NIO',
				    'currency_name' => 'Nicaraguan Cordoba',
				    'currency_symbol' => 'NIO',
				  ),
				  'NOK' =>
				  array (
				    'currency_code' => 'NOK',
				    'currency_name' => 'Norwegian Krone',
				    'currency_symbol' => 'NOK',
				  ),
				  'NPR' =>
				  array (
				    'currency_code' => 'NPR',
				    'currency_name' => 'Nepalese Rupee',
				    'currency_symbol' => '\\u0930\\u0942',
				  ),
				  'NZD' =>
				  array (
				    'currency_code' => 'NZD',
				    'currency_name' => 'New Zealand Dollar',
				    'currency_symbol' => 'NZ$',
				  ),
				  'PEN' =>
				  array (
				    'currency_code' => 'PEN',
				    'currency_name' => 'Peruvian Sol',
				    'currency_symbol' => 'S\\/',
				  ),
				  'PGK' =>
				  array (
				    'currency_code' => 'PGK',
				    'currency_name' => 'Papua New Guinean Kina',
				    'currency_symbol' => 'PGK',
				  ),
				  'PHP' =>
				  array (
				    'currency_code' => 'PHP',
				    'currency_name' => 'Philippine Peso',
				    'currency_symbol' => '\\u20b1',
				  ),
				  'PKR' =>
				  array (
				    'currency_code' => 'PKR',
				    'currency_name' => 'Pakistani Rupee',
				    'currency_symbol' => '\\u20a8',
				  ),
				  'QAR' =>
				  array (
				    'currency_code' => 'QAR',
				    'currency_name' => 'Qatari Riyal',
				    'currency_symbol' => 'QR',
				  ),
				  'RUB' =>
				  array (
				    'currency_code' => 'RUB',
				    'currency_name' => 'Russian Ruble',
				    'currency_symbol' => '\\u20bd',
				  ),
				  'SAR' =>
				  array (
				    'currency_code' => 'SAR',
				    'currency_name' => 'Saudi Arabian Riyal',
				    'currency_symbol' => 'SR',
				  ),
				  'SCR' =>
				  array (
				    'currency_code' => 'SCR',
				    'currency_name' => 'Seychellois Rupee',
				    'currency_symbol' => 'SRe',
				  ),
				  'SEK' =>
				  array (
				    'currency_code' => 'SEK',
				    'currency_name' => 'Swedish Krona',
				    'currency_symbol' => 'SEK',
				  ),
				  'SGD' =>
				  array (
				    'currency_code' => 'SGD',
				    'currency_name' => 'Singapore Dollar',
				    'currency_symbol' => 'S$',
				  ),
				  'SLL' =>
				  array (
				    'currency_code' => 'SLL',
				    'currency_name' => 'Sierra Leonean Leone',
				    'currency_symbol' => 'Le',
				  ),
				  'SOS' =>
				  array (
				    'currency_code' => 'SOS',
				    'currency_name' => 'Somali Shilling',
				    'currency_symbol' => 'Sh.so.',
				  ),
				  'SSP' =>
				  array (
				    'currency_code' => 'SSP',
				    'currency_name' => 'South Sudanese Pound',
				    'currency_symbol' => 'SS\\u00a3',
				  ),
				  'SVC' =>
				  array (
				    'currency_code' => 'SVC',
				    'currency_name' => 'Salvadoran Colon',
				    'currency_symbol' => '\\u20a1',
				  ),
				  'SZL' =>
				  array (
				    'currency_code' => 'SZL',
				    'currency_name' => 'Swazi Lilangeni',
				    'currency_symbol' => 'E',
				  ),
				  'THB' =>
				  array (
				    'currency_code' => 'THB',
				    'currency_name' => 'Thai Baht',
				    'currency_symbol' => '\\u0e3f',
				  ),
				  'TTD' =>
				  array (
				    'currency_code' => 'TTD',
				    'currency_name' => 'Trinidadian Dollar',
				    'currency_symbol' => 'TT$',
				  ),
				  'TZS' =>
				  array (
				    'currency_code' => 'TZS',
				    'currency_name' => 'Tanzanian Shilling',
				    'currency_symbol' => 'Sh',
				  ),
				  'USD' =>
				  array (
				    'currency_code' => 'USD',
				    'currency_name' => 'US Dollar',
				    'currency_symbol' => '$',
				  ),
				  'UYU' =>
				  array (
				    'currency_code' => 'UYU',
				    'currency_name' => 'Uruguayan Peso',
				    'currency_symbol' => '$U',
				  ),
				  'UZS' =>
				  array (
				    'currency_code' => 'UZS',
				    'currency_name' => 'Uzbekistani Som',
				    'currency_symbol' => 'so\'m',
				  ),
				  'YER' =>
				  array (
				    'currency_code' => 'YER',
				    'currency_name' => 'Yemeni Rial',
				    'currency_symbol' => '\\ufdfc',
				  ),
				  'ZAR' =>
				  array (
				    'currency_code' => 'ZAR',
				    'currency_name' => 'South African Rand',
				    'currency_symbol' => 'R',
				  ),
				);
		if (!$currency) {
			$currency = "INR";
		}
		if (array_key_exists($currency, $currency_array)) {
			$currencyData = $currency_array[$currency];
			$currency_symbol = json_decode('"'.$currencyData["currency_symbol"].'"');
			if (!$wantOnlyCurrency) {
				$amount = str_replace(",", "", $amount);
				$amount = convertAmount($amount);
				$whole = convertAmount(floor($amount), true);
				$decimal = substr(strrchr($amount, "."), 1);
				return '<span class="tpg_amount tpg_tooltip_currency" data-tooltip="' . $currency_symbol . ' - ' . $currencyData["currency_name"] . ' (' . $currencyData["currency_code"] . ')"><span class="tpg_currency">' . $currency_symbol . '</span> <span class="tpg_whole">' . $whole . '</span><span class="tpg_paise">.' . $decimal . '</span></span>';
			}else{
				return '<span class="tpg_amount tpg_tooltip_currency" data-tooltip="' . $currency_symbol . ' - ' . $currencyData["currency_name"] . ' (' . $currencyData["currency_code"] . ')"><span class="tpg_currency">' . $currencyData["currency_code"] . '</span></span>';
			}
		}
	}

    /**
     * Get the max value of a given key.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function max($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        return $this->filter(function ($value) {
            return ! is_null($value);
        })->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);

            return is_null($result) || $value > $result ? $value : $result;
        });
    }

    /**
     * Merge the collection with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function merge($items)
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Recursively merge the collection with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function mergeRecursive($items)
    {
        return new static(array_merge_recursive($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Create a collection by using this collection for keys and another for its values.
     *
     * @param  mixed  $values
     * @return static
     */
    public function combine($values)
    {
        return new static(array_combine($this->all(), $this->getArrayableItems($values)));
    }

    /**
     * Union the collection with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function union($items)
    {
        return new static($this->items + $this->getArrayableItems($items));
    }

    /**
     * Get the min value of a given key.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function min($callback = null)
    {
        $callback = $this->valueRetriever($callback);

        return $this->map(function ($value) use ($callback) {
            return $callback($value);
        })->filter(function ($value) {
            return ! is_null($value);
        })->reduce(function ($result, $value) {
            return is_null($result) || $value < $result ? $value : $result;
        });
    }

    /**
     * Create a new collection consisting of every n-th element.
     *
     * @param  int  $step
     * @param  int  $offset
     * @return static
     */
    public function nth($step, $offset = 0)
    {
        $new = [];

        $position = 0;

        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $new[] = $item;
            }

            $position++;
        }

        return new static($new);
    }

    /**
     * Get the items with the specified keys.
     *
     * @param  mixed  $keys
     * @return static
     */
    public function only($keys)
    {
        if (is_null($keys)) {
            return new static($this->items);
        }

        if ($keys instanceof self) {
            $keys = $keys->all();
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(Arr::only($this->items, $keys));
    }

    /**
     * "Paginate" the collection by slicing it into a smaller collection.
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return static
     */
    public function forPage($page, $perPage)
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->slice($offset, $perPage);
    }

    /**
     * Partition the collection into two arrays using the given callback or key.
     *
     * @param  callable|string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return static
     */
    public function partition($key, $operator = null, $value = null)
    {
        $partitions = [new static, new static];

        $callback = func_num_args() === 1
                ? $this->valueRetriever($key)
                : $this->operatorForWhere(...func_get_args());

        foreach ($this->items as $key => $item) {
            $partitions[(int) ! $callback($item, $key)][$key] = $item;
        }

        return new static($partitions);
    }

    /**
     * Pass the collection to the given callback and return the result.
     *
     * @param  callable $callback
     * @return mixed
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
    }

    /**
     * Get and remove the last item from the collection.
     *
     * @return mixed
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * Push an item onto the beginning of the collection.
     *
     * @param  mixed  $value
     * @param  mixed  $key
     * @return $this
     */
    public function prepend($value, $key = null)
    {
        $this->items = Arr::prepend($this->items, $value, $key);

        return $this;
    }

    /**
     * Push an item onto the end of the collection.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function push($value)
    {
        $this->offsetSet(null, $value);

        return $this;
    }

    /**
     * Push all of the given items onto the collection.
     *
     * @param  iterable  $source
     * @return static
     */
    public function concat($source)
    {
        $result = new static($this);

        foreach ($source as $item) {
            $result->push($item);
        }

        return $result;
    }

    /**
     * Get and remove an item from the collection.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        return Arr::pull($this->items, $key, $default);
    }

    /**
     * Put an item in the collection by key.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return $this
     */
    public function put($key, $value)
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * Get one or a specified number of items randomly from the collection.
     *
     * @param  int|null  $number
     * @return static|mixed
     *
     * @throws \InvalidArgumentException
     */
    public function random($number = null)
    {
        if (is_null($number)) {
            return Arr::random($this->items);
        }

        return new static(Arr::random($this->items, $number));
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param  callable  $callback
     * @param  mixed  $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param  callable|mixed  $callback
     * @return static
     */
    public function reject($callback = true)
    {
        $useAsCallable = $this->useAsCallable($callback);

        return $this->filter(function ($value, $key) use ($callback, $useAsCallable) {
            return $useAsCallable
                ? ! $callback($value, $key)
                : $value != $callback;
        });
    }

    /**
     * Replace the collection items with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function replace($items)
    {
        return new static(array_replace($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Recursively replace the collection items with the given items.
     *
     * @param  mixed  $items
     * @return static
     */
    public function replaceRecursive($items)
    {
        return new static(array_replace_recursive($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Reverse items order.
     *
     * @return static
     */
    public function reverse()
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     *
     * @param  mixed  $value
     * @param  bool  $strict
     * @return mixed
     */
    public function search($value, $strict = false)
    {
        if (! $this->useAsCallable($value)) {
            return array_search($value, $this->items, $strict);
        }

        foreach ($this->items as $key => $item) {
            if (call_user_func($value, $item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Get and remove the first item from the collection.
     *
     * @return mixed
     */
    public function shift()
    {
        return array_shift($this->items);
    }

    /**
     * Shuffle the items in the collection.
     *
     * @param  int  $seed
     * @return static
     */
    public function shuffle($seed = null)
    {
        return new static(Arr::shuffle($this->items, $seed));
    }

    /**
     * Slice the underlying collection array.
     *
     * @param  int  $offset
     * @param  int  $length
     * @return static
     */
    public function slice($offset, $length = null)
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Split a collection into a certain number of groups.
     *
     * @param  int  $numberOfGroups
     * @return static
     */
    public function split($numberOfGroups)
    {
        if ($this->isEmpty()) {
            return new static;
        }

        $groups = new static;

        $groupSize = floor($this->count() / $numberOfGroups);

        $remain = $this->count() % $numberOfGroups;

        $start = 0;

        for ($i = 0; $i < $numberOfGroups; $i++) {
            $size = $groupSize;

            if ($i < $remain) {
                $size++;
            }

            if ($size) {
                $groups->push(new static(array_slice($this->items, $start, $size)));

                $start += $size;
            }
        }

        return $groups;
    }

    /**
     * Chunk the underlying collection array.
     *
     * @param  int  $size
     * @return static
     */
    public function chunk($size)
    {
        if ($size <= 0) {
            return new static;
        }

        $chunks = [];

        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Sort through each item with a callback.
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function sort(callable $callback = null)
    {
        $items = $this->items;

        $callback
            ? uasort($items, $callback)
            : asort($items);

        return new static($items);
    }

    /**
     * Sort the collection using the given callback.
     *
     * @param  callable|string  $callback
     * @param  int  $options
     * @param  bool  $descending
     * @return static
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        $results = [];

        $callback = $this->valueRetriever($callback);

        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options)
            : asort($results, $options);

        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param  callable|string  $callback
     * @param  int  $options
     * @return static
     */
    public function sortByDesc($callback, $options = SORT_REGULAR)
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Sort the collection keys.
     *
     * @param  int  $options
     * @param  bool  $descending
     * @return static
     */
    public function sortKeys($options = SORT_REGULAR, $descending = false)
    {
        $items = $this->items;

        $descending ? krsort($items, $options) : ksort($items, $options);

        return new static($items);
    }

    /**
     * Sort the collection keys in descending order.
     *
     * @param  int $options
     * @return static
     */
    public function sortKeysDesc($options = SORT_REGULAR)
    {
        return $this->sortKeys($options, true);
    }

    /**
     * Splice a portion of the underlying collection array.
     *
     * @param  int  $offset
     * @param  int|null  $length
     * @param  mixed  $replacement
     * @return static
     */
    public function splice($offset, $length = null, $replacement = [])
    {
        if (func_num_args() === 1) {
            return new static(array_splice($this->items, $offset));
        }

        return new static(array_splice($this->items, $offset, $length, $replacement));
    }

    /**
     * Get the sum of the given values.
     *
     * @param  callable|string|null  $callback
     * @return mixed
     */
    public function sum($callback = null)
    {
        if (is_null($callback)) {
            return array_sum($this->items);
        }

        $callback = $this->valueRetriever($callback);

        return $this->reduce(function ($result, $item) use ($callback) {
            return $result + $callback($item);
        }, 0);
    }

    /**
     * Take the first or last {$limit} items.
     *
     * @param  int  $limit
     * @return static
     */
    public function take($limit)
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function tap(callable $callback)
    {
        $callback(new static($this->items));

        return $this;
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function transform(callable $callback)
    {
        $this->items = $this->map($callback)->all();

        return $this;
    }

    /**
     * Return only unique items from the collection array.
     *
     * @param  string|callable|null  $key
     * @param  bool  $strict
     * @return static
     */
    public function unique($key = null, $strict = false)
    {
        $callback = $this->valueRetriever($key);

        $exists = [];

        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /**
     * Return only unique items from the collection array using strict comparison.
     *
     * @param  string|callable|null  $key
     * @return static
     */
    public function uniqueStrict($key = null)
    {
        return $this->unique($key, true);
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return static
     */
    public function values()
    {
        return new static(array_values($this->items));
    }

    /**
     * Get a value retrieving callback.
     *
     * @param  callable|string|null  $value
     * @return callable
     */
    protected function valueRetriever($value)
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            return data_get($item, $value);
        };
    }

    /**
     * Zip the collection together with one or more arrays.
     *
     * e.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]]
     *
     * @param  mixed ...$items
     * @return static
     */
    public function zip($items)
    {
        $arrayableItems = array_map(function ($items) {
            return $this->getArrayableItems($items);
        }, func_get_args());

        $params = array_merge([function () {
            return new static(func_get_args());
        }, $this->items], $arrayableItems);

        return new static(call_user_func_array('array_map', $params));
    }

    /**
     * Pad collection to the specified length with a value.
     *
     * @param  int  $size
     * @param  mixed  $value
     * @return static
     */
    public function pad($size, $value)
    {
        return new static(array_pad($this->items, $size, $value));
    }

    /**
     * Get the collection of items as a plain array.
     *
     * @return array
     */
    public function toArray()
    {
        return array_map(function ($value) {
            return $value instanceof Arrayable ? $value->toArray() : $value;
        }, $this->items);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif ($value instanceof Jsonable) {
                return json_decode($value->toJson(), true);
            } elseif ($value instanceof Arrayable) {
                return $value->toArray();
            }

            return $value;
        }, $this->items);
    }

    /**
     * Get the collection of items as JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Get a CachingIterator instance.
     *
     * @param  int  $flags
     * @return \CachingIterator
     */
    public function getCachingIterator($flags = CachingIterator::CALL_TOSTRING)
    {
        return new CachingIterator($this->getIterator(), $flags);
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * Count the number of items in the collection using a given truth test.
     *
     * @param  callable|null  $callback
     * @return static
     */
    public function countBy($callback = null)
    {
        if (is_null($callback)) {
            $callback = function ($value) {
                return $value;
            };
        }

        return new static($this->groupBy($callback)->map(function ($value) {
            return $value->count();
        }));
    }

    /**
     * Add an item to the collection.
     *
     * @param  mixed  $item
     * @return $this
     */
    public function add($item)
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Get a base Support collection instance from this collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function toBase()
    {
        return new self($this);
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Results array of items from Collection or Arrayable.
     *
     * @param  mixed  $items
     * @return array
     */
    protected function getArrayableItems($items)
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof self) {
            return $items->all();
        } elseif ($items instanceof Arrayable) {
            return $items->toArray();
        } elseif ($items instanceof Jsonable) {
            return json_decode($items->toJson(), true);
        } elseif ($items instanceof JsonSerializable) {
            return (array) $items->jsonSerialize();
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        }

        return (array) $items;
    }

    /**
     * Add a method to the list of proxied methods.
     *
     * @param  string  $method
     * @return void
     */
    public static function proxy($method)
    {
        static::$proxies[] = $method;
    }

    /**
     * Dynamically access collection proxies.
     *
     * @param  string  $key
     * @return mixed
     *
     * @throws \Exception
     */
    public function __get($key)
    {
        if (! in_array($key, static::$proxies)) {
            throw new Exception("Property [{$key}] does not exist on this collection instance.");
        }

        return new HigherOrderCollectionProxy($this, $key);
    }
}
