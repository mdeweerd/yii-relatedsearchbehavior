Yii Extension - RelatedSearchBehavior


Creating CGridViews with related tables or getting fields from related tables is simplified with this behavior.  It magically adds the needed 'with' clauses and provides aliases to fields of records in the relations.

[Live demo](http://relatedsearchbehavior.ynamics.com/)

Uses the [KeenActiveDataProvider](http://www.yiiframework.com/wiki/385/displaying-sorting-and-filtering-hasmany-manymany-relations-in-cgridview/#hh10) extension to limit the number of requests to the database.

##Requirements

Developped with Yii 1.1.12 - surely useable with earlier and later versions.

##Forum
A [Forum thread](http://www.yiiframework.com/forum/index.php/topic/40185-related-search-behavior/ "Forum thread") is available for this extension.

##Usage

To use this extension you must do 5 steps:

1. "Install" the RelatedSearchBehavior;
2. Update the Model's search() method;
3. Add/Update the 'behaviors()' method;
4. Add the search fields to the Model rules as safe fields;
5. Use the search field as any other field in the CGridView.

Here are the details for these steps:

### 1. "Install" the RelatedSearchBehavior;

Installation is as easy as adding the location to the 'import' configuration of the Yii configuration file (main.php):
~~~
[php]
    return array(
         [...]
	'import'=>array(
            [...]
            'ext.KeenActiveDataProvider',
	    'ext.RelatedSearchBehavior',
	),
~~~

In the above the RelatedSearchBehavior file was placed in the extensions directory.

### 2. Update the Model's search() method;

Update the overload of ActiveRecord::search().  The following example shows how you can set the default search order.  You can set any parameter accepted by CActiveDataProvider (such as pagination) in the second array.
So, replace code like this:

~~~
[php]
return new CActiveDataProvider($this, array(
  'criteria'=>$criteria,
  'sort'=>array(
    'defaultOrder'=>'title ASC',
  )
));
~~~

by code like this (I prefer using intermediate variables like '$sort' to improve readability and increase flexibility):
~~~
[php]
$sort=array(
    'defaultOrder'=>'title ASC',
);
return $this->relatedSearch(
    $criteria,
    array('sort'=>$sort)
);
~~~

In the above example, 'title' can be one of the relations that you defined.

### 3. Add/Update the 'behaviors()' method

Attach the behavior to the CActiveRecord and specifies the related fields.

~~~
[php]
function behaviors() {
    return array(
        'relatedsearch'=>array(
             'class'=>'RelatedSearchBehavior',
             'relations'=>array(
                  'serial'=>'device.device_identifier',
                  'location'=>'device.location.description',
                   // Field where search value is different($this->deviceid)
                 'fieldwithdifferentsearchvalue'=>array(
                     'field'=>'device.displayname',
                     'searchvalue'=>'deviceid'
                  ),
                 // Next line describes a field we do not search,
                 // but we define it here for convienience
                 'mylocalreference'=>'field.very.far.away.in.the.relation.tree',
             ),
         ),
    );
}
~~~

### 4. Add the search fields to the Model rules as safe fields;

~~~
[php]
 	public function rules()
	{
	    return array(
	        [...]
			array('serial,location,deviceid','safe','on'=>'search'),
		);
	}
~~~

### 5. Use the search field as any other field in the CGridView.

For the CGridView column specification, you can then just put 'serial' for the column
  (no need to do 'name'=>..., 'filter'=>..., 'value'=>... .

Example:
~~~
[php]
$this->widget('zii.widgets.grid.CGridView', array(
  [...]
  'columns'=>array(
      [...]
      'serial',
      'location',
  )
));
~~~

### 6. To use "autoScope"

Autoscope allows you to search a field using a scope without declaring the scope yourself.

For instance, if your Model has a field 'username', you can use this:
~~~
[php]
   MyModel::model()->username('me')->findAll();
~~~

Before, you have to:
1. Add RelatedSearchBehavior to the behaviors of your CActiveRecord (already done in the preceding steps,
2. Add the following code to your CActiveRecord Model.

~~~
[php]
	/**
	 * Add automatic scopes for attributes (uses RelatedSearchBehavior).
	 */
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
~~~

You are allowed to provide all the regular compare parameters:
~~~
[php]
   MyModel::model()->username($searchvalue,$partialMatch,$operator,$escape)->findAll();
~~~
defaults are the same as compare:  autoscope(<userdefined>,false,"AND",false).

This is usefull in complex nested conditions, not so much for simple searches like the above.

### 6. Using relations in CSort's attributes for sorting.

'CSort' allows you to specify 'virtual attributes' for sorting as mentioned in [the Yii documentation](http://www.yiiframework.com/doc/api/1.1/CSort#attributes-detail).
Without RelatedSearchBehavior, you must make sure that you include the relations used in the search condition.
With RelatedSearchBehavior, you do not need to take care about that - the extension takes care about it for you (since 1.16).

~~~
[php]
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
~~~

The preferred approach is that you'ld use attributes defined for RelatedSearchBehavior, but this might be usefull in combined sort conditions:


~~~
[php]
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
~~~

### 7. Tips & notes
- If you like RelatedSearchBehavior, you can create or update your Gii template to generate it automatically.
- If you use 'ERememberFiltersBehavior', you must set the rememberScenario before getting the dataProvider - otherwise the relations will not be resolved in the sort clause.
So you write:
~~~
[php]
$model->rememberScenario="admin"; // Must be before ->search().
$dataProvider=$model->search(); // Uses RelatedSearchBehavior
~~~


##History
     * 1.03  Quoting relations in database.
     * 1.04  Added autoScope.
     *       Added option 'partialMatch' for relation.
     * 1.05  Enable multiple attributes in default sort.
     * 1.06  Fix to autoScope - return owner (chaining) + correct example in comment.
     * 1.07  Improved compatibility with ERememberFiltersBehavior ("standard" sort key).
     * 1.08  Fix in KeenActiveDataProvider for postgresql + updates to demo for postgresql.
     * 1.09  Allow array as search value.
     * 1.10  Use alias defined in model's relation (Svobik7)
     * 1.11  Corrected test of owner class type (lower case 'c') and improved error message 
     * 1.12  Autoscope for relations and 'addRelatedCondition' method as a complement to 'addCondition'.
     * 1.13  Handle 'getter' in autoscope call.
     * 1.14  Look recursively for relations for autoscope.
     * 1.15  Added 'getDataProvider'.
     * 1.16  Added relations used in sort "attributes" provided as a parameter.
   
##Resources

[KeenActiveDataProvider](http://www.yiiframework.com/wiki/385/displaying-sorting-and-filtering-hasmany-manymany-relations-in-cgridview/#hh10)