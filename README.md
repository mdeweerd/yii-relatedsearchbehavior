yii-relatedsearchbehavior
=========================

Yii Extension - RelatedSearchBehavior

1.  [Requirements](#requirements)
2.  [Forum](#forum)
3.  [Usage](#usage)
4.  [History](#history)
5.  [Resources](#resources)

Creating CGridViews with related tables or getting fields from related tables is simplified with this behavior. It magically adds the needed 'with' clauses and provides aliases to fields of records in the relations.

[Live demo](https://relatedsearchbehavior.ynamics.com/)

Uses the [KeenActiveDataProvider](https://www.yiiframework.com/wiki/385/displaying-sorting-and-filtering-hasmany-manymany-relations-in-cgridview/#hh10) extension to limit the number of requests to the database.

#### Requirements [¶](#requirements)

Developped with Yii 1.1.12 - surely useable with earlier and later versions.

#### Forum [¶](#forum)

A [Forum thread](https://www.yiiframework.com/forum/index.php/topic/40185-related-search-behavior/ "Forum thread") is available for this extension.

#### Usage [¶](#usage)

To use this extension you must do 5 steps:

1.  "Install" the RelatedSearchBehavior;
2.  Update the Model's search() method;
3.  Add/Update the 'behaviors()' method;
4.  Add the search fields to the Model rules as safe fields;
5.  Use the search field as any other field in the CGridView.

Here are the details for these steps:

##### 1\. "Install" the RelatedSearchBehavior; [¶](#1-install-the-relatedsearchbehavior)

Installation is as easy as adding the location to the 'import' configuration of the Yii configuration file (main.php):

```php
return array(
         [...]
	'import'=>array(
            [...]
            'ext.KeenActiveDataProvider', 
	    'ext.RelatedSearchBehavior',  
	), 
```

In the above the RelatedSearchBehavior file was placed in the extensions directory.

##### 2\. Update the Model's search() method; [¶](#2-update-the-models-search-method)

Update the overload of ActiveRecord::search(). The following example shows how you can set the default search order. You can set any parameter accepted by CActiveDataProvider (such as pagination) in the second array. So, replace code like this:

```php
return new CActiveDataProvider($this, array(
  'criteria'=>$criteria,
  'sort'=>array(
    'defaultOrder'=>'title ASC',
  )
)); 
```

by code like this (I prefer using intermediate variables like '$sort' to improve readability and increase flexibility):

```php
$sort=array(
    'defaultOrder'=>'title ASC',
);
return $this->relatedSearch(
    $criteria,
    array('sort'=>$sort)
); 
```

In the above example, 'title' can be one of the related fields that you defined.

##### 3\. Add/Update the 'behaviors()' method [¶](#3-addupdate-the-behaviors-method)

Attach the behavior to the CActiveRecord and specifies the related fields.

```php
function behaviors() {
    return array(
        'relatedsearch'=>array(
             'class'=>'RelatedSearchBehavior',
             'relations'=>array(
                  'serial'=>'device.device_identifier',
                  'location'=>'device.location.description',
                   
                 'fieldwithdifferentsearchvalue'=>array(
                     'field'=>'device.displayname',
                     'searchvalue'=>'deviceid'
                  ),
                 
                 
                 'mylocalreference'=>'field.very.far.away.in.the.relation.tree',
             ),
         ),
    );
} 
```

##### 4\. Add the search fields to the Model rules as safe fields; [¶](#4-add-the-search-fields-to-the-model-rules-as-safe-fields)

```php
public function rules() {
	    return array(
	        [...]
			array('serial,location,deviceid','safe','on'=>'search'),
		);
	} 
```

##### 5\. Use the search field as any other field in the CGridView. [¶](#5-use-the-search-field-as-any-other-field-in-the-cgridview)

For the CGridView column specification, you can then just put 'serial' for the column (no need to do 'name'=>..., 'filter'=>..., 'value'=>... .

Example:

```
$this->widget('zii.widgets.grid.CGridView', array(
  [...]
  'columns'=>array(
      [...]
      'serial',
      'location',
  )
)); 
```

##### 6\. To use "autoScope" [¶](#6-to-use-autoscope)

Autoscope allows you to search a field using a scope without declaring the scope yourself.

For instance, you can use this:

```php
MyModel::model()->location('Belgium')->findAll(); 
```

Before, you have to:

1.  Add RelatedSearchBehavior to the behaviors of your CActiveRecord (already done in the preceding steps,
2.  Add the following generic code to your CActiveRecord Model.

```php
 public function __call($name,$parameters) {
	    try {
	        return parent::__call($name,$parameters);
	    } catch (CException $e) {
	        if(preg_match(
	                '/'.Yii::t(
	                        'yii',
	                        quotemeta(
	                                Yii::t(
	                                        'yii',
	                                        '{class} and its behaviors do not have a method or closure named "{name}".'
	                                        )
	                                ),
	                                array('{class}'=>'.*','{name}'=>'.*')
	                        )
	                .'/',$e->getMessage())) {
	            return $this->autoScope($name, $parameters);
	        } else {
	            throw $e;
	        }
	    }
	} 
```

You are allowed to provide all the regular compare parameters:

```
MyModel::model()->location($searchvalue,$partialMatch,$operator,$escape)->findAll(); 
```

defaults are the same as compare: autoscope(,false,"AND",false).

This is usefull in complex nested conditions, not so much for simple searches like the above.

##### 7\. Using relations in CSort's attributes for sorting. [¶](#7-using-relations-in-csorts-attributes-for-sorting)

'CSort' allows you to specify 'virtual attributes' for sorting as mentioned in [the Yii documentation](https://www.yiiframework.com/doc/api/1.1/CSort#attributes-detail). Without RelatedSearchBehavior, you must make sure that you include the relations used in the search condition. With RelatedSearchBehavior, you do not need to take care about that - the extension takes care about it for you (since 1.16).

```php
$sort=array(
    'defaultOrder'=>'title ASC',
    'attributes'=>
	    array(
		    'price'=>array(
			    'asc'=>'item.price',
			    'desc'=>'item.price DESC',
			    'label'=>'Item Price'
		    ),
	    ),
    );
return $this->relatedSearch(
    $criteria,
    array('sort'=>$sort)
); 
```

The preferred approach is that you'ld use attributes defined for RelatedSearchBehavior, but this might be usefull in combined sort conditions:

```php
$sort=array(
    'defaultOrder'=>'title ASC',
    'attributes'=>
	    array(
		    'groupprice'=>array(
			    'asc'=>'item.group, item.price',
			    'desc'=>'item.group DESC, item.price DESC',
			    'label'=>'Item Price'
		    ),
	    ),
    );
return $this->relatedSearch(
    $criteria,
    array('sort'=>$sort)
); 
```

##### 8\. exactSearchAttributes [¶](#8-exactSearchAttributes)

When `CActiveRecord::exactSearchAttributes` is defined in a `CActiveRecord`, the attributes listed there are matched exactly by default rather than partial.
This is particularly useful in combination with tables, and works for columns as well (not just relations).
For instance, IDs should not be partially matched, the id "1" would also match 10..19, 21, 31, etc.
Requiring an exact match can also improve the performance of the search as this is more appropriate when using an index.

Example:
```php
    public function exactSearchAttributes() {
        return [
            'is_active',
            'context_id',
            'entity_id',
            'type_id',
        ];
    }
```
In the above example:

- `is_active` is a boolean, so exact matches are perfect there.
- `context_id` is an idea that matches a description in the UI
- `entity_id` matches some entity, also shown as a label specific to the entity
- `type_id` defines a type for the record, and also matches some label.

##### 9\. Tips & notes [¶](#9-tips-amp-notes)

- If you like RelatedSearchBehavior, you can create or update your Gii template to generate it automatically.
- If you use 'ERememberFiltersBehavior', you must set the rememberScenario before getting the dataProvider - otherwise the relations will not be resolved in the sort clause. So you write:

```
$model->rememberScenario="admin"; 
$dataProvider=$model->search(); 
```

This is the search method in projects where the author uses this extension.
All Models extends from YActiveRecord which extends from CActiveRecord and implements several additions to default CActiveRecord features.  It implements the following search method (the RelatedSearchBehavior has to be added to the modell class itself):
```php
    /**
     * Search method valid for all Active Records (with RelatedSearchBehavior)
     *
     * @return KeenActiveDataProvider
     */
    public function search() {
        $criteria=new CDbCriteria();
        $t=$this->getTableAlias(false, false);
        $ds=$this->getDbConnection()->getSchema();

        $columns=$this->getMetaData()->columns;
        //$relations=$this->relations;
        foreach($this->getSafeAttributeNames() as $attribute) {
            $value=$this->{$attribute};
            if($value==='=') {
            	$value=array(null,'');
            }
            if(is_array($value)&&!empty($value)||(!is_array($value)&&"$value" !== "")) {
                if(isset($columns[$attribute])) {
                    if(in_array($attribute,$this->exactSearchAttributes())) {
                        Yii::trace("Exact match required for $attribute");
                    }
                    $criteria->compare($ds->quoteColumnName("$t.$attribute"),
                            $value,
                            !$columns[$attribute]->isForeignKey
                            &&!in_array($attribute,$this->exactSearchAttributes())
                    );
                }
            /**
             * Sample code to handle exceptions (fields that are not in
             * relations). else if(!isset($relations[$attribute])) { // Not a
             * related search item -> do something else
             * $this->compareProperty($attribute, $value,true); }
             */
            }
        }
        // $criteria->together=true;

        return $this->relatedSearch($criteria
                //,array('sort'=>array('defaultOrder'=>$this->getTableAlias(true,false).'.entity_id DESC'))
        );
    }

    /**
     * Provides the list of attributes for which an exact search is
     * needed and not a partial search.
     * (typical: keys, enums, etc.
     * @return string[]
     */
    public function exactSearchAttributes() {
        return [];
    }
```

#### History [¶](#history)

See `RelatedSearchbehavior.php` for a short description of the versions

#### Resources [¶](#resources)

- [KeenActiveDataProvider](https://www.yiiframework.com/wiki/385/displaying-sorting-and-filtering-hasmany-manymany-relations-in-cgridview/#hh10)
- [Live Demo](https://relatedsearchbehavior.ynamics.com/)
- [GitHub](https://github.com/mdeweerd/yii-relatedsearchbehavior)

