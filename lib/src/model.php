<?php
// TODO: Add index to relationships to disambiguate if there are more than one relationship to the same model
// TODO: Check this index in the ModelDef::getReciprocalRelationship
abstract class Model
{
    protected static $__CLASS__ = __CLASS__;

    protected static $_pdo;
    protected static $_modelDefs = array();

    public static $_modelStack = array();

    protected static $_lastQuery = "";

    protected $_modelDef;
    protected $_data = array();

    public function __construct($deep = true)
    {
        $this->_modelDef = self::getModelDef();

        foreach($this->_modelDef->properties as $property) {
            $this->_data[$property->name] = null;
        }

        if ($deep) {
            foreach($this->_modelDef->relationships as $relationship) {
                switch ($relationship->type) {
                    case RelationshipType::BelongsTo:
                    case RelationshipType::References:
                        $memberName = $relationship->modelInstanceName;
                        $className = $relationship->modelName;
                        $this->_data[$memberName] = new $className(false);
                        break;

                    case RelationshipType::HasMany:
                    case RelationshipType::ManyToMany:
                        $memberName = $relationship->modelInstanceNamePlural;
                        $this->_data[$memberName] = array();

                        $memberName = $relationship->modelInstanceName . 'IDs';
                        $this->_data[$memberName] = array();

                        $memberName = 'num' . $relationship->modelAliasNamePlural;
                        $this->_data[$memberName] = 0;
                        break;
                }
            }
        }

        if ($this->_modelDef->isHierarchical()) {
            $depthName = $this->_modelDef->instanceName . 'Depth';
            $this->_data[$depthName] = null;
        }
    }

    protected static abstract function _define();

    protected static function getLastQuery()
    {
        return self::$_lastQuery;
    }

    protected static function getModelDef($className = '')
    {
        $className = $className ? $className : get_called_class();

        if (key_exists($className, self::$_modelDefs)) {

            $modelDef = self::$_modelDefs[$className];

        } else {
            $modelDef = new ModelDef($className);

            self::$_modelDefs[$className] = $modelDef;
            self::$_modelStack[] = $modelDef;

            call_user_func($className . '::_define');

            $modelDef->init();

            array_pop(self::$_modelStack);
        }

        return $modelDef;
    }

    protected static function connect()
    {
        if (is_null(self::$_pdo)) {
            self::$_pdo = new PDO(DSN, DB_USERNAME, DB_PASSWORD);
        }

        return self::$_pdo;
    }

    public function __call($name, $arguments)
    {
        if ($name == "find") {
            $id = $arguments[0];
            return $this->findInstance(array('id' => $id));

        } else if (substr($name, 0, 6) == "findBy") {
            $propertyName = substr($name, 6);

            if ($propertyName == "ID") {
                $propertyName = "id";
            } else {
                $propertyName = pascal_to_camel_case($propertyName);
            }

            $model = self::getModelDef();

            if (key_exists($propertyName, $model->properties)) {
                $property = $model->properties[$propertyName];

                if ($property->unique) {
                    $value = $arguments[0];
                    return $this->findInstance(array($propertyName => $value));
                }
            }
        }

        $this->triggerUndefinedMethodError($name);
    }

    public static function __callStatic($name, $arguments)
    {
        if ($name == "find") {
            $options = count($arguments) > 0 ? $arguments[0] : null;

            return self::find($options);

        } else if (substr($name, 0, 6) == "findBy") {
            $parameters = array();
            $parameterNames = explode("And", substr($name, 6));

            for($i = 0; $i < count($parameterNames); $i++) {
                $parameterName = $parameterNames[$i];

                if (substr($parameterName, strlen($parameterName) - 2) == "ID") {
                    $modelClassName = substr($parameterName, 0, strlen($parameterName) - 2);
                    $modelInstanceName = pascal_to_camel_case($modelClassName);
                    $parameterName = $modelInstanceName . ".id";
                    $parameters[$parameterName] = $arguments[$i];
                } else {
                    $parameterName = pascal_to_camel_case($parameterName);
                    $parameters[$parameterName] = $arguments[$i];
                }
            }

            if (count($arguments) < count($parameterNames)) {
                // TODO: Throw error - missing parameters
            } else if (count($arguments) == count($parameterNames)) {
                $options = $parameters;
            } else if (count($arguments) == (count($parameterNames) + 1)) {
                $options = $arguments[count($arguments) - 1];
                $options = array_merge($parameters, $options);
            } else if (count($arguments) > (count($parameterNames) + 1)) {
                // TODO: Throw error - too many parameters
            }

            return self::find($options);
        }

        self::triggerUndefinedMethodError($name);
    }

    public function __get($name)
    {
        if (key_exists($name, $this->_data)) {
            return $this->_data[$name];
        } else {
            $this->triggerUndefinedPropertyError($name);
        }
    }

    public function __set($name, $value)
    {
        if (key_exists($name, $this->_data)) {
            $this->_data[$name] = $value;
        } else {
            $this->triggerUndefinedPropertyError($name);
        }
    }

    protected function triggerUndefinedPropertyError($name)
    {
        $message = "Undefined property: " . get_called_class() . "::" . $name;

        $stackCalls = debug_backtrace();

        $file = $stackCalls[1]['file'];
        $line = $stackCalls[1]['line'];
        $function = $stackCalls[2]['function'];

        if (self::isCommandLine()) {
            $message .= ' in ' . $function . ' called from ' .
                        $file . ' on line ' .
                        $line . "\nerror handler";
        } else {
            $message .= ' in <strong>' . $function . '</strong> called from <strong>' .
                        $file . '</strong> on line <strong>' .
                        $line . '</strong>' . "\n<br />error handler";
        }

        trigger_error($message, E_USER_NOTICE);
    }

    protected function triggerUndefinedMethodError($name)
    {
        $message = "Undefined method: " . get_called_class() . "::" . $name;

        $stackCalls = debug_backtrace();

        $file = $stackCalls[1]['file'];
        $line = $stackCalls[1]['line'];
        $function = $stackCalls[3]['function'];

        if (self::isCommandLine()) {
            $message .= ' in ' . $function . ' called from ' .
                        $file . ' on line ' .
                        $line . "\nerror handler";
        } else {
            $message .= ' in <strong>' . $function . '</strong> called from <strong>' .
                        $file . '</strong> on line <strong>' .
                        $line . '</strong>' . "\n<br />error handler";
        }

        trigger_error($message, E_USER_ERROR);
    }

    protected function isCommandLine()
    {
        return php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']);
    }

    protected function findInstance($options)
    {
        $sb = new StringBuilder();

        if (is_array($options)) {
            $pdo = self::connect();

            $this->buildSelectClause($sb, $options, true);
            $this->buildFromClause($sb, $options);
            $this->buildJoinClauses($sb, $options);
            $this->buildInstanceWhereClause($sb, $options);
            $this->buildGroupByClause($sb, $options);
            $this->buildHavingClause($sb, $options);
            $this->buildOrderByClause($sb, $options);

            $sql = $sb->toString();

            $statement = $pdo->prepare($sql);

            $parameters = array();

            if (key_exists("id", $options)) {
                $parameters["id"] = $this->getOptionalParameter($options, "id");
            } else {
                foreach($this->_modelDef->properties as $property) {
                    if (key_exists($property->name, $options) && $property->unique) {
                        if (key_exists($property->name, $options)) {
                            $parameters[$property->name] = $this->getOptionalParameter($options, $property->name);
                        }
                        break;
                    }
                }
            }

            $result = $statement->execute($parameters);
            self::logQuery($statement, $sql, $parameters, $result);

            if ($statement->rowCount() == 1) {
                $row = $statement->fetch(PDO::FETCH_ASSOC);

                $this->parseRow($row);

                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    protected static function find($options = null)
    {
        $model= self::getModelDef();

        $sb = new StringBuilder();

        $pdo = self::connect();

        $sqlClauses = array();

        self::buildSelectClause($sb, $options);
        self::buildFromClause($sb, $options);
        self::buildJoinClauses($sb, $options);
        self::buildWhereClause($sb, $options);
        self::buildGroupByClause($sb, $options);
        self::buildHavingClause($sb, $options);
        self::buildOrderByClause($sb, $options);
        self::buildLimitClause($sb, $options);

        $sql = $sb->toString();

        self::$_lastQuery = $sql;

        $statement = $pdo->prepare($sql);

        $parameters = array();

        if (is_array($options)) {
            foreach($model->relationships as $relationship) {
                switch ($relationship->type) {
                    case RelationshipType::BelongsTo:
                    case RelationshipType::References:
                    case RelationshipType::ManyToMany:
                        $rModel = self::getModelDef($relationship->modelName);
                        $parameterName = $relationship->modelInstanceName . '.id';
                        $parameterName2 = $relationship->modelInstanceName . 'ID';

                        if (key_exists($parameterName, $options)) {
                            $parameter = self::getOptionalParameter($options, $parameterName);

                            if (is_array($parameter)) {
                                for($i = 0; $i < count($parameter); $i++) {
                                    $itemParameterName = $parameterName2 . ($i + 1);
                                    $parameters[$itemParameterName] = $parameter[$i];
                                }
                            } else {
                                $parameters[$parameterName2] = is_array($parameter) ? implode(',', $parameter) : $parameter;
                            }
                        }
                        break;
                }
            }
        }

        $result = $statement->execute($parameters);
        self::logQuery($statement, $sql, $parameters, $result);

        $objects = array();

        if ($statement->rowCount() > 0) {
            while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $object = new $model->className();
                $object->parseRow($row);
                $objects[$object->id] = $object;
            }

            foreach($model->relationships as $relationship) {
                switch ($relationship->type) {
                    case RelationshipType::HasMany:
                    case RelationshipType::ManyToMany:
                        $parameterName = $relationship->modelInstanceName . '.options';
                        $parameter = self::getOptionalParameter($options, $parameterName);

                        if (is_array($parameter)) {
                            self::attachObjects($objects, $relationship, $parameter);
                        }
                        break;
                }
            }
        }

        return $objects;
    }

    private static function attachObjects($objects, $relationship, $options)
    {
        $model= self::getModelDef();
        $rModel = self::getModelDef($relationship->modelName);
        $reciprocalRelationship = $rModel->getReciprocalRelationship($relationship);

        $parameter = $reciprocalRelationship->modelInstanceName . '.id';
        $options[$parameter] = array_keys($objects);

        $children = call_user_func($rModel->className . '::find', $options);

        switch ($relationship->type) {
            case RelationshipType::HasMany:
                foreach($children as $child) {
                    // OLD VERSION: $objectID = $child{$model->instanceName}->id;
                    $objectID = $child->_data[$model->instanceName]->id;

                    $object = $objects[$objectID];

                    // OLD VERSION: $child{$reciprocalRelationship->modelInstanceName} = $object;
                    $child->_data[$reciprocalRelationship->modelInstanceName] = $object;

                    // OLD VERSION: $object{$relationship->modelInstanceNamePlural}[] = $child;
                    $object->_data[$relationship->modelInstanceNamePlural][] = $child;
                }
                break;
            case RelationshipType::ManyToMany:
                foreach($children as $child) {
                    $objectIDs = $child{$model->instanceName . 'IDs'};
                    foreach($objectIDs as $objectID) {
                        if (key_exists($objectID, $objects)) {
                            $object = $objects[$objectID];

                            //$object{$relationship->modelInstanceNamePlural}[$child->id] = $child;
                            $object->_data[$relationship->modelInstanceNamePlural][$child->id] = $child;
                        }
                    }
                }
                break;
        }
    }

    private static function sortAsHierarchy($objects)
    {
        $model= self::getModelDef();

        $parentName = 'parent' . $model->className;
        $childArrayName = 'child' . $model->classNamePlural;

        foreach($objects as $object) {
            if ($object{$parentName} && key_exists($object{$parentName}->id, $objects)) {
                $parentID = $object{$parentName}->id;
                $parent = $objects[$parentID];
                $object{$parentName} = $parent;
                $parent{$childArrayName}[] = $object;
            }
        }

        $sortedObjects = array();
        foreach($objects as $object) {
            $parentID = $object{$parentName}->id;

            if (!key_exists($parentID, $objects)) {
                self::enumRecursively($objects, 0, $sortedObjects);
            }
        }

        return $sortedObjects;
    }

    private static function enumRecursively($object, $depth, &$sortedObjects)
    {
        $model= self::getModelDef();

        $depthName = $model->instanceName . 'Depth';
        $childArrayName = 'child' . $model->classNamePlural;

        $object{$depthName} = $depth;

        $sortedObjects[$object->id] = $object;

        foreach($object{$childArrayName} as $child) {
            self::enumRecursively($child, $depth + 1, $sortedObjects);
        }
    }

    protected function buildSelectClause($sb, $options, $findInstance = false)
    {
        if ($findInstance) {
            $sb->addLine(0, 'SELECT');
        } else {
            $sb->addLine(0, 'SELECT SQL_CALC_FOUND_ROWS');
        }

        $model = self::getModelDef();
        $tableName = self::_getTableName();

        $columnNames = new StringBuilder(",\n");

        // Add columns
        foreach($model->properties as $property) {
            $columnNames->addLine(1, $tableName . '.' . $property->name . ' AS ' .  $tableName . '_' . $property->name);
        }

        // Add columns for related data
        foreach($model->relationships as $relationship) {
            switch ($relationship->type) {
                case RelationshipType::BelongsTo:
                case RelationshipType::References:
                    $rModel = self::getModelDef($relationship->modelName);

                    foreach($rModel->properties as $foreignProperty) {
                        $columnNames->addLine(1, $relationship->modelInstanceName . '.' . $foreignProperty->name . ' AS ' . $relationship->modelInstanceName . '_' . $foreignProperty->name);
                    }
                    break;
                case RelationshipType::HasMany:
                    $columnNames->addLine(1, 'COALESCE(COUNT(DISTINCT ' . $relationship->modelInstanceName . '.id), 0) AS ' . $tableName . '_num' . $relationship->modelAliasNamePlural);
                    break;

                 case RelationshipType::ManyToMany:
                    $rModel = self::getModelDef($relationship->modelName);
                    $reciprocalRelationship = $rModel->getReciprocalRelationship($relationship);

                    $columnBuilder2 = new StringBuilder("\n");

                    if ($relationship->isReflexive()) {
                        $joinTableName = self::createSelfJoinTableName($model->className, $relationship->modelAliasName);

                        $columnBuilder2->addLine(1, '(SELECT');
                        $columnBuilder2->addLine(2, 'COALESCE(COUNT( ' . $joinTableName . '.' . $relationship->modelInstanceName . 'ID), 0)');
                        $columnBuilder2->addLine(1, 'FROM');
                        $columnBuilder2->addLine(2, $joinTableName);
                        $columnBuilder2->addLine(1, 'WHERE');
                        $columnBuilder2->addLine(2, $joinTableName . '.' . $model->tableName . 'ID = ' . $model->tableName . '.id) AS ' . $tableName . '_num' . ucwords($relationship->modelInstanceNamePlural));

                        $columnBuilder2->addLine(1, '(SELECT');
                        $columnBuilder2->addLine(2, 'GROUP_CONCAT(' . $joinTableName . '.' . $relationship->modelInstanceName . 'ID)');
                        $columnBuilder2->addLine(1, 'FROM');
                        $columnBuilder2->addLine(2, $joinTableName);
                        $columnBuilder2->addLine(1, 'WHERE');
                        $columnBuilder2->addLine(2, $joinTableName . '.' . $model->tableName  . 'ID = ' . $model->tableName . '.id) AS ' . $tableName . '_' . $relationship->modelInstanceName . 'IDs');

                    } else {
                        $joinTableName = self::createJoinTableName($reciprocalRelationship->modelAliasName, $relationship->modelAliasName);

                        $columnBuilder2->addLine(1, '(SELECT');
                        $columnBuilder2->addLine(2, 'COALESCE(COUNT(DISTINCT ' . $joinTableName . '.' . $relationship->modelInstanceName . 'ID), 0)');
                        $columnBuilder2->addLine(1, 'FROM');
                        $columnBuilder2->addLine(2, $joinTableName);
                        $columnBuilder2->addLine(1, 'WHERE');
                        $columnBuilder2->addLine(2, $joinTableName . '.' . $reciprocalRelationship->modelInstanceName . 'ID = ' . $model->tableName . '.id) AS ' . $tableName . '_num' . ucwords($relationship->modelInstanceNamePlural) . ',');

                        $columnBuilder2->addLine(1, '(SELECT');
                        $columnBuilder2->addLine(2, 'GROUP_CONCAT(' . $joinTableName . '.' . $relationship->modelInstanceName . 'ID)');
                        $columnBuilder2->addLine(1, 'FROM');
                        $columnBuilder2->addLine(2, $joinTableName);
                        $columnBuilder2->addLine(1, 'WHERE');
                        $columnBuilder2->addLine(2, $joinTableName . '.' . $reciprocalRelationship->modelInstanceName . 'ID = ' . $model->tableName . '.id) AS ' . $tableName . '_' . $relationship->modelInstanceName . 'IDs');
                    }

                    $columnNames->add($columnBuilder2);
                    break;
            }
        }

        $sb->add($columnNames);
    }

    protected function buildFromClause($sb, $options)
    {
        $model = self::getModelDef();

        $sb->addLine(0, 'FROM');

        $tableList = new StringBuilder(",\n");

        foreach($model->relationships as $relationship) {
            switch ($relationship->type) {
                case RelationshipType::ManyToMany:
                    $parameterName = $relationship->modelInstanceName . '.id';

                    if (self::getOptionalParameter($options, $parameterName)) {
                        $rModel = self::getModelDef($relationship->modelName);
                        $tableList->addLine(1, $rModel->tableName);

                        $tableList->addLine(1, self::_getJoinTableName($relationship));
                    }
                    break;
            }
        }

        $tableList->addLine(1, $model->tableName);

        $sb->add($tableList);
    }

    protected function buildInstanceWhereClause($sb, $options)
    {
        $sb->addLine(0, 'WHERE');

        if (is_array($options)) {
            if (key_exists("id", $options)) {
                $sb->addLine(1, $this->_modelDef->instanceName . ".id = :id");
                return;
            } else {
                foreach($this->_modelDef->properties as $property) {
                    if ($property->unique && key_exists($property->name, $options)) {
                        $sb->addLine(1, $this->_modelDef->instanceName . '.' . $property->name . ' = :' . $property->name);
                        return;
                    }
                }
            }
        }

        $sb->addLine(1, '1');
    }

    protected function buildWhereClause($sb, $options)
    {
        $model = self::getModelDef();

        $sb->addLine(0, 'WHERE');

        $conditionBuilder = new StringBuilder(" AND\n");

        if (is_array($options)) {
            foreach($model->relationships as $relationship) {
                switch ($relationship->type) {
                    case RelationshipType::BelongsTo:
                        $parameterName = $relationship->modelInstanceName . '.id';
                        $parameterName2 = $relationship->modelInstanceName . 'ID';

                        if (key_exists($parameterName, $options)) {
                            $parameter = self::getOptionalParameter($options, $parameterName);

                            if (is_array($parameter)) {
                                $itemParameterNames = array();
                                for($i = 0; $i < count($parameter); $i++) {
                                    $itemParameterNames[] = ':' . $parameterName2 . ($i + 1);
                                }

                                $conditionBuilder->addLine(1, $model->instanceName . '.' . $parameterName2 . ' IN (' . implode(', ', $itemParameterNames) . ')');
                            } else {
                                $conditionBuilder->addLine(1, $model->instanceName . '.' . $parameterName2 . ' = :' . $parameterName2);
                            }
                        }
                        break;
                    case RelationshipType::ManyToMany:
                        $parameterName = $relationship->modelInstanceName . '.id';
                        $parameterName2 = $relationship->modelInstanceName . 'ID';

                        if (key_exists($parameterName, $options)) {
                            $parameter = self::getOptionalParameter($options, $parameterName);

                            if (!is_null($parameter)) {
                                $joinTableName = self::_getJoinTableName($relationship);

                                if (is_array($parameter)) {
                                    $conditionBuilder->addLine(1, $model->instanceName . '.id = ' . $joinTableName . '.' . $model->instanceName . 'ID');
                                    $conditionBuilder->addLine(1, $joinTableName . '.' . $parameterName2 . ' IN (:' . $parameterName2 . ')');

                                } else {
                                    $conditionBuilder->addLine(1, $model->instanceName . '.id = ' . $joinTableName . '.' . $model->instanceName . 'ID');
                                    $conditionBuilder->addLine(1, $joinTableName . '.' . $parameterName2 . ' = :' . $parameterName2);
                                }
                            }
                        }
                        break;
                }
            }

            foreach($options as $fieldName => $value) {
                $property = $model->getPropertyByName($fieldName);

                if ($property) {
                    $firstChar = substr($value, 0, 1);
                    $firstTwoChars = substr($value, 0, 2);

                    if ($firstTwoChars == "<=") {
                        $comparator = "<=";
                        $value = substr($value, 2);
                    } else if ($firstTwoChars == ">=") {
                        $comparator = ">=";
                        $value = substr($value, 2);
                    } else if ($firstTwoChars == "<>") {
                        $comparator = "<>";
                        $value = substr($value, 2);
                    } else if ($firstChar == "<") {
                        $comparator = "<";
                        $value = substr($value, 1);
                    } else if ($firstChar == ">") {
                        $comparator = ">";
                        $value = substr($value, 1);

                    } else {
                        $comparator = "=";
                    }

                    $condition = $model->tableName . "." . $fieldName . " " . $comparator . " " . self::formatValue($property->type, $value);

                    $conditionBuilder->addLine(1, $condition);
                }
            }
        }

        if ($conditionBuilder->getNumLines() > 0) {
            $sb->add($conditionBuilder);
        } else {
            $sb->addLine(1, '1');
        }
    }

    protected static function buildJoinClauses($sb, $options)
    {
        $modelDef = self::getModelDef();

        foreach($modelDef->relationships as $relationship) {
            switch ($relationship->type) {
                case RelationshipType::BelongsTo:
                case RelationshipType::References:
                    $rModel = self::getModelDef($relationship->modelName);

                    $sb->addLine(0, 'LEFT OUTER JOIN');

                    if ($rModel->instanceName != $relationship->modelInstanceName) {
                        $sb->addLine(1, $rModel->tableName . ' AS ' . $relationship->modelInstanceName);
                    } else {
                        $sb->addLine(1, $rModel->tableName);
                    }

                    $sb->addLine(0, 'ON');
                    $sb->addLine(1, $relationship->modelInstanceName . '.id = ' . $modelDef->tableName . '.' . $relationship->modelInstanceName . 'ID');
                    break;

                case RelationshipType::HasMany:
                    $rModel = self::getModelDef($relationship->modelName);

                    $sb->addLine(0, 'LEFT OUTER JOIN');

                    if ($rModel->instanceName != $relationship->modelInstanceName) {
                        $sb->addLine(1, $rModel->tableName . ' AS ' . $relationship->modelInstanceName);
                    } else {
                        $sb->addLine(1, $rModel->tableName);
                    }

                    $sb->addLine(0, 'ON');

                    $reciprocalRelationship = $rModel->getReciprocalRelationship($relationship);

                    $sb->addLine(1, $relationship->modelInstanceName . '.' . $reciprocalRelationship->modelInstanceName . 'ID = ' . $modelDef->tableName . '.id');
                    break;
            }
        }
    }

    protected static function buildGroupByClause($sb, $options)
    {
        $modelDef = self::getModelDef();

        $sb->addLine(0, 'GROUP BY');
        $sb->addLine(1, $modelDef->tableName . '.id');
    }

    protected static function buildHavingClause($sb, $options)
    {
        $tableName = self::_getTableName();

        $filters = self::getOptionalParameter($options, 'filters', '');

        if ($filters) {
            $conditions = new StringBuilder(" AND\n");

            foreach($filters as $filter) {
                $fieldName = $filter[0];
                $comparator = $filter[1];
                $value = $filter[2];

                if (substr($fieldName, 0, 3) == 'num') {
                    $conditions->addLine(1, $tableName . '_' . $fieldName . ' ' . $comparator . ' ' . $value);
                }
            }

            $sb->addLine(0, 'HAVING');
            $sb->add($conditions);
        }
    }

    protected static function buildOrderByClause($sb, $options)
    {
        $tableName = self::_getTableName();

        $sortList = self::getOptionalParameter($options, 'sort');

        if ($sortList) {
            $sortBuilder = new StringBuilder(",\n");

            $sorts = explode(",", $sortList);

            $numSorts = count($sorts);
            for($i = 0; $i < $numSorts; $i++) {
                $sort = $sorts[$i];

                if ($sort{0} == "-") {
                    $sortEntry = $tableName . "_" . str_replace(".", "_", substr($sort, 1)) . " DESC";
                } else {
                    $sortEntry = $tableName . "_" . str_replace(".", "_", $sort) . " ASC";
                }

                $sortBuilder->addLine(1, $sortEntry);
            }

            $sb->addLine(0, 'ORDER BY');

            $sb->add($sortBuilder);
        }
    }

    protected static function buildLimitClause($sb, $options)
    {
        $offset = self::getOptionalParameter($options, "offset", 0);
        $limit = self::getOptionalParameter($options, "limit", 0);

        if ($offset > 0 || $limit > 0) {
            $sb->addLine(0, 'LIMIT');
            $sb->addLine(1, $offset . ', ' . $limit);
        }
    }

    protected static function formatValue($propertyType, $value)
    {
        switch ($propertyType) {
            case PropertyType::Bool:
                return self::formatBool($value);
            case PropertyType::String:
            case PropertyType::Password:
            case PropertyType::Text:
            case PropertyType::Email:
            case PropertyType::URL:
            case PropertyType::File:
            case PropertyType::Image:
            case PropertyType::Date:
            case PropertyType::DateTime:
            case PropertyType::Time:
                return self::formatText($value);
            default:
                return $value;
        }
    }

    protected static function formatText($value)
    {
        if ($value == NULL) {
            return "''";
        } else {
            return "'" . str_replace("'", "''", $value) . "'";
        }
    }

    protected static function formatBool($value)
    {
        if (strtolower($value) == "true") {
            return 1;
        } elseif (strtolower($value) == "false") {
            return 0;
        } else {
            return ($value ? 1 : 0);
        }
    }

    protected function getOptionalParameter($options, $parameterName, $defaultValue = '')
    {
        return !is_null($options) && is_array($options) &&
               key_exists($parameterName, $options) ? $options[$parameterName] : $defaultValue;
    }

    public function insert()
    {
        $pdo = self::connect();

        $sb = new StringBuilder();

        $sb->addLine(0, 'INSERT INTO ' . $this->_modelDef->tableName . ' (');

        $columnNames = new StringBuilder(",\n", ")");

        // Add columns for parent and referenced models
        foreach($this->_modelDef->relationships as $relationship) {
            if ($relationship->type == RelationshipType::BelongsTo ||
                $relationship->type == RelationshipType::References) {

                $columnNames->addLine(1, $relationship->modelInstanceName . 'ID');
            }
        }
        // Add columns for model properties
        foreach($this->_modelDef->properties as $property) {
            if ($property->name != 'id') {
                $columnNames->addLine(1, $property->name);
            }
        }

        $sb->add($columnNames);

        $sb->addLine(0, 'VALUES (');

        $values = new StringBuilder(",\n", ")");

        // Add values for parent and referenced models
        foreach($this->_modelDef->relationships as $relationship) {
            if ($relationship->type == RelationshipType::BelongsTo ||
                $relationship->type == RelationshipType::References) {

                $values->addLine(1, ':' . $relationship->modelInstanceName . 'ID');
            }
        }
        // Add values for model properties
        foreach($this->_modelDef->properties as $property) {
            if ($property->name != 'id') {
                if ($property->autoCreate) {
                    $values->addLine(1, 'now()');
                } else {
                    $value = $this->__get($property->name);

                    if (strcasecmp($value, 'auto') == 0) {
                        $values->addLine(1, 'now()');
                    } else {
                        $values->addLine(1, ':' . $property->name);
                    }
                }
            }
        }

        $sb->add($values);

        $sql = $sb->toString();

        $statement = $pdo->prepare($sql);

        $parameters = array();

        // Set parameters for parent and referenced models
        foreach($this->_modelDef->relationships as $relationship) {
            if ($relationship->type == RelationshipType::BelongsTo ||
                $relationship->type == RelationshipType::References) {

                $rModel = $this->__get($relationship->modelInstanceName);

                $parameters[$relationship->modelInstanceName . 'ID'] = $rModel->id;
            }
        }

        // Set parameters for model properties
        foreach($this->_modelDef->properties as $property) {
            if ($property->name == 'id') { continue; }
            if ($property->autoCreate) { continue; }

            $value = $this->__get($property->name);

            if (strcasecmp($value, 'auto') == 0) { continue; }

            $parameters[$property->name] = $value;
        }

        $result = $statement->execute($parameters);
        self::logQuery($statement, $sql, $parameters, $result);

        $this->id = $pdo->lastInsertID();

        return $result;
    }

    public function update()
    {
        $pdo = self::connect();

        $sb = new StringBuilder();

        $sb->addLine(0, 'UPDATE');
        $sb->addLine(1, $this->_modelDef->tableName);
        $sb->addLine(0, 'SET');

        $assignments = new StringBuilder(",\n");

        // Assign parent and referenced models
        foreach($this->_modelDef->relationships as $relationship) {
            if ($relationship->type == RelationshipType::BelongsTo ||
                $relationship->type == RelationshipType::References) {

                $rModel = $this->__get($relationship->modelInstanceName);

                if (!is_null($rModel->id)) {
                    $columnName = $relationship->modelInstanceName . 'ID';
                    $assignments->addLine(1, $columnName . ' = :' . $columnName);
                }
            }
        }
        // Assign model properties
        foreach($this->_modelDef->properties as $property) {
            if ($property->name == 'id') { continue; }

            $value = $this->__get($property->name);

            if ($property->autoUpdate || strcasecmp($value, 'auto') == 0) {
                $assignments->addLine(1, $property->name . ' = now()');
            } else if (!is_null($this->__get($property->name))) {
                $assignments->addLine(1, $property->name . ' = :' . $property->name);
            }
        }

        $sb->add($assignments);

        $sb->addLine(0, 'WHERE');
        $sb->addLine(1, 'id = :id');

        $sql = $sb->toString();

        $statement = $pdo->prepare($sql);

        // Set parameters for parent and referenced models
        foreach($this->_modelDef->relationships as $relationship) {
            if ($relationship->type == RelationshipType::BelongsTo ||
                $relationship->type == RelationshipType::References) {

                $rModel = $this->__get($relationship->modelInstanceName);

                if (!is_null($rModel->id)) {
                    $parameters[$relationship->modelInstanceName . 'ID'] = $rModel->id;
                }
            }
        }
        // Set parameters for model properties
        foreach($this->_modelDef->properties as $property) {
            if ($property->autoUpdate) { continue; }

            if (!is_null($this->__get($property->name))) {

                $value = $this->__get($property->name);

                if (strcasecmp($value, 'auto') == 0) { continue; }

                $parameters[$property->name] = $value;
            }
        }

        $result = $statement->execute($parameters);
        self::logQuery($statement, $sql, $parameters, $result);

        return $result;
    }


    private static $queries = array();
    private static $pathLengths = array();

    public function delete()
    {
        $pdo = self::connect();

        $model = self::getModelDef();

        $tableName = $this->_getTableName();

        $queryBuilder = new StringBuilder();
        $queryBuilder->addLine(0, "DELETE FROM");
        $queryBuilder->addLine(1, $tableName);
        $queryBuilder->addLine(0, "WHERE");
        $queryBuilder->addLine(1, $tableName . ".id = :" . $tableName . "ID");

        self::$queries = array();
        self::$queries[$tableName] = array($queryBuilder->toString());
        self::$pathLengths = array();
        self::$pathLengths[$tableName] = 0;

        foreach($model->relationships as $relationship) {
            switch ($relationship->type) {
                case RelationshipType::HasMany:
                    $rModel = self::getModelDef($relationship->modelName);

                    call_user_func($relationship->modelName . '::deleteByRelatedModelID', $relationship, array(), array());
                    break;

                case RelationshipType::ManyToMany:
                    $tableName = self::_getJoinTableName($relationship);

                    $queryBuilder = new StringBuilder();
                    $queryBuilder->addLine(0, "DELETE FROM");
                    $queryBuilder->addLine(1, $tableName);
                    $queryBuilder->addLine(0, "WHERE");
                    $queryBuilder->addLine(1, $tableName . '.' . $relationship.parentModelName . ' = :id');

                    if (!key_exists($tableName, self::$queries)) {
                        self::$queries[$tableName] = array($queryBuilder->toString());
                    } else {
                        self::$queries[$tableName][] = $queryBuilder->toString();
                    }

                    if (key_exists($tableName, self::$pathLengths)) {
                        if (self::$pathLengths[$tableName] > count($path) + 1) {
                            self::$pathLengths[$tableName] = count($path) + 1;
                        }
                    } else {
                        self::$pathLengths[$tableName] = count($path) + 1;
                    }
                    break;
            }
        }

        // Sort queries by maximum path length
        $queries = array();
        for($i = 0; $i < 100; $i++) {
            foreach(self::$queries as $tableName => $bucket) {
                if (self::$pathLengths[$tableName] == $i) {
                    foreach($bucket as $query) {
                        $queries[] = $query;
                    }
                }
            }
        }
        $queries = array_reverse($queries);

        // Execute delete queries
        $parameterName = $this->_getTableName() . 'ID';
        foreach($queries as $query) {
            $statement = $pdo->prepare($query);

            $parameters = array($parameterName => $this->id);
            
            $result = $statement->execute($parameters);
            self::logQuery($statement, $sql, $parameters, $result);
        }

        return $result;
    }

    private static function deleteByRelatedModelID($r, $tables, $path)
    {
        $pdo = self::connect();

        $tableName = self::_getTableName();
        $model = self::getModelDef();

        if (key_exists($tableName, self::$pathLengths)) {
            if (self::$pathLengths[$tableName] > count($path) + 1) {
                self::$pathLengths[$tableName] = count($path) + 1;
            }
        } else {
            self::$pathLengths[$tableName] = count($path) + 1;
        }

        if (count($path) == 0) {
            $parentJoin = $r->modelInstanceName . '.' . $r->parentModelInstanceName . 'ID = :' . $r->parentModelInstanceName . 'ID';
        } else {
            $parentJoin = $r->modelInstanceName . '.' . $r->parentModelInstanceName . 'ID = ' .  $r->parentModelInstanceName . '.id';
        }

        $tables = array_merge(array($tableName), $tables);
        $path = array_merge(array($parentJoin), $path);

        $queryBuilder = new StringBuilder();
        $queryBuilder->addLine(0, "DELETE FROM");
        $queryBuilder->addLine(1, $tableName);

        if (count($path) > 1) {
            $queryBuilder->addLine(0, "USING");

            $usingBuilder = new StringBuilder(" INNER JOIN\n");
            foreach($tables as $table) {
                $usingBuilder->addLine(1, $table);
            }

            $queryBuilder->add($usingBuilder);
        }

        $queryBuilder->addLine(0, "WHERE");

        $joinBuilder = new StringBuilder(" AND\n");

        foreach($path as $join) {
            $joinBuilder->addLine(1, $join);
        }

        $queryBuilder->add($joinBuilder);

        if (!key_exists($tableName, self::$queries)) {
            self::$queries[$tableName] = array($queryBuilder->toString());
        } else {
            self::$queries[$tableName][] = $queryBuilder->toString();
        }

        foreach($model->relationships as $relationship) {
            switch ($relationship->type) {
                case RelationshipType::ManyToMany:
                    $tableName = self::_getJoinTableName($relationship);

                    $queryBuilder = new StringBuilder();
                    $queryBuilder->addLine(0, "DELETE FROM");
                    $queryBuilder->addLine(1, $tableName);
                    $queryBuilder->addLine(0, "USING");

                    $usingBuilder = new StringBuilder(" INNER JOIN\n");
                    $usingBuilder->addLine(1, $tableName); // Add the join table

                    foreach($tables as $table) {
                        $usingBuilder->addLine(1, $table);
                    }

                    $queryBuilder->add($usingBuilder);

                    $queryBuilder->addLine(0, "WHERE");
                    $queryBuilder->addLine(1, $tableName . '.' . $relationship->parentModelInstanceName . 'ID = ' . $model->instanceName . '.id AND');

                    $queryBuilder->add($joinBuilder);

                    if (!key_exists($tableName, self::$queries)) {
                        self::$queries[$tableName] = array($queryBuilder->toString());
                    } else {
                        self::$queries[$tableName][] = $queryBuilder->toString();
                    }

                    if (key_exists($tableName, self::$pathLengths)) {
                        if (self::$pathLengths[$tableName] > count($path) + 1) {
                            self::$pathLengths[$tableName] = count($path) + 1;
                        }
                    } else {
                        self::$pathLengths[$tableName] = count($path) + 1;
                    }
                    break;
            }
        }

        foreach($model->relationships as $relationship) {
            switch ($relationship->type) {
                case RelationshipType::HasMany:
                    $rModel = self::getModelDef($relationship->modelName);

                    call_user_func($relationship->modelName . '::deleteByRelatedModelID', $relationship, $tables, $path);
                    break;
            }
        }
    }

    public function isValid(&$errorMessage)
    {
        $model = self::getModelDef();

        // Check for required relationships
        foreach($model->relationships as $relationship) {

            if ($relationship->required) {

                switch ($relationship->type) {
                    case RelationshipType::BelongsTo:
                        if (!$relationship->isReflexive()) {
                            if (!$this->{$relationship->modelInstanceName}->id) {
                                $errorMessage = "A " . $relationship->modelAliasName . " must be selected.";
                                return false;
                            }
                        }
                        break;
                    case RelationshipType::References:
                        if (!$this->{$relationship->modelInstanceName}->id) {
                            $errorMessage = "A " . $relationship->modelAliasName . " must be selected.";
                            return false;
                        }
                        break;
                }
            }
        }

        // Check for required properties
        foreach($model->properties as $property) {
            if ($property->name == "id") { continue; }

            if ($property->required) {
                if (!$this->{$property->name}) {
                    $errorMessage = $property->displayName . " is a required field.";
                    return false;
                }
            }
        }

        // Check data types
        foreach($model->properties as $property) {
            if ($property->name == "id") { continue; }

            switch ($property->type) {
                case PropertyType::Int:
                    if ($this->{$property->name} && !Validator::isInteger($this->{$property->name})) {
                        $errorMessage = $property->displayName . " must be an integer.";
                        return false;
                    }
                    break;
                case PropertyType::UInt:
                    if ($this->{$property->name} && !Validator::isPositiveInteger($this->{$property->name})) {
                        $errorMessage = $property->displayName . " must be a positive integer.";
                        return false;
                    }
                    break;
                case PropertyType::Float:
                    if ($this->{$property->name} && !Validator::isNumber($this->{$property->name})) {
                        $errorMessage = $property->displayName . " must be a number.";
                        return false;
                    }
                    break;
                case PropertyType::Email:
                    if ($this->{$property->name} && !Validator::isEmail($this->{$property->name})) {
                        $errorMessage = $property->displayName . " is not a valid email address.";
                        return false;
                    }
                    break;
                case PropertyType::URL:
                    if ($this->{$property->name} && !Validator::isURL($this->{$property->name})) {
                        $errorMessage = $property->displayName . " is not a valid URL.";
                        return false;
                    }
                    break;
                case PropertyType::Date:
                    if ($this->{$property->name} && !Validator::isDate($this->{$property->name})) {
                        $errorMessage = $property->displayName . " is not a valid Date.";
                        return false;
                    }
                    break;
            }
        }

        // Check values
        foreach($model->properties as $property) {
            if ($property->name == "id") { continue; }

            switch ($property->type) {
                case PropertyType::String:
                    if (isset($property->minLength)) {
                        if (strlen($this->{$property->name}) < $property->minLength) {
                            $errorMessage = $property->displayName . " must have a minimum length of " . $property->minLength . " characters.";
                            return false;
                        }
                    }
                    if (isset($property->maxLength)) {
                        if (strlen($this->{$property->name}) > $property->maxLength) {
                            $errorMessage = $property->displayName . " must be no longer than " . $property->maxLength . " characters.";
                            return false;
                        }
                    }
                    break;
                case PropertyType::Int:
                case PropertyType::UInt:
                case PropertyType::Float:
                    if (isset($property->minValue)) {
                        if ($this->{$property->name} < $property->minValue) {
                            $errorMessage = "The value of " . $property->displayName . " must be at least " . $property->minValue . ".";
                            return false;
                        }
                    }
                    if (isset($property->maxValue)) {
                        if ($this->{$property->name} > $property->maxValue) {
                            $errorMessage = "The value of " . $property->displayName . " must be at most " . $property->maxValue . ".";
                            return false;
                        }
                    }
                    break;
            }
        }

        // Check for uniqueness
        foreach($model->properties as $property) {
            if ($property->name == "id") { continue; }

            if ($property->unique) {
                if (self::contains($property->name, $this->{$property->name}, $this->id)) {
                    $errorMessage = "The " . $property->name . " " . $this->{$property->name} . " already exists.";
                    return false;
                }
            }
        }

        return true;
    }

    public static function contains($propertyName, $propertyValue, $id = null)
    {
        $model = self::getModelDef();

        $pdo = self::connect();

        $sql = "SELECT id FROM " . $model->tableName . " WHERE " . $propertyName . " = :" . $propertyName;

        if ($id) {
            $sql .= " AND id <> :id";
        }

        $statement = $pdo->prepare($sql);
        $statement->bindParam($propertyName, $propertyValue);

        if ($id) {
            $statement->bindParam("id", $id);
        }
        $result = $statement->execute();

        return ($statement->rowCount() >= 1);
    }

    public static function _getTableName()
    {
        return self::getModelDef()->tableName;
    }

    public function _getJoinTableName($relationship)
    {
        $model = self::getModelDef();

        if ($relationship->asName) {

            return pascal_to_camel_case($relationship->asName);

        } else {
            if ($relationship->isReflexive()) {
                return self::createSelfJoinTableName($model->instanceName, $relationship->modelInstanceName);
            } else {
                $rModel = self::getModelDef($relationship->modelName);
                $reciprocalRelationship = $rModel->getRelationship($model->className);

                return self::createJoinTableName($reciprocalRelationship->modelInstanceName, $relationship->modelInstanceName);
            }
        }
    }

    private function createJoinTableName($table1Name, $table2Name)
    {
        // Need to make the tables names the same case so that the sort works properly
        $tableNames = array(str_to_camel_case($table1Name), str_to_camel_case($table2Name));
        sort($tableNames);

        $joinTableName = str_to_camel_case($tableNames[0]) . str_to_pascal_case($tableNames[1]) . "Link";

        return $joinTableName;
    }

    private function createSelfJoinTableName($modelName, $modelAliasName)
    {
        $joinTableName = str_to_camel_case($modelName) . str_to_pascal_case($modelAliasName) . "Link";
        return $joinTableName;
    }


    public function save()
    {
        if ($this->id) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    public static function getTotalFound()
    {
        $pdo = self::connect();

        $statement = $pdo->prepare("SELECT FOUND_ROWS()");
        $result = $statement->execute();
        $row = $statement->fetch();

        return $row[0];
    }

    public static function logQuery($statement, $sql, $parameters, $result)
    {
        if ($result && !APPIGNITE_LOG_QUERIES) { return; }
        
        date_default_timezone_set('UTC');

        $output = "--------------------------------------------------------------------------------\n\n";
        
        if (!$result) {
            $errorInfo = $statement->errorInfo();
          
            $output .= date("Y-m-d H:i:s.u") . ": PDO Error\n\n";
            $output .= "Error Code: " . $errorInfo[1] . "\n";
            $output .= "Error Message: " . $errorInfo[2] . "\n\n";  
        } else {
            $output .= date("Y-m-d H:i:s.u") . ": Query Suceeded!\n\n";                 
        }
        
        $output .= $sql . "\n\n";          
        
        foreach($parameters as $key => $value) {
            $output .=  ":" . $key . " = " . $value . "\n";
        }

        $output .= "\n";
        
        file_put_contents(APPIGNITE_LOG_PATHNAME, utf8_decode($output), FILE_APPEND);
    }    

    public function parseProperties($row, $context = null)
    {
        $tableName = $context ? $context : $this->_getTableName();

        foreach($this->_modelDef->properties as $property) {
            $columnName = $tableName . '_' . $property->name;

            if (key_exists($columnName, $row)) {
                $columnValue = $row[$columnName];

                switch ($property->type) {
                    case PropertyType::String:
                    case PropertyType::Password:
                    case PropertyType::Text:
                    case PropertyType::URL:
                        $this->{$property->name} = $columnValue;                        
                        break;
                    default:
                        $this->{$property->name} = $columnValue;
                        break;
                }
            }
        }
    }

    public function parseRow($row, $context = null)
    {
        $this->parseProperties($row, $context);

        $tableName = $context ? $context : $this->_getTableName();

        // Parse roll up information
        foreach($this->_modelDef->relationships as $relationship) {
            switch ($relationship->type) {
                case RelationshipType::HasMany:
                case RelationshipType::ManyToMany:
                    $valueName = 'num' . $relationship->modelAliasNamePlural;
                    $this->{$valueName} = $row[$tableName . '_' . $valueName];
                    break;
            }
        }

        foreach($this->_modelDef->relationships as $relationship) {
            switch ($relationship->type) {
                case RelationshipType::BelongsTo:
                case RelationshipType::References:
                    $memberName = $relationship->modelInstanceName;
                    $this->{$memberName}->parseProperties($row, $relationship->modelInstanceName);
                    break;
                case RelationshipType::ManyToMany:
                    $memberName = $relationship->modelInstanceName . 'IDs';
                    $idList = $row[$tableName . '_' . $memberName];
                    $this->{$memberName} = $idList ? explode(',', $idList) : array();
                    break;
            }
        }
    }
}
?>