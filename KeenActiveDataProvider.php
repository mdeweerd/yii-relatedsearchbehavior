<?php
/**
 * KeenActiveDataProvider implements a data provider based on ActiveRecord and is
 * extended from CActiveDataProvider.
 *
 * KeenActiveDataProvider provides data in terms of ActiveRecord objects. It uses
 * the AR {@link CActiveRecord::findAll} method to retrieve the data from database.
 * The {@link criteria} property can be used to specify various query options. If
 * you add a 'with' option to the criteria, and the same relations are added to the
 * 'withKeenLoading' option, they will be automatically set to select no columns.
 * ie. array('author'=>array('select'=>false)
 *
 * HAS_ONE and BELONG_TO type relations should not be set in withKeenLoading,
 * but in the $criteria->with, because its more efficient to load them in the
 * normal query.
 *
 * There will be a CDbCriteria->group set automatically, that groups the model
 * to its own primary keys.
 *
 * The relation names you specify in the 'withKeenLoading' property of the
 * configuration array will be loaded in a keen fashion. A separate database
 * query will be done to pull the data of those specified related models.
 *
 * KeenActiveDataProvider may be used in the following way:
 * <pre>
 * $dataProvider=new KeenActiveDataProvider('Post', array(
 *     'criteria'=>array(
 *         'condition'=>'status=1',
 *         'order'=>'create_time DESC',
 *         'with'=>array('author'),
 *     ),
 *     'pagination'=>array(
 *         'pageSize'=>20,
 *     ),
 *     'withKeenLoading'=>array('categories'),
 * ));
 * // $dataProvider->getData() will return a list of Post objects with their related data
 * </pre>
 *
 * @property CDbCriteria $criteria The query criteria.
 * @property CSort $sort The sorting object. If this is false, it means the sorting is disabled.
 * @property mixed $withKeenLoading The relations specified here as a comma separated string
 * or array will be loaded in a keen fashion.
 *
 * @author yJeroen <http://www.yiiframework.com/forum/index.php/user/39877-yjeroen/>
 * @author tom[] <?>
 */
class KeenActiveDataProvider extends CActiveDataProvider
{
    /**
     * @var mixed[]
     */
    private $_withKeenLoading = [];

    public $extrakeys = [];

    /**
     * Constructor.
     * Can change $config, before calling CActiveDataProvider's __construct.
     *
     * @param mixed $modelClass the model class (e.g. 'Post') or the model finder instance
     *                          (e.g. <code>Post::model()</code>, <code>Post::model()->published()</code>).
     * @param array $config     configuration (name=>value) to be applied as the initial property values of this class.
     */
    public function __construct($modelClass, $config=[])
    {
        //tr('constructing!','constructing!');
        parent::__construct($modelClass, $config);
    }

    /**
     * Specifies which related objects should be Keenly loaded.
     * This method takes variable number of parameters. Each parameter specifies
     * the name of a relation or child-relation. These parameters will be used in
     * the criteria for CActiveRecord::model()->findAllByAttributes($data, $criteria).
     *
     * By default, the options specified in {@link relations()} will be used to do
     * relational query. In order to customize the options on the fly, we should
     * pass an array parameter to the withKeenLoading parameter of the DataProviders
     * configuration array.
     * For example,
     * <pre>
     * $dataProvider=new KeenActiveDataProvider('Post', array(
     *     'criteria'=>array(
     *        'condition'=>'status=1',
     *        'with'=>array('author'),
     *   ),
     *   'pagination'=>array(
     *     'pageSize'=>20,
     *   ),
     *   'withKeenLoading'=>array(
     *     'author'=>array('select'=>'name'),
     *     'comments'=>array('condition'=>'approved=1', 'order'=>'create_time'),
     *   )
     * ));
     * </pre>
     *
     * withKeenLoading can be set as a string with comma separated relation names,
     * or an array. The array keys are relation names, and the array values are
     * the corresponding query options.
     *
     * In some cases, you don't want all relations to be Keenly loaded in a single
     * query because of data efficiency. In that case, you can group relations in
     * multiple queries using a multidimensional array. (Arrays inside an array.)
     * Each array will be keenly loaded in a separate query.
     * Example:
     * 'withKeenLoading'=>array( array('relationA','relationB'),array('relationC') )
     *
     * HAS_ONE and BELONG_TO type relations shouldn't be set in withKeenLoading,
     * but in the $criteria->with, because its more efficient to load them in the
     * normal query.
     *
     * @param mixed $value the relational query criteria. This is used for fetching
     *                     related objects in a Keen loading fashion.
     *
     * @return void
     */
    public function setWithKeenLoading($value)
    {
        if (is_string($value)) {
            $this->_withKeenLoading = preg_split('@,@', $value, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $this->_withKeenLoading = (array)$value;
        }
        $newWithKeen = [];
        foreach ($this->_withKeenLoading as $k=>$v) {
            if (!(is_int($k) && is_array($v))) {
                unset($this->_withKeenLoading[$k]);
                $newWithKeen[$k] = $v;
            }
        }
        $this->_withKeenLoading[] = $newWithKeen;
    }

    /**
     * Fetches the data from the persistent data storage.
     * Additionally, calls KeenActiveDataProvider::afterFetch method
     *
     * @return array list of data items
     */
    protected function fetchData()
    {
        if ($this->_withKeenLoading) {
            $this->_prepareKeenLoading();
        }
        $data = parent::fetchData();
        if ($data && $this->_withKeenLoading) {
            $data = $this->afterFetch($data);
        }
        return $data;
    }

    /**
     * Sets the relations, that are not HAS_ONE and BELONG_TO type relations,
     * in the CDbCriteria::$with that have also been set in
     * KeenActiveDataProvider::$withKeenLoading, to the value of
     * array('select'=>false), to not unnecessarily load data. The related
     * data will be loaded in a Keen fashion.
     *
     * @return void
     */
    private function _prepareKeenLoading()
    {
        if (!empty($this->criteria->with)) {
            $this->criteria->with = (array)$this->criteria->with;

            foreach ((array)$this->criteria->with as $k=>$v) {
                if (is_int($k) && (strpos($v, '.') !== false
                    || (!$this->model->metaData->relations[$v] instanceof CHasOneRelation
                    && !$this->model->metaData->relations[$v] instanceof CBelongsToRelation))
                    || !is_int($k) && (strpos($k, '.') !== false
                    || (!$this->model->metaData->relations[$k] instanceof CHasOneRelation
                    && !$this->model->metaData->relations[$k] instanceof CBelongsToRelation))
                ) {
                    foreach ($this->_withKeenLoading as $groupedKeen) {
                        foreach ($groupedKeen as $keenKey=>$keenValue) {
                            if (is_int($k) && $v === $keenValue) {
                                unset($this->criteria->with[$k]);
                                $this->criteria->with[$v] = array('select'=>false);
                            } elseif ((is_int($keenKey) && $k === $keenValue) || (is_string($keenKey) && $k === $keenKey)) {
                                $this->criteria->with[$k] = array('select'=>false);
                            }
                        }
                    }
                } else {
                    foreach ($this->_withKeenLoading as $groupedKey=>$groupedKeen) {
                        foreach ($groupedKeen as $keenKey=>$keenValue) {
                            if (is_int($k) && $v === $keenValue) {
                                unset($this->_withKeenLoading[$groupedKey][$keenKey]);
                            } elseif ((is_int($keenKey) && $k === $keenValue) || (is_string($keenKey) && $k === $keenKey)) {
                                unset($this->_withKeenLoading[$groupedKey][$keenKey]);
                            }
                        }
                    }
                }
            }

            if (true) {
                $this->criteria->distinct=true; // Same as grouping on primary key.
            } else {
                $pkNames = (array)$this->model->tableSchema->primaryKey;
                $dc=$this->model->dbConnection;
                foreach ($pkNames as $k=>$v) {
                    //$pkNames[$k] = $dc->quoteColumnName($this->model->tableAlias.'.'.$v);
                    $pkNames[$k] = $dc->quoteColumnName($this->model->tableAlias.'.'.$v);
                }

                $this->criteria->group = implode(',', $pkNames);
            }
        }
    }

    /**
     * Loads the primary keys and values of the found models in an array.
     *
     * @param array $data An array of models returned by CActiveDataProvider::fetchData()
     *
     * @return non-empty-array<string,mixed[]> The keys will be the column name of the primary key of the model
     * and the value will be an array of the primary key values of the models that have
     * been loaded by CActiveDataProvider::fetchData()
     *
     * @suppress PhanPluginMoreSpecificActualReturnType
     */
    private function _loadKeys($data)
    {
        $pks = [];
        foreach ((array)$this->model->tableSchema->primaryKey as $pkName) {
            foreach ($data as $dataItem) {
                $pks[$pkName][] = $dataItem->$pkName;
            }
        }
        return $pks;
    }

    /**
     * Loads additional related data in bulk, instead of each model lazy loading its related data
     *
     * @param array $data An array of models returned by CActiveDataProvider::fetchData()
     *
     * @return array $data An array of models with related data Keenly loaded.
     */
    protected function afterFetch($data)
    {
        $pks = $this->_loadKeys($data);
        foreach ($this->_withKeenLoading as $keenGroup) {
            if (!empty($keenGroup)) {
                $relatedModels = $this->model->findAllByAttributes(
                    $pks,
                    array('select'=>array_merge($this->extrakeys, CPropertyValue::ensureArray($this->criteria->group)),
                                'with'=>$keenGroup)
                );
                foreach ($data as $model) {
                    /**
                     * @var CActiveRecord $relatedModel
                     */
                    foreach ($relatedModels as $relatedModel) {
                        $same = false;
                        foreach ((array)$this->model->tableSchema->primaryKey as $pkName) {
                            if ($model->$pkName === $relatedModel->$pkName) {
                                $same = true;
                            }
                        }
                        if ($same) {
                            foreach ($this->model->metaData->relations as &$relation) {
                                if ($relatedModel->hasRelated($relation->name)) {
                                    $model->{$relation->name} = $relatedModel->{$relation->name};
                                }
                            }
                        }
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Functions from JsonActiveDataProvider
     */

    /**
     *
     * @var mixed If is set to null (default) all attributes of the model will be added.
     *            If a string is given, only this attribute will added.
     *            If an array of strings is given, all elements will be retrieved.
     */
    public $attributes;

    /**
     *
     * @var mixed If is set to null (default) no relations of the model will be added.
     *            If a string is given, only the mentioned relation will be added.
     *            If an array is given, there a two valid array formats:
     *
     *            1. array('relation1', 'relation2') will return the mentioned relations with all attributes, but no sub relations
     *            2. array('relation1', 'relation2' => array('attributes' => array('foo', 'bar'), 'relations' => array('subRelation'))) will return configured attributes and relations for relation2
     *
     *            Sub configurations of relations follow the same rules like the global configuration for attributes and relations
     */
    public $relations;

    /**
     *
     * @var array An array where the key is the original attribute name and the value the alias to be used instead when retrieving it. This will affect all retrieved models recursively.
     */
    public $attributeAliases;

    /**
     *
     * @var boolean When set to true the root of the json will have meta information from the data provider for counts and pagination.
     */
    public $includeDataProviderInformation = true;

    /**
     *
     * @var ?callable Callback to be called after an model was processed for json, the callback will receive the model itself, the attribute array and the relation array
     */
    public $onAfterModelToArray = null;

    /**
     * Get information about data counts
     *
     * @return array{itemCount:int,totalItemCount:int,currentPage:int,pageCount:int,pageSize:int}
     */
    public function getArrayCountData()
    {
        return array(
            'itemCount' => $this->getItemCount(),
            'totalItemCount' => (int) $this->getTotalItemCount(),
            'currentPage' => $this->pagination ? $this->pagination->currentPage : 1,
            'pageCount' => $this->pagination ? $this->pagination->pageCount : 1,
            'pageSize' => $this->pagination ? $this->pagination->pageSize : $this->getItemCount(),
        );
    }

    /**
     *
     * @param boolean $refresh When true, refresh data from DB.
     *
     * @return array
     */
    public function getArrayData($refresh=false)
    {
        Yii::import('ext.yii-json-dataprovider.ModelToArrayConverter');

        $arrayData = [];

        if ($data = $this->getData($refresh)) {
            $converter = new ModelToArrayConverter($data, $this->attributes, $this->relations);
            $converter->attributeAliases = $this->attributeAliases;
            $converter->onAfterModelToArray = $this->onAfterModelToArray;
            $arrayData = $converter->convert();
        }

        if ($this->includeDataProviderInformation) {
            return array_merge(
                $this->getArrayCountData(), array(
                    'data' => $arrayData
                )
            );
        } else {
            return $arrayData;
        }
    }

    /**
     * Get data as JSON
     *
     * @param boolean $refresh When true, refresh data from DB.
     *
     * @return string
     */
    public function getJsonData($refresh=false)
    {
        return CJSON::encode($this->getArrayData($refresh));
    }
}
