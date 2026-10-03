<?php
/**
 * PHP 8 string functions that WordPress core polyfills (wp-includes/compat.php) – the plugin relies on them.
 *
 * @package Wille_GEO
 */

if ( ! function_exists( 'str_starts_with' ) ) {
	function str_starts_with( string $haystack, string $needle ): bool {
		return 0 === strncmp( $haystack, $needle, strlen( $needle ) );
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	function str_ends_with( string $haystack, string $needle ): bool {
		return '' === $needle || substr( $haystack, -strlen( $needle ) ) === $needle;
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	function str_contains( string $haystack, string $needle ): bool {
		return '' === $needle || false !== strpos( $haystack, $needle );
	}
}
