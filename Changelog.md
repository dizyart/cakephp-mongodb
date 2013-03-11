## MongoDB CakePHP Plugin changelog ##

There has been a lot of changes for v2.0a, since it is a complete rework of the plugin.

Although a big effort was made to retain backward-compatibility, some things have changed and your code _might_ break. This is not a proper migration guideline, but more of a hint.

### Version 2.0a ###

* Added association support (hasMany, belongsTo, hasOne; HABTM only via MongodbAppModel)
* Added Model inheritance (only via MongodbAppModel)
* Default primary key is 'id', the translation to '_id' has been moved to behind the scenes.
* Moving the SqlCompatible behavior to the source and making it default behavior
