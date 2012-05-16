<?php
class RelationshipDef
{
    public $parentModelName;
    public $parentModelInstanceName;
    public $modelName;
    public $modelNamePlural;
    public $type;
    public $modelAliasName;
    public $modelAliasNamePlural;
    public $modelInstanceName;
    public $modelInstanceNamePlural;

    public $asName;                 // Only applies to ManyToMany (i.e.)
    public $asInstanceName;         // Only applies to ManyToMany (i.e.)
    public $asInstanceNamePlural;   // Only applies to ManyToMany (i.e.)


    // The required attribute is analogous to the required attribute on properties
    // 1. BelongsTo relationships should always be required, unless it's reflexive (like comments)
    // in which case it must NOT be required.
    // 2. References type relationships can either be required or not.
    public $required = true;

    public function __construct($parentModelName, $modelName, $type, $attributes)
    {
        $this->parentModelName = str_to_pascal_case($parentModelName);
        $this->parentModelInstanceName = pascal_to_camel_case($this->parentModelName);
        $this->modelName = str_to_pascal_case($modelName);
        $this->modelNamePlural = Inflect::pluralize($this->modelName);
        $this->type = $type;

        if (key_exists('as', $attributes)) {

            $this->asName = $attributes['as'];

            $this->asInstanceName = str_to_camel_case($this->asName);
            $this->asInstanceNamePlural = Inflect::pluralize($this->asInstanceName);

        } else if (key_exists('alias', $attributes)) {

            $this->modelAliasName = str_to_pascal_case($attributes['alias']);
            $this->modelInstanceName = pascal_to_camel_case($this->modelAliasName);

        } else {

            $this->modelAliasName = $this->modelName;
            $this->modelInstanceName = str_to_camel_case($this->modelName);
        }

        $this->modelInstanceNamePlural = Inflect::pluralize($this->modelInstanceName);
        $this->modelAliasNamePlural = camel_case_to_pascal($this->modelInstanceNamePlural);
    }

    public function isReflexive()
    {
        return ($this->parentModelName == $this->modelName);
    }
}
?>