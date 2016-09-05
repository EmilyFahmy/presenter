<?php

namespace Hemp\Presenter;

use Illuminate\Support\Str;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;

class Presenter implements Jsonable, Arrayable
{
    protected static $mutatorCache;

    protected static $snakeAttributes;

    /**
     * The decorated model
     * @var Illuminate/Database/Eloquent/Model
     */
    protected $model;

    /**
     * Create a new instance of the Presenter
     * @param Illuminate/Database/Eloquent/Model $model
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Get the decorated model
     * @return Illuminate/Database/Eloquent/Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Pass magic properties to accessors
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        try {
            $method = 'get' . studly_case($name) . 'Attribute';

            if (method_exists($this, $method)) {
                return $this->{$method}($name);
            }

            if (method_exists($this->model, $name)) {
                $this->model->{$name}(func_get_args());
            }

            return $this->model->{$name};
        } catch (Exception $e) {
            throw new Exception("Property [{$name}] could not be resolved.");
        }
    }

    /**
     * Call the model's version of the method if available
     * @param  string $method
     * @param  array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->model, $method], $args);
    }

    /**
     * Convert the decorated instance to a string
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Convert the decorated instance to JSON
     * @param  integer $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert the decorator instance to an array
     * @return array
     */
    public function toArray()
    {
        $mutatedAttributes = $this->mutatorsToArray();

        return array_merge($mutatedAttributes, $this->model->toArray());
    }

    /**
     * Convert the decorators instance's mutators to an array.
     * @return array
     */
    public function mutatorsToArray()
    {
        $mutatedAttributes = [];

        $mutators = $this->getMutatedAttributes();

        foreach ($mutators as $mutator) {
            $mutatedAttributes[Str::snake($mutator)] = $this->mutateAttribute($mutator);
        }

        return $mutatedAttributes;
    }

    /**
     * Get the mutated attributes for a given instance.
     * @return array
     */
    public function getMutatedAttributes()
    {
        $class = static::class;

        if (! isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Extract and cache all the mutated attributes of a class.
     * @param  string  $class
     * @return void
     */
    public static function cacheMutatedAttributes($class)
    {
        $mutatedAttributes = [];

        // Here we will extract all of the mutated attributes so that we can quickly
        // spin through them after we export models to their array form, which we
        // need to be fast. This'll let us know the attributes that can mutate.
        if (preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches)) {
            foreach ($matches[1] as $match) {
                if (static::$snakeAttributes) {
                    $match = Str::snake($match);
                }

                $mutatedAttributes[] = lcfirst($match);
            }
        }

        static::$mutatorCache[$class] = $mutatedAttributes;
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function mutateAttribute($key)
    {
        return $this->{'get'.Str::studly($key).'Attribute'}();
    }
}
