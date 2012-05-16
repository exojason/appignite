<?php
function _bool($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::Bool, $attributes);
}
function _int($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::Int, $attributes);
}
function _uint($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::UInt, $attributes);
}
function _float($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::Float, $attributes);
}
function _string($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::String, $attributes);
}
function _text($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::Text, $attributes);
}
function _date($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::Date, $attributes);
}
function _time($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::Time, $attributes);
}
function _datetime($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::DateTime, $attributes);
}
function _password($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::Password, $attributes);
}
function _email($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::Email, $attributes);
}
function _url($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::URL, $attributes);
}
function _image($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::Image, $attributes);
}
function _file($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addProperty($name, PropertyType::File, $attributes);
}
function _belongsTo($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addRelationship($model->className, $name,
                            RelationshipType::BelongsTo, $attributes);
}
function _hasMany($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addRelationship($model->className, $name,
                            RelationshipType::HasMany, $attributes);
}
function _manyToMany($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addRelationship($model->className, $name,
                            RelationshipType::ManyToMany, $attributes);
}
function _references($name, $attributes = array())
{
    $model = end(Model::$_modelStack);
    $model->addRelationship($model->className, $name,
                            RelationshipType::References, $attributes);
}
?>