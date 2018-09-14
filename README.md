# WP_Temporary

[![Build Status](https://www.travis-ci.org/dimadin/wp-temporary.svg?branch=master)](https://www.travis-ci.org/dimadin/wp-temporary)
[![Latest Stable Version](https://poser.pugx.org/dimadin/wp-temporary/version)](https://packagist.org/packages/dimadin/wp-temporary)

`WP_Temporary` is a helper class with a group of static methods that are used the same way as counterpart transient functions for storing data in the database until they expire. Basically, it's the same as when transient is stored in the database (with object cache disabled) so it isn't deleted until it expires.

Transients use object cache if it's available and only fallback to the database if it's not. Because of that, transients might disappear at any time before their expiration. `WP_Temporary` solves this because data stored with it does not disappear until it expires.

Additionally, `WP_Temporary` has a separate method that changes value of already set temporary data, but without changing expiration time of that data. This is different from transients that only allow setting of data that always changes expiration time so it start counting from the moment of setting.

`WP_Temporary` has built-in garbage collector and WP-CLI command.

## Using

`WP_Temporary` is available as Composer package that you can use in your project.

```bash
composer require dimadin/wp-temporary
```

Alternately, you can download file `class-wp-temporary.php` and include it in your project.

## API

### WP_Temporary::set()

Equivalent of [`set_transient()`](https://developer.wordpress.org/reference/functions/set_transient/).

### WP_Temporary::get()

Equivalent of [`get_transient()`](https://developer.wordpress.org/reference/functions/get_transient/).

### WP_Temporary::delete()

Equivalent of [`delete_transient()`](https://developer.wordpress.org/reference/functions/delete_transient/).

### WP_Temporary::update()

This method is identical to `WP_Temporary::set()` with one difference: if temporary already exists, it will not change expiration time, just change its value (without using third parameter `$expiration`); if it doesn't exist, it will fallback to `WP_Temporary::set()`.

### WP_Temporary::set_site()

Equivalent of [`set_site_transient()`](https://developer.wordpress.org/reference/functions/set_site_transient/).

### WP_Temporary::get_site()

Equivalent of [`get_site_transient()`](https://developer.wordpress.org/reference/functions/get_site_transient/).

### WP_Temporary::delete_site()

Equivalent of [`delete_site_transient()`](https://developer.wordpress.org/reference/functions/delete_site_transient/).

### WP_Temporary::update_site()

This method is identical to `WP_Temporary::set_site()` with one difference: if temporary already exists, it will not change expiration time, just change its value (without using third parameter `$expiration`); if it doesn't exist, it will fallback to `WP_Temporary::set_site()`.

### WP_Temporary::clean()

This is garbage collector for `WP_Temporary`. It cleans up database from all expired temporaries (whose expiration was more than one minute ago).

Note that it isn't run by default, you need to either call it in your code with

`WP_Temporary::clean();`

or hook it to some action. For example, this calls it once daily using WP Cron:

`add_action( 'wp_scheduled_delete', array( 'WP_Temporary', 'clean' ) );`

### WP_Temporary::init_wp_cli()

This method that initializes WP-CLI command `wp temporary`. It isn't run by default, you need to either call it in your code with

`WP_Temporary::init_wp_cli();`

or hook it to some action. For example, this calls only when WP-CLI is initialized:

`add_action( 'cli_init', array( 'WP_Temporary', 'init_wp_cli' ) );`

Note that if you are not using Composer for including `WP_Temporary()`, you must manually include file `/cli/Temporary_Command.php`.

## WP-CLI

`WP_Temporary` implements the following commands:

### wp temporary

Adds, gets, updates, and deletes temporary data.

The temporary data uses the WordPress database to persist values between requests. On a single site installation, values are stored in the `wp_options` table. On a multisite installation, values are stored in the `wp_options` or the `wp_sitemeta` table, depending on use of the `--network` flag.

**EXAMPLES**

    # Set temporary.
    $ wp temporary set sample_key "test data" 3600
    Success: Temporary added.

    # Update temporary.
    $ wp temporary update sample_key "test data" 3600
    Success: Temporary updated.

    # Get temporary.
    $ wp temporary get sample_key
    test data

    # Get all temporaries.
    $ wp temporary get --all

    # Delete temporary.
    $ wp temporary delete sample_key
    Success: Temporary deleted.

    # Delete all temporaries.
    $ wp temporary delete --all
    Success: 14 temporaries deleted from the database.

#### wp temporary get

Get a temporary value.

    wp temporary get [<key>] [--format=<format>] [--network] [--all]

**OPTIONS**

    [<key>]
        Key for the temporary.

    [--format=<format>]
        Render output in a particular format.
        ---
        options:
          - json
          - yaml
        ---

    [--network]
         Get the value of a network|site temporary.

    [--all]
        Get all temporaries.

**EXAMPLES**

    # Get temporary.
    $ wp temporary get sample_key
    test data

    # Get temporary.
    $ wp temporary get random_key
    Warning: Temporary with key "random_key" is not set.

    # Get all temporaries.
    $ wp temporary get --all

#### wp temporary set

Set a temporary value.

    wp temporary set <key> <value> [<expiration>] [--network]

**OPTIONS**

    <key>
        Key for the temporary.

    <value>
        Value to be set for the temporary.

    [<expiration>]
        Time until expiration, in seconds.

    [--network]
         Set the value of a network|site temporary.

**EXAMPLES**

    $ wp temporary set sample_key "test data" 3600
    Success: Temporary added.

#### wp temporary update

Update a temporary value.

Change value of existing temporary without affecting expiration, or set new temporary with provided expiration if temporary doesn't exist.

    wp temporary update <key> <value> [<expiration>] [--network]

**OPTIONS**

    <key>
        Key for the temporary.

    <value>
        Value to be set for the temporary.

    [<expiration>]
        Time until expiration, in seconds.

    [--network]
         Set the value of a network|site temporary.

**EXAMPLES**

    $ wp temporary update sample_key "test data" 3600
    Success: Temporary updated.

#### wp temporary delete

Get a temporary value.

    wp temporary delete [<key>] [--network] [--all]

**OPTIONS**

    [<key>]
        Key for the temporary.

    [--network]
        Delete the value of a network|site temporary.

    [--all]
        Delete all temporaries.

**EXAMPLES**

    # Delete temporary.
    $ wp temporary delete sample_key
    Success: Temporary deleted.

    # Delete all temporaries.
    $ wp temporary delete --all
    Success: 14 temporaries deleted from the database.

#### wp temporary clean

Delete all expired temporaries.

    wp temporary clean

**EXAMPLES**

    $ wp temporary clean
    Success: Expired temporaries deleted from the database.
