<?php

/* Prevent direct access to this file. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provide the Documentation admin area view for the plugin.
 *
 * @link       https://github.com/sayandey18
 * @since      2.0.0
 *
 * @package    Simple_Jwt_Auth
 * @subpackage Simple_Jwt_Auth/admin/partials
 */

$postman_url = add_query_arg(
	array(
		'action' => 'simplejwt_postman_collection',
		'nonce'  => wp_create_nonce( 'simplejwt_postman_collection' ),
	),
	admin_url( 'admin-post.php' )
);
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
			<div class="simplejwt-site-control">
				<div class="simplejwt-item-card">
					<h2><?php esc_html_e( 'Setup', 'simple-jwt-auth' ); ?></h2>
					<div class="simplejwt-card-body">
						<p class="simplejwt-body-desc simplejwt-mt-10"><?php esc_html_e( '1. Add the key-encryption-key to your wp-config.php file (must be exactly 32 characters).', 'simple-jwt-auth' ); ?></p>
						<pre class="simplejwt-code-block"><code>define( 'SIMPLE_JWT_AUTH_ENCRYPT_KEY', 'your-32-char-encryption-key' );</code></pre>

						<p class="simplejwt-body-desc simplejwt-mt-10"><?php esc_html_e( '2. (Optional) Define the signing algorithm and keys as constants to override the values stored in the plugin settings.', 'simple-jwt-auth' ); ?></p>
						<pre class="simplejwt-code-block"><code>define( 'SIMPLE_JWT_AUTH_ALGORITHM', 'HS256' );
define( 'SIMPLE_JWT_AUTH_SECRET_KEY', 'your-secret-key' );
define( 'SIMPLE_JWT_AUTH_PRIVATE_KEY', '-----BEGIN PRIVATE KEY-----...' );
define( 'SIMPLE_JWT_AUTH_PUBLIC_KEY', '-----BEGIN PUBLIC KEY-----...' );</code></pre>

						<p class="simplejwt-body-desc simplejwt-mt-10"><?php esc_html_e( '3. If the Authorization header is not available on your host, add the following to your .htaccess file.', 'simple-jwt-auth' ); ?></p>
						<pre class="simplejwt-code-block"><code>RewriteEngine on
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]</code></pre>
					</div>
				</div>

				<div class="simplejwt-item-card">
					<h2><?php esc_html_e( 'API Endpoints', 'simple-jwt-auth' ); ?></h2>
					<div class="simplejwt-card-body">
						<table class="simplejwt-docs-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Endpoint', 'simple-jwt-auth' ); ?></th>
									<th><?php esc_html_e( 'Method', 'simple-jwt-auth' ); ?></th>
									<th><?php esc_html_e( 'Description', 'simple-jwt-auth' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><code>/token</code></td>
									<td><code>POST</code></td>
									<td><?php esc_html_e( 'Generate an access and refresh token.', 'simple-jwt-auth' ); ?></td>
								</tr>
								<tr>
									<td><code>/token/refresh</code></td>
									<td><code>POST</code></td>
									<td><?php esc_html_e( 'Rotate an access and refresh token.', 'simple-jwt-auth' ); ?></td>
								</tr>
								<tr>
									<td><code>/token/revoke</code></td>
									<td><code>POST</code></td>
									<td><?php esc_html_e( 'Revoke a refresh token.', 'simple-jwt-auth' ); ?></td>
								</tr>
								<tr>
									<td><code>/token/validate</code></td>
									<td><code>POST</code></td>
									<td><?php esc_html_e( 'Validate an access token.', 'simple-jwt-auth' ); ?></td>
								</tr>
								<tr>
									<td><code>/me</code></td>
									<td><code>GET</code></td>
									<td><?php esc_html_e( 'Return the authenticated user.', 'simple-jwt-auth' ); ?></td>
								</tr>
							</tbody>
						</table>

						<p class="simplejwt-body-desc simplejwt-mt-20"><?php esc_html_e( 'Generate a token by sending your WordPress credentials.', 'simple-jwt-auth' ); ?></p>
						<pre class="simplejwt-code-block"><code>curl -X POST "<?php echo esc_url( $this->simplejwt_public_endpoints( 'token' ) ); ?>" \
	-H "Content-Type: application/json" \
	-d '{"username": "your_username", "password": "your_password"}'</code></pre>

						<p class="simplejwt-body-desc simplejwt-mt-20"><?php esc_html_e( 'Then pass the token as a Bearer header to every protected request.', 'simple-jwt-auth' ); ?></p>
						<pre class="simplejwt-code-block"><code>curl -X GET "<?php echo esc_url( $this->simplejwt_public_endpoints( 'me' ) ); ?>" \
	-H "Authorization: Bearer YOUR_ACCESS_TOKEN"</code></pre>

						<div class="simplejwt-mt-20">
							<a class="simplejwt-submit-btn" href="<?php echo esc_url( $postman_url ); ?>"><?php esc_html_e( 'Download Postman Collection', 'simple-jwt-auth' ); ?></a>
							<p class="simplejwt-body-desc simplejwt-mt-10"><?php esc_html_e( 'Import the downloaded JSON into Postman (Import → Upload files or Link) to try the endpoints with your site URL preconfigured.', 'simple-jwt-auth' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
