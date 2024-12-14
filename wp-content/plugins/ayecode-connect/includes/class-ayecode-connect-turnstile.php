<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AyeCode_Connect_Turnstile {
	private static $instance = null;
	private $options;
	private $widget_count = 0;

	public function __construct() {
		// Get saved options or empty array if none exist
		$saved_options = get_option( 'ayecode_turnstile_options', array() );

		// Default options - everything enabled
		$defaults = array(
			'site_key'      => '',
			'secret_key'    => '',
			'theme'         => 'light',
			'size'          => 'normal',
			'disable_roles' => [],
			'protections'   => array(
				'login'            => 1,
				'register'         => 1,
				'forgot_password'  => 1,
				'comments'         => 1,
				'gd_add_listing'   => 1,
				'gd_report_post'   => 1,
				'gd_claim_listing' => 1,
				'uwp_login'        => 1,
				'uwp_register'     => 1,
				'uwp_forgot'       => 1,
				'uwp_account'      => 1,
				'uwp_frontend'     => 1,
				'bs_contact'       => 1,
				'gp_checkout'      => 1,
			)
		);

		// If constants exist, use those values for keys
		if ( defined( 'AYECODE_TURNSTILE_SITE_KEY' ) ) {
			$defaults['site_key'] = AYECODE_TURNSTILE_SITE_KEY;
		}
		if ( defined( 'AYECODE_TURNSTILE_SECRET_KEY' ) ) {
			$defaults['secret_key'] = AYECODE_TURNSTILE_SECRET_KEY;
		}

		$saved_options['protections'] = ! empty( $saved_options['protections'] ) ? wp_parse_args( $saved_options['protections'], $defaults['protections'] ) : $defaults['protections'];

		// Merge saved options with defaults
		$this->options = wp_parse_args( $saved_options, $defaults );

		$this->init_hooks();
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function init_hooks() {
		// Only initialize if we have valid keys
		if ( $this->get_site_key() && $this->get_secret_key() && ! $this->check_role_disabled() ) {

			// WP Login protection
			if ( ! empty( $this->options['protections']['login'] ) ) {
				add_action( 'login_form', array( $this, 'add_turnstile_widget' ) );
				add_filter( 'authenticate', array( $this, 'verify_login' ), 99, 3 );
			}


			// WP forgot password
			if ( ! empty( $this->options['protections']['forgot_password'] ) ) {
				add_action( 'lostpassword_form', array( $this, 'add_turnstile_widget' ) );
				add_action( 'lostpassword_post', array( $this, 'verify_lost_password' ) );
			}

			// WP Registration protection
			if ( ! empty( $this->options['protections']['register'] ) ) {
				add_action( 'register_form', array( $this, 'add_turnstile_widget' ) );
				add_filter( 'registration_errors', array( $this, 'verify_registration' ), 99, 3 );
			}

			// Comments protection @todo this should show just before the submit button, maybe via JS
			if ( ! empty( $this->options['protections']['comments'] ) ) {
				add_action( 'comment_form_submit_button', array( $this, 'add_turnstile_widget_comments' ), 10, 2 );
				add_action( 'pre_comment_on_post', array( $this, 'verify_comment' ) );
			}


			// GD Add listing, we only check new listings not updates
			if ( ! empty( $this->options['protections']['gd_add_listing'] ) ) {
				$post_info = null;
				if ( isset( $_REQUEST['pid'] ) && $_REQUEST['pid'] != '' ) {
					$post_id   = $_REQUEST['pid'];
					$post_info = get_post( $post_id );
				}

				if ( empty( $post_info ) ) {
					add_action( 'geodir_after_main_form_fields', array(
						$this,
						'add_gd_add_listing_turnstile_widget_wrap'
					), 0 );
				}
				add_filter( 'geodir_validate_ajax_save_post_data', array( $this, 'verify_add_listing' ), 5, 3 );
			}

			// GD Report Post geodir_report_post_form_after_fields
			if ( ! empty( $this->options['protections']['gd_report_post'] ) ) {
				add_action( 'geodir_report_post_form_after_fields', array( $this, 'add_turnstile_widget' ) );
				add_action( 'geodir_report_post_validate_data', array( $this, 'verify_report_post' ), 10, 3 );
			}

			// GD Claim Listing standard form
			if ( ! empty( $this->options['protections']['gd_claim_listing'] ) ) {
				add_action( 'geodir_claim_post_form_after_fields', array( $this, 'add_turnstile_widget' ) );
				add_filter( 'geodir_validate_ajax_claim_listing_data', array(
					$this,
					'claim_listing_form_check'
				), 10, 2 );
			}


			// UWP Forms
			add_action( 'uwp_template_fields', array( $this, 'add_turnstile_uwp_forms' ), 10, 1 );
			add_filter( 'uwp_validate_result', array( $this, 'verify_uwp' ), 10, 3 );

			// UWP Frontend Post Addon
			if ( ! empty( $this->options['protections']['uwp_frontend'] ) ) {
				add_action( 'uwp_frontend_post_after_form_fields', array(
					$this,
					'add_turnstile_widget'
				), 10, 3 ); // frontend post addon
				add_action( 'wp_ajax_uwp_fep_post_submit', array( $this, 'verify_uwp_frontend_post' ), 5 );
				add_action( 'wp_ajax_nopriv_uwp_fep_post_submit', array( $this, 'verify_uwp_frontend_post' ), 5 );
			}


			// BlockStrap Contact Form
			if ( ! empty( $this->options['protections']['bs_contact'] ) ) {
				add_filter( 'getpaid_before_payment_form_pay_button', array( $this, 'add_turnstile_widget' ), 10, 2 );
				add_action( 'getpaid_checkout_error_checks', array( $this, 'verify_getpaid_checkout_form' ), 10, 2 );

				// Remove Google reCaptcha checks
				remove_action( 'getpaid_before_payment_form_pay_button', 'getpaid_display_recaptcha_before_payment_button' );
				remove_action( 'getpaid_checkout_error_checks', 'getpaid_validate_recaptcha_response' );
			}


			// GetPaid CCheckout Form
			if ( ! empty( $this->options['protections']['gp_checkout'] ) ) {
				add_filter( 'blockstrap_blocks_contact_form_captcha_input', array(
					$this,
					'blockstrap_blocks_contact_form_captcha_input'
				), 10, 2 );
				add_action( 'blockstrap_blocks_contact_form_captcha_valid', array(
					$this,
					'verify_blockstrap_contact_form'
				), 10, 2 );
			}

			// Add lazy loading script
			if ( is_admin() ) {
				add_action( 'admin_footer', array( $this, 'add_lazy_load_script' ) ); //@todo do we need this?
			} elseif ( in_array( $GLOBALS['pagenow'], array(
				'wp-login.php',
				'wp-register.php'
			) ) ) { // @todo test on sub domain install
				add_action( 'login_footer', array( $this, 'add_lazy_load_script' ) );
				add_action( 'login_footer', array( $this, 'adjust_login_form_size_css' ) );
			} else {
				add_action( 'wp_footer', array( $this, 'add_lazy_load_script' ) );
			}


		}
	}

	/**
     * Add the turnstile widget above the comments form submit button.
     *
	 * @param $submit_button
	 * @param $args
	 *
	 * @return string
	 */
    public function add_turnstile_widget_comments( $submit_button, $args ) {

	    ob_start();
	    $this->add_turnstile_widget();
	    $html = ob_get_clean();

        return  $html . $submit_button;
    }

	/**
     * Add the turnstile widget to the BlockStrap Contact Form.
     *
	 * @param $html
	 * @param $args
	 *
	 * @return false|mixed|string
	 */
	public function blockstrap_blocks_contact_form_captcha_input( $html, $args ) {

		// If captcha is not disabled then show it
		if ( empty( $args['field_recaptcha'] ) ) {
			ob_start();
			$this->add_turnstile_widget();
			$html = ob_get_clean();
		}

		return $html;
	}

	/**
	 * Checks if the current user's role is disabled based on the provided configuration.
	 *
	 * The function evaluates the roles specified in the `disable_roles` option and compares them
	 * against the current user's role. If the user's role is marked as disabled, the function returns true.
	 *
	 * @return bool Returns true if the user's role is disabled; otherwise, returns false.
	 */
	function check_role_disabled() {
		if ( ! is_user_logged_in() ) { // visitors
			return false;
		}

		if ( ! empty( $this->options['disable_roles'] ) ) {
			global $current_user;
			$role = ! empty( $current_user ) && isset( $current_user->roles[0] ) ? $current_user->roles[0] : '';

			if ( $role != '' && in_array( $role, $this->options['disable_roles'] ) ) { // disable captcha
				return true;
			}
		}

		return false;
	}

	/**
	 * Adds the Turnstile widget to UsersWP (UWP) forms based on the specified form type.
	 *
	 * This function checks the provided form type against the configured protections for various UWP forms
	 * (login, register, forgot password, and account). If the form type is enabled in the options, it adds the
	 * Turnstile widget to the respective form.
	 *
	 * @param string $type The type of UWP form (e.g., 'login', 'register', 'forgot', 'account').
	 *
	 * @return void
	 */
	public function add_turnstile_uwp_forms( $type ) {
		if ( 'login' === $type && ! empty( $this->options['protections']['uwp_login'] ) ) {
			$this->add_turnstile_widget();
		}

		if ( 'register' === $type && ! empty( $this->options['protections']['uwp_register'] ) ) {
			$this->add_turnstile_widget();
		}

		if ( 'forgot' === $type && ! empty( $this->options['protections']['uwp_forgot'] ) ) {
			$this->add_turnstile_widget();
		}

		if ( 'account' === $type && ! empty( $this->options['protections']['uwp_account'] ) ) {
			$this->add_turnstile_widget();
		}

		if ( 'frontend' === $type && ! empty( $this->options['protections']['uwp_frontend'] ) ) {
			$this->add_turnstile_widget();
		}
	}


	/**
	 * Add some CSS for the login form sizing.
	 *
	 * @return void
	 */
	public function adjust_login_form_size_css() {
		?>
        <style>
            #login {
                min-width: 350px;
            }
        </style>
		<?php
	}

	/**
	 * Retrieves the site key for the configuration.
	 *
	 * This function checks if a predefined constant `AYECODE_TURNSTILE_SITE_KEY` is available and returns its value.
	 * If the constant is not defined, it retrieves the site key from the options, sanitizes it, and returns the sanitized value.
	 *
	 * @return string The site key if defined, or an empty string if not available.
	 */
	private function get_site_key() {
		if ( defined( 'AYECODE_TURNSTILE_SITE_KEY' ) ) {
			return AYECODE_TURNSTILE_SITE_KEY;
		}

		return isset( $this->options['site_key'] ) ? sanitize_text_field( $this->options['site_key'] ) : '';
	}

	/**
	 * Retrieves the secret key used for authentication or configuration.
	 *
	 * This function checks if a constant (`AYECODE_TURNSTILE_SECRET_KEY`) is defined and returns its value if available.
	 * If the constant is not defined, it attempts to retrieve the secret key from the `options` array, sanitizing the value.
	 *
	 * @return string Returns the secret key if available; otherwise, returns an empty string.
	 */
	private function get_secret_key() {
		if ( defined( 'AYECODE_TURNSTILE_SECRET_KEY' ) ) {
			return AYECODE_TURNSTILE_SECRET_KEY;
		}

		return isset( $this->options['secret_key'] ) ? sanitize_text_field( $this->options['secret_key'] ) : '';
	}

	/**
	 * Adds a wrapper structure for the Turnstile widget in the GeoDirectory add listing form.
	 *
	 * The method outputs HTML to render a row structure with a label and a container
	 * for the Turnstile widget, ensuring it integrates with the form layout.
	 *
	 * @return void Outputs the HTML for the widget wrapper directly.
	 */
	public function add_gd_add_listing_turnstile_widget_wrap() {
		?>
        <div class="geodir_form_row clear_both mb-3 row">
            <label class="  col-sm-2 col-form-label"></label>
            <div class="col-sm-10">
				<?php
				$this->add_turnstile_widget();
				?>
            </div>
        </div>
		<?php
	}

	/**
	 * Outputs the HTML markup for a Turnstile widget including configuration attributes and initialization script.
	 *
	 * This method generates a Turnstile widget placeholder with attributes such as site key, theme, and size,
	 * which are configured by the current instance options. If triggered via an AJAX request, the method
	 * includes a script to initialize the widget placeholders dynamically.
	 *
	 * @return void This method does not return a value; it directly outputs the widget's HTML and JavaScript.
	 */
	public function add_turnstile_widget() {
		$doing_ajax = wp_doing_ajax() ? 'ajax-' : '';
		$id         = 'cf-turnstile-' . esc_attr( $doing_ajax ) . absint( $this->widget_count ++ );
		?>
        <div class="ayecode-turnstile-placeholder mb-2"
             data-sitekey="<?php echo esc_attr( $this->get_site_key() ); ?>"
             data-theme="<?php echo esc_attr( isset( $this->options['theme'] ) ? sanitize_text_field( $this->options['theme'] ) : 'light' ); ?>"
             data-size="<?php echo esc_attr( isset( $this->options['size'] ) ? sanitize_text_field( $this->options['size'] ) : 'normal' ); ?>"
             id="<?php echo esc_attr( $id ); ?>">
        </div>
		<?php

		// initialise it if called via AJAX
		if ( wp_doing_ajax() ) {
			?>
            <script>
                (function () {
                    window.initializeTurnstilePlaceholders();
                })();
            </script>
			<?php
		}
	}

	/**
	 * Outputs a JavaScript script responsible for lazy loading and rendering Cloudflare Turnstile placeholders.
	 *
	 * The method embeds a script that initializes functionality to defer the loading of the Turnstile API script
	 * until placeholders become visible in the viewport. It manages loading the Turnstile API, rendering placeholders,
	 * and handling script loading states through a queued approach for effective lazy loading.
	 *
	 * @return void This method does not return a value and directly outputs the script to the page.
	 */
	public function add_lazy_load_script() {
		?>
        <script>
            (function () {
                window.ayecodeTurnstileLoaded = false;
                window.ayecodeTurnstileQueue = [];

                function loadTurnstileScript() {
                    var script = document.createElement('script');
                    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onAyeCodeTurnstileLoad';
                    script.async = true;
                    document.head.appendChild(script);
                }

                window.onAyeCodeTurnstileLoad = function () {
                    window.ayecodeTurnstileLoaded = true;
                    // Render queued placeholders
                    window.ayecodeTurnstileQueue.forEach(function (placeholder) {
                        turnstile.render(placeholder, {
                            sitekey: placeholder.getAttribute('data-sitekey'),
                            theme: placeholder.getAttribute('data-theme'),
                            size: placeholder.getAttribute('data-size'),
                        });
                    });
                    window.ayecodeTurnstileQueue = [];
                };

                window.initializeTurnstilePlaceholders = function () { // Attach to window object
                    var placeholders = document.querySelectorAll('.ayecode-turnstile-placeholder:not([data-initialized])');
                    placeholders.forEach(function (placeholder) {
                        placeholder.setAttribute('data-initialized', 'true');
                        var observer = new IntersectionObserver(function (entries) {
                            if (entries[0].isIntersecting) {
                                observer.unobserve(placeholder);

                                if (window.ayecodeTurnstileLoaded) {
                                    // Already loaded, render immediately
                                    turnstile.render(placeholder, {
                                        sitekey: placeholder.getAttribute('data-sitekey'),
                                        theme: placeholder.getAttribute('data-theme'),
                                        size: placeholder.getAttribute('data-size'),
                                    });
                                } else {
                                    // Not loaded yet, queue it
                                    window.ayecodeTurnstileQueue.push(placeholder);
                                    // Load the script if this is the first
                                    if (window.ayecodeTurnstileQueue.length === 1) {
                                        loadTurnstileScript();
                                    }
                                }
                            }
                        }, {threshold: 0.1});

                        observer.observe(placeholder);
                    });
                };

                function resetAllTurnstiles() {
                    var placeholders = document.querySelectorAll('.ayecode-turnstile-placeholder[data-initialized]');
                    placeholders.forEach(function (placeholder) {
                        try {
                            // Attempt to reset the Turnstile widget
                            turnstile.reset(placeholder);
                        } catch (error) {
                            console.warn(`[Turnstile Reset Warning]: ${error.message}`);
                        }
                    });
                }


                // Listen for custom event to reset and reinitialize Turnstiles
                document.addEventListener('ayecode_reset_captcha', function () {
                    resetAllTurnstiles();
                    initializeTurnstilePlaceholders();
                });


                // Initialize on page load
                document.addEventListener('DOMContentLoaded', window.initializeTurnstilePlaceholders);
            })();
        </script>


		<?php
	}

	/**
	 * Verify the cloudflare turnstile response.
	 *
	 * @param $context
	 *
	 * @return true|WP_Error
	 */
	private function verify_turnstile( $context = '' ) {
		if ( ! isset( $_POST['cf-turnstile-response'] ) ) {
			return new WP_Error(
				'turnstile_missing',
				__( 'Security verification missing.', 'ayecode-connect' )
			);
		}

		$token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) );

		if ( empty( $token ) ) {
			return new WP_Error(
				'turnstile_empty',
				__( 'Security verification failed.', 'ayecode-connect' )
			);
		}

		$secret_key = $this->get_secret_key();
		if ( empty( $secret_key ) ) {
			return new WP_Error(
				'turnstile_config',
				__( 'Turnstile configuration error.', 'ayecode-connect' )
			);
		}

		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
			'body' => array(
				'secret'   => $secret_key,
				'response' => $token,
				'remoteip' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			)
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'turnstile_error',
				__( 'Security verification service unavailable.', 'ayecode-connect' )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );


		if ( empty( $body['success'] ) ) {
			return new WP_Error(
				'turnstile_failed',
				__( 'Security verification failed.', 'ayecode-connect' )
			);
		}

		return true;
	}

	/**
	 * Verify WP login.
	 *
	 * @param $user
	 * @param $username
	 * @param $password
	 *
	 * @return mixed|true|WP_Error
	 */
	public function verify_login( $user, $username, $password ) {
		if ( ! empty( $username ) && ! empty( $password ) ) {
			$verify = $this->verify_turnstile( 'login' );
			if ( is_wp_error( $verify ) ) {
				return $verify;
			}
		}

		return $user;
	}

	/**
	 * Verify the WP registration.
	 *
	 * @param $errors
	 * @param $sanitized_user_login
	 * @param $user_email
	 *
	 * @return mixed
	 */
	public function verify_registration( $errors, $sanitized_user_login, $user_email ) {
		$verify = $this->verify_turnstile( 'register' );
		if ( is_wp_error( $verify ) ) {
			$errors->add( $verify->get_error_code(), $verify->get_error_message() );
		}

		return $errors;
	}

	/**
	 * Verify the WP forgot password form.
	 *
	 * @return void
	 */
	public function verify_lost_password() {
		$verify = $this->verify_turnstile( 'lostpassword' );
		if ( is_wp_error( $verify ) ) {
			wp_die( $verify->get_error_message(),
				__( 'Password Reset Failed', 'ayecode-connect' ),
				array( 'response' => 403, 'back_link' => true ) );
		}
	}

	/**
	 * Verify the WP comment form submission.
	 *
	 * @param $comment_post_ID
	 *
	 * @return void
	 */
	public function verify_comment( $comment_post_ID ) {
		$verify = $this->verify_turnstile( 'comment' );
		if ( is_wp_error( $verify ) ) {
			wp_die(
				esc_html( $verify->get_error_message() ),
				esc_html__( 'Comment Submission Failed', 'ayecode-connect' ),
				array( 'response' => 403, 'back_link' => true )
			);
		}
	}

	/**
	 * Check captcha for add listing form
	 *
	 * @param $valid
	 * @param $post_data
	 * @param bool $update
	 *
	 * @return string|void
	 *
	 */
	public function verify_add_listing( $valid, $post_data, $update = false ) {

		if ( $valid ) { // no point checking if its already invalid
			if ( isset( $post_data['post_title'] ) && isset( $post_data['post_type'] ) && ! $update ) {
				$verify = $this->verify_turnstile( 'gd_add_listing' );
				if ( is_wp_error( $verify ) ) {
					$valid = $verify;
				}
			}
		}

		return $valid;

	}

	/**
	 * Check captcha for add listing form
	 *
	 * @param $valid
	 * @param $post_data
	 * @param bool $update
	 *
	 * @return string|void
	 */
	public function claim_listing_form_check( $valid, $post_data ) {

		if ( ! is_wp_error( $valid ) ) { // no point checking if its already invalid
			$verify = $this->verify_turnstile( 'gd_claim_listing' );
			if ( is_wp_error( $verify ) ) {
				$valid = $verify;
			}
		}

		return $valid;

	}

	/**
	 * Verifies the UsersWP (UWP) forms for the turnstile response.
	 *
	 * @param mixed $result The initial result that will be modified or returned based on verification.
	 * @param string $type The type of UWP action being processed (e.g., register, login, etc.).
	 * @param array $data An array of data associated with the UWP action, including nonces and other metadata.
	 *
	 * @return mixed Returns the modified result after verification or the initial result if conditions are not met.
	 */
	public function verify_uwp( $result, $type, $data ) {

		if ( empty( $type ) && ! isset( $data[ 'uwp_' . $type . '_nonce' ] ) ) {
			return $result;
		}

		if ( empty( $this->options['protections'][ 'uwp_' . $type ] ) || is_wp_error( $result ) ) {
			return $result;
		}


		if ( $type ) {
			switch ( $type ) {
				case 'register':
				case 'login':
				case 'forgot':
				case 'account':
				case 'frontend':

					// remove the WP check so we don't double-check
					if ( 'login' === $type ) {
						remove_filter( 'authenticate', array( $this, 'verify_login' ), 99 );
					}

					$verify = $this->verify_turnstile( 'uwp_' . $type );
					if ( is_wp_error( $verify ) ) {
						$result = $verify;
					}

					break;
			}
		}

		return $result;
	}

	/**
	 * Validate the turnstile response for the UWP forntend post addon.
	 *
	 * @param $valid
	 * @param $post_data
	 *
	 */
	public function verify_uwp_frontend_post() {

		$verify = $this->verify_turnstile( 'uwp_fontend_post' );
		if ( is_wp_error( $verify ) ) {
			$message = aui()->alert( array(
					'type'    => 'error',
					'content' => $verify->get_error_message()
				)
			);
			wp_send_json_error( $message );
		}

	}

	/**
	 * Validate the turnstile response for the BlockStrap Contact Form.
	 *
	 * @param $valid
	 * @param $args
	 *
	 * @return mixed|true|WP_Error
	 */
	public function verify_blockstrap_contact_form( $valid, $args ) {

		// Value is not set in post but in the form data so we must set it for the validation function
		$_POST['cf-turnstile-response'] = esc_attr( $args['cf-turnstile-response'] );

		$verify = $this->verify_turnstile( 'blockstrap_contact_form' );
		if ( is_wp_error( $verify ) ) {
			$valid = $verify;
		} elseif ( $verify ) {
			$valid = true;
		}

		return $valid;

	}

	/**
	 * Check captcha for GD Report Post form.
	 *
	 * @param $data
	 * @param $request
	 * @param $gd_post
	 *
	 * @return mixed|true|WP_Error
	 */
	public function verify_report_post( $data, $request, $gd_post ) {

		if ( ! is_wp_error( $data ) ) { // no point checking if its already invalid
			$verify = $this->verify_turnstile( 'gd_report_post' );
			if ( is_wp_error( $verify ) ) {
				$data = $verify;
			}

		}

		return $data;

	}

	/**
	 * Check captcha for GetPaid Checkout.
	 *
	 * @param $submission
	 *
	 * @return void
	 */
	public function verify_getpaid_checkout_form( $submission ) {

		// Value is not set in post but in the form data so we must set it for the validation function
		$token                          = $submission->get_field( 'cf-turnstile-response' );
		$_POST['cf-turnstile-response'] = esc_attr( $token );

		$verify = $this->verify_turnstile( 'uwp_fontend_post' );
		if ( is_wp_error( $verify ) ) {
			wp_send_json_error( $verify->get_error_message() );
		}

	}
}

// run
AyeCode_Connect_Turnstile::instance();