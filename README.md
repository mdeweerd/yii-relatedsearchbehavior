yii-relatedsearchbehavior
=========================

Yii Extension - RelatedSearchBehavior


# CActiveRecord::exactSearchAttributes

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
