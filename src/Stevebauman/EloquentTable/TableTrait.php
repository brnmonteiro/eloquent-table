<?php

namespace Stevebauman\EloquentTable;

use Closure;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * TableTrait.
 *
 * Allows a laravel collection / eloquent collection to be converted into an
 * HTML table.
 *
 * @author Steve Bauman <steven_bauman_7@hotmail.com>
 * @license    http://opensource.org/licenses/MIT MIT
 */
trait TableTrait
{
    /*
     * Stores the columns to display
     *
     * @var array
     */
    public $eloquentTableColumns = [];

    /*
     * Stores the columns to hide when using
     * responsive templates
     *
     * @var array
     */
    public $eloquentTableHiddenColumns = [];

    /*
     * Stores the column modifications
     *
     * @var array
     */
    public $eloquentTableModifications = [];

    /*
     * Stores rows modifications
     *
     * @var array
     */
    public $eloquentTableRowAttributesModifications = [];

    /*
     * Stores cells modifications
     *
     * @var array
     */
    public $eloquentTableCellAttributesModifications = [];

    /*
     * Stores attributes to display onto the table
     *
     * @var array
     */
    public $eloquentTableAttributes = [];

    /*
     * Stores column relationship meanings
     *
     * @var array
     */
    public $eloquentTableMeans = [];

    /*
     * Stores column names to apply sorting
     *
     * @var array
     */
    public $eloquentTableSort = [];

    public $eloquentMassAction = true;

    /*
     * Enables / disables showing the pages on the table if the collection
     * is paginated
     *
     * @var bool
     */
    public $eloquentTablePages = false;

    public function withMassActions(Closure $closure = null, $value = 'id')
    {
        $closure = $closure();

        if (! $closure) {
            return $this;
        }

        if ($closure) {
            $closure = '
            <div class="custom-table custom-table__mass-actions">
                <input type="checkbox" data-mass-action="all">

                <div class="actions">
                    <i class="glyphicon glyphicon-chevron-down"></i>
                    <div class="actions__items">
                        ' . implode(PHP_EOL, $closure) . '
                    </div>
                </div>
            </div>';
        }

        $this->eloquentTableColumns = [
            'select' => [
                'content' => $closure,
                'class'   => 'select',
            ],
        ] + $this->eloquentTableColumns;

        $this->modify('select', function ($item) use ($value) {
            if (isset($item[$value])) {
                if (is_array($item[$value])) {
                    $values = implode(',', $item[$value]);
                } else {
                    $values = $item[$value];
                }
            } elseif (isset($item['id'])) {
                $values = $item['id'];
            } else {
                $values = null;
            }

            return '<input type="checkbox" data-mass-action="' . $values . '">';
        });

        $this->modifyCell('select', function () {
            return [
                'class'          => 'text-center',
                'cell-checkbox'  => null,
            ];
        });

        return $this;
    }

    /**
     * Assigns columns to display.
     *
     * @param array $columns
     *
     * @return $this
     */
    public function columns(array $columns = [])
    {
        $this->eloquentTableColumns = array_merge($this->eloquentTableColumns, $columns);

        return $this;
    }

    /**
     * Remove columns to display.
     *
     * @param array $columns
     *
     * @return $this
     */
    public function removeColumns(array $columns = [])
    {
        if ($columns) {
            foreach ($columns as $column) {
                if (array_key_exists($column, $this->eloquentTableColumns)) {
                    unset($this->eloquentTableColumns[$column]);
                }
            }
        }

        return $this;
    }

    /**
     * Only columns to display.
     *
     * @param array $columns
     *
     * @return $this
     */
    public function onlyColumns(array $columns = [])
    {
        if ($columns) {
            $this->eloquentTableColumns = array_intersect_key($this->eloquentTableColumns, array_flip($columns));
        }

        return $this;
    }

    /**
     * Assigns columns to hide for smartphone viewing
     * on responsive designed websites such as bootstrap.
     *
     * @param array $columns
     *
     * @return $this
     */
    public function hidden(array $columns = [])
    {
        $this->eloquentTableHiddenColumns = $columns;

        return $this;
    }

    /**
     * Enables pages to be shown on the view.
     *
     * @return $this
     */
    public function showPages()
    {
        $this->eloquentTablePages = true;

        return $this;
    }

    /**
     * Assigns attributes to display on the table.
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function attributes(array $attributes = [])
    {
        $this->eloquentTableAttributes = $attributes;

        return $this;
    }

    /**
     * Generates view for the table.
     *
     * @param string $view
     *
     * @return mixed
     */
    public function render($view = '')
    {
        // If no attributes have been set, we'll set them to the configuration defaults
        if (count($this->eloquentTableAttributes) === 0) {
            $attributes = Config::get('eloquenttable' . EloquentTableServiceProvider::$configSeparator . 'default_table_attributes', []);

            $this->attributes($attributes);
        }

        /*
         * If a view isn't specified, we'll check the configuration
         * separator to see what laravel version we're using so the
         * correct blade tags are used.
         */
        if (! $view) {
            if (! ($view = Config::get('eloquenttable' . EloquentTableServiceProvider::$configSeparator . 'default_render_view'))) {
                if (EloquentTableServiceProvider::$configSeparator === '::') {
                    $view = 'eloquenttable::laravel-4-table';
                } else {
                    $view = 'eloquenttable::laravel-5-table';
                }
            }
        }

        return View::make($view, [
            'collection' => $this,
        ])->render();
    }

    /**
     * Stores modifications to columns.
     *
     * @param string  $column
     * @param Closure $closure
     *
     * @return $this
     */
    public function modify($columns, Closure $closure = null)
    {
        if (is_array($columns)) {
            foreach ($columns as $column => $closure) {
                $this->modify($column, $closure);
            }

            return $this;
        }

        $this->eloquentTableModifications[$columns] = $closure;

        return $this;
    }

    /**
     * Stores modifications to cells.
     *
     * @param string  $column
     * @param Closure $closure
     *
     * @return $this
     */
    public function modifyCell($column, $closure)
    {
        $this->eloquentTableCellAttributesModifications[$column] = $closure;

        return $this;
    }

    /**
     * Stores modifications to rows.
     *
     * @param string  $name
     * @param Closure $closure
     *
     * @return $this
     */
    public function modifyRow($name, $closure)
    {
        $this->eloquentTableRowAttributesModifications[$name] = $closure;

        return $this;
    }

    /**
     * Retrieves cell attributes.
     *
     * @param string $column
     * @param Array  $record
     *
     * @return string
     */
    public function getCellAttributes($column, $record = null)
    {
        $attributes = [];
        if (array_key_exists($column, $this->eloquentTableCellAttributesModifications)) {
            $attributes = call_user_func($this->eloquentTableCellAttributesModifications[$column], $record);
            if (array_key_exists($column, $this->eloquentTableHiddenColumns)) {
                $attributes = array_merge($attributes, $this->eloquentTableHiddenColumns[$column]);
            } elseif (in_array($column, $this->eloquentTableHiddenColumns)) {
                /*
                 * No custom attributes found, using default config attributes
                 */
                $attributes = array_merge($attributes, Config::get('eloquenttable' . EloquentTableServiceProvider::$configSeparator . 'default_hidden_column_attributes'));
            }

            return $this->arrayToHtmlAttributes($attributes);
        } else {
            return;
        }
    }

    /**
     * Retrieves row attributes.
     *
     * @param Array $record
     *
     * @return string
     */
    public function getRowAttributes($record)
    {
        $attributes = [];
        foreach ($this->eloquentTableRowAttributesModifications as $closure) {
            $tmpAtrributes = call_user_func($closure, $record);
            if (is_array($tmpAtrributes)) {
                $attributes = array_merge($attributes, $tmpAtrributes);
            }
        }

        return $this->arrayToHtmlAttributes($attributes);
    }

    /**
     * Stores columns to sort in an array.
     *
     * @param array $columns
     *
     * @return $this
     */
    public function sortable($columns = [])
    {
        $this->eloquentTableSort = $columns;

        return $this;
    }

    /**
     * Tells the collection to use a different key (such as a relationship key)
     * rather than the one specified in the column.
     *
     * @param string $column
     * @param string $relation
     *
     * @return $this
     */
    public function means($column, $relation)
    {
        $this->eloquentTableMeans[$column] = $relation;

        return $this;
    }

    /**
     * Retrieves an eloquent relationships nested property
     * from a column.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function getRelationshipProperty($column)
    {
        $attributes = explode('.', $column);

        $tmpStr = $this;

        foreach ($attributes as $attribute) {
            if ($attribute === end($attributes)) {
                if (is_object($tmpStr)) {
                    $tmpStr = $tmpStr->$attribute;
                }
            } else {
                $tmpStr = $this->$attribute;
            }
        }

        return $tmpStr;
    }

    /**
     * Retrieves an eloquent relationship object from a column.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function getRelationshipObject($column)
    {
        $attributes = explode('.', $column);

        if (count($attributes) > 1) {
            $relationship = $attributes[count($attributes) - 2];
        } else {
            $relationship = $attributes[count($attributes) - 1];
        }

        return $this->$relationship;
    }

    /**
     * Retrieves hidden column attributes.
     *
     * @param string $column
     *
     * @return string
     */
    public function getHiddenColumnAttributes($column)
    {
        /*
         * Check if custom attributes are being set on hidden column
         */
        if (array_key_exists($column, $this->eloquentTableHiddenColumns)) {
            return $this->arrayToHtmlAttributes($this->eloquentTableHiddenColumns[$column]);
        } elseif (in_array($column, $this->eloquentTableHiddenColumns)) {
            /*
             * No custom attributes found, using default config attributes
             */
            return $this->arrayToHtmlAttributes(Config::get('eloquenttable' . EloquentTableServiceProvider::$configSeparator . 'default_hidden_column_attributes'));
        } else {
            /*
             * Column wasn't found on the table
             */
            return;
        }
    }

    /**
     * Allows all columns on the current database table to be sorted through
     * query scope.
     *
     * @param $query
     * @param string $field
     * @param string $sort
     *
     * @return mixed
     */
    public function scopeSort($query, $field = null, $sort = null)
    {
        /*
         * Make sure both the field and sort variables are present
         */
        if ($field && $sort) {
            /*
             * Retrieve all column names for the current model table
             */
            $columns = Schema::getColumnListing($this->getTable());

            /*
             * Make sure the field inputted is available on the current table
             */
            if (in_array($field, $columns)) {
                /*
                 * Make sure the sort input is equal to asc or desc
                 */
                if ($sort === 'asc' || $sort === 'desc') {
                    /*
                     * Return the query sorted
                     */
                    return $query->orderBy($field, $sort);
                }
            }
        }

        /*
         * Default order by created at field
         */
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Overrides the newCollection method from the model this extends from.
     *
     * @param array $models
     *
     * @return TableCollection
     */
    public function newCollection(array $models = [])
    {
        return new TableCollection($models);
    }

    /**
     * Converts an array of attributes to an html attribute string.
     *
     * @param array $attributes
     *
     * @return string
     */
    private function arrayToHtmlAttributes(array $attributes = [])
    {
        $attributeString = '';

        if (count($attributes) > 0) {
            foreach ($attributes as $key => $value) {
                $attributeString .= ' ' . $key . "='" . $value . "'";
            }
        }

        return $attributeString;
    }
}
