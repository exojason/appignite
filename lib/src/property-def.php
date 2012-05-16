<?php
class PropertyDef
{
    public $name;
    public $columnName;
    public $instanceName;
    public $displayName;
    public $type = 0;
    public $size = 0;
    public $required = false;
    public $unique = false;
    public $defaultValue;

    // For use with text
    public $searchable = false;

    // For use with numeric data types
    public $minValue;
    public $maxValue;

    // For use with string data types
    public $minLength;
    public $maxLength;

    // For use with date and datetime data types
    public $minYear = 1900;
    public $maxYear = 2100;

    public $autoCreate = false;
    public $autoUpdate = false;

    public function __construct($name, $type, $attributes)
    {
        $this->name = str_to_camel_case($name);
        $this->type = $type;

        if (key_exists('required', $attributes)) {
            $this->required = $attributes['required'];
        }
        if (key_exists('unique', $attributes)) {
            $this->unique = $attributes['unique'];
        }
        if (key_exists('display', $attributes)) {
            $this->displayName = $attributes['display'];
        } else {
            $this->displayName = ucwords($this->name);
        }

        switch($type) {
            case PropertyType::DateTime:
            case PropertyType::Date:
            case PropertyType::Time:
                if (key_exists('auto-create', $attributes)) {
                    $this->autoCreate = $attributes['auto-create'];
                }
                if (key_exists('auto-update', $attributes)) {
                    $this->autoUpdate = $attributes['auto-update'];
                }
                break;
        }

        $this->columnName = $this->name;
        $this->instanceName = $this->name;
    }
}
?>