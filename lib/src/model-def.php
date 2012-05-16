<?php
class ModelDef
{
    public $className;
    public $classNamePlural;
    public $instanceName;
    public $instanceNamePlural;
    public $tableName;

    public $displayName;
    public $displayNamePluralName;

    public $properties = array();
    public $relationships = array();

    public $creationTimestamped;
    public $creationTimestampedName;  // (Created) => Sent, Posted, etc
    public $creationTimestampedNameObj;

    public $modificationTimestamped;
    public $modificationTimestampedName;
    public $modificationTimestampedNameObj;

    public $linkModels = array();

    public $identifierProperty;

    public $hasRollUpColumns;

    protected $createAuthorization;
    protected $readAuthorization;
    protected $modifyAuthorization;
    protected $deleteAuthorization;

    public function __construct($className)
    {
        $this->className = $className;
        $this->instanceName = str_to_camel_case($this->className);
        $this->tableName = $this->instanceName;
        $this->classNamePlural = str_to_pascal_case(Inflect::pluralize($this->className));
        $this->instanceNamePlural = Inflect::pluralize($this->instanceName);

        $this->addProperty('id', 'int', array(
            'unique' => true,
            'unsigned' => true,
            'auto-increment' => true
        ));
    }

    public function addProperty($name, $type, $attributes)
    {
        $property = new PropertyDef($name, $type, $attributes);
        $this->properties[$property->name] = $property;
    }

    public function addRelationship($className, $modelName, $type, $attributes = array())
    {
        $relationship = new RelationshipDef($className, $modelName, $type, $attributes);

        $this->relationships[$relationship->modelAliasName] = $relationship;
    }

    public function authorizeCreate($value)
    {
        $this->_createAuthorization = $value;
    }

    public function authorizeRead($value)
    {
        $this->_readAuthorization = $value;
    }

    public function authorizeModify($value)
    {
        $this->_modifyAuthorization = $value;
    }

    public function authorizeDelete($value)
    {
        $this->_deleteAuthorization = $value;
    }

    public function isHierarchical()
    {
        foreach($this->relationships as $relationship) {
            if ($relationship->type == RelationshipType::BelongsTo && $relationship->modelName == $this->className) {
                return true;
            }
        }
        return false;
    }

    public function getReciprocalRelationship($relationship)
    {
        foreach($this->relationships as $r) {

            if ($r->modelName == $relationship->parentModelName) {

                if ($relationship->type == RelationshipType::ManyToMany &&
                    $r->type == RelationshipType::ManyToMany) {

                    return $r;

                } else if ($relationship->type == RelationshipType::BelongsTo &&
                           $r->type == RelationshipType::HasMany) {

                    return $r;

                } else if ($relationship->type == RelationshipType::HasMany &&
                           $r->type == RelationshipType::BelongsTo) {

                    return $r;
                }
            }
        }

        return false;
    }

    public function findReflexiveRelationship($relationshipType)
    {
        if ($relationship->modelName == $this->className) {

            foreach($this->relationships as $relationship) {

                if ($relationship->modelName == $this->className) {

                    if ($relationshipType == RelationshipType::ManyToMany) {

                        return $relationship;

                    } else if ($relationshipType == RelationshipType::BelongsTo &&
                               $relationship->type == RelationshipType::HasMany) {

                        return $relationship;

                    } else if ($relationship->type == RelationshipType::BelongsTo &&
                               $relationshipType == RelationshipType::HasMany) {

                        return $relationship;
                    }
                }
            }
        }

        return false;
    }

    public function init()
    {
        // The first property is always the ID, so we want the identifier
        // property to default to the second property
        //$this->identifierProperty = $this->properties[1];

        // Determine if the model has rollup columns
        $this->hasRollUpColumns = false;
        foreach($this->relationships as $relationship) {
            if ($relationship->type == RelationshipType::HasMany) {
                $this->hasRollUpColumns = true;
                break;
            }
        }
    }

    public function getPropertyByName($name)
    {
        foreach($this->properties as $property) {
            if (strcasecmp($property->name, $name) == 0) {
                return $property;
            }
        }
        return false;
    }

    public function getRelationship($modelAliasName)
    {
        foreach($this->relationships as $relationship) {
            if ($modelAliasName == $relationship->modelAliasName) {
                return $relationship;
            }
        }
        return false;
    }
}
?>