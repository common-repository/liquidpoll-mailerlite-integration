<?php
/**
 * Plugin Name: LiquidPoll - MailerLite Integration
 * Plugin URI: https://liquidpoll.com/plugin/liquidpoll-mailerlite-intregration
 * Description: Integration with MailerLite
 * Version: 1.0.1
 * Author: LiquidPoll
 * Text Domain: liquidpoll-mailerlite
 * Domain Path: /languages/
 * Author URI: https://liquidpoll.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

use WPDK\Utils;

defined( 'ABSPATH' ) || exit;

defined( 'LIQUIDPOLL_MAILERLITE_PLUGIN_URL' ) || define( 'LIQUIDPOLL_MAILERLITE_PLUGIN_URL', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/' );
defined( 'LIQUIDPOLL_MAILERLITE_PLUGIN_DIR' ) || define( 'LIQUIDPOLL_MAILERLITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'LIQUIDPOLL_MAILERLITE_PLUGIN_FILE' ) || define( 'LIQUIDPOLL_MAILERLITE_PLUGIN_FILE', plugin_basename( __FILE__ ) );


if ( ! class_exists( 'LIQUIDPOLL_Integration_mailerlite' ) ) {
	/**
	 * Class LIQUIDPOLL_Integration_mailerlite
	 */
	class LIQUIDPOLL_Integration_mailerlite {

		protected static $_instance = null;


		/**
		 * LIQUIDPOLL_Integration_mailerlite constructor.
		 */
		function __construct() {

			load_plugin_textdomain( 'liquidpoll-mailerlite', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );

			add_filter( 'LiquidPoll/Filters/poll_meta_field_sections', array( $this, 'add_field_sections' ) );
			add_filter( 'woc_filters_settings_pages', array( $this, 'add_settings_field_sections' ) );

			add_action( 'liquidpoll_email_added_local', array( $this, 'add_emails_to_mailerlite' ) );
		}


		/**
		 * add_emails_to_mailerlite
		 *
		 * @param $args
		 */
		function add_emails_to_mailerlite( $args ) {

			global $wpdb;

			$poll_id               = Utils::get_args_option( 'poll_id', $args );
			$poller_id_ip          = Utils::get_args_option( 'poller_id_ip', $args );
			$email_address         = Utils::get_args_option( 'email_address', $args );
			$first_name            = Utils::get_args_option( 'first_name', $args );
			$last_name             = Utils::get_args_option( 'last_name', $args );
			$mailerlite_groups     = Utils::get_meta( 'poll_form_int_mailerlite_groups', $poll_id, array() );
			$polled_value          = $wpdb->get_var( $wpdb->prepare( "SELECT polled_value FROM " . LIQUIDPOLL_RESULTS_TABLE . " WHERE poll_id = %d AND poller_id_ip = %s ORDER BY datetime DESC LIMIT 1", $poll_id, $poller_id_ip ) );
			$is_integration_enable = Utils::get_meta( 'poll_form_int_mailerlite_enable', $poll_id, false ) == '1' ? true : false;

			if ( ! empty( $polled_value ) ) {
				$poll         = liquidpoll_get_poll( $poll_id );
				$poll_options = $poll->get_poll_options();
				$poll_type    = $poll->get_type();

				foreach ( $poll_options as $option_id => $option ) {
					if ( $polled_value == $option_id ) {

						if ( 'poll' == $poll_type ) {
							$mailerlite_groups = array_merge( $mailerlite_groups, Utils::get_args_option( 'mailerlite_groups', $option, array() ) );
						}

						if ( 'nps' == $poll_type ) {
							$mailerlite_groups = array_merge( $mailerlite_groups, Utils::get_args_option( 'mailerlite_nps_groups', $option, array() ) );

						}

						break;
					}
				}
			}

			if ( ! empty( $email_address ) && $is_integration_enable ) {
				$this->mailerlite_api( 'POST', 'subscribers', array(
					'email'  => $email_address,
					'fields' => array(
						'last_name' => $last_name,
						'name'      => $first_name
					),
					'groups' => $mailerlite_groups,
				) );
			}
		}


		/**
		 * Add section in settings field
		 *
		 * @param $field_sections
		 *
		 * @return array
		 */
		function add_settings_field_sections( $field_sections ) {
			$field_sections['integrations'] = array(
				'title'    => esc_html__( 'Integrations', 'wp-poll' ),
				'sections' => array(
					array(
						'title'  => esc_html__( 'MailerLite', 'wp-poll' ),
						'fields' => array(
							array(
								'id'          => 'liquidpoll_mailerlite_api_key',
								'title'       => esc_html__( 'MailerLite Api Keys', 'wp-poll' ),
								'subtitle'    => esc_html__( 'Add MailerLite api tokens.', 'wp-poll' ),
								'placeholder' => '93u867df431io7',
								'type'        => 'text',
							),
						),
					),
				),
			);

			return $field_sections;
		}


		/**
		 * Add section in form field
		 *
		 * @param $field_sections
		 *
		 * @return array
		 */
		function add_field_sections( $field_sections ) {


			$field_sections['poll_form']['fields'][] = array(
				'type'       => 'subheading',
				'content'    => esc_html__( 'Integration - MailerLite', 'wp-poll' ),
				'dependency' => array( '_type', 'any', 'poll,nps,reaction', 'all' ),
			);

			$field_sections['poll_form']['fields'][] = array(
				'id'         => 'poll_form_int_mailerlite_enable',
				'title'      => esc_html__( 'Enable Integration', 'wp-poll' ),
				'label'      => esc_html__( 'This will store the submissions in MailerLite.', 'wp-poll' ),
				'type'       => 'switcher',
				'default'    => false,
				'dependency' => array( '_type', 'any', 'poll,nps,reaction', 'all' ),
			);

			$field_sections['poll_form']['fields'][] = array(
				'id'         => 'poll_form_int_mailerlite_groups',
				'title'      => esc_html__( 'Select Group', 'wp-poll' ),
				'subtitle'   => esc_html__( 'Select MailerLite Groups', 'wp-poll' ),
				'type'       => 'select',
				'multiple'   => true,
				'chosen'     => true,
				'options'    => $this->get_mailerlite_groups(),
				'dependency' => array( '_type|poll_form_int_mailerlite_enable', 'any|==', 'poll,nps,reaction|true', 'all' ),
			);

			foreach ( Utils::get_args_option( 'fields', $field_sections['poll_options'], array() ) as $index => $arr_field ) {
				if ( isset( $arr_field['id'] ) && 'poll_meta_options' == $arr_field['id'] ) {
					$field_sections['poll_options']['fields'][ $index ]['fields'][] = array(
						'id'         => 'mailerlite_groups',
						'title'      => esc_html__( 'Select Groups', 'wp-poll' ),
						'subtitle'   => esc_html__( 'Select MailerLite groups', 'wp-poll' ),
						'type'       => 'select',
						'multiple'   => true,
						'chosen'     => true,
						'options'    => $this->get_mailerlite_groups(),
						'dependency' => array( '_type', '==', 'poll', 'all' ),
					);
					break;
				}
			}

			foreach ( Utils::get_args_option( 'fields', $field_sections['poll_options'], array() ) as $index => $arr_field ) {
				if ( isset( $arr_field['id'] ) && 'poll_meta_options_nps' == $arr_field['id'] ) {
					$field_sections['poll_options']['fields'][ $index ]['fields'][] = array(
						'id'         => 'mailerlite_nps_groups',
						'title'      => esc_html__( 'Select Groups', 'wp-poll' ),
						'subtitle'   => esc_html__( 'Select MailerLite groups', 'wp-poll' ),
						'type'       => 'select',
						'multiple'   => true,
						'chosen'     => true,
						'options'    => $this->get_mailerlite_groups(),
						'dependency' => array( '_type', '==', 'nps', 'all' ),
					);
					break;
				}
			}

			return $field_sections;
		}


		/**
		 * Get mailerlite groups
		 *
		 * @return array
		 */
		public function get_mailerlite_groups() {

			$post_id = (int) isset( $_REQUEST['post'] ) ? $_REQUEST['post'] : '';
			$action  = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
			$groups  = array();

			if ( 'poll' == get_post_type( $post_id ) && 'edit' == $action ) {
				$response = $this->mailerlite_api( 'GET', 'groups' );
			}

			if ( empty( $response ) ) {
				$response = array();
			}

			foreach ( $response as $group ) {
				$groups[ $group['id'] ] = $group['name'];
			}

			return $groups;
		}


		/**
		 * MailerLite Connector API
		 *
		 * @param string $method Method to connect: GET, POST..
		 * @param string $module URL endpoint.
		 * @param array $data Body data.
		 *
		 * @return array|void|false
		 */
		private function mailerlite_api( $method, $module, $data = array() ) {

			$apikey = Utils::get_option( 'liquidpoll_mailerlite_api_key' );


			if ( ! $apikey ) {
				return;
			}
			$args = array(
				'method'  => $method,
				'headers' => array(
					'X-MailerLite-ApiKey' => $apikey,
					'Content-Type'        => 'application/json',
					'Accept'              => 'application/json',
				),
			);
			if ( ! empty( $data ) ) {
				$args['body'] = json_encode( $data );
			}
			$url      = 'https://api.mailerlite.com/api/v2/' . $module;
			$response = wp_remote_request( $url, $args );

			if ( 200 === $response['response']['code'] ) {
				$body = wp_remote_retrieve_body( $response );

				return json_decode( $body, true );
			} else {
				return false;
			}
		}


		/**
		 * @return \LIQUIDPOLL_Integration_mailerlite|null
		 */
		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}
}

add_action( 'wpdk_init_wp_poll', array( 'LIQUIDPOLL_Integration_mailerlite', 'instance' ) );
