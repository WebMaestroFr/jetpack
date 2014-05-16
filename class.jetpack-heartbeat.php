<?php

class Jetpack_Heartbeat {

	/**
	 * Holds the singleton instance of this class
	 *
	 * @since 2.3.3
	 * @var Jetpack_Heartbeat
	 */
	static $instance = false;

	private $cron_name = 'jetpack_heartbeat';

	/**
	 * Singleton
	 *
	 * @since 2.3.3
	 * @static
	 * @return Jetpack_Heartbeat
	 */
	public static function init() {
		if ( ! self::$instance ) {
			self::$instance = new Jetpack_Heartbeat;
		}

		return self::$instance;
	}

	/**
	 * Constructor for singleton
	 *
	 * @since 2.3.3
	 * @return Jetpack_Heartbeat
	 */
	private function __construct() {
		if ( ! Jetpack::is_active() )
			return;

		// Schedule the task
		add_action( $this->cron_name, array( $this, 'cron_exec' ) );

		if ( ! wp_next_scheduled( $this->cron_name ) ) {
			wp_schedule_event( time(), 'daily', $this->cron_name );
		}
	}
	
	/**
	 * Method that gets executed on the wp-cron call
	 *
	 * @since 2.3.3
	 * @global string $wp_version
	 */
	public function cron_exec() {

		/*
		 * This should run daily.  Figuring in for variances in
		 * WP_CRON, don't let it run more than every 23 hours at most.
		 *
		 * i.e. if it ran less than 23 hours ago, fail out.
		 */
		$last = (int) Jetpack_Options::get_option( 'last_heartbeat' );
		if ( $last && ( $last + DAY_IN_SECONDS - HOUR_IN_SECONDS > time() ) ) {
			return;
		}

		/*
		 * Check for an identity crisis
		 *
		 * If one exists:
		 * - Bump stat for ID crisis
		 * - Email site admin about potential ID crisis
		 */

		// Coming Soon!

		foreach ( self::generate_stats_array( 'v2-' ) as $key => $value ) {
			$jetpack->stat( $key, $value );
		}

		Jetpack_Options::update_option( 'last_heartbeat', time() );

		$jetpack->do_stats( 'server_side' );
	}

	public static function generate_stats_array( $prefix = '' ) {
		$return = array();

		$return["{$prefix}version"]        = JETPACK__VERSION;
		$return["{$prefix}wp-version"]     = get_bloginfo( 'version' );
		$return["{$prefix}php-version"]    = PHP_VERSION;
		$return["{$prefix}branch"]         = floatval( JETPACK__VERSION );
		$return["{$prefix}wp-branch"]      = floatval( get_bloginfo( 'version' ) );
		$return["{$prefix}php-branch"]     = floatval( PHP_VERSION );
		$return["{$prefix}ssl"]            = Jetpack::permit_ssl();
		$return["{$prefix}language"]       = get_bloginfo( 'language' );
		$return["{$prefix}charset"]        = get_bloginfo( 'charset' );
		$return["{$prefix}is-multisite"]   = is_multisite() ? 'multisite' : 'singlesite';
		$return["{$prefix}identitycrisis"] = Jetpack::check_identity_crisis( 1 ) ? 'yes' : 'no';
		$return["{$prefix}plugins"]        = implode( ',', Jetpack::get_active_plugins() );

		if ( ! empty( $_SERVER['SERVER_ADDR'] ) || ! empty( $_SERVER['LOCAL_ADDR'] ) ) {
			$ip     = ! empty( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : $_SERVER['LOCAL_ADDR'];
			$ip_arr = array_map( 'intval', explode( '.', $ip ) );
			if ( 4 == sizeof( $ip_arr ) ) {
				$return["{$prefix}ip-2-octets"] = implode( '.', array_slice( $ip_arr, 0, 2 ) );
				$return["{$prefix}ip-3-octets"] = implode( '.', array_slice( $ip_arr, 0, 3 ) );
			}
		}

		foreach ( Jetpack::get_available_modules() as $slug ) {
			$return["{$prefix}module-{$slug}"] = Jetpack::is_module_active( $slug ) ? 'on' : 'off';
		}

		return $return;
	}

	public function deactivate() {
		$timestamp = wp_next_scheduled( $this->cron_name );
		wp_unschedule_event( $timestamp, $this->cron_name );
	}

}
