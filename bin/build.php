<?php
/**
 * Builds an optimized, production-ready plugin zip for Simple JWT Auth.
 *
 * Usage: composer run build   (or: php bin/build.php)
 *
 * The script builds in a temporary directory so the developer's local
 * `includes/vendor` (which contains dev tools) is left untouched:
 *   1. Copies the plugin source to a temp dir, honoring `.distignore` and
 *      always skipping `includes/vendor` and `dist`.
 *   2. Installs production dependencies only (`composer install --no-dev`).
 *   3. Verifies no dev-only packages leaked into the compiled vendor.
 *   4. Writes `dist/simple-jwt-auth.zip`.
 *
 * @package Simple_Jwt_Auth
 */

define( 'SIMPLE_JWT_BUILD_ROOT', dirname( __DIR__ ) );
define( 'SIMPLE_JWT_BUILD_DIST', SIMPLE_JWT_BUILD_ROOT . DIRECTORY_SEPARATOR . 'dist' );
define( 'SIMPLE_JWT_BUILD_SLUG', 'simple-jwt-auth' );

/**
 * Run a shell command, printing its output, and abort on failure.
 *
 * @param string $command The command to run.
 * @return void
 */
function simplejwt_build_run( $command ) {
	echo '+ ' . $command . PHP_EOL;
	passthru( $command, $exit_code );

	if ( 0 !== $exit_code ) {
		fwrite( STDERR, 'Command failed with exit code ' . $exit_code . PHP_EOL );
		exit( 1 );
	}
}

/**
 * Read and parse the `.distignore` file.
 *
 * @return array<string>
 */
function simplejwt_build_excludes() {
	$file = SIMPLE_JWT_BUILD_ROOT . DIRECTORY_SEPARATOR . '.distignore';

	if ( ! file_exists( $file ) ) {
		return array();
	}

	$lines   = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$exclude = array();

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			continue;
		}

		$exclude[] = trim( $line, '/\\' );
	}

	// Always skip the local (dev-polluted) vendor and the output dir.
	$exclude[] = 'includes/vendor';
	$exclude[] = 'dist';

	return $exclude;
}

/**
 * Whether a relative path is excluded.
 *
 * @param string        $rel_path Relative path (forward slashes).
 * @param array<string> $excludes Excluded names/prefixes.
 * @return bool
 */
function simplejwt_build_is_excluded( $rel_path, $excludes ) {
	$rel_path = trim( $rel_path, '/' );

	foreach ( $excludes as $pattern ) {
		if ( $rel_path === $pattern ) {
			return true;
		}

		if ( 0 === strpos( $rel_path, $pattern . '/' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Recursively copy a directory, skipping excluded paths.
 *
 * @param string        $src      Source directory.
 * @param string        $dst      Destination directory.
 * @param array<string> $excludes Excluded names/prefixes.
 * @return void
 */
function simplejwt_build_copy_dir( $src, $dst, $excludes ) {
	if ( ! is_dir( $dst ) && ! mkdir( $dst, 0755, true ) && ! is_dir( $dst ) ) {
		fwrite( STDERR, 'Unable to create directory: ' . $dst . PHP_EOL );
		exit( 1 );
	}

	$items = scandir( $src );

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}

		$src_path = $src . DIRECTORY_SEPARATOR . $item;
		$rel_path = str_replace( DIRECTORY_SEPARATOR, '/', substr( $src_path, strlen( SIMPLE_JWT_BUILD_ROOT ) + 1 ) );

		if ( simplejwt_build_is_excluded( $rel_path, $excludes ) ) {
			continue;
		}

		$dst_path = $dst . DIRECTORY_SEPARATOR . $item;

		if ( is_dir( $src_path ) ) {
			simplejwt_build_copy_dir( $src_path, $dst_path, $excludes );
		} else {
			copy( $src_path, $dst_path );
		}
	}
}

/**
 * Recursively remove a directory.
 *
 * @param string $dir Directory to remove.
 * @return void
 */
function simplejwt_build_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = scandir( $dir );

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}

		$path = $dir . DIRECTORY_SEPARATOR . $item;

		if ( is_dir( $path ) ) {
			simplejwt_build_rmdir( $path );
		} else {
			unlink( $path );
		}
	}

	rmdir( $dir );
}

$excludes = simplejwt_build_excludes();
$tmp      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . SIMPLE_JWT_BUILD_SLUG . '-build-' . uniqid();

/*
 * 1. Copy the plugin source (no vendor, no dev files) to a temp dir.
 */
simplejwt_build_copy_dir( SIMPLE_JWT_BUILD_ROOT, $tmp, $excludes );

/*
 * 2. Copy the lock file for a reproducible production install (it is excluded
 *    from the copy step via `.distignore`).
 */
$lock_src = SIMPLE_JWT_BUILD_ROOT . DIRECTORY_SEPARATOR . 'composer.lock';

if ( file_exists( $lock_src ) ) {
	copy( $lock_src, $tmp . DIRECTORY_SEPARATOR . 'composer.lock' );
}

/*
 * 3. Install production dependencies only, inside the temp dir.
 */
simplejwt_build_run(
	'composer install --no-dev --optimize-autoloader --no-interaction --no-progress --working-dir=' . escapeshellarg( $tmp )
);

/*
 * 4. Sanity checks on the compiled tree.
 */
$autoload = $tmp . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, 'Build failed: includes/vendor/autoload.php is missing.' . PHP_EOL );
	simplejwt_build_rmdir( $tmp );
	exit( 1 );
}

foreach ( array( 'phpunit', 'squizlabs', 'wp-coding-standards', 'phpcompatibility', 'yoast' ) as $dev_pkg ) {
	$pkg_dir = $tmp . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $dev_pkg;

	if ( is_dir( $pkg_dir ) ) {
		fwrite( STDERR, 'Build failed: dev package leaked into vendor: ' . $dev_pkg . PHP_EOL );
		simplejwt_build_rmdir( $tmp );
		exit( 1 );
	}
}

/*
 * 5. Drop the lock file from the final artifact.
 */
@unlink( $tmp . DIRECTORY_SEPARATOR . 'composer.lock' );

/*
 * 6. Create the zip archive.
 */
if ( ! is_dir( SIMPLE_JWT_BUILD_DIST ) && ! mkdir( SIMPLE_JWT_BUILD_DIST, 0755, true ) && ! is_dir( SIMPLE_JWT_BUILD_DIST ) ) {
	fwrite( STDERR, 'Unable to create dist directory: ' . SIMPLE_JWT_BUILD_DIST . PHP_EOL );
	simplejwt_build_rmdir( $tmp );
	exit( 1 );
}

$zip_path = SIMPLE_JWT_BUILD_DIST . DIRECTORY_SEPARATOR . SIMPLE_JWT_BUILD_SLUG . '.zip';

if ( file_exists( $zip_path ) ) {
	unlink( $zip_path );
}

$zip = new ZipArchive();

if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, 'Unable to create zip archive: ' . $zip_path . PHP_EOL );
	simplejwt_build_rmdir( $tmp );
	exit( 1 );
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	$file_path = $file->getRealPath();
	$rel_path  = SIMPLE_JWT_BUILD_SLUG . '/' . str_replace( DIRECTORY_SEPARATOR, '/', substr( $file_path, strlen( $tmp ) + 1 ) );

	if ( $file->isDir() ) {
		$zip->addEmptyDir( $rel_path );
	} else {
		$zip->addFile( $file_path, $rel_path );
	}
}

$zip->close();

simplejwt_build_rmdir( $tmp );

echo 'Built ' . $zip_path . ' (' . round( filesize( $zip_path ) / 1024 ) . ' KB)' . PHP_EOL;
