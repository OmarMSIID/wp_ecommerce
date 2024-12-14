<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle Cloudflare Turnstile settings for AyeCode Connect.
 */
class AyeCode_Connect_Turnstile_Settings {
	private static $instance = null;

	/**
	 * Settings array.
	 */
	private $settings = array(
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

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_item' ), 11 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Get instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add submenu item.
	 */
	public function add_menu_item() {
		add_submenu_page(
			'ayecode-connect',
			__( 'Turnstile Settings', 'ayecode-connect' ),
			__( 'Turnstile Captcha', 'ayecode-connect' ),
			'manage_options',
			'ayecode-turnstile',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'ayecode_turnstile_settings', 'ayecode_turnstile_options' );
	}

	/**
	 * Settings page content.
	 */
	public function settings_page() {
		$options             = get_option( 'ayecode_turnstile_options', $this->settings );
		$site_key_constant   = defined( 'AYECODE_TURNSTILE_SITE_KEY' );
		$secret_key_constant = defined( 'AYECODE_TURNSTILE_SECRET_KEY' );
		?>
        <div class="bsui" style="margin-left: -20px;">
            <!-- Clean & Mean UI -->
            <style>
                #wpbody-content > div.notice,
                #wpbody-content > div.error {
                    display: none;
                }
            </style>
            <!-- Header -->
            <nav class="navbar bg-white border-bottom">
                <a class="navbar-brand p-0" href="#">
                    <img src="<?php echo plugins_url( 'assets/img/ayecode.png', dirname( __FILE__ ) ); ?>" width="120"
                         alt="AyeCode Ltd">
                </a>
            </nav>

            <div class="container-fluid py-4">
                <div class="row">
                    <div class="col-12 col-md-8 mx-auto">
                        <div class="card shadow-sm mw-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h2 class="h5 mb-0"><i class="fab fa-cloudflare text-orange"></i> <?php _e( 'Cloudflare Turnstile Settings', 'ayecode-connect' ); ?></h2>
                                <div class=""><i class="fas fa-book"></i> <a href="https://docs.wpgeodirectory.com/article/781-how-to-setup-cloudflare-turnstile-captcha" target="_blank"><?php _e( 'Documentation', 'ayecode-connect' ); ?></a> </div>
                            </div>
                            <div class="card-body">
								<?php

                                // settings saved
								if ( ! empty( $_REQUEST['settings-updated'] ) ) {
									echo aui()->alert( array(
											'type'    => 'success',
											'content' => __( "Settings Saved", "ayecode-connect" )
										)
									);
								}

								if ( $site_key_constant || $secret_key_constant ) {
									echo aui()->alert( array(
											'type'    => 'info',
											'content' => __( "Some settings are defined in wp-config.php and cannot be modified here.", "ayecode-connect" )
										)
									);
								}

								if ( defined( 'UWP_RECAPTCHA_VERSION' ) ) {
									echo aui()->alert( array(
											'type'    => 'danger',
											'content' => __( "Please disable UsersWP reCaptch plugin. This plugin replaces the need for it.", "ayecode-connect" )
										)
									);
								}
								?>

                                <form method="post" action="options.php">
									<?php settings_fields( 'ayecode_turnstile_settings' ); ?>

                                    <!-- API Keys -->
                                    <div class="mb-4">
                                        <h3 class="h6"><?php _e( 'API Keys', 'ayecode-connect' ); ?></h3>
										<?php
										echo aui()->input(
											array(
												'id'               => 'site_key',
												'name'             => 'ayecode_turnstile_options[site_key]',
												'label_type'       => 'horizontal',
												'label'            => __( 'Site Key', 'ayecode-connect' ),
												'label_col'        => '4',
												'type'             => 'text',
												'value'            => esc_attr( $site_key_constant ? constant( 'AYECODE_TURNSTILE_SITE_KEY' ) : $options['site_key'] ),
												'extra_attributes' => $site_key_constant ? [ 'disabled' => 'disabled' ] : array()
											)
										);

										echo aui()->input(
											array(
												'id'               => 'secret_key',
												'name'             => 'ayecode_turnstile_options[secret_key]',
												'label_type'       => 'horizontal',
												'label'            => __( 'Secret Key', 'ayecode-connect' ),
												'label_col'        => '4',
												'type'             => 'password',
												'value'            => esc_attr( $secret_key_constant ? constant( 'AYECODE_TURNSTILE_SECRET_KEY' ) : $options['secret_key'] ),
												'extra_attributes' => $secret_key_constant ? [ 'disabled' => 'disabled' ] : array()
											)
										);
										?>
                                    </div>

                                    <!-- Appearance -->
                                    <div class="mb-4">
                                        <h3 class="h6"><?php _e( 'Appearance', 'ayecode-connect' ); ?></h3>

										<?php
										echo aui()->select(
											array(
												'id'         => "theme",
												'name'       => "ayecode_turnstile_options[theme]",
												'label_type' => 'horizontal',
												'label_col'  => '4',
												'multiple'   => false,
												'class'      => 'mw-100',
												'options'    => array(
													'light' => __( 'Light', 'ayecode-connect' ),
													'dark'  => __( 'Dark', 'ayecode-connect' ),
													'auto'  => __( 'Auto', 'ayecode-connect' ),
												),
												'label'      => __( 'Theme', 'ayecode-connect' ),
												'value'      => $options['theme'],
											)
										);

										echo aui()->select(
											array(
												'id'         => "size",
												'name'       => "ayecode_turnstile_options[size]",
												'label_type' => 'horizontal',
												'label_col'  => '4',
												'multiple'   => false,
												'class'      => 'mw-100',
												'options'    => array(
													'normal'   => __( 'Normal', 'ayecode-connect' ),
													'compact'  => __( 'Compact', 'ayecode-connect' ),
													'flexible' => __( 'Flexible', 'ayecode-connect' ),
												),
												'label'      => __( 'Size', 'ayecode-connect' ),
												'value'      => $options['size'],
											)
										);
										?>
                                    </div>

                                    <!-- Integration Settings -->
                                    <div class="mb-4">
                                        <h3 class="h6"><?php _e( 'Enable Protection On', 'ayecode-connect' ); ?></h3>

										<?php

										$turnstile_protections = [
											'login'           => [
												'title'   => __( 'WordPress Login', 'ayecode-connect' ),
												'default' => true
											],
											'register'        => [
												'title'   => __( 'WordPress Registration', 'ayecode-connect' ),
												'default' => true
											],
											'forgot_password' => [
												'title'   => __( 'WordPress Forgot Password', 'ayecode-connect' ),
												'default' => true
											],
											'comments'        => [
												'title'   => __( 'Comments Form (includes GeoDirectory Reviews)', 'ayecode-connect' ),
												'default' => true
											]
										];


										// GeoDirectory
										if ( defined( 'GEODIRECTORY_VERSION' ) ) {
											$turnstile_protections['gd_add_listing'] = [
												'title'   => __( 'GeoDirectory Add Listing Form', 'ayecode-connect' ),
												'default' => true
											];

											$turnstile_protections['gd_report_post'] = [
												'title'   => __( 'GeoDirectory Report Post Form', 'ayecode-connect' ),
												'default' => true
											];
										}

										// GD Claim listing addon
										if ( defined( 'GEODIR_CLAIM_VERSION' ) ) {
											$turnstile_protections['gd_claim_listing'] = [
												'title'   => __( 'GeoDirectory Claim Listing Form (standard)', 'ayecode-connect' ),
												'default' => true
											];
										}

										if ( defined( 'USERSWP_VERSION' ) ) {
											$turnstile_protections['uwp_login']    = [
												'title'   => __( 'UsersWP Login', 'ayecode-connect' ),
												'default' => true
											];
											$turnstile_protections['uwp_register'] = [
												'title'   => __( 'UsersWP Registration', 'ayecode-connect' ),
												'default' => true
											];
											$turnstile_protections['uwp_forgot']   = [
												'title'   => __( 'UsersWP Forgot Password', 'ayecode-connect' ),
												'default' => true
											];
											$turnstile_protections['uwp_account']  = [
												'title'   => __( 'UsersWP Account', 'ayecode-connect' ),
												'default' => true
											];

										}


										// UWP Frontend Post Addon
										if ( defined( 'UWP_FRONTEND_POST_VERSION' ) ) {
											$turnstile_protections['uwp_frontend'] = [
												'title'   => __( 'UsersWP Frontend Post', 'ayecode-connect' ),
												'default' => true
											];
										}


										// BlockStrap Contact Form
										if ( defined( 'BLOCKSTRAP_BLOCKS_VERSION' ) ) {
											$turnstile_protections['bs_contact'] = [
												'title'   => __( 'BlockStrap Contact Form', 'ayecode-connect' ),
												'default' => true
											];
										}

										// GetPaid Checkout Form
										if ( defined( 'WPINV_VERSION' ) ) {
											$turnstile_protections['gp_checkout'] = [
												'title'   => __( 'GetPaid Checkout Form', 'ayecode-connect' ),
												'default' => true
											];
										}


										$turnstile_protections = apply_filters( 'ayecode_turnstile_protections', $turnstile_protections );


										if ( ! empty( $turnstile_protections ) ) {
											foreach ( $turnstile_protections as $protection_key => $protection_value ) {
												echo aui()->input(
													array(
														'id'               => $protection_key,
														'name'             => 'ayecode_turnstile_options[protections][' . $protection_key . ']',
														'label'            => $protection_value['title'],
														'label_type'       => 'horizontal',
														'label_col'        => '4',
														'type'             => 'checkbox',
														'label_force_left' => true,
														'checked'          => (bool) $protection_value['default'],
														'value'            => '1',
														'switch'           => 'md',
													)
												);
											}
										}

										?>

                                    </div>


                                    <!-- User Role Settings -->
                                    <div class="mb-4">
                                        <h3 class="h6"><?php _e( 'Disable for', 'ayecode-connect' ); ?></h3>

										<?php

										$roles        = get_editable_roles();
										$role_options = array();
										if ( count( $roles ) > 0 ) {
											foreach ( $roles as $role => $data ) {
												$role_options[ $role ] = $data['name'];
											}
										}

										echo aui()->select(
											array(
												'id'         => 'disable_roles',
												'name'       => 'ayecode_turnstile_options[disable_roles][]',
												'label_type' => 'horizontal',
												'label_col'  => '4',
												'multiple'   => true,
												'select2'    => true,
												'class'      => ' mw-100',
												'options'    => $role_options,
												'label'      => __( 'Disable for user roles', 'geodirectory' ),
												'value'      => $options['disable_roles'],
											)
										);
										?>

                                    </div>
                                    <button type="submit"
                                            class="btn btn-primary"><?php _e( 'Save Changes', 'ayecode-connect' ); ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
		<?php
	}
}

// Initialize the class
AyeCode_Connect_Turnstile_Settings::instance();