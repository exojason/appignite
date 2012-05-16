<?php
class Name
{
    public $display;
    public $displayPlural;
    public $camel;
    public $camelPlural;
    public $pascal;
    public $pascalPlural;

    public function __construct($displayName)
    {
        $this->display = $displayName;
        $this->displayPlural = ucwords(Inflect::pluralize($displayName));
        $this->camel = str_to_camel_case($displayName);
        $this->camelPlural = str_to_camel_case(Inflect::pluralize($displayName));
        $this->pascal = str_to_pascal_case($displayName);
        $this->pascalPlural = str_to_pascal_case(Inflect::pluralize($displayName));
    }
}
?>