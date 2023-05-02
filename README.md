# Document

## sync bulk

sync collection of data with <b> Validation </b> service

`php artisan informer:sync`

use `-h` for detail arguments and optional

## usage

use in Model Eloquent as a Trait

`use Informable;`

you can set `entity` property for define in validation service, if not set get tables name model.

`$entity = 'customer';`

# Trait Method Description

| method | Description                          |
|----|--------------------------------------|
| `$model->sync([])`| sync data with validation service    |
| `$model->syncCreated([])` | created data with validation service |
| `$model->syncDeleted()`|deleted data with validation service|
| `$model->syncRestored([])`|restore data with validation service|
| `$model->syncDeleted()`|force deleted data with validation service|




