<?php
/**
 * Temporary_Command class
 *
 * @package WP_Temporary
 * @subpackage WP_CLI
 * @since 1.0.0
 */

if ( ! class_exists( 'Temporary_Command', false ) && class_exists( 'WP_CLI_Command' ) ) :
	/**
	 * Adds, gets, updates, and deletes temporary data.
	 *
	 * The temporary data uses the WordPress database to persist values
	 * between requests. On a single site installation, values are stored in the
	 * `wp_options` table. On a multisite installation, values are stored in the
	 * `wp_options` or the `wp_sitemeta` table, depending on use of the `--network`
	 * flag.
	 *
	 * ## EXAMPLES
	 *
	 *     # Set temporary.
	 *     $ wp temporary set sample_key "test data" 3600
	 *     Success: Temporary added.
	 *
	 *     # Update temporary.
	 *     $ wp temporary update sample_key "test data" 3600
	 *     Success: Temporary updated.
	 *
	 *     # Get temporary.
	 *     $ wp temporary get sample_key
	 *     test data
	 *
	 *     # Get all temporaries.
	 *     $ wp temporary get --all
	 *
	 *     # Delete temporary.
	 *     $ wp temporary delete sample_key
	 *     Success: Temporary deleted.
	 *
	 *     # Delete all temporaries.
	 *     $ wp temporary delete --all
	 *     Success: 14 temporaries deleted from the database.
	 *
	 * @since 1.0.0
	 */
	class Temporary_Command extends WP_CLI_Command {

		/**
		 * Get a temporary value.
		 *
		 * ## OPTIONS
		 *
		 * [<key>]
		 * : Key for the temporary.
		 *
		 * [--format=<format>]
		 * : Render output in a particular format.
		 * ---
		 * options:
		 *   - json
		 *   - yaml
		 * ---
		 *
		 * [--network]
		 * : Get the value of a network|site temporary.
		 *
		 * [--all]
		 * : Get all temporaries.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp temporary get sample_key
		 *     test data
		 *
		 *     $ wp temporary get random_key
		 *     Warning: Temporary with key "random_key" is not set.
		 *
		 *     # Get all temporaries.
		 *     $ wp temporary get --all
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Array of positional arguments.
		 * @param array $assoc_args Associative array of associative arguments.
		 */
		public function get( $args, $assoc_args ) {
			list( $key ) = $args;

			// Whether to get value for single site or multisite.
			$for_network = WP_CLI\Utils\get_flag_value( $assoc_args, 'network' );

			// Whether to get values for all temporaries.
			$all = WP_CLI\Utils\get_flag_value( $assoc_args, 'all' );

			if ( true === $all ) {
				$this->get_all( $for_network );
				return;
			}

			$func = $for_network ? 'WP_Temporary::get_site' : 'WP_Temporary::get';

			$value = $func( $key );

			if ( false === $value ) {
				WP_CLI::warning( sprintf( 'Temporary with key "%s" is not set.', $key ) );
				exit;
			}

			WP_CLI::print_value( $value, $assoc_args );
		}

		/**
		 * Set a temporary value.
		 *
		 * `<expiration>` is the time until expiration, in seconds.
		 *
		 * ## OPTIONS
		 *
		 * <key>
		 * : Key for the temporary.
		 *
		 * <value>
		 * : Value to be set for the temporary.
		 *
		 * [<expiration>]
		 * : Time until expiration, in seconds.
		 *
		 * [--network]
		 * : Set the value of a network|site temporary.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp temporary set sample_key "test data" 3600
		 *     Success: Temporary added.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Array of positional arguments.
		 * @param array $assoc_args Associative array of associative arguments.
		 */
		public function set( $args, $assoc_args ) {
			list( $key, $value ) = $args;

			$expiration = WP_CLI\Utils\get_flag_value( $args, 2, 0 );

			$func = WP_CLI\Utils\get_flag_value( $assoc_args, 'network' ) ? 'WP_Temporary::set_site' : 'WP_Temporary::set';
			if ( $func( $key, $value, $expiration ) ) {
				WP_CLI::success( 'Temporary added.' );
			} else {
				WP_CLI::error( 'Temporary could not be set.' );
			}
		}

		/**
		 * Update a temporary value.
		 *
		 * `<expiration>` is the time until expiration, in seconds.
		 *
		 * Change value of existing temporary without affecting expiration,
		 * or set new temporary with provided expiration if temporary doesn't exist.
		 *
		 * ## OPTIONS
		 *
		 * <key>
		 * : Key for the temporary.
		 *
		 * <value>
		 * : Value to be set for the temporary.
		 *
		 * [<expiration>]
		 * : Time until expiration, in seconds.
		 *
		 * [--network]
		 * : Set the value of a network|site temporary.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp temporary update sample_key "test data" 3600
		 *     Success: Temporary updated.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Array of positional arguments.
		 * @param array $assoc_args Associative array of associative arguments.
		 */
		public function update( $args, $assoc_args ) {
			list( $key, $value ) = $args;

			$expiration = WP_CLI\Utils\get_flag_value( $args, 2, 0 );

			$func = WP_CLI\Utils\get_flag_value( $assoc_args, 'network' ) ? 'WP_Temporary::update_site' : 'WP_Temporary::update';

			if ( $func( $key, $value, $expiration ) ) {
				WP_CLI::success( 'Temporary updated.' );
			} else {
				WP_CLI::error( 'Temporary could not be updated.' );
			}
		}

		/**
		 * Delete a temporary value.
		 *
		 * ## OPTIONS
		 *
		 * [<key>]
		 * : Key for the temporary.
		 *
		 * [--network]
		 * : Delete the value of a network|site temporary.
		 *
		 * [--all]
		 * : Delete all temporaries.
		 *
		 * ## EXAMPLES
		 *
		 *     # Delete temporary.
		 *     $ wp temporary delete sample_key
		 *     Success: Temporary deleted.
		 *
		 *     # Delete all temporaries.
		 *     $ wp temporary delete --all
		 *     Success: 14 temporaries deleted from the database.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Array of positional arguments.
		 * @param array $assoc_args Associative array of associative arguments.
		 */
		public function delete( $args, $assoc_args ) {
			$key = ( ! empty( $args ) ) ? $args[0] : null;

			// Whether to delete all temporaries.
			$all = WP_CLI\Utils\get_flag_value( $assoc_args, 'all' );

			if ( true === $all ) {
				$this->delete_all();
				return;
			}

			if ( ! $key ) {
				WP_CLI::error( 'Please specify temporary key, or use --all' );
			}

			$func = WP_CLI\Utils\get_flag_value( $assoc_args, 'network' ) ? 'WP_Temporary::delete_site' : 'WP_Temporary::delete';

			if ( $func( $key ) ) {
				WP_CLI::success( 'Temporary deleted.' );
			} else {
				$func = WP_CLI\Utils\get_flag_value( $assoc_args, 'network' ) ? 'WP_Temporary::get_site' : 'WP_Temporary::get';
				if ( $func( $key ) ) {
					WP_CLI::error( 'Temporary was not deleted even though the temporary appears to exist.' );
				} else {
					WP_CLI::warning( 'Temporary was not deleted, it does not appear to exist.' );
				}
			}
		}

		/**
		 * Delete all expired temporaries.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp temporary clean
		 *     Success: Expired temporaries deleted from the database.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Array of positional arguments.
		 * @param array $assoc_args Associative array of associative arguments.
		 */
		public function clean( $args, $assoc_args ) {
			WP_Temporary::clean();
			WP_CLI::success( 'Expired temporaries deleted from the database.' );

			if ( is_multisite() ) {
				WP_CLI::warning( 'Temporaries of other sites in the network cannot be deleted.' );
			}
		}

		/**
		 * Display table with of all temporaries in the database.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $for_network Whether to look for single site or network temporaries.
		 */
		protected function get_all( $for_network = false ) {
			$items = array();

			// Whether to get values for single site or multisite.
			if ( $for_network ) {
				$func           = 'WP_Temporary::get_site';
				$option_func    = 'get_site_option';
				$timeout_prefix = '_site_temporary_timeout_';
			} else {
				$func           = 'WP_Temporary::get';
				$option_func    = 'get_option';
				$timeout_prefix = '_temporary_timeout_';
			}

			foreach ( $this->get_all_keys( $for_network ) as $key ) {
				$value = $func( $key );

				if ( false === $value ) {
					continue;
				}

				$timeout = $option_func( $timeout_prefix . $key );

				if ( false === $timeout ) {
					$timeout_value = 'No Timeout';
				} else {
					$timeout_value = $this->get_human_timeout( $timeout );
				}

				$items[] = array(
					'Temporary' => $key,
					'Value'     => $value,
					'Expires'   => $timeout_value,
				);
			}

			if ( ! $items ) {
				WP_CLI::warning( 'There are no set temporaries.' );
				return;
			}

			WP_CLI\Utils\format_items( 'table', $items, array( 'Temporary', 'Value', 'Expires' ) );
		}

		/**
		 * Deletes all temporaries.
		 *
		 * @since 1.0.0
		 */
		protected function delete_all() {
			$count = 0;

			foreach ( $this->get_all_keys( false ) as $key ) {
				WP_Temporary::delete( $key );
				$count++;
			}

			foreach ( $this->get_all_keys( true ) as $key ) {
				WP_Temporary::delete_site( $key );
				$count++;
			}

			if ( $count > 0 ) {
				if ( 1 === $count ) {
					$string = '%d temporary deleted from the database.';
				} else {
					$string = '%d temporaries deleted from the database.';
				}

				WP_CLI::success( sprintf( $string, $count ) );

				if ( is_multisite() ) {
					WP_CLI::warning( 'Temporaries of other sites in the network are not deleted.' );
				}
			} else {
				WP_CLI::success( 'No temporaries found.' );

				if ( is_multisite() ) {
					WP_CLI::warning( 'Temporaries of other sites in the network cannot be deleted.' );
				}
			}
		}

		/**
		 * Get names of all temporaries in the database.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $for_network Whether to look for single site or network temporaries.
		 * @return array $keys
		 */
		protected function get_all_keys( $for_network = false ) {
			global $wpdb;

			if ( $for_network ) {
				if ( is_multisite() ) {
					// Get temporaries names in multisite environment.
					$keys = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT REPLACE(meta_key, '_site_temporary_', '') AS temporary_name
							FROM {$wpdb->sitemeta}
							WHERE meta_key LIKE %s
							AND meta_key NOT LIKE %s",
							$wpdb->esc_like( '_site_temporary_' ) . '%',
							$wpdb->esc_like( '_site_temporary_timeout_' ) . '%'
						)
					);
				} else {
					// Get temporaries names in single site environment.
					$keys = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT REPLACE(option_name, '_site_temporary_', '') AS temporary_name
							FROM {$wpdb->options}
							WHERE option_name LIKE %s
							AND option_name NOT LIKE %s",
							$wpdb->esc_like( '_site_temporary_' ) . '%',
							$wpdb->esc_like( '_site_temporary_timeout_' ) . '%'
						)
					);
				}
			} else {
				// Get single site temporaries names.
				$keys = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT REPLACE(option_name, '_temporary_', '') AS temporary_name
						FROM {$wpdb->options}
						WHERE option_name LIKE %s
						AND option_name NOT LIKE %s",
						$wpdb->esc_like( '_temporary_' ) . '%',
						$wpdb->esc_like( '_temporary_timeout_' ) . '%'
					)
				);
			}

			return $keys;
		}

		/**
		 * Format number of seconds since the Unix Epoch to human readable form.
		 *
		 * @since 1.0.0
		 *
		 * @param int $timeout Number of seconds since the Unix Epoch.
		 * @return string
		 */
		protected function get_human_timeout( $timeout ) {
			static $time = null;

			if ( empty( $time ) ) {
				$time = time();
			}

			// If timeout was in the past.
			if ( $time > $timeout ) {
				return human_time_diff( $timeout, $time ) . ' ago';
			} else {
				return 'in ' . human_time_diff( $time, $timeout );
			}
		}
	}
endif;
