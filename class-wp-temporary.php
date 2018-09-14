<?php
/**
 * Simple and standardized way of storing data in the database temporarily.
 *
 * @package   WP_Temporary
 * @version   1.0.0
 * @author    Milan Dinić <milandinic.com>
 * @license   https://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 * @link      https://github.com/dimadin/wp-temporary
 */

if ( ! class_exists( 'WP_Temporary', false ) ) :
	/**
	 * Simple and standardized way of storing data in the database temporarily.
	 *
	 * Holds a methods that are used the same way as transient
	 * functions for storing data in the database until they
	 * expire. Basically, it's same as when transients are stored
	 * in the database.
	 *
	 * Additionally, it provides two methods for updating values
	 * of existing values without changing expiration time, and
	 * a method for cleaning database of expired temporaries.
	 *
	 * @since 1.0.0
	 */
	class WP_Temporary {
		/**
		 * Version of WP_Temporary.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		const VERSION = '1.0.0';

		/**
		 * Delete a temporary.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $temporary Temporary name. Expected to not be SQL-escaped.
		 * @return bool true if successful, false otherwise.
		 */
		public static function delete( $temporary ) {

			/**
			 * Fires immediately before a specific temporary is deleted.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * @since 1.0.0
			 *
			 * @param string $temporary Temporary name.
			 */
			do_action( "delete_temporary_{$temporary}", $temporary );

			$option_timeout = '_temporary_timeout_' . $temporary;
			$option         = '_temporary_' . $temporary;
			$result         = delete_option( $option );
			if ( $result ) {
				delete_option( $option_timeout );
			}

			if ( $result ) {

				/**
				 * Fires after a temporary is deleted.
				 *
				 * @since 1.0.0
				 *
				 * @param string $temporary Deleted temporary name.
				 */
				do_action( 'deleted_temporary', $temporary );
			}

			return $result;
		}

		/**
		 * Get the value of a temporary.
		 *
		 * If the temporary does not exist, does not have a value, or has expired,
		 * then the return value will be false.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $temporary Temporary name. Expected to not be SQL-escaped.
		 * @return mixed Value of temporary.
		 */
		public static function get( $temporary ) {

			/**
			 * Filter the value of an existing temporary.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * Passing a truthy value to the filter will effectively short-circuit retrieval
			 * of the temporary, returning the passed value instead.
			 *
			 * @since 1.0.0
			 *
			 * @param mixed $pre_temporary The default value to return if the temporary does not exist.
			 *                             Any value other than false will short-circuit the retrieval
			 *                             of the temporary, and return the returned value.
			 * @param string $temporary    Temporary name.
			 */
			$pre = apply_filters( "pre_temporary_{$temporary}", false, $temporary );
			if ( false !== $pre ) {
				return $pre;
			}

			$temporary_option = '_temporary_' . $temporary;
			if ( ! wp_installing() ) {
				// If option is not in alloptions, it is not autoloaded and thus has a timeout.
				$alloptions = wp_load_alloptions();
				if ( ! isset( $alloptions[ $temporary_option ] ) ) {
					$temporary_timeout = '_temporary_timeout_' . $temporary;
					$timeout           = get_option( $temporary_timeout );
					if ( false !== $timeout && $timeout < time() ) {
						delete_option( $temporary_option );
						delete_option( $temporary_timeout );
						$value = false;
					}
				}
			}

			if ( ! isset( $value ) ) {
				$value = get_option( $temporary_option );
			}

			/**
			 * Filter an existing temporary's value.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * @since 1.0.0
			 *
			 * @param mixed  $value     Value of temporary.
			 * @param string $temporary Temporary name.
			 */
			return apply_filters( "temporary_{$temporary}", $value, $temporary );
		}

		/**
		 * Set/update the value of a temporary.
		 *
		 * If updating and expiration is passed, it will start expiration from now.
		 *
		 * You do not need to serialize values. If the value needs to be serialized, then
		 * it will be serialized before it is set.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $temporary  Temporary name. Expected to not be SQL-escaped. Must be
		 *                           172 characters or fewer in length.
		 * @param mixed  $value      Temporary value. Must be serializable if non-scalar.
		 *                           Expected to not be SQL-escaped.
		 * @param int    $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
		 * @return bool False if value was not set and true if value was set.
		 */
		public static function set( $temporary, $value, $expiration = 0 ) {
			$expiration = (int) $expiration;

			/**
			 * Filter a specific temporary before its value is set.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * @since 1.0.0
			 *
			 * @param mixed  $value      New value of temporary.
			 * @param int    $expiration Time until expiration in seconds.
			 * @param string $temporary  Temporary name.
			 */
			$value = apply_filters( "pre_set_temporary_{$temporary}", $value, $expiration, $temporary );

			/**
			 * Filter the expiration for a temporary before its value is set.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $expiration Time until expiration in seconds. Use 0 for no expiration.
			 * @param mixed  $value      New value of temporary.
			 * @param string $temporary  Temporary name.
			 */
			$expiration = apply_filters( "expiration_of_temporary_{$temporary}", $expiration, $value, $temporary );

			$temporary_timeout = '_temporary_timeout_' . $temporary;
			$temporary_option  = '_temporary_' . $temporary;
			if ( false === get_option( $temporary_option ) ) {
				$autoload = 'yes';
				if ( $expiration ) {
					$autoload = 'no';
					add_option( $temporary_timeout, time() + $expiration, '', 'no' );
				}
				$result = add_option( $temporary_option, $value, '', $autoload );
			} else {
				// If expiration is requested, but the temporary has no timeout option,
				// delete, then re-create temporary rather than update.
				$update = true;
				if ( $expiration ) {
					if ( false === get_option( $temporary_timeout ) ) {
						delete_option( $temporary_option );
						add_option( $temporary_timeout, time() + $expiration, '', 'no' );
						$result = add_option( $temporary_option, $value, '', 'no' );
						$update = false;
					} else {
						update_option( $temporary_timeout, time() + $expiration );
					}
				}
				if ( $update ) {
					$result = update_option( $temporary_option, $value );
				}
			}

			if ( $result ) {

				/**
				 * Fires after the value for a specific temporary has been set.
				 *
				 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
				 *
				 * @since 1.0.0
				 *
				 * @param mixed  $value      Temporary value.
				 * @param int    $expiration Time until expiration in seconds.
				 * @param string $temporary  Temporary name.
				 */
				do_action( "set_temporary_{$temporary}", $value, $expiration, $temporary );

				/**
				 * Fires after the value for a temporary has been set.
				 *
				 * @since 1.0.0
				 *
				 * @param string $temporary  The name of the temporary.
				 * @param mixed  $value      Temporary value.
				 * @param int    $expiration Time until expiration in seconds.
				 */
				do_action( 'setted_temporary', $temporary, $value, $expiration );
			}

			return $result;
		}

		/**
		 * Update the value of a temporary with existing timeout.
		 *
		 * If temporary doesn't exist, it will set new temporary.
		 *
		 * You do not need to serialize values. If the value needs to be serialized, then
		 * it will be serialized before it is updated.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $temporary  Temporary name. Expected to not be SQL-escaped. Must be
		 *                           172 characters or fewer in length.
		 * @param mixed  $value      Temporary value. Must be serializable if non-scalar.
		 *                           Expected to not be SQL-escaped.
		 * @param int    $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
		 * @return bool False if value was not updated and true if value was updated.
		 */
		public static function update( $temporary, $value, $expiration = 0 ) {

			/**
			 * Filter a specific temporary before its value is updated.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * @since 1.0.0
			 *
			 * @param mixed  $value     New value of temporary.
			 * @param string $temporary Temporary name.
			 */
			$value = apply_filters( "pre_update_temporary_{$temporary}", $value, $temporary );

			$temporary_option = '_temporary_' . $temporary;

			// If temporary doesn't exist, create new one,
			// otherwise update it with new value.
			if ( false === self::get( $temporary ) ) {
				$result = self::set( $temporary, $value, $expiration );
			} else {
				$temporary_option = '_temporary_' . $temporary;
				$result           = update_option( $temporary_option, $value );
			}

			if ( $result ) {

				/**
				 * Fires after the value for a specific temporary has been updated.
				 *
				 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
				 *
				 * @since 1.0.0
				 *
				 * @param mixed  $value      Temporary value.
				 * @param int    $expiration Time until expiration in seconds.
				 * @param string $temporary  Temporary name.
				 */
				do_action( "update_temporary_{$temporary}", $value, $expiration, $temporary );

				/**
				 * Fires after the value for a temporary has been updated.
				 *
				 * @since 1.0.0
				 *
				 * @param string $temporary  The name of the temporary.
				 * @param mixed  $value      Temporary value.
				 * @param int    $expiration Time until expiration in seconds.
				 */
				do_action( 'updated_temporary', $temporary, $value, $expiration );
			}

			return $result;
		}

		/**
		 * Delete a site temporary.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $temporary Temporary name. Expected to not be SQL-escaped.
		 * @return bool True if successful, false otherwise.
		 */
		public static function delete_site( $temporary ) {

			/**
			 * Fires immediately before a specific site temporary is deleted.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * @since 1.0.0
			 *
			 * @param string $temporary Temporary name.
			 */
			do_action( "delete_site_temporary_{$temporary}", $temporary );

			$option_timeout = '_site_temporary_timeout_' . $temporary;
			$option         = '_site_temporary_' . $temporary;
			$result         = delete_site_option( $option );
			if ( $result ) {
				delete_site_option( $option_timeout );
			}

			if ( $result ) {

				/**
				 * Fires after a temporary is deleted.
				 *
				 * @since 1.0.0
				 *
				 * @param string $temporary Deleted temporary name.
				 */
				do_action( 'deleted_site_temporary', $temporary );
			}

			return $result;
		}

		/**
		 * Get the value of a site temporary.
		 *
		 * If the temporary does not exist, does not have a value, or has expired,
		 * then the return value will be false.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @see WP_Temporary::get()
		 *
		 * @param string $temporary Temporary name. Expected to not be SQL-escaped.
		 * @return mixed Value of temporary.
		 */
		public static function get_site( $temporary ) {

			/**
			 * Filter the value of an existing site temporary.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * Passing a truthy value to the filter will effectively short-circuit retrieval,
			 * returning the passed value instead.
			 *
			 * @since 1.0.0
			 *
			 * @param mixed  $pre_site_temporary The default value to return if the site temporary does not exist.
			 *                                   Any value other than false will short-circuit the retrieval
			 *                                   of the temporary, and return the returned value.
			 * @param string $temporary          Temporary name.
			 */
			$pre = apply_filters( "pre_site_temporary_{$temporary}", false, $temporary );

			if ( false !== $pre ) {
				return $pre;
			}

			// Core temporaries that do not have a timeout. Listed here so querying timeouts can be avoided.
			$no_timeout       = array( 'update_core', 'update_plugins', 'update_themes' );
			$temporary_option = '_site_temporary_' . $temporary;
			if ( ! in_array( $temporary, $no_timeout, true ) ) {
				$temporary_timeout = '_site_temporary_timeout_' . $temporary;
				$timeout           = get_site_option( $temporary_timeout );
				if ( false !== $timeout && $timeout < time() ) {
					delete_site_option( $temporary_option );
					delete_site_option( $temporary_timeout );
					$value = false;
				}
			}

			if ( ! isset( $value ) ) {
				$value = get_site_option( $temporary_option );
			}

			/**
			 * Filter the value of an existing site temporary.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * @since 1.0.0
			 *
			 * @param mixed  $value     Value of site temporary.
			 * @param string $temporary Temporary name.
			 */
			return apply_filters( "site_temporary_{$temporary}", $value, $temporary );
		}

		/**
		 * Set/update the value of a site temporary.
		 *
		 * If updating and expiration is passed, it will start expiration from now.
		 *
		 * You do not need to serialize values, if the value needs to be serialize, then
		 * it will be serialized before it is set.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @see WP_Temporary::set()
		 *
		 * @param string $temporary  Temporary name. Expected to not be SQL-escaped. Must be
		 *                           167 characters or fewer in length.
		 * @param mixed  $value      Temporary value. Expected to not be SQL-escaped.
		 * @param int    $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
		 * @return bool False if value was not set and true if value was set.
		 */
		public static function set_site( $temporary, $value, $expiration = 0 ) {

			/**
			 * Filter the value of a specific site temporary before it is set.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * @since 1.0.0
			 *
			 * @param mixed  $value     New value of site temporary.
			 * @param string $temporary Temporary name.
			 */
			$value = apply_filters( "pre_set_site_temporary_{$temporary}", $value, $temporary );

			$expiration = (int) $expiration;

			/**
			 * Filter the expiration for a site temporary before its value is set.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $expiration Time until expiration in seconds. Use 0 for no expiration.
			 * @param mixed  $value      New value of site temporary.
			 * @param string $temporary  Temporary name.
			 */
			$expiration = apply_filters( "expiration_of_site_temporary_{$temporary}", $expiration, $value, $temporary );

			$temporary_timeout = '_site_temporary_timeout_' . $temporary;
			$option            = '_site_temporary_' . $temporary;
			if ( false === get_site_option( $option ) ) {
				if ( $expiration ) {
					add_site_option( $temporary_timeout, time() + $expiration );
				}
				$result = add_site_option( $option, $value );
			} else {
				if ( $expiration ) {
					update_site_option( $temporary_timeout, time() + $expiration );
				}
				$result = update_site_option( $option, $value );
			}

			if ( $result ) {

				/**
				 * Fires after the value for a specific site temporary has been set.
				 *
				 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
				 *
				 * @since 1.0.0
				 *
				 * @param mixed  $value      Site temporary value.
				 * @param int    $expiration Time until expiration in seconds.
				 * @param string $temporary  Temporary name.
				 */
				do_action( "set_site_temporary_{$temporary}", $value, $expiration, $temporary );

				/**
				 * Fires after the value for a site temporary has been set.
				 *
				 * @since 1.0.0
				 *
				 * @param string $temporary  The name of the site temporary.
				 * @param mixed  $value      Site temporary value.
				 * @param int    $expiration Time until expiration in seconds.
				 */
				do_action( 'setted_site_temporary', $temporary, $value, $expiration );
			}

			return $result;
		}

		/**
		 * Update the value of a site temporary with existing timeout.
		 *
		 * If site temporary doesn't exist, it will set new site temporary.
		 *
		 * You do not need to serialize values. If the value needs to be serialized, then
		 * it will be serialized before it is updated.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @see WP_Temporary::update()
		 *
		 * @param string $temporary  Temporary name. Expected to not be SQL-escaped. Must be
		 *                           167 characters or fewer in length.
		 * @param mixed  $value      Temporary value. Must be serializable if non-scalar.
		 *                           Expected to not be SQL-escaped.
		 * @param int    $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
		 * @return bool False if value was not updated and true if value was updated.
		 */
		public static function update_site( $temporary, $value, $expiration = 0 ) {

			/**
			 * Filter a specific temporary before its value is updated.
			 *
			 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
			 *
			 * @since 1.0.0
			 *
			 * @param mixed  $value     New value of temporary.
			 * @param string $temporary Temporary name.
			 */
			$value = apply_filters( "pre_update_site_temporary_{$temporary}", $value, $temporary );

			$temporary_option = '_site_temporary_' . $temporary;

			// If temporary doesn't exist, create new one,
			// otherwise update it with new value.
			if ( false === self::get_site( $temporary ) ) {
				$result = self::set_site( $temporary, $value, $expiration );
			} else {
				$temporary_option = '_site_temporary_' . $temporary;
				$result           = update_site_option( $temporary_option, $value );
			}

			if ( $result ) {

				/**
				 * Fires after the value for a specific temporary has been updated.
				 *
				 * The dynamic portion of the hook name, `$temporary`, refers to the temporary name.
				 *
				 * @since 1.0.0
				 *
				 * @param mixed  $value      Temporary value.
				 * @param int    $expiration Time until expiration in seconds.
				 * @param string $temporary  Temporary name.
				 */
				do_action( "update_site_temporary_{$temporary}", $value, $expiration, $temporary );

				/**
				 * Fires after the value for a temporary has been updated.
				 *
				 * @since 1.0.0
				 *
				 * @param string $temporary  The name of the temporary.
				 * @param mixed  $value      Temporary value.
				 * @param int    $expiration Time until expiration in seconds.
				 */
				do_action( 'updated_site_temporary', $temporary, $value, $expiration );
			}

			return $result;
		}

		/**
		 * Clean expired temporaries from database.
		 *
		 * Search database for all expired temporaries older
		 * that one minute and use methods for retrieval to
		 * delete them.
		 *
		 * Inspired by https://github.com/Seebz/Snippets/tree/master/Wordpress/plugins/purge-transients
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public static function clean() {
			global $wpdb;

			/**
			 * Allow short-circuit of cleaning of temporaries.
			 *
			 * Passing a truthy value to the filter
			 * will short-circuit process of cleaning.
			 *
			 * @since 1.0.0
			 *
			 * @param bool|mixed $pre_value Should cleaning be not done.
			 *                               Default false to skip it.
			 */
			$pre = apply_filters( 'wp_temporary_clean_pre', false );
			if ( false !== $pre ) {
				return;
			}

			// Older than minute, just for case.
			$older_than_time = time() - MINUTE_IN_SECONDS;

			// Clean single site temporaries.
			$temporaries = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT REPLACE(option_name, '_temporary_timeout_', '') AS temporary_name
					FROM {$wpdb->options}
					WHERE option_name LIKE %s
					AND option_value < %d",
					$wpdb->esc_like( '_temporary_timeout_' ) . '%',
					$older_than_time
				)
			);

			foreach ( $temporaries as $temporary ) {
				self::get( $temporary );
			}

			// Clean network wide temporaries.
			if ( is_multisite() ) {
				$temporaries = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT REPLACE(meta_key, '_site_temporary_timeout_', '') AS temporary_name
						FROM {$wpdb->sitemeta}
						WHERE meta_key LIKE %s
						AND meta_value < %d",
						$wpdb->esc_like( '_site_temporary_timeout_' ) . '%',
						$older_than_time
					)
				);
			} else {
				$temporaries = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT REPLACE(option_name, '_site_temporary_timeout_', '') AS temporary_name
						FROM {$wpdb->options}
						WHERE option_name LIKE %s
						AND option_value < %d",
						$wpdb->esc_like( '_site_temporary_timeout_' ) . '%',
						$older_than_time
					)
				);
			}

			foreach ( $temporaries as $temporary ) {
				self::get_site( $temporary );
			}

			/**
			 * Fires after the cleaning of temporaries has been done.
			 *
			 * @since 1.0.0
			 */
			do_action( 'wp_temporary_clean_after' );
		}

		/**
		 * Add WP-CLI commands.
		 *
		 * @since 1.0.0
		 */
		public static function init_wp_cli() {
			// Only if this is WP-CLI request.
			if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
				return;
			}

			WP_CLI::add_command( 'temporary', 'Temporary_Command' );
		}
	}
endif;
