<?php

class Jetpack_Sync_Module_Options extends Jetpack_Sync_Module {
	private $options_whitelist;

	public function name() {
		return 'options';
	}

	public function init_listeners( $callable ) {
		// options
		add_action( 'added_option', $callable, 10, 2 );
		add_action( 'updated_option', $callable, 10, 3 );
		add_action( 'deleted_option', $callable, 10, 1 );

		// Sync Core Icon: Detect changes in Core's Site Icon and make it syncable.
		add_action( 'add_option_site_icon', array( $this, 'jetpack_sync_core_icon' ) );
		add_action( 'update_option_site_icon', array( $this, 'jetpack_sync_core_icon' ) );
		add_action( 'delete_option_site_icon', array( $this, 'jetpack_sync_core_icon' ) );

		// full sync
		add_action( 'jetpack_full_sync_options', $callable );

		$whitelist_option_handler = array( $this, 'whitelist_options' );
		add_filter( 'jetpack_sync_before_enqueue_deleted_option', $whitelist_option_handler );
		add_filter( 'jetpack_sync_before_enqueue_added_option', $whitelist_option_handler );
		add_filter( 'jetpack_sync_before_enqueue_updated_option', $whitelist_option_handler );
	}

	public function init_before_send() {
		// full sync
		add_filter( 'jetpack_sync_before_send_jetpack_full_sync_options', array( $this, 'expand_options' ) );
	}

	public function set_defaults() {
		$this->update_options_whitelist();
		// theme mod varies from theme to theme.
		$this->options_whitelist[] = 'theme_mods_' . get_option( 'stylesheet' );
	}

	function enqueue_full_sync_actions() {
		/**
		 * Tells the client to sync all options to the server
		 *
		 * @since 4.2.0
		 *
		 * @param boolean Whether to expand options (should always be true)
		 */
		do_action( 'jetpack_full_sync_options', true );

		return 1; // The number of actions enqueued
	}

	function get_full_sync_actions() {
		return array( 'jetpack_full_sync_options' );
	}

	// Is public so that we don't have to store so much data all the options twice.
	function get_all_options() {
		$options = array();
		foreach ( $this->options_whitelist as $option ) {
			$options[ $option ] = get_option( $option );
		}

		return $options;
	}

	function update_options_whitelist() {
		/** This filter is already documented in json-endpoints/jetpack/class.wpcom-json-api-get-option-endpoint.php */
		$this->options_whitelist = apply_filters( 'jetpack_options_whitelist', Jetpack_Sync_Defaults::$default_options_whitelist );
	}

	function set_options_whitelist( $options ) {
		$this->options_whitelist = $options;
	}

	function get_options_whitelist() {
		return $this->options_whitelist;
	}

	// reject non-whitelisted options
	function whitelist_options( $args ) {
		if ( ! $this->is_whitelisted_option( $args[0] ) ) {
			return false;
		}

		return $args;
	}

	function is_whitelisted_option( $option ) {
		return in_array( $option, $this->options_whitelist );
	}

	function jetpack_sync_core_icon() {
		if ( function_exists( 'get_site_icon_url' ) ) {
			$url = get_site_icon_url();
		} else {
			return;
		}

		require_once( JETPACK__PLUGIN_DIR . 'modules/site-icon/site-icon-functions.php' );
		// If there's a core icon, maybe update the option.  If not, fall back to Jetpack's.
		if ( ! empty( $url ) && $url !== jetpack_site_icon_url() ) {
			// This is the option that is synced with dotcom
			Jetpack_Options::update_option( 'site_icon_url', $url );
		} else if ( empty( $url ) ) {
			Jetpack_Options::delete_option( 'site_icon_url' );
		}
	}

	public function expand_options( $args ) {
		if ( $args[0] ) {
			return $this->get_all_options();
		}

		return $args;
	}
}
