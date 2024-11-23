<?php
namespace Krokedil\Qvickly\Payments\Requests\Helpers;

/**
 * Class Store.
 *
 * Related to settings irrelevant of cart or checkout.
 *
 * @package Krokedil\Qvickly\Payments\Requests\Helpers
 */
class Store {

	/**
	 * Retrieve the language from the locale.
	 *
	 * @return string|false The ISO 639-1 language code or `false` if unsupported locale.
	 */
	public static function get_language() {
		$language           = explode( '_', get_locale() )[0];
		$supported_language = array( 'sv', 'da', 'no', 'en' );

		if ( in_array( $language, $supported_language, true ) ) {
			return $language;
		}

		return false;
	}

	/**
	 * Get the locale.
	 *
	 * @return string
	 */
	public static function get_locale() {
		$locale = get_locale();
		switch ( $locale ) {
			case 'fi':
				$locale = 'fi_fi';
				break;
			default:
				break;
		}
		return str_replace( '_', '-', $locale );
	}
}
