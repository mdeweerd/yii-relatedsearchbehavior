<?php
/**
 * RelatedSearchBehavior Class File
 *
 * Behavior making it easier to provide search functionality for relations
 * in a grid view.
 * Also uses the {@link KeenActiveDataProvider} extension to limit the number of requests
 * to the database.
 *
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 *  The MIT License
 * @author https://github.com/mdeweerd/yii-relatedsearchbehavior
 * @author Svobik7 (yiiframework.com/forum) -
 * @author https://github.com/LeoZandvliet
 *
 * @example
 *
 * 1. Add the RelatedSearchBehavior to the Active record class.
 * <pre>
 *    public function behaviors() {
 *        return array(
 *            'relatedsearch'=>array(
 *                'class'=>'RelatedSearchBehavior',
 *                'relations'=>array(
 *                    'serial'=>'device.device_identifier',
 *                    'location'=>'device.location.description',
 *                    /* Next line describes a field where the value to search for is $this->deviceid
 *                      (from dropdown for instance) and the value to show is 'nametoshowtouser' which
 *                      has to be available as a value of the model ('value' is optional, 'field' is used by default \*\/
 *                    'fieldwithoptions'=>array(
 *                        'field'=>'device.displayname',
 *                        'searchvalue'=>'deviceid',   // Optional, when missing searchvalue is 'fieldwithoption', if not value of this option
 *                        'value'=>'nametoshowtouser', // Optional, value to show
 *                        'partialMatch'=>false,       // Optional, default is 'true'.
 *                        ),
 *                    /* Next line describes a field we do not search, but we define it here for convienience \*\/
 *                    'mylocalreference'=>'field.very.far.away.in.the.relation.tree',
 *                ),
 *		);
 *      $sort=array(
 *          'defaultOrder'=>'device_identifier DESC',
 *      );
 *
 *      // $this->disableAutoSearch('field_implemented_in_cactiverecord'); // For search variable implemented in active record.
 *		return $this->relatedSearch(
 *					$criteria,
 *					array('sort'=>$sort) // Optional default sort
 *      );
 * </pre>
 *
 * Add the new fields as safe attributes for the search scenario in rules:
 * <pre>
 * 	public function rules()
 *	{
 *	    return array(
 *	        [...]
 *			array('serial,location,deviceid','safe','on'=>'search'),
 *		);
 *	}
 * </pre>
 *
 * For the CGridView column specification, you can then just put 'serial' for the column
 *  (no need to do 'name'=>..., 'filter'=>..., 'value'=>... .
 *
 * Example:
 * <pre>
 * $this->widget('zii.widgets.grid.CGridView', array(
 *  [...]
 *	'columns'=>array(
 *      [...]
 *		'serial',
 *   )
 * ));
 * </pre>
 *
 * @method CActiveRecord getOwner()
 * @property $owner CActiveRecord
 */
class RelatedSearchBehavior extends CActiveRecordBehavior {
    /**
     * TODO: Idea to support CDBExpressions similar to this:
     array(
     'criteria'=>array(
     'select'=>array(
     'DATEDIFF(t.date_expires, CURDATE()) AS datediff',
     ),
     ),
     )
 );
 Requires adding select to criteria and indication of 'with' expression...
 */

    /**
     * Extends the search criteria with related search criteria.
     *
     * @param CDbCriteria|null $criteria
     *        If null, will get criteria currently set on the model using applyScopes.
     *        If not, the criteExisting search criteria
     * @param array $options  Options for the dataprovider.
     * @return KeenActiveDataProvider
     */
    public function getDataProvider($criteria=null,$options=array()) {
        if($criteria===null) {
            $criteria=new CDbCriteria();
            $this->getOwner()->applyScopes($criteria);
        }
        return $this->relatedSearch($criteria,$options,false);
    }
    /**
     * Extends the search criteria with related search criteria.
     *
     * @param CDbCriteria $criteria  Existing search criteria
     * @param array $options  Options for the dataprovider.
     * @return KeenActiveDataProvider
     */
    public function relatedSearch($criteria,$options=array(),$useGET=true) {
        if(!$criteria instanceof CDbCriteria) {
            $criteria=new CDbCriteria($criteria);
        }
        $owner=$this->getOwner();
        $owner->applyScopes($criteria);
        // CVarDumper::dump([$criteria,$options,$useGET],10,true);
        // If this is a search scenario.
        $isSearch=($owner->scenario==='search');

        // Local copy of relations (used often);
        $relations=$this->relations;

        // Create instance of DataProvider.
        $provider=new KeenActiveDataProvider($this->getOwner());

        // Check sort keys and sort values.
        $sort=$provider->getSort();
        if(isset($options['sort'])) {
            foreach($options['sort'] as $name=>$value) {
                $sort->$name=$value;
            }
        }
        $sort_attributes=array();
        $with=array();
        $sort_keys=array();
        $order=array();
        $group=[];
        $group_keys=[];
        $required_relations=array();

        $ownerAlias=$this->getOwner()->tableAlias;

        if($useGET && isset($_GET[$sort->sortVar])) {
            $sort_key=$_GET[$sort->sortVar];
            $sort_order='asc';
            // May have  asc or desc after the dot.
            if(($pos=strpos($sort_key, '.'))!==false) {
                $sort_order=substr($sort_key,$pos+1);
                $sort_key=substr($sort_key, 0, $pos);
            }
            if(array_key_exists($sort_key,$sort->attributes)) {
                $sort_cond=$sort->attributes[$sort_key][$sort_order];
                foreach(preg_split('/,/',$sort_cond) as $sort_rule) {
                    // Allow database expressions (MIN(), MAX(), etc) in the sort rule,
	                // also exclude the tablealias itself as required relation.
		            if(preg_match('/(\w+)[^\.\(]*\./',$sort_rule,/** @var string[] $matches */ $matches)) {
                    if($matches[1]!==$ownerAlias)
			            $required_relations[]=$matches[1];
                    }
                }
            }
            $sort_keys[]=$sort_key; // Default order is asc.
        }
        foreach($required_relations as $relation) {
            $w=null;

            foreach(array_reverse(preg_split('/\./',$relation)) as $r) {
                if($w!==null) {
                    $w=array($r=>array('with'=>$w,'joinType'=>'LEFT JOIN', 'select'=>array()));
                } else {
                    $w=array($r=>array('select'=>array()));
                }
            }
            $with[$relation]=$w;
        }
        //CVarDumper::dump($with);

        if(strval($criteria->order)!=='') {
            foreach(preg_split('/,/',$criteria->order) as $sort_rule) {
                if(preg_match('/^\s*(\w+)(.*)/',$sort_rule,$matches)) {
                    $order[]=array($matches[1],$matches[2]);
                    $sort_keys[]=$matches[1];
                } else {
                    $order[]=array($sort_rule,'');
                    $sort_keys[]="";
                }
            }
        }


        if(strval($criteria->group)!=='') {
            foreach(preg_split('/,/',$criteria->group) as $group_item) {
                if(preg_match('/^\s*(\w+)(.*)/',$group_item,$matches)) {
                    $group[]=array($matches[1],$matches[2]);
                    $group_keys[]=$matches[1];
                } else {
                    $group[]=array($group_item,'');
                    $group_keys[]="";
                }
            }
        }


        /** @var CDbSchema $dbSchema */
        $dbSchema=$owner->getDbConnection()->getSchema();
        if(method_exists($owner,'exactSearchAttributes')) {
            $exactSearchAttributes=$owner->exactSearchAttributes();
        } else {
            $exactSearchAttributes=[];
        }

        $resolved_relations=array();
        $resolved_columns=array();
        /* Convert relation properties to search and sort conditions */
        foreach($relations as $var=>$relationvar) {
            if(in_array(strtolower($var),array('owner','enabled'))) {
                throw new CException("Related name '$var' in '".get_class($this->getOwner())."' clashes with CActiveRecord property.  Choose another name.");
            }
            //Yii::trace("REL Check $var EXACT");
            $partialMatch=!in_array($var,$exactSearchAttributes);
            // Ovar is the attribute for the search value in the Model's object.
            // It depends on the options for the relation.
            $ovar=$var;
            // Check configuration of the relation.
            // Either an array with 'field', 'searchvalue', 'partialMatch', or the path to the relation.
            if(is_array($relationvar)) {
                // Array option for the relation
                $relationfield=$relationvar['field'];
                if(isset($relationvar['searchvalue'])) {
                     $ovar=$relationvar['searchvalue'];
                }
                if(isset($relationvar['partialMatch'])) {
                     $partialMatch=$relationvar['partialMatch'];
                }
            } else {
                // Field for the relation.
                $relationfield=$relationvar;
            }
            // Get the search value for the relation, but only if searching.
            if($isSearch&&!isset($this->_disableAutoSearch[$ovar]))
                $search_value=$this->getOwner()->{$ovar};
            else
                $search_value='';

            if($search_value==='=') {
            	$search_value=array(null,'');
            }
            //  print "<br>B $relationfield $var ".CVarDumper::dumpAsString($relationvar);

            // Resolve the relation.
            $done=false;  // Becomes true when the relation is fully resolved.
            while(!$done) {
                $done=true;
                // Get relation part, table alias, and column reference in query.
                $relation=$relationfield;
                $column=$relationfield;
                // The column name itself is everything after the last dot in the relationfield.
                $pos=strrpos($relationfield, '.');
                if($pos!==false) {
                    $column=substr($relationfield, $pos+1);
                }

                // The full relation path is everything before the last dot.
                $pos=strrpos($relation, '.');
                $relation=substr($relation, 0, $pos);

                // $relation and $column are now known.  The relation may still be a complex path.

                // The join table alias is the last part of the relation.
                $shortrelation=$relation;
                if(($pos=strrpos($shortrelation, '.'))!==false) {
                    $shortrelation=substr($shortrelation, $pos+1);
                }

                if($shortrelation==='') {
                    //print "<br>Local field $ovar $var '$search_value'";
                    Yii::trace("<br>Local field $ovar $var '$search_value'",'relatedSearchBehavior');
                    // Alias for local field.
                    $done=true;
                } else {
                    // Check if the relation has an alias and if it does, use it as the relation reference
                    // to make the SQL request work.
                    $ownerRelationsDefinitions = $this->getOwner()->relations();
                    if (isset($ownerRelationsDefinitions[$shortrelation])) {
                        $currentRelationDefinition = $ownerRelationsDefinitions[$shortrelation];

                        if (isset($currentRelationDefinition['alias'])) {
                            $shortrelation = $currentRelationDefinition['alias'];
                        }

                        /** Check recursively for relations */
                        /** @var CActiveRecord $model */
                        $model=$currentRelationDefinition[1]::model();
                        if(array_key_exists($column,$model->relations())) {
                            Yii::trace("Skip relation {$currentRelationDefinition[1]}/{$column}",'relatedSearchBehavior');

                            // This is a realation, so an object, not comparable.
                            continue 2; // Relation object, not comparing
                        } elseif(isset($model->relations)) {
                            //print get_class($model)." $column<br>";
                            $model_relations=$model->relations;
                            //Yii::trace("Model relation $column");
                            if(isset($model_relations[$column])) {
                                //print "$column<br/>";
                                $relationfield=$relation.'.';
                                $model_relationvar=$model_relations[$column];
                                if(is_array($model_relationvar)) {
                                    // Array option for the relation
                                    $relationfield.=$model_relationvar['field'];
                                } else {
                                    // Field for the relation.
                                    $relationfield.=$model_relationvar;
                                }
                                //print "$relationfield<br/>\n";
                                $done=false;
                            }
                        }
                    }
                }
                //'@phan-assert string $shortrelation';
            }
            '@phan-var string $shortrelation
             @phan-var string $column
             @phan-var string|array $relation';

            // The column reference in the query is the table alias + the column name.
            if(is_string($shortrelation)&&$shortrelation !== '') {
                $column="$shortrelation.$column";
            } else {
                $column="$ownerAlias.$column";
            }
            $column=$dbSchema->quoteColumnName($column);

            // Resolution done, add the information to our table.
            if($shortrelation !== '') {
                $resolved_relations[$var]=$relation;
            }
            $resolved_columns[$var]=$column;

            /* Now check if we need this relation.
             * The relation may be needed for a search value, a sort value in the sort field, or
             * a sort in the criteria.
             */
            /* Actual search functionality */
            $require_relation=false;
            /* a. Check if the relation is needed for searching, and perform the search. */
            // If a search is done on this relation, add compare condition and require relation in query.
            // Excluding object to avoid special cases.
            $isArr=is_array($search_value);
            if(  (!$isArr&&strval($search_value)!=="")
               ||($isArr&&!empty($search_value))
            ) {
                if(!is_object($search_value)) {
                    $require_relation=($shortrelation!=='');
                    $criteria->compare($column,$search_value,$partialMatch);
                } else {
                    $type=get_class($search_value);
                    throw new CException("Provided search value for '$ovar' ($column) is '$type', should be string or array.");
                }
            }
            // b. If a sort is done on this relation, require the relation in the query.
            // c. If a sort is done in the criteria, require the relation.
            if(   ($shortrelation!=='')
                  &&(
                      in_array(strval($var),$sort_keys)
                  ||  in_array(strval($var),$group_keys)
                  )
            ) {
                $require_relation=true;
            }

            // d. If a relation is used in the criteria condition, require it and adjust the condition
            // Pattern to search for, search if relation is used as whole word only (dont want partials to be replaced)
            $pattern = '/(?<![.`\'"])\b('.preg_quote($var).')\b/';
            $totalReplaced = 0;
            $criteria->condition=preg_replace($pattern, $column,$criteria->condition, -1, $totalReplaced);
            if(($totalReplaced!==0)&&$shortrelation!=='') {
                $require_relation=true;
            }

            // If the relation is required, add it to the search condition.
            if($require_relation&&is_string($relation)) {
                // Add with conditions to setup join relations
                $w=null;
                foreach(array_reverse(preg_split('/\./',$relation)) as $r) {
                    if($w!==null) {
                        $w=array($r=>array('with'=>$w,'select'=>array()));
                    } else {
                        $w=array($r=>array('select'=>array()));
                    }
                }
                $with[$relation]=$w;
                //Yii::trace("Extra With:".CVarDumper::dumpAsString($w,10,false));
            }
            // Add sort attributes (always).
            $sort_attributes[strval($var)] = array(
                    "asc" => $column,
                    "desc" => "$column DESC",
                    "label" => $this->getOwner()->getAttributeLabel($var),
            );
        }

        // Yii::trace("Resolved relations:".CVarDumper::dumpAsString($resolved_relations,10,false));
        // Yii::trace("Resolved columns:".CVarDumper::dumpAsString($resolved_columns,10,false));
        // Yii::trace("Sort attributes:".CVarDumper::dumpAsString($sort_attributes,10,false));
        // Yii::trace("With:".CVarDumper::dumpAsString($with,10,false));
        // Yii::trace("Order:".CVarDumper::dumpAsString(["order"=>$order],10,false));

        if(count($group)) {
            $result=[];
            // Update criteria
            foreach($group as $value) {
                if(array_key_exists($value[0],$resolved_columns)) {
                    $value[0]=$resolved_columns[$value[0]];
                }
                $result[]=$value[0].$value[1];
            }
            $criteria->group=implode(",",$result);
        }

        /** Update order rule with resolved relations */
        if(count($order)) {
            $result=[];
            // Update criteria
            foreach($order as $value) {
                if(array_key_exists($value[0],$resolved_columns)) {
                    $value[0]=$resolved_columns[$value[0]];
                }
                $result[]=$value[0].$value[1];
            }
            $criteria->order=implode(",",$result);
        }
        /* Always allow sorting on default attributes */
        $sort_attributes[]="*";

        if(isset($options['sort'])){
            $sort->attributes= CMap::mergeArray($sort->attributes, $sort_attributes);
        }
        else
        {
            $sort->attributes=$sort_attributes;
        }

        /* Check defaultOrder for use of alias. */
        if(isset($sort->defaultOrder)) {
            if(is_string($sort->defaultOrder)) {
                // Currently support alias for one related field.
                if(preg_match_all('/\s*(?<var>[^,\s]*)\s+(?<sort>DESC|ASC)?\s*,?/i', $sort->defaultOrder,$matches,PREG_SET_ORDER)) {
                	$sort_fields=array();
                	foreach($matches as $m) {
	                    $var=$m['var'];
	                    $order=$m['sort'];
	                    if(isset($sort_attributes[$var])) {
	                    	if("$order"==="") {
	                    		$order="asc";
	                    	}
	                    	/* Find the appropriate sorting rule from sorting directives in $sort_attributes */
	                        $sort_fields[]=$sort_attributes[$var][strtolower($order)];
	                        /* Require the relation to make the sort possible */
	                        $with[$resolved_relations[$var]]=$resolved_relations[$var];
	                    } else {
	                    	$sort_fields[] = "$var $order";
	                    }
                	}
                	$sort->defaultOrder=implode(',',$sort_fields);
                }
            } /* else, is an array, do nothing */
        }
        //   print "Default order ".$sort->defaultOrder;exit;
        // Yii::trace("With:".CVarDumper::dumpAsString($with,10,false));

        foreach($with as $w) {
            $criteria->mergeWith(array('with'=>$w));
        }
        $criteria->together=true;
        //CVarDumper::dumpAsString($criteria->toArray(),10,true);exit;
        // Construct options for the data provider.
        $providerConfig=array();
        // Copy the options provides to empty array (to prevent overwriting the original array.
        $providerConfig=CMap::mergeArray($providerConfig, $options);
        // Merge our constructed options with the array.
        $providerConfig=CMap::mergeArray(
                $providerConfig,
                array(
                        'criteria'=>$criteria,
                        'sort'=>$sort,
                )
        );
        foreach($providerConfig as $key=>$value) {
            $provider->$key=$value;
        }
        return $provider;
    }


    /****************************************************
     * Implementation of getter/setters for search fields
    */
    public $relations=array();

    private $_data = array();
    /**
     * Provides set search values in the 'search' scenario and database values in any other case.
     *
     * (non-PHPdoc)
     * @see CComponent::__get()
    */
    public function __get($key) {
        /** @var CActiveRecord $owner */
        $owner=$this->getOwner();
        if($owner->getScenario()==='search') {
            $key=strtolower($key);
            // When in the search scenario get the value for the search stored locally.
            return (array_key_exists($key,$this->_data) ? $this->_data[$key] : null);
        } else {
            // Not in search scenario - return the normal value.
            if(isset($this->relations[$key])||isset($this->relations[$key=strtolower($key)])) {
                // This field is known in our relations
                $relationvar = $this->relations[$key];
                if(is_array($relationvar)) {
                    // Complex field: has different value for search and display value.
                    if(isset($relationvar['value'])) {
                        $valueField=$relationvar['value'];
                    } else {
                        $valueField=$relationvar['field'];
                    }
                    $value=CHtml::value($owner,$valueField);
                } else {
                    // Standard field: same value for search and for display value.
                    //$relationfield=$relationvar;
                    //\Yii::trace('Owner '.get_class($owner)."->$relationvar scenario ".$owner->getScenario(),'system.db.relatedSearchBehavior.ownerscenario');
                    $value=CHtml::value($owner,$relationvar);
                }
                return $value;
            }
        }
    	throw new CException(\Yii::t('yii','Property "{class}.{property}" is not defined.',
			array('{class}'=>get_class($this), '{property}'=>$key)));
    }

    /**
     * Adds a between condition.
     *
     * Similar to CDbCriteria::addBetweenConditoni
     *
     */
    public function addBetweenCondition($field,$value_start,$value_end,$operator='AND') {
        $this->addRelationCompare($field, ['#between#'=>true,$value_start,$value_end],false,$operator);
    }
    private $_disableAutoSearch=[];

    /**
     * Disable adding internal search value variable for the given field - use the field value from
     * the owner class instead.
     *
     * In order to accept search values for relational fields, RelatedSearchBehavior implements
     * local storage for these relational fields.
     * In some cases the owner class (the CActiveRecord) already implements this and calling
     * this method instructs RelatedSearchBehavior to use the search value found in the owner instance.
     *
     * @param string $field
     */
    public function disableAutoSearch($field) {
        $this->_disableAutoSearch[$field]=true;
    }

    /**
     * @var integer Counter to create unique aliases, etc.
     */
    static $count=0;

    /**
     * Add a condition for a relation.
     *
     * Same parameters as a compare condition for CDbCriteria except when $search_value['#between#'] is set
     * in which case this becomes a between condition.
     *
     * TODO: Refactor this with 'relatedSearch' method.
     * @param string $field
     * @param mixed[] $search_value
     * @param string|false $partialMatch
     * @param string $operator
     * @param bool $escape
     */
    public function addRelationCompare($field,$search_value, $partialMatch=false, $operator='AND', $escape=true) {
        $relationvar=$this->relations[$field];
        // Check configuration of the relation.
        // Either an array with 'field', 'searchvalue', 'partialMatch', or
        // the path to the relation.
        if(is_array($relationvar)) {
            // Array option for the relation
            $relationfield=$relationvar['field'];
            /*
            if(isset($relationvar['searchvalue'])) {
                $ovar=$relationvar['searchvalue'];
            }
            */
            if(isset($relationvar['partialMatch'])) {
                $partialMatch=$relationvar['partialMatch'];
            }
        } else {
            // Field for the relation.
            $relationfield=$relationvar;
        }
        /////////////////////////////////////////////////////////////////
        // Get relation part, table alias, and column reference in query.
        /////////////////////////////////////////////////////////////////
        $done=false;
        while(!$done) {
            $done=true;
            // Get relation part, table alias, and column reference in query.
            $relation=$relationfield;
            $column=$relationfield;
            // The column name itself is everything after the last dot in the relationfield.
            $pos=strrpos($relationfield, '.');
            if($pos!==false) {
                $column=substr($relationfield, $pos+1);
            }

            // The full relation path is everything before the last dot.
            $pos=strrpos($relation, '.');
            if($pos!==false) {
                $relation=substr($relation, 0, $pos);
            } else {
                $relation='';
            }

            // The join table alias is the last part of the relation.
            $shortrelation=$relation;
            if(($pos=strrpos($shortrelation, '.'))!==false) {
                $shortrelation=substr($shortrelation, $pos+1);
            }

            // Check if the relation has an alias and if it does, use it as the relation reference
            // to make the SQL request work.
            $ownerRelationsDefinitions = $this->getOwner()->relations();
            if (isset($ownerRelationsDefinitions[$shortrelation])) {
                $currentRelationDefinition = $ownerRelationsDefinitions[$shortrelation];

                if (isset($currentRelationDefinition['alias'])) {
                    $shortrelation = $currentRelationDefinition['alias'];
                }

                /** Check recursively for relations */
                /** @var CActiveRecord $model */
                $model=$currentRelationDefinition[1]::model();
                if(isset($model->relations)) {
                    $model_relations=$model->relations;
                    if(isset($model_relations[$column])) {
                        $m_relation=$model_relations[$column];
                        if(is_array($m_relation)) {
                            /*
                             * if(isset($relationvar['searchvalue'])) {
                             *    $ovar=$relationvar['searchvalue'];
                             * }
                             */
                            if(isset($m_relation['partialMatch'] )) {
                                $partialMatch=$m_relation['partialMatch'];
                            }
                            // Array option for the relation
                            $m_relation=$m_relation['field'];
                        }
                        $relationfield=$relation.'.'.$m_relation;
                        $done=false;
                    }
                }
            }
        }


        /** @var CDbSchema $dbSchema */
        $dbSchema=$this->getOwner()->getDbConnection()->getSchema();

        // The column reference in the query is the table alias + the column name.
        if($shortrelation!=='') {
            $column="$shortrelation.$column";
        }
        $column=$dbSchema->quoteColumnName($column);

        /* Actual search functionality */

        // If a search is done on this relation, add compare condition and require relation in query.
        // Excluding object to avoid special cases.
        if(!is_object($search_value)) {
        	/** @var CDbCriteria $criteria */
            $criteria=$this->getOwner()->getDbCriteria();
            // Add with conditions to setup join relations
            $w=null;
            foreach(array_reverse(preg_split('/\./',$relation)) as $r) {
                if($r==='') continue; // Local table, no column.
                if($w!==null) {
                    $w=array($r=>array('with'=>$w,'select'=>array()));
                } else {
                    $w=array($r=>array('select'=>array()));
                }
            }
            if($w!==null) {
                $criteria->mergeWith(array('with'=>$w));
            }

            if(is_array($search_value)&&isset($search_value['#between#'])) {
                $criteria->addBetweenCondition($column,$search_value[0],$search_value[1],$operator);
            } else {
                $criteria->compare($column,$search_value,$partialMatch,$operator,$escape);
            }
        } else {
            $obj=CVarDumper::dumpAsString($search_value);
            throw new CException("Provided search value for '$field' ($column) is an object ($obj), should be string or array.");
        }
    }

    /**
     * Sets the value for the search key.
     * (non-PHPdoc)
     * @see CComponent::__set()
     * @return mixed
     */
    public function __set($key, $value) {
        $owner=$this->getOwner();
        if($owner->getScenario()==='search') {
            if($owner->isAttributeSafe($key)) {
                $this->_data[strtolower($key)] = $value;
                return;
            }
        }
        throw new CException("Can only set safe search attributes");
    }

    /**
     * Check if a property is available.
     *
     * Relies on __isset() because any attribute here is a property.
     *
     * (non-PHPdoc)
     * @see CComponent::canGetProperty()
     */
    public function canGetProperty($name) {
        return parent::canGetProperty($name)||$this->__isset($name);
    }

    /**
     * Validate properties that are save in the 'search scenario'.
     * (non-PHPdoc)
     * @see CComponent::canSetProperty()
     */
    public function canSetProperty($key) {
        if(parent::canSetProperty($key)) return true;
        $owner=$this->getOwner();
        if($owner->getScenario()==='search') {
            return($owner->isAttributeSafe($key));
        }
        return false;
    }

    /**
     * Checks if a value is available and set through this behavior.
     *
     * 1. Checks if the value was set in the search scenario (no need to test if this
     *    is the search scenario, because that is tested in the setter.
     * 2. Checks if the value is available through a defined relation (alias).
     *
     * (non-PHPdoc)
     * @see CComponent::__isset()
     */
    public function __isset($name) {
        if(array_key_exists(strtolower($name),$this->_data)) {
            return true;
        } else {
            foreach(array_keys($this->relations) as $key/*=>$relationvar*/) {
                if($key===$name||(strtolower($key)===strtolower($name))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Unsets a value - only unsets search values.
     *
     * (non-PHPdoc)
     * @see CComponent::__unset()
     */
    public function __unset($key) {
        $key=strtolower($key);
        if(isset($this->_data[$key])) {
            unset($this->_data[$key]);
        }
    }

    public function rbRelations() {
        return $this->relations;
    }
    /**
     * Implement automatic scopes for fields.
     *
     * Should be called from owner_class like this:
     * public function __call($name,$parameters) {
     *     try {
     *         return parent::__call($name,$parameters);
     *     } catch (CException $e) {
     *        if(preg_match(
     *	                '/'.Yii::t(
     *	                        'yii',
     *	                        quotemeta(
     *	                                Yii::t(
     *	                                        'yii',
     *	                                        '{class} and its behaviors do not have a method or closure named "{name}".'
     *	                                        )
     *	                                ),
     *	                                array('{class}'=>'.*','{name}'=>'.*')
     *	                        )
     *	                .'/',$e->getMessage())) {
     *             return $this->autoScope($name, $parameters);
     *         } else {
     *             throw $e;
     *         }
     *     }
     * }
     *
     * (non-PHPdoc)
     * @see CComponent::__call()
     */
    public function autoScope($name, $parameters) {
        /** @var CActiveRecord $owner */
        $owner = $this->getOwner();

        if(count($parameters) && ($owner instanceof CActiveRecord)) {
            if(   ($inOwner=$owner->hasAttribute($name))
                ||isset($this->relations[$name])
            ) {
                $column=$name;
                $value=$parameters[0];
                if($value==='=') {
            		$value=array(null,'');
            	}
            	$partialMatch=false;
                $operator="AND";
                $escape=true;
                switch(count($parameters)) {
                    case 4:
                        $escape=$parameters[3];
                        /* fall through */
                    case 3:
                        $operator=$parameters[2];
                        /* fall through */
                    case 2:
                        $partialMatch=$parameters[1];
                        /* fall through */
                }
                if($inOwner) {
                    $db_col = $owner->getDbConnection()->getSchema()->quoteColumnName($owner->getTableAlias().'.'.$column);
                    if($value===null) {
                        // Creates is null condition for exact match
                        $owner->getDbCriteria()->addInCondition($column, [null], $operator);
                    } elseif($value!==null||$partialMatch) {//if($column==='device_type_protocol'&&(self::$count++>-1))throw new CException($db_col.self::$count);
                    	if(!$partialMatch||!is_array($value)) {
                            if(!is_array($value)) {
                        	    $owner->getDbCriteria()->compare($db_col, $value,$partialMatch,$operator,$escape);
                            } else {
                        	    $owner->getDbCriteria()->addInCondition($db_col, $value,$operator);
                            }
                    	} else {
                    		/** Partial match requested on array - implement regular expression */
                    		if($escape) {
                    			$value=array_map("preg_quote",$value);
                    		}
                    		$condition=$db_col." REGEXP '(".implode('|',$value).")'";
                        	$owner->getDbCriteria()->addCondition($condition,$operator);
                    	}

                    } else {
                        if(!is_array($value)) {
                            $value=[$value];
                        }
                        $owner->getDbCriteria()->addInCondition($column, $value, $operator);
                    }
                } else {
                    $this->addRelationCompare($name,$value,$partialMatch,$operator,$escape);
                }
                return $owner;
            }
        }
        if(!count($parameters)) {
            if(strpos($name, 'get') === 0) {
                return $owner->{substr($name, 3)};
            } else {
                $isAutoScope=($inOwner=$owner->hasAttribute($name)) ||
                         isset($this->relations[$name]);
                if($isAutoScope) $message='Parameters required for autoscope "{class}.{property}';
                else
                    $message='Property or method "{class}.{property}" is not defined.';
            }
        } else {
            if($owner instanceof CActiveRecord) {
                $message='Property or method "{class}.{property}" is not defined.';
            } else {
                $message='"{class}" must inherit from CActiveRecord for {extension}';
            }
        }
        $errorParams=array(
                '{class}' => get_class($owner),
                '{property}' => $name,
                '{extension}' => get_class($this)
        );
        throw new CException(
                Yii::t('RelatedSearchBehavior.t', $message, $errorParams));
    }
    // Suggestions:
    //  Add 'quoteRelationField' method to quote a relation according to the defined fields.

    /** History
     * 1.03  Quoting relations in database.
     * 1.04  Added autoScope.
     *       Added option 'partialMatch' for relation.
     * 1.05  Enable multiple attributes in default sort.
     * 1.06  Fix to autoScope - return owner (chaining) + correct example in comment.
     * 1.07  Rely on DataProvider to create sort object in order to get the usual key for the $_GET sort var.
     * 1.08  Fix in KeenActiveDataProvider to quote column in GROUP BY.
     * 1.09  Allow array for search value.
     * 1.10  Use alias defined in model's relation (Svobik7)
     * 1.11  Corrected test of owner class type (lower case 'c') and improved error message for autoscope.
     * 1.12  Autoscope for relations and 'addRelatedCondition' method as a complement to 'addCondition'.
     * 1.13  Handle 'getter' in autoscope call.
     * 1.14  Look recursively for relations.
     * 1.15  Added 'getDataProvider'.
     * 1.16  Added relations used in sort "attributes" provided as a parameter.
     * 1.17  Improved error messages. Fix for relations that are defined through option array.
     *       Fix for local fields (aliases/virtual attributes with modified search field)
     * 1.18  Renamed 'addRelationCondition' in 'addRelatedCompare'.
     * 1.19  Reactivated 'together'.
     * 1.20  Converting partial match on list in regular expression comparison.
     * 1.21  Added 'addBetweenCondition'
     * 1.22  Allow DB expression in sort rule, exclude tablealias from req. relations, allow multiple fields in sort rule.
     *       Disable autosearch option
     * 1.23  Resolve nested "complex" relations (relations expressed as array in related model)
     * 1.24  When matching empty array, match it exactly (does not match anything)
     * 1.25  PHPDoc improvements, use of strval where possible (PHAN checks).
     * 1.26  Allow related search to be directly used in the $criteria->condition (LeoZandvliet)
     * 1.27  Improved regex added in 1.26.
     */
}
