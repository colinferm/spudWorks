<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */


// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'spudworks');

/** MySQL database username */
define('DB_USER', getenv('MYSQL_USER'));

/** MySQL database password */
define('DB_PASSWORD', getenv('MYSQL_PASSWORD'));

/** MySQL hostname */
define('DB_HOST', getenv('MYSQL_HOST'));

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'iw&3xfO]7]ri.H?hos6)_gMbI>:*Thh!]igF[#Mf*.?0Do$s3Q2mnz<)^Xu:6.AH');
define('SECURE_AUTH_KEY',  ' Nh@j3w<9UB:`im*@6+n *5zPQ$M?L_`-YU|gW=pZ}f,k;]wg3)-2o6x|a56Jy:7');
define('LOGGED_IN_KEY',    'zEm2+D1*VDF1i[+*gynk+D9l [u`]oJN|%(+jk;+/=o[6w5KX8EKh_B4!M |o_fu');
define('NONCE_KEY',        'Oa:>V?H7A:=+OchS&/Nj7O{ix,u2E=?/z_/w(y`)JJd~mQyu*o~-E~Q@PS>cnRb+');
define('AUTH_SALT',        '9G|2cUB!DIY)R0SY84Qdo[3o eNL`]4mbA{PR-8>GbHcwZek[E3/ybHKrG<{3j.g');
define('SECURE_AUTH_SALT', '|x@c/2;rZ#e c*WeCsYuCl{=zQ?O+.SE{RjC8%JO-MmrKl}dO)j<A~4^}u[~+x6^');
define('LOGGED_IN_SALT',   '%2i3SIq~i.eW>s|rwY(0{#6n-qd>bx&3`Zho0bzuW+{gm&[d^<.m+M;:SHOgNc]6');
define('NONCE_SALT',       '|IMI%e#xAj;R$v@+IB]4oSs,HeMG4^nLU%I#Lj50P6_b:-9K((7ehSp$*d1-n]t}');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'JETPACK_DEV_DEBUG', true);
define('WP_DEBUG', false);
@ini_set('display_errors', 0);

define('WP_MEMORY_LIMIT', '1536M');
define('WP_ALLOW_MULTISITE', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', true);
define('DOMAIN_CURRENT_SITE', 'spudworks.localhost:8080');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);
define('COOKIE_DOMAIN', '');
/*
//define('COOKIE_DOMAIN', $_SERVER['HTTP_HOST']);
*/

/** FTP Settings */
define('FS_METHOD', 'direct');
define("FTP_HOST", "54.165.223.43");
define("FTP_USER", "ftpuser");
define("FTP_PASS", "Upl0ad!");

define('WP_REDIS_PORT', 6379);

/**
 * S3 media offload (spudWorks Magazine theme). In dev these come from the
 * docker-compose environment and point at MinIO; in production put the real
 * AWS values here and leave out the two ENDPOINT constants.
 */
if (getenv('S3_KEY')) {
	define('SPUDMAG_S3_KEY', getenv('S3_KEY'));
	define('SPUDMAG_S3_SECRET', getenv('S3_SECRET'));
	define('SPUDMAG_S3_REGION', getenv('S3_REGION'));
	define('SPUDMAG_S3_ENDPOINT', getenv('S3_ENDPOINT'));
	define('SPUDMAG_S3_PUBLIC_ENDPOINT', getenv('S3_PUBLIC_ENDPOINT'));
}

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

if ( (!empty( $_SERVER['HTTP_X_FORWARDED_HOST'])) || (!empty( $_SERVER['HTTP_X_FORWARDED_FOR'])) ) {
 $_SERVER['HTTPS'] = 'on';
}

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
