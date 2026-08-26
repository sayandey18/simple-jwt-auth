<?php

/* Prevent direct access to this file. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provide a admin area view for the plugin.
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://github.com/sayandey18
 * @since      1.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth/admin/partials
 */
?>

<div class="simplejwt-navbar">
	<div class="simplejwt-navbar-wrapper">
		<div class="simplejwt-menu-area">
			<ul class="simplejwt-menu-wrapper">
				<li class="simplejwt-menu-items">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-jwt-auth' ) ); ?>">
						<?php esc_html_e( 'JWT Settings', 'simple-jwt-auth' ); ?>
					</a>
				</li>
				<li class="simplejwt-menu-items">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-jwt-auth-options' ) ); ?>">
						<?php esc_html_e( 'Options', 'simple-jwt-auth' ); ?>
					</a>
				</li>
				<li class="simplejwt-menu-items">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-jwt-auth-docs' ) ); ?>">
						<?php esc_html_e( 'Documentation', 'simple-jwt-auth' ); ?>
					</a>
				</li>
			</ul>
		</div>
		<div class="simplejwt-logo-area">
			<img width="144px" height="36px" src="<?php echo esc_attr( plugin_dir_url( __FILE__ ) . '../img/jwt-auth.svg' ); ?>" alt="jwt" />
		</div>
	</div>
</div>

<div class="simplejwt-section">
	<div class="simplejwt-container">
		<?php
		/**
		 * Trigger the display of custom admin notices.
		 *
		 * @since   1.0.0
		 */
		do_action( 'simplejwt_admin_alert' );
		?>

		<div class="simplejwt-container-items">
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="simplejwt_options">
				<div class="simplejwt-site-control">
					<?php $simplejwt_nonce = wp_create_nonce( 'simplejwt_nonce' ); ?>
					<input type="hidden" name="action" value="simplejwt_options_action">
					<input type="hidden" name="simplejwt_nonce" value="<?php echo esc_attr( $simplejwt_nonce ); ?>" />
					<div class="simplejwt-item-card">
						<h2><?php esc_html_e( 'Security', 'simple-jwt-auth' ); ?></h2>
						<div class="simplejwt-card-header">
							<div class="simplejwt-stack-heading">
								<h3><?php esc_html_e( 'Disable XML-RPC', 'simple-jwt-auth' ); ?></h3>
								<p class="simplejwt-body-desc simplejwt-mt-10"><?php esc_html_e( 'XML-RPC allows apps to connect to your WordPress site, but might expose your site\'s security. Disable this feature if you don\'t need it.', 'simple-jwt-auth' ); ?></p>
							</div>
							<div class="simplejwt-action-area">
								<div class="simplejwt-checkbox-wrapper">
									<input type="checkbox" class="simplejwt-checkbox-btn" id="simplejwt_disable_xmlrpc" name="simplejwt_disable_xmlrpc" <?php checked( isset( $config['disable_xmlrpc'] ) && $config['disable_xmlrpc'] == '1', true ); ?> />
								</div>
							</div>
						</div>
					</div>
					<div class="simplejwt-item-card">
						<h2><?php esc_html_e( 'Deleting Data', 'simple-jwt-auth' ); ?></h2>
						<div class="simplejwt-card-header">
							<div class="simplejwt-stack-heading">
								<h3><?php esc_html_e( 'Remove configs data', 'simple-jwt-auth' ); ?></h3>
								<p class="simplejwt-body-desc simplejwt-mt-10"><?php esc_html_e( 'Checking this box will permanently delete all JWT authentication tables and data while uninstalling, which is irreversible and cannot be recovered.', 'simple-jwt-auth' ); ?></p>
							</div>
							<div class="simplejwt-action-area">
								<div class="simplejwt-checkbox-wrapper">
									<input type="checkbox" class="simplejwt-checkbox-btn" id="simplejwt_drop_configs" name="simplejwt_drop_configs" <?php checked( get_option( 'simplejwt_drop_configs' ) == '1', true ); ?> />
								</div>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>
