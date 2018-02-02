<?php
/**
 * Tests: Tests_WP_Temporary class
 *
 * @package WP_Temporary
 * @subpackage Tests
 * @since 1.0.0
 */

/**
 * Class with tests for single site temporaries.
 *
 * @since 1.0.0
 */
class Tests_WP_Temporary extends WP_UnitTestCase {
	/**
	 * Basic test of WP_Temporary.
	 *
	 * Based on core test Tests_Option_Transient::test_the_basic()
	 *
	 * @since 1.0.0
	 */
	public function test_the_basics() {
		$key    = 'key1';
		$value  = 'value1';
		$value2 = 'value2';

		$this->assertFalse( WP_Temporary::get( 'doesnotexist' ) );
		$this->assertTrue( WP_Temporary::set( $key, $value ) );
		$this->assertEquals( $value, WP_Temporary::get( $key ) );
		$this->assertFalse( WP_Temporary::set( $key, $value ) );
		$this->assertTrue( WP_Temporary::set( $key, $value2 ) );
		$this->assertEquals( $value2, WP_Temporary::get( $key ) );
		$this->assertTrue( WP_Temporary::delete( $key ) );
		$this->assertFalse( WP_Temporary::get( $key ) );
		$this->assertFalse( WP_Temporary::delete( $key ) );
	}

	/**
	 * Test saving serialized data.
	 *
	 * Based on core test Tests_Option_Transient::test_serialized_data()
	 *
	 * @since 1.0.0
	 */
	public function test_serialized_data() {
		$key   = rand_str();
		$value = array(
			'foo' => true,
			'bar' => true,
		);

		$this->assertTrue( WP_Temporary::set( $key, $value ) );
		$this->assertEquals( $value, WP_Temporary::get( $key ) );

		$value = (object) $value;
		$this->assertTrue( WP_Temporary::set( $key, $value ) );
		$this->assertEquals( $value, WP_Temporary::get( $key ) );
		$this->assertTrue( WP_Temporary::delete( $key ) );
	}

	/**
	 * Test saving data with timeout.
	 *
	 * Based on core test Tests_Option_Transient::test_transient_data_with_timeout()
	 *
	 * @since 1.0.0
	 */
	public function test_temporary_data_with_timeout() {
		$key   = rand_str();
		$value = rand_str();

		$this->assertFalse( get_option( '_temporary_timeout_' . $key ) );

		$now = time();

		$this->assertTrue( WP_Temporary::set( $key, $value, 100 ) );

		// Ensure the temporary timeout is set for 100-101 seconds in the future.
		$this->assertGreaterThanOrEqual( $now + 100, get_option( '_temporary_timeout_' . $key ) );
		$this->assertLessThanOrEqual( $now + 101, get_option( '_temporary_timeout_' . $key ) );

		// Update the timeout to a second in the past and watch the temporary be invalidated.
		$this->assertEquals( $value, WP_Temporary::get( $key ) );
		update_option( '_temporary_timeout_' . $key, $now - 1 );
		$this->assertFalse( WP_Temporary::get( $key ) );
	}

	/**
	 * Test adding timeout to existing temporary that has no timeout.
	 *
	 * Based on core test Tests_Option_Transient::test_transient_add_timeout()
	 *
	 * @since 1.0.0
	 */
	public function test_temporary_add_timeout() {
		$key    = rand_str();
		$value  = rand_str();
		$value2 = rand_str();

		$this->assertTrue( WP_Temporary::set( $key, $value ) );
		$this->assertEquals( $value, WP_Temporary::get( $key ) );
		$this->assertFalse( get_option( '_temporary_timeout_' . $key ) );

		$now = time();

		// Add timeout to existing timeout-less temporary.
		$this->assertTrue( WP_Temporary::set( $key, $value2, 1 ) );
		$this->assertGreaterThanOrEqual( $now, get_option( '_temporary_timeout_' . $key ) );

		$this->assertEquals( $value2, WP_Temporary::get( $key ) );
		update_option( '_temporary_timeout_' . $key, $now - 1 );
		$this->assertFalse( WP_Temporary::get( $key ) );
	}

	/**
	 * Test if returning false for timeout for temporary with timeout will delete temporary or timeout.
	 *
	 * Based on core test Tests_Option_Transient::test_nonexistent_key_dont_delete_if_false()
	 *
	 * @since 1.0.0
	 */
	public function test_false_timeout_dont_delete() {
		// Create a bogus a temporary.
		$key   = rand_str();
		$value = rand_str();

		WP_Temporary::set( $key, $value, 60 * 10 );
		$this->assertEquals( $value, WP_Temporary::get( $key ) );

		// Useful variables for tracking.
		$temporary_timeout = '_temporary_timeout_' . $key;

		// Mock an action for tracking action calls.
		$a = new MockAction();

		// Make sure the timeout option returns false.
		add_filter( 'option_' . $temporary_timeout, '__return_false' );

		// Add some actions to make sure options are _not_ deleted.
		add_action( 'delete_option', array( $a, 'action' ) );

		// Act.
		$status = WP_Temporary::get( $key );
		$this->assertEquals( $value, $status );

		// Make sure delete option was not called for both the temporary and the timeout.
		$this->assertEquals( 0, $a->get_call_count() );
	}

	/**
	 * Test if returning zero for timeout for temporary with timeout will delete temporary and timeout.
	 *
	 * Based on core test Tests_Option_Transient::test_nonexistent_key_old_timeout()
	 *
	 * @since 1.0.0
	 */
	public function test_old_timeout_delete() {
		// Create a temporary.
		$key   = rand_str();
		$value = rand_str();

		WP_Temporary::set( $key, $value, 60 * 10 );
		$this->assertEquals( $value, WP_Temporary::get( $key ) );

		// Make sure the timeout option returns zero.
		$timeout          = '_temporary_timeout_' . $key;
		$temporary_option = '_temporary_' . $key;
		add_filter( 'option_' . $timeout, '__return_zero' );

		// Mock an action for tracking action calls.
		$a = new MockAction();

		// Add some actions to make sure options are deleted.
		add_action( 'delete_option', array( $a, 'action' ) );

		// Act.
		$status = WP_Temporary::get( $key );
		$this->assertFalse( $status );

		// Make sure delete option was called for both the temporary and the timeout.
		$this->assertEquals( 2, $a->get_call_count() );

		$expected = array(
			array(
				'action' => 'action',
				'tag'    => 'delete_option',
				'args'   => array( $temporary_option ),
			),
			array(
				'action' => 'action',
				'tag'    => 'delete_option',
				'args'   => array( $timeout ),
			),
		);
		$this->assertEquals( $expected, $a->get_events() );
	}

	/**
	 * Test if updating temporary changes timeout.
	 *
	 * @since 1.0.0
	 */
	public function test_updating() {
		$key = rand_str();

		// Setting temporary should be true.
		$this->assertTrue( WP_Temporary::set( $key, 'value1', 5 ) );

		// Direct retrieval of temporary value should return set value.
		$this->assertEquals( get_option( '_temporary_' . $key ), 'value1' );

		// Direct retrieval of temporary timeout should be integer and greater than current time.
		$raw_key1_timeout_before_sleep = get_option( '_temporary_timeout_' . $key );
		$this->assertTrue( is_int( $raw_key1_timeout_before_sleep ) );
		$this->assertGreaterThan( time(), $raw_key1_timeout_before_sleep );

		// Getting of temporary should return set value.
		$this->assertEquals( WP_Temporary::get( $key ), 'value1' );

		// Updating temporary should be true.
		$this->assertTrue( WP_Temporary::update( $key, 'value1-update1', 5 ) );

		// Getting of temporary should return updated value.
		$this->assertEquals( WP_Temporary::get( $key ), 'value1-update1' );

		// Timeout should be unchanged.
		$this->assertEquals( $raw_key1_timeout_before_sleep, get_option( '_temporary_timeout_' . $key ) );

		// Sleep for two minutes.
		sleep( 2 * MINUTE_IN_SECONDS );

		// Updating temporary should be true.
		$this->assertTrue( WP_Temporary::update( $key, 'value1-update2', 5 ) );

		// Getting of temporary should return updated value.
		$this->assertEquals( WP_Temporary::get( $key ), 'value1-update2' );

		// Direct retrieval of temporary timeout should be integer, and greater than current time or timeout before sleep.
		$raw_key1_timeout_after_sleep = get_option( '_temporary_timeout_' . $key );
		$this->assertTrue( is_int( $raw_key1_timeout_after_sleep ) );
		$this->assertGreaterThan( time(), $raw_key1_timeout_after_sleep );
		$this->assertGreaterThan( $raw_key1_timeout_before_sleep, $raw_key1_timeout_after_sleep );
	}

	/**
	 * Test if direct retrieval of expired temporary works.
	 *
	 * @since 1.0.0
	 */
	public function test_without_cleaning() {
		$key = rand_str();

		// Setting temporary should be true.
		$this->assertTrue( WP_Temporary::set( $key, 'value1', 5 ) );

		// Direct retrieval of temporary value should return set value.
		$this->assertEquals( get_option( '_temporary_' . $key ), 'value1' );

		// Direct retrieval of temporary timeout should be integer and greater than current time.
		$raw_key1_timeout_before_sleep = get_option( '_temporary_timeout_' . $key );
		$this->assertTrue( is_int( $raw_key1_timeout_before_sleep ) );
		$this->assertGreaterThan( time(), $raw_key1_timeout_before_sleep );

		// Getting of temporary should return set value.
		$this->assertEquals( WP_Temporary::get( $key ), 'value1' );

		// Sleep for two minutes so that timeout expires.
		sleep( 2 * MINUTE_IN_SECONDS );

		// Direct retrieval of temporary value should return set value.
		$this->assertEquals( get_option( '_temporary_' . $key ), 'value1' );

		// Direct retrieval of temporary timeout should be integer and less than current time.
		$raw_key1_timeout_after_sleep = get_option( '_temporary_timeout_' . $key );
		$this->assertTrue( is_int( $raw_key1_timeout_after_sleep ) );
		$this->assertLessThan( time(), $raw_key1_timeout_after_sleep );

		// Getting of expired temporary should be false.
		$this->assertFalse( WP_Temporary::get( $key ) );

		// Direct retrieval of expired temporary value should be false.
		$this->assertFalse( get_option( '_temporary_' . $key ) );

		// Direct retrieval of expired temporary timeout should be false.
		$this->assertFalse( get_option( '_temporary_timeout_' . $key ) );
	}

	/**
	 * Test if cleaning will delete expired temporaries.
	 *
	 * @since 1.0.0
	 */
	public function test_with_cleaning() {
		$key   = rand_str();
		$value = rand_str();

		// Setting temporary should be true.
		$this->assertTrue( WP_Temporary::set( $key, $value, 5 ) );

		// Direct retrieval of temporary value should return set value.
		$this->assertEquals( get_option( '_temporary_' . $key ), $value );

		// Direct retrieval of temporary timeout should be integer and greater than current time.
		$raw_key2_timeout_before_sleep = get_option( '_temporary_timeout_' . $key );
		$this->assertTrue( is_int( $raw_key2_timeout_before_sleep ) );
		$this->assertGreaterThan( time(), $raw_key2_timeout_before_sleep );

		// Getting of temporary should return set value.
		$this->assertEquals( WP_Temporary::get( $key ), $value );

		// Sleep for two minutes.
		sleep( 2 * MINUTE_IN_SECONDS );

		// Do cleaning.
		WP_Temporary::clean();

		// Direct retrieval of expired and cleaned temporary value should be false.
		$this->assertFalse( get_option( '_temporary_' . $key ) );

		// Direct retrieval of expired and cleaned temporary timeout should be false.
		$this->assertFalse( get_option( '_temporary_timeout_' . $key ) );

		// Getting of expired temporary and cleaned should be false.
		$this->assertFalse( WP_Temporary::get( $key ) );

		// Direct retrieval of expired and cleaned temporary value should be false.
		$this->assertFalse( get_option( '_temporary_' . $key ) );

		// Direct retrieval of expired and cleaned temporary timeout should be false.
		$this->assertFalse( get_option( '_temporary_timeout_' . $key ) );
	}
}
