<?php
/** Enable W3 Total Cache */
define('WP_CACHE', true); // Added by W3 Total Cache

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'biology_db' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'K2c8:G?9X+^f3*UQ=d%eLOwKR9C!%?cblw(]pf8*v-MPZfrgWiNN.dSi3xEwlb_U' );
define( 'SECURE_AUTH_KEY',  'h]W}Tpo_?_.4]{Tz,_U#c$4?Zf2cRFPuh:OXWC<~`M&6aJ=Kn:y/iIm*9(LsNO{?' );
define( 'LOGGED_IN_KEY',    'l%<SrRlJ5srqomesuB;{U m|PMz;6T(!AU$Ub|R@auwXA5#6~JJi_7[g&A!L:nT4' );
define( 'NONCE_KEY',        '?KW4hNM}9q_ a|XUV`R! l68Pm5NeG!oO!9~R_ly&Q,,U0!d(1]k=)h+9O83gJx|' );
define( 'AUTH_SALT',        'N9lRqeo3:h:Ja~mj{Y@-cBswL-[TK`/[]%bR&GRY;H+Cg5)BO2BKM^uB}=4.>[Ec' );
define( 'SECURE_AUTH_SALT', '<]*zKJRw;${YN2Q41z,(yT8D8l9Z-HPE.8fc5wn$#2NX/i-4KyOLUhuF.sosrk&,' );
define( 'LOGGED_IN_SALT',   '^mA9(I?}`r/{Jm)!Rx@G/ #%x0G()AOFn3vyI2RAu&4IcUG:QFaP>*D6k)KF!o{k' );
define( 'NONCE_SALT',       'REUv^yg~:TUND1WSGtg()|sH__k$D[}{!4;}I2i:c*.Vn^(N[ha`5hGJAjAI4tTc' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
