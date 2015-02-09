<?php

abstract class WPCOM_JSON_API_Sharing_Button_Endpoint extends WPCOM_JSON_API_Endpoint {

	public static $all_visibilities = array( 'visible', 'hidden' );

	protected $sharing_service;

	protected function setup() {
		$this->sharing_service = new Sharing_Service();

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'You do not have the capability to manage sharing buttons for this site', 403 );
		} else if ( ! class_exists( 'Sharing_Service' ) || ! class_exists( 'Sharing_Source' ) || 
				( method_exists( 'Jetpack', 'is_module_active' ) && ! Jetpack::is_module_active( 'sharedaddy' ) ) ) {
			return new WP_Error( 'missing_jetpack_module', 'The Sharing module must be activated in order to use this endpoint', 400 );
		}
	}

	public function format_sharing_button( $button ) {
		$response = array(
			'ID'           => $button->get_id(),
			'name'         => $button->get_name(),
			'shortname'    => $button->shortname,
			'custom'       => is_a( $button, 'Share_Custom' ),
			'enabled'      => $this->is_button_enabled( $button ),
		);

		if ( $response['enabled'] ) {
			// Status is either "disabled" or the visibility value
			$response['visibility'] = $this->get_button_visibility( $button );
		}

		if ( ! empty( $button->genericon ) ) {
			// Only pre-defined sharing buttons include genericon
			$response['genericon'] = $button->genericon;
		}

		if ( method_exists( $button, 'get_options' ) ) {
			// merge get_options() values into response, primarily to account
			// for custom sharing button values
			foreach ( $button->get_options() as $key => $value ) {
				// Capitalize URL property
				if ( 'url' === strtolower( $key ) ) {
					$key = strtoupper( $key );
				}

				$response[ $key ] = $value;
			}			
		}

		return $response;
	}

	public function get_button_visibility( $button ) {
		$services = $this->sharing_service->get_blog_services();
		$visibilities = self::$all_visibilities;
		$button_id = $button->get_id();

		foreach ( $visibilities as $visibility ) {
			if ( isset( $services[ $visibility ][ $button_id ] ) ) {
				return $visibility;
			}
		}

		return false;
	}

	public function is_button_enabled( $button ) {
		return false !== $this->get_button_visibility( $button );
	}

}

class WPCOM_JSON_API_Get_Sharing_Buttons_Endpoint extends WPCOM_JSON_API_Sharing_Button_Endpoint {

	// GET /sites/%s/sharing-buttons -> $blog_id
	public function callback( $path = '', $blog_id = 0 ) {
		$continue = $this->setup();
		if ( is_wp_error( $continue ) ) {
			return $continue;
		}

		$args = $this->query_args();

		// Validate request
		$blog_id = $this->api->switch_to_blog_and_validate_user( $this->api->get_blog_id( $blog_id ) );
		if ( is_wp_error( $blog_id ) ) {
			return $blog_id;
		}
		
		if ( ! empty( $args['visibility'] ) && ! in_array( $args['visibility'], self::$all_visibilities ) ) {
			return new WP_Error( 'invalid_visibility', sprintf( 'The visibility field must be one of the following values: %s', implode( ', ', self::$all_visibilities ) ), 400 );
		}

		// Determine which visibilities to include based on request
		$visibilities = empty( $args['visibility'] ) ? self::$all_visibilities : array( $args['visibility'] );

		// Discover enabled services
		$buttons = array();
		$enabled_services = $this->sharing_service->get_blog_services();
		$all_services = $this->sharing_service->get_all_services_blog();

		// Include buttons of desired visibility
		foreach ( $visibilities as $visibility ) {
			$buttons = array_merge( $buttons, $enabled_services[ $visibility ] );
		}

		// Unless `enabled_only` or `visibility` is specified, append the 
		// remaining buttons to the end of the array
		if ( ( ! isset( $args['enabled_only'] ) || ! $args['enabled_only'] ) && empty( $args['visibility'] ) ) {
			foreach ( $all_services as $id => $button ) {
				if ( ! array_key_exists( $id, $buttons ) ) {
					$buttons[ $id ] = $button;
				}
			}
		}

		// Format each button in the response
		$response = array();
		foreach ( $buttons as $button ) {
			$response[] = $this->format_sharing_button( $button );
		}

		return array(
			'found'           => count( $response ),
			'sharing_buttons' => $response
		);
	}
}

class WPCOM_JSON_API_Get_Sharing_Button_Endpoint extends WPCOM_JSON_API_Sharing_Button_Endpoint {

	// GET /sites/%s/sharing-buttons/%s -> $blog_id, $button_id
	public function callback( $path = '', $blog_id = 0, $button_id = 0 ) {
		$continue = $this->setup();
		if ( is_wp_error( $continue ) ) {
			return $continue;
		}

		// Validate request
		$blog_id = $this->api->switch_to_blog_and_validate_user( $this->api->get_blog_id( $blog_id ) );
		if ( is_wp_error( $blog_id ) ) {
			return $blog_id;
		}

		// Search existing services for button
		$all_buttons = $this->sharing_service->get_all_services_blog();
		if ( ! array_key_exists( $button_id, $all_buttons ) ) {
			return new WP_Error( 'not_found', 'The specified sharing button was not found', 404 );
		} else {
			return $this->format_sharing_button( $all_buttons[ $button_id ] );
		}
	}

}

class WPCOM_JSON_API_Update_Sharing_Button_Endpoint extends WPCOM_JSON_API_Sharing_Button_Endpoint {

	// POST /sites/%s/sharing-buttons/new -> $blog_id
	// POST /sites/%s/sharing-buttons/%s -> $blog_id, $button_id
	public function callback( $path = '', $blog_id = 0, $button_id = 0 ) {
		$continue = $this->setup();
		if ( is_wp_error( $continue ) ) {
			return $continue;
		}

		$new = $this->api->ends_with( $path, '/new' );
		$input = $this->input();

		// Validate request
		$blog_id = $this->api->switch_to_blog_and_validate_user( $this->api->get_blog_id( $blog_id ) );
		if ( is_wp_error( $blog_id ) ) {
			return $blog_id;
		}

		if ( ! empty( $input['visibility'] ) && ! in_array( $input['visibility'], self::$all_visibilities ) ) {
			return new WP_Error( 'invalid_visibility', sprintf( 'The visibility field must be one of the following values: %s', implode( ', ', self::$all_visibilities ) ), 400 );
		} else if ( $new && empty( $input['URL'] ) ) {
			return new WP_Error( 'invalid_request', 'The URL field is required', 400 );
		} else if ( $new && empty( $input['icon'] ) ) {
			return new WP_Error( 'invalid_request', 'The icon field is required', 400 );
		}

		// Assign default values
		$visibility = $input['visibility'];
		if ( empty( $visibility ) || ( ! isset( $input['visibility'] ) && true === $input['enabled'] ) ) {
			$visibility = 'visible';
		}

		// Update or create button
		$blog_services = $this->sharing_service->get_blog_services();
		if ( $new ) {
			// Attempt to create new button
			$updated_service = $this->sharing_service->new_service( $input['name'], $input['URL'], $input['icon'] );
			if ( false !== $updated_service && ( ( isset( $input['enabled'] ) && true === $input['enabled'] ) || isset( $input['visibility'] ) ) ) {
				$blog_services[ $visibility ][ (string) $updated_service->get_id() ] = $updated_service;	
				$this->sharing_service->set_blog_services( array_keys( $blog_services['visible'] ), array_keys( $blog_services['hidden'] ) );
			}
		} else {
			// Find existing button
			$all_buttons = $this->sharing_service->get_all_services_blog();
			if ( ! array_key_exists( $button_id, $all_buttons ) ) {
				// Button doesn't exist
				return new WP_Error( 'not_found', 'The specified sharing button was not found', 404 );
			}

			$updated_service = $all_buttons[ $button_id ];
			$service_id = $updated_service->get_id();
			if ( is_a( $all_buttons[ $button_id ], 'Share_Custom' ) ) {
				// Replace options for existing custom button
				$options = $updated_service->get_options();
				$name = isset( $input['name'] ) ? $input['name'] : $options['name'];
				$url = isset( $input['URL'] ) ? $input['URL'] : $options['url'];
				$icon = isset( $input['icon'] ) ? $input['icon'] : $options['icon'];
				$updated_service = new Share_Custom( $service_id, array( 'name' => $name, 'url' => $url, 'icon' => $icon ) );
				$this->sharing_service->set_service( $button_id, $updated_service );
			}

			// Update button visibility
			$visibility_changed = ( isset( $input['visibility'] ) || true === $input['enabled'] ) && ! array_key_exists( $service_id, $blog_services[ $visibility ] );
			$is_disabling = false === $input['enabled'];
			if ( $visibility_changed || $is_disabling ) {
				// Remove from all other visibilities
				foreach ( $blog_services as $service_visibility => $services ) {
					if ( $service_visibility !== $visibility || $is_disabling ) {
						unset( $blog_services[ $service_visibility ][ $service_id ] );
					}
				}

				if ( $visibility_changed ) {
					$blog_services[ $visibility ][ $service_id ] = $updated_service;				
				}

				$this->sharing_service->set_blog_services( array_keys( $blog_services['visible'] ), array_keys( $blog_services['hidden'] ) );	
			}
		}

		if ( false === $updated_service ) {
			return new WP_Error( 'invalid_request', sprintf( 'The sharing button was not %s', $new ? 'created' : 'updated' ), 400 );
		} else {
			return $this->format_sharing_button( $updated_service );
		}
	}

}

class WPCOM_JSON_API_Delete_Sharing_Button_Endpoint extends WPCOM_JSON_API_Sharing_Button_Endpoint {

	// POST /sites/%s/sharing-buttons/%s/delete -> $blog_id, $button_id
	public function callback( $path = '', $blog_id = 0, $button_id = 0 ) {
		$continue = $this->setup();
		if ( is_wp_error( $continue ) ) {
			return $continue;
		}

		// Validate request
		$blog_id = $this->api->switch_to_blog_and_validate_user( $this->api->get_blog_id( $blog_id ) );
		if ( is_wp_error( $blog_id ) ) {
			return $blog_id;
		}

		// Find existing button
		$all_buttons = $this->sharing_service->get_all_services_blog();
		if ( ! array_key_exists( $button_id, $all_buttons ) ) {
			// Button doesn't exist
			return new WP_Error( 'not_found', 'The specified sharing button was not found', 404 );
		}

		// Verify button is custom
		if ( ! is_a( $all_buttons[ $button_id ], 'Share_Custom' ) ) {
			return new WP_error( 'invalid_request', 'Only custom sharing buttons can be deleted', 400 );
		}

		$success = $this->sharing_service->delete_service( $button_id );
		return array(
			'ID'      => $button_id,
			'success' => $success
		);
	}

}