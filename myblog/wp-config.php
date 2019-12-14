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
define( 'DB_NAME', 'myblog' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '$35RV(GMzG:NFz:6ZP3t{0,k34Z`0!fE;-d}J7?N}KwFi1}UE3:nip_Gw<wtg8U?' );
define( 'SECURE_AUTH_KEY',  '?H!ETbH)Q4h7p|mb&er-:c5N.BOH6*T;-k+Kh$v&aJ;tUvK>+&~kA!pVBNH6,9/E' );
define( 'LOGGED_IN_KEY',    'NIq@/&u<w;!/]U{k4=(,(ZAeii}6[<~*x4L%:VrJ#wPENeq~61-L?,*Q9$:4[Ht]' );
define( 'NONCE_KEY',        'Y9q{I+J1IvIh&>kQqx/CNz5gl`q{x||KStE.>bSA9?!<|ks,N+!63YXS01 @?aul' );
define( 'AUTH_SALT',        'M;KOxBD[W`a?j]q]sHVvx[enl~n;IL>7x/h~|k~q|233_q2=gS,WZm7e]GOkZUV!' );
define( 'SECURE_AUTH_SALT', '.k.:&1Ap,)}_dwg: Y:u%GZ>sm^ONN(f}x06wm9`YPuOy_{r05Kj}qgk*+(0^Is%' );
define( 'LOGGED_IN_SALT',   'eC?hm@e]^STBB8#T}J_Uu5Guk4deS%AqI.S~V3{S:O`DQvY9oZP&^>wBEx/w.4gn' );
define( 'NONCE_SALT',       'V38iV,HP8/~_L;V~p<5n07ONpdBG?EL@GeKN-lR*hCnCD3t]V5sj}uVQrdZ_TxRk' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
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
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once( ABSPATH . 'wp-settings.php' );
