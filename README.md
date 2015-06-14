WP_Temporary
============

`WP_Temporary` is a helper class with a group of static methods that are used the same way as counterpart transient functions for storing data in the database until they expire. Basically, it's the same as when transient are stored in the database so it isn't deleted until it expires.

Additionally, it provides two methods for updating values of existing temporaries without changing expiration time, and method for cleaning up database of expired temporaries.

Using
--------

**Setting**

`WP_Temporary::set( $temporary, $value, $expiration );`

instead of

`set_transient( $temporary, $value, $expiration );`

**Getting**

`WP_Temporary::get( $temporary );`

instead of

`get_transient( $temporary );`


**Deleting**

`WP_Temporary::delete( $temporary );`

instead of

`delete_transient( $temporary );`

**Updating**

`WP_Temporary::update( $temporary, $value, $expiration );`

This is unique for `WP_Temporary`. It only changes value of existing temporary, not timeout, or creates new one with provided expiration.

**Setting (site)**

`WP_Temporary::set_site( $temporary, $value, $expiration );`

instead of

`set_site_transient( $temporary, $value, $expiration );`

**Getting (site)**

`WP_Temporary::get_site( $temporary );`

instead of

`get_site_transient( $temporary );`


**Deleting (site)**

`WP_Temporary::delete_site( $temporary );`

instead of

`delete_site_transient( $temporary );`

**Updating (site)**

`WP_Temporary::update_site( $temporary, $value, $expiration );`

This is unique for `WP_Temporary`. It only changes value of existing temporary, not timeout, or creates new one with provided expiration.

**Garbage Collector**

`WP_Temporary::clean();`

This is unique for `WP_Temporary`. It cleans up database for all expired temporaries older than one minute.

For example, called once daily using WP Cron:

`add_action( 'wp_scheduled_delete', array( 'WP_Temporary', 'clean' ) );`
